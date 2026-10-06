<?php
declare(strict_types=1);

function ins_db(): PDO {
    static $db;
    if ($db) return $db;
    $dir = getenv('INSURANCE_DATA_DIR') ?: dirname(__DIR__) . '/var';
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Cannot create private storage.');
    $db = new PDO('sqlite:' . $dir . '/insurance.sqlite', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS migrations(version INTEGER PRIMARY KEY, applied_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS agencies(id INTEGER PRIMARY KEY, name TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, demo INTEGER NOT NULL DEFAULT 0, settings TEXT NOT NULL DEFAULT '{}');
CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY, agency_id INTEGER REFERENCES agencies(id), email TEXT UNIQUE NOT NULL, name TEXT NOT NULL, password TEXT NOT NULL, role TEXT NOT NULL, permissions TEXT NOT NULL DEFAULT '{}', active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS records(id INTEGER PRIMARY KEY, agency_id INTEGER NOT NULL REFERENCES agencies(id), kind TEXT NOT NULL, client_id INTEGER, assigned_id INTEGER REFERENCES users(id), data TEXT NOT NULL, archived INTEGER NOT NULL DEFAULT 0, version INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(agency_id,id), FOREIGN KEY(agency_id,client_id) REFERENCES records(agency_id,id));
CREATE INDEX IF NOT EXISTS records_scope ON records(agency_id,kind,archived);
CREATE TABLE IF NOT EXISTS events(id INTEGER PRIMARY KEY, agency_id INTEGER NOT NULL, record_id INTEGER NOT NULL, client_id INTEGER NOT NULL, type TEXT NOT NULL, due TEXT NOT NULL, amount INTEGER NOT NULL DEFAULT 0, received INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'open', contacted INTEGER NOT NULL DEFAULT 0, anchor TEXT NOT NULL, occurrence INTEGER NOT NULL DEFAULT 0, frequency TEXT NOT NULL DEFAULT 'once', revision INTEGER NOT NULL DEFAULT 1, UNIQUE(record_id,type,due), FOREIGN KEY(agency_id,record_id) REFERENCES records(agency_id,id), FOREIGN KEY(agency_id,client_id) REFERENCES records(agency_id,id));
CREATE TABLE IF NOT EXISTS payments(id INTEGER PRIMARY KEY, agency_id INTEGER NOT NULL, event_id INTEGER NOT NULL REFERENCES events(id), amount INTEGER NOT NULL, paid_at TEXT NOT NULL, reference TEXT NOT NULL, source TEXT NOT NULL DEFAULT 'manually recorded', actor_id INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS notifications(id INTEGER PRIMARY KEY, agency_id INTEGER NOT NULL, event_id INTEGER NOT NULL REFERENCES events(id), revision INTEGER NOT NULL, milestone INTEGER NOT NULL, channel TEXT NOT NULL, state TEXT NOT NULL, created_at TEXT NOT NULL, UNIQUE(event_id,revision,milestone,channel));
CREATE TABLE IF NOT EXISTS audit(id INTEGER PRIMARY KEY, agency_id INTEGER, actor_id INTEGER, action TEXT NOT NULL, target TEXT NOT NULL, created_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS record_revisions(id INTEGER PRIMARY KEY,agency_id INTEGER NOT NULL,record_id INTEGER NOT NULL,version INTEGER NOT NULL,data TEXT NOT NULL,actor_id INTEGER NOT NULL,created_at TEXT NOT NULL,FOREIGN KEY(agency_id,record_id) REFERENCES records(agency_id,id));
CREATE TABLE IF NOT EXISTS support(id INTEGER PRIMARY KEY, agency_id INTEGER NOT NULL, admin_id INTEGER NOT NULL REFERENCES users(id), expires_at TEXT NOT NULL, revoked INTEGER NOT NULL DEFAULT 0);
CREATE TABLE IF NOT EXISTS attempts(bucket TEXT PRIMARY KEY, count INTEGER NOT NULL, expires INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS resets(id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL, hash TEXT NOT NULL, expires INTEGER NOT NULL, used INTEGER NOT NULL DEFAULT 0);
INSERT OR IGNORE INTO migrations VALUES(1,datetime('now'));
SQL);
    @chmod($dir . '/insurance.sqlite', 0600);
    return $db;
}
function ins_query(string $sql, array $args=[]): PDOStatement { $s=ins_db()->prepare($sql); $s->execute($args); return $s; }
function ins_json(array $data): string { return json_encode($data, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE); }
function ins_unpack(array $row): array { $row['data']=json_decode($row['data'],true,512,JSON_THROW_ON_ERROR); return $row; }
function ins_audit(?int $agency, ?int $actor, string $action, string $target): void { ins_query('INSERT INTO audit(agency_id,actor_id,action,target,created_at) VALUES(?,?,?,?,?)',[$agency,$actor,$action,$target,gmdate('c')]); }
function ins_transaction(callable $fn): mixed { $db=ins_db(); $db->exec('BEGIN IMMEDIATE'); try {$result=$fn(); $db->exec('COMMIT'); return $result;} catch(Throwable $e) {$db->exec('ROLLBACK'); throw $e;} }
