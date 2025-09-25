<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Database;

$stockConfig = $config['stock_database'] ?? $config['database'];
$stockConfig['ensure_backups_table'] = $stockConfig['ensure_backups_table'] ?? false;

try {
    $stockDatabase = new Database($stockConfig);
    $pdo = $stockDatabase->pdo();
} catch (\PDOException $exception) {
    die('Erreur de connexion : ' . $exception->getMessage());
}
