<?php
declare(strict_types=1);

function rangoliProductRevision(array $product): string
{
    return hash('sha256', json_encode($product, JSON_THROW_ON_ERROR));
}

function rangoliProductNumber(array $input, string $key, float $minimum, float $maximum, bool $integer = false): float
{
    $raw = $input[$key] ?? '';
    if (!is_scalar($raw) || !is_numeric($raw)) throw new RuntimeException('Please enter a valid ' . str_replace('_', ' ', $key) . '.');
    $value = (float) $raw;
    if (!is_finite($value) || $value < $minimum || $value > $maximum || ($integer && floor($value) !== $value)) {
        throw new RuntimeException('Please check the ' . str_replace('_', ' ', $key) . '.');
    }
    return $value;
}

function rangoliSaveProduct(array $products, array $input): array
{
    $id = (string) ($input['product_id'] ?? '');
    $index = null;
    foreach ($products as $i => $product) if ((string) $product['id'] === $id) { $index = $i; break; }
    if ($id !== '' && $index === null) throw new RuntimeException('This product was not found. Please return to the product list.');
    $existing = $index !== null ? $products[$index] : [];
    if ($existing && !hash_equals(rangoliProductRevision($existing), (string) ($input['revision'] ?? ''))) {
        throw new RuntimeException('This product changed since you opened it. Please reopen it before saving so its latest stock is kept.');
    }
    $fields = [];
    foreach (['name' => 120, 'category' => 80, 'sku' => 50] as $key => $max) {
        $value = trim((string) ($input[$key] ?? ''));
        if ($value === '' || strlen($value) > $max) throw new RuntimeException('Please enter a ' . $key . ' (up to ' . $max . ' characters).');
        $fields[$key] = $value;
    }
    foreach ($products as $product) {
        if ((string) $product['id'] !== $id && strcasecmp((string) ($product['sku'] ?? ''), $fields['sku']) === 0) throw new RuntimeException('That SKU is already used by another product.');
        if (strcasecmp((string) ($product['category'] ?? ''), $fields['category']) === 0) $fields['category'] = $product['category'];
    }
    foreach (['height', 'width'] as $key) $fields[$key] = rangoliProductNumber($input, $key, 0.001, 100000);
    foreach (['retail_price', 'dealer_price', 'cost_price'] as $key) $fields[$key] = rangoliProductNumber($input, $key, 0, 10000000);
    foreach (['stock', 'low_stock'] as $key) $fields[$key] = (int) rangoliProductNumber($input, $key, 0, 1000000, true);
    $fields['unit'] = (string) ($input['unit'] ?? 'in');
    if (!in_array($fields['unit'], ['in', 'cm', 'mm', 'ft'], true)) throw new RuntimeException('Choose a valid measurement unit.');
    if ($fields['dealer_price'] > $fields['retail_price']) throw new RuntimeException('Dealer price cannot exceed the selling price.');
    $image = (string) ($input['image_data'] ?? '');
    if ($image === '') $image = (string) ($existing['image'] ?? '');
    if (strlen($image) > 2800000 || !preg_match('~^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$~D', $image, $match)) throw new RuntimeException('Please choose a JPG, PNG or WebP product photo.');
    $bytes = base64_decode($match[2], true);
    $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
    if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || $info['mime'] !== 'image/' . $match[1] || $info[0] > 2000 || $info[1] > 2000) throw new RuntimeException('The photo could not be read. Please choose it again.');
    $fields['image'] = $image;
    $fields['updated_at'] = gmdate('c');
    $saved = array_merge($existing, $fields);
    if ($index === null) { $saved['id'] = bin2hex(random_bytes(8)); $saved['created_at'] = gmdate('c'); array_unshift($products, $saved); }
    else $products[$index] = $saved;
    return $products;
}
