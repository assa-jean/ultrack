<?php

class SessionDbHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private PDO $pdo;
    private string $table;
    private bool $hasLegacyAccess = false;
    private bool $hasLegacyCreatedAt = false;
    private bool $hasLegacyUpdatedAt = false;

    public function __construct(PDO $pdo, string $table = 'sessions')
    {
        $this->pdo = $pdo;
        $this->table = $table;

        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `{$this->table}` (
                `id` varbinary(128) NOT NULL,
                `user_ref` varchar(64) NULL,
                `data` mediumblob NOT NULL,
                `last_activity` int unsigned NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_sessions_last_activity` (`last_activity`),
                INDEX `idx_sessions_user_ref` (`user_ref`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $columnRows = $this->pdo->query("SHOW COLUMNS FROM `{$this->table}`")->fetchAll(PDO::FETCH_ASSOC);
        $columns = array_column($columnRows, 'Field');
        if (!in_array('user_ref', $columns, true)) {
            $this->pdo->exec("ALTER TABLE `{$this->table}` ADD COLUMN `user_ref` varchar(64) NULL");
        }
        if (!in_array('last_activity', $columns, true)) {
            $this->pdo->exec("ALTER TABLE `{$this->table}` ADD COLUMN `last_activity` int unsigned NOT NULL DEFAULT 0");
        }
        if (in_array('access', $columns, true)) {
            $this->pdo->exec("UPDATE `{$this->table}` SET `last_activity` = `access` WHERE `last_activity` = 0");
        }
        $this->hasLegacyAccess = in_array('access', $columns, true);
        $this->hasLegacyCreatedAt = in_array('created_at', $columns, true);
        $this->hasLegacyUpdatedAt = in_array('updated_at', $columns, true);

        foreach ($columnRows as $column) {
            if ($column['Field'] === 'id' && strtolower((string) $column['Type']) !== 'varbinary(128)') {
                $this->pdo->exec("ALTER TABLE `{$this->table}` MODIFY COLUMN `id` varbinary(128) NOT NULL");
            }
            if ($column['Field'] === 'data' && strtolower((string) $column['Type']) !== 'mediumblob') {
                $this->pdo->exec("ALTER TABLE `{$this->table}` MODIFY COLUMN `data` mediumblob NOT NULL");
            }
        }

        $indexes = $this->pdo->query("SHOW INDEX FROM `{$this->table}`")->fetchAll(PDO::FETCH_COLUMN, 2);
        if (!in_array('idx_sessions_last_activity', $indexes, true)) {
            $this->pdo->exec("ALTER TABLE `{$this->table}` ADD INDEX `idx_sessions_last_activity` (`last_activity`)");
        }
        if (!in_array('idx_sessions_user_ref', $indexes, true)) {
            $this->pdo->exec("ALTER TABLE `{$this->table}` ADD INDEX `idx_sessions_user_ref` (`user_ref`)");
        }
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT `data` FROM `{$this->table}` WHERE `id` = :id AND `last_activity` > :cutoff LIMIT 1");
            $stmt->execute([':id' => $id, ':cutoff' => time() - (int) ini_get('session.gc_maxlifetime')]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row['data'] ?? '';
        } catch (Throwable $e) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $timestamp = time();
            $fields = ['id', 'user_ref', 'data', 'last_activity'];
            $parameters = [
                ':id' => $id,
                ':user_ref' => $_SESSION['user_ref'] ?? null,
                ':data' => $data,
                ':last_activity' => $timestamp,
            ];
            $updates = [
                '`user_ref` = VALUES(`user_ref`)',
                '`data` = VALUES(`data`)',
                '`last_activity` = VALUES(`last_activity`)',
            ];
            if ($this->hasLegacyAccess) {
                $fields[] = 'access';
                $parameters[':access'] = $timestamp;
                $updates[] = '`access` = VALUES(`access`)';
            }
            if ($this->hasLegacyCreatedAt) {
                $fields[] = 'created_at';
                $parameters[':created_at'] = $timestamp;
            }
            if ($this->hasLegacyUpdatedAt) {
                $fields[] = 'updated_at';
                $parameters[':updated_at'] = $timestamp;
                $updates[] = '`updated_at` = VALUES(`updated_at`)';
            }

            $quotedFields = array_map(static fn(string $field): string => '`' . $field . '`', $fields);
            $placeholders = array_map(static fn(string $field): string => ':' . $field, $fields);
            $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $quotedFields) . ') VALUES ('
                . implode(', ', $placeholders) . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

            return $this->pdo->prepare($sql)->execute($parameters);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id");
            return $stmt->execute([':id' => $id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE `last_activity` < :cutoff");
            $stmt->execute([':cutoff' => time() - $max_lifetime]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function create_sid(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function validateId(string $id): bool
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $id) === 1 && strlen($id) >= 32;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE `{$this->table}` SET `last_activity` = :last_activity WHERE `id` = :id"
            );

            return $stmt->execute([
                ':last_activity' => time(),
                ':id' => $id,
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }
}
