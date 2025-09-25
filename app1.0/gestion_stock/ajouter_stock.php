<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

require_once 'connexion.php';

$lieuxDisponibles = ['Rabat', 'Ouled Saleh'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        isset($_POST["reference"], $_POST["quantite"],
              $_POST["groupe"], $_POST["famille"], $_POST["emplacement"], $_POST["etat"], $_POST["lieu"])
        && (
            ($_POST["reference"] !== "autre" && isset($_POST["designation"]))
            || ($_POST["reference"] === "autre" && isset($_POST["new_reference"], $_POST["new_designation"]))
        )
    ) {
        $reference = $_POST["reference"];
        $designation = $_POST["designation"];

        if ($reference === "autre") {
            $reference = $_POST["new_reference"] ?? '';
            $designation = $_POST["new_designation"] ?? '';
        }

        $quantite = (int) $_POST["quantite"];
        $groupe = $_POST["groupe"];
        $famille = $_POST["famille"];
        $emplacement = $_POST["emplacement"];
        $etat = $_POST["etat"];
        $lieu = $_POST["lieu"] ?? '';
        if (!in_array($lieu, $lieuxDisponibles, true)) {
            $lieu = $lieuxDisponibles[0];
        }

        // Vérifier si la référence existe déjà
        if ($lieu === $lieuxDisponibles[0]) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE reference = ? AND (lieu = ? OR lieu IS NULL)");
            $check->execute([$reference, $lieu]);
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM stock WHERE reference = ? AND lieu = ?");
            $check->execute([$reference, $lieu]);
        }
        $exists = $check->fetchColumn();

        if ($exists) {
            header("Location: dashboard.php?error=exist&section=ajouter&lieu=" . urlencode($lieu));
            exit();
        } else {
            // Insertion
            $stmt = $pdo->prepare("INSERT INTO stock (reference, designation, quantite, groupe, famille, emplacement, etat, lieu) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$reference, $designation, $quantite, $groupe, $famille, $emplacement, $etat, $lieu]);

            header("Location: dashboard.php?added=1&section=ajouter&lieu=" . urlencode($lieu));
            exit();
        }
    } else {
        $lieu = $_POST["lieu"] ?? $lieuxDisponibles[0];
        if (!in_array($lieu, $lieuxDisponibles, true)) {
            $lieu = $lieuxDisponibles[0];
        }
        header("Location: dashboard.php?error=missing&section=ajouter&lieu=" . urlencode($lieu));
        exit();
    }
}
?>
