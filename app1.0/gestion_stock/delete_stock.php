<?php
session_start();
require_once 'connexion.php';
if (!isset($_SESSION['logged_in']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    header('Location: dashboard.php?error=forbidden');
    exit();
}
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $pdo->prepare("DELETE FROM stock WHERE id = ?")->execute([$id]);
}
$lieuxDisponibles = ['Rabat', 'Ouled Saleh'];
$redirectLieu = $_GET['lieu'] ?? null;
if ($redirectLieu && !in_array($redirectLieu, $lieuxDisponibles, true)) {
    $redirectLieu = null;
}
$params = ['section' => 'consulter'];
if ($redirectLieu) {
    $params['lieu'] = $redirectLieu;
}
header('Location: dashboard.php' . ($params ? '?' . http_build_query($params) : ''));
exit();
