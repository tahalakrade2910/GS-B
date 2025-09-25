<?php
return [
    'database' => [
        'host' => 'localhost',
        'database' => 'backup',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'stock_database' => [
        'host' => 'localhost',
        'database' => 'gestion_stock',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'ensure_backups_table' => false,
    ],
    'ftp' => [
        'host' => '127.0.0.1',   // since XAMPP + FileZilla are on same PC
        'username' => 'Taha',
        'password' => '1234',
        'port' => 21,            // FTP runs here (not 14147!)
        'timeout' => 90,
        'base_path' => '/',      // because home dir = C:\Backupsdm
        'passive' => true,
    ],
];
