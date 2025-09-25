<?php
session_start();
require_once 'connexion.php';

if (!isset($_SESSION['logged_in']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    header('Location: dashboard.php?error=forbidden');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $groupe = $_POST['groupe'];
    $famille = $_POST['famille'];
    $reference = $_POST['reference'];
    $designation = $_POST['designation'];
    $quantite = $_POST['quantite'];
    $emplacement = $_POST['emplacement'];
    $etat = $_POST['etat'];
    $lieu = $_POST['lieu'] ?? 'Rabat';
    $lieuxDisponibles = ['Rabat', 'Ouled Saleh'];
    if (!in_array($lieu, $lieuxDisponibles, true)) {
        $lieu = $lieuxDisponibles[0];
    }

    if ($reference === 'autre') {
        $reference = $_POST['new_reference'] ?? $reference;
        $designation = $_POST['new_designation'] ?? $designation;
    }

    $stmt = $pdo->prepare("UPDATE stock SET groupe=?, famille=?, reference=?, designation=?, quantite=?, emplacement=?, etat=?, lieu=? WHERE id=?");
    $stmt->execute([$groupe, $famille, $reference, $designation, $quantite, $emplacement, $etat, $lieu, $id]);

    $redirectLieu = $_POST['current_lieu'] ?? $lieu;
    if (!in_array($redirectLieu, $lieuxDisponibles, true)) {
        $redirectLieu = $lieuxDisponibles[0];
    }

    header("Location: dashboard.php?updated=1&section=consulter&lieu=" . urlencode($redirectLieu));
    exit();
}
?>
