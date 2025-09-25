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
