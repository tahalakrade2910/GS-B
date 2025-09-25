<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.php');
    exit();
}

if (($_SESSION['role'] ?? 'user') !== 'admin') {
    header('Location: ../tableau_de_bord.php');
    exit();
}

require_once __DIR__ . '/../gestion_stock/connexion.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: index.php');
    exit();
}

if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $id) {
    header('Location: index.php?error=self_delete');
    exit();
}

$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$id]);

header('Location: index.php?deleted=1');
exit();
