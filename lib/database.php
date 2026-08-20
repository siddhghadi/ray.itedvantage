<?php
declare(strict_types=1);

function crmDatabase(): ?PDO
{
    static $connection = false;
    if ($connection instanceof PDO) return $connection;

    $configFile = __DIR__ . '/../storage/database.php';
    if (!is_file($configFile)) return null;
    $config = require $configFile;
    if (!is_array($config)) return null;

    try {
        $connection = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['database']),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
        );
        crmMigrate($connection);
        return $connection;
    } catch (Throwable $error) {
        error_log('Ray CRM database error: ' . $error->getMessage());
        return null;
    }
}

function crmMigrate(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS td_leads (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        business_name VARCHAR(190) NOT NULL,
        contact_name VARCHAR(190) NULL,
        email VARCHAR(190) NULL,
        phone VARCHAR(50) NULL,
        website VARCHAR(255) NULL,
        address TEXT NULL,
        category VARCHAR(190) NULL,
        status ENUM('new','pending','contacted','closed','lost') NOT NULL DEFAULT 'new',
        total_value DECIMAL(12,2) NOT NULL DEFAULT 0,
        amount_received DECIMAL(12,2) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_td_status (status), INDEX idx_td_email (email), INDEX idx_td_phone (phone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function importLeadCsv(PDO $db, string $path): int
{
    $handle = fopen($path, 'rb');
    if ($handle === false) return 0;
    $header = fgetcsv($handle);
    if (!$header) { fclose($handle); return 0; }

    $keys = array_map(static function ($value): string {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string) $value), '_'));
    }, $header);
    $aliases = [
        'business_name' => ['business_name','business','company','company_name','title','name','place_name'],
        'contact_name' => ['contact_name','owner','owner_name','contact','person'],
        'email' => ['email','email_address','mail'], 'phone' => ['phone','phone_number','mobile','telephone','contact_number'],
        'website' => ['website','site','domain','url'], 'address' => ['address','full_address','location'],
        'category' => ['category','type','business_category','industry'],
    ];
    $indexes = [];
    foreach ($aliases as $field => $names) {
        foreach ($names as $name) {
            $position = array_search($name, $keys, true);
            if ($position !== false) { $indexes[$field] = $position; break; }
        }
    }
    if (!isset($indexes['business_name'])) { fclose($handle); return 0; }

    $insert = $db->prepare('INSERT INTO td_leads (business_name, contact_name, email, phone, website, address, category) VALUES (?,?,?,?,?,?,?)');
    $duplicate = $db->prepare('SELECT id FROM td_leads WHERE (email <> \'\' AND email = ?) OR (phone <> \'\' AND phone = ?) OR (business_name = ? AND website = ?) LIMIT 1');
    $count = 0;
    $db->beginTransaction();
    try {
        while (($row = fgetcsv($handle)) !== false) {
            $value = static fn(string $field): string => trim((string) ($row[$indexes[$field] ?? -1] ?? ''));
            $business = $value('business_name');
            if ($business === '') continue;
            $email = strtolower($value('email'));
            $phone = $value('phone');
            $website = $value('website');
            $duplicate->execute([$email, $phone, $business, $website]);
            if ($duplicate->fetch()) continue;
            $insert->execute([$business, $value('contact_name'), $email, $phone, $website, $value('address'), $value('category')]);
            $count++;
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        fclose($handle);
        throw $error;
    }
    fclose($handle);
    return $count;
}
