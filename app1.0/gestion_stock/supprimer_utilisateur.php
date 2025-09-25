<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php?error=forbidden');
    exit();
}

require_once 'connexion.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: consulter_utilisateurs.php');
    exit();
}

if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $id) {
    header('Location: consulter_utilisateurs.php?error=self_delete');
    exit();
}

$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$id]);

header('Location: consulter_utilisateurs.php?deleted=1');
exit();
