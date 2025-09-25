<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $charset = $config['charset'] ?? 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['database'] ?? '',
            $charset
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', $options);
        } catch (PDOException $exception) {
            throw new PDOException('Unable to connect to the database: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
        }

        $shouldEnsureBackupsTable = $config['ensure_backups_table'] ?? true;
        if ($shouldEnsureBackupsTable) {
            $this->ensureBackupsTable();
        }

        $shouldEnsureSoftwareTable = $config['ensure_device_software_table'] ?? true;
        if ($shouldEnsureSoftwareTable) {
            $this->ensureDeviceSoftwareTable();
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function ensureBackupsTable(): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `backups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `equipment` VARCHAR(255) NOT NULL,
    `designation` VARCHAR(255) NOT NULL,
    `numero_serie` VARCHAR(255) NULL,
    `client` VARCHAR(255) NOT NULL,
    `fournisseur` VARCHAR(255) NULL,
    `date_backup` DATE NOT NULL,
    `commentaire` TEXT NULL,
    `status` VARCHAR(50) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(255) NULL,
    `file_date` DATE NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $exception) {
            throw new PDOException('Unable to ensure the backups table exists: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }

    public function ensureDeviceSoftwareTable(): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `device_software` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `dm_type` VARCHAR(50) NOT NULL,
    `dm_model` VARCHAR(100) NOT NULL,
    `version` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(255) NULL,
    `added_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        try {
            $this->pdo->exec($sql);
        } catch (PDOException $exception) {
            throw new PDOException('Unable to ensure the device_software table exists: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
        }
    }
}
