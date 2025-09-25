<?php
require_once 'connexion.php';
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php?error=forbidden');
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: consulter_stock.php");
    exit();
}

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM stock WHERE id = ?");
$stmt->execute([$id]);
$stock = $stmt->fetch();
$lieuxDisponibles = ['Rabat', 'Ouled Saleh'];
$stockLieu = $stock['lieu'] ?? 'Rabat';
if (!in_array($stockLieu, $lieuxDisponibles, true)) {
    $stockLieu = 'Rabat';
}

if (!$stock) {
    echo "Article introuvable.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier le stock</title>
    <link rel="icon" type="image/png" href="favicon.png" />
    <link rel="stylesheet" href="../assets/css/styles.css" />
</head>
<body>
<?php $baseUrl = '..'; require __DIR__ . '/../partials/top_nav.php'; ?>
<main class="portal-wrapper">
    <section class="card">
        <h2>Modifier l'article</h2>
        <form method="POST" action="update_stock.php" class="backup-form">
            <input type="hidden" name="id" value="<?= $stock['id'] ?>">
            <div class="form-group">
                <label for="reference">Référence</label>
                <input type="text" id="reference" name="reference" value="<?= htmlspecialchars($stock['reference']) ?>" required>
            </div>
            <div class="form-group">
                <label for="designation">Désignation</label>
                <input type="text" id="designation" name="designation" value="<?= htmlspecialchars($stock['designation']) ?>" required>
            </div>
            <div class="form-group">
                <label for="quantite">Quantité</label>
                <input type="number" id="quantite" name="quantite" value="<?= (int) $stock['quantite'] ?>" required>
            </div>
            <div class="form-group">
                <label for="emplacement">Emplacement</label>
                <input type="text" id="emplacement" name="emplacement" value="<?= htmlspecialchars($stock['emplacement']) ?>" required>
            </div>
            <div class="form-group">
                <label for="lieu">Lieu</label>
                <select id="lieu" name="lieu" required>
                    <?php foreach ($lieuxDisponibles as $lieuOption): ?>
                        <option value="<?= htmlspecialchars($lieuOption) ?>" <?= $lieuOption === $stockLieu ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lieuOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions">
                <button type="submit">Enregistrer les modifications</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>
