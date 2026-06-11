<?php

declare(strict_types=1);

namespace Panel;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
    }

    public function migrate(): void
    {
        $this->pdo->exec((string) file_get_contents(__DIR__ . '/schema.sql'));

        // Column additions for databases created by earlier versions.
        $cols = array_column($this->all('PRAGMA table_info(sites)'), 'name');
        if (!in_array('cf_only', $cols, true)) {
            $this->pdo->exec('ALTER TABLE sites ADD COLUMN cf_only INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('ini_json', $cols, true)) {
            $this->pdo->exec("ALTER TABLE sites ADD COLUMN ini_json TEXT NOT NULL DEFAULT '{}'");
        }
    }

    public function getSetting(string $key, string $default = ''): string
    {
        $row = $this->one('SELECT value FROM settings WHERE key = ?', [$key]);
        return $row === null ? $default : (string) $row['value'];
    }

    public function setSetting(string $key, string $value): void
    {
        $this->run(
            'INSERT INTO settings (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            [$key, $value]
        );
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<int|string, mixed> $params */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function lastId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function audit(?int $userId, string $action, string $details, string $ip): void
    {
        $this->run(
            'INSERT INTO audit_log (user_id, action, details, ip, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $action, $details, $ip, time()]
        );
    }
}
