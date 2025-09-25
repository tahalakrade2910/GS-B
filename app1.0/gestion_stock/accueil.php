<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Gestion du stock</title>
    <link rel="stylesheet" href="../assets/css/styles.css" />
    <link rel="icon" type="image/png" href="favicon.png" />
</head>
<body>
<?php $baseUrl = '..'; require __DIR__ . '/../partials/top_nav.php'; ?>
<header class="page-header">
    <img src="../assets/images/logo.png" alt="Logo IMAlliance" class="page-logo" />
    <div class="page-header-content">
        <h1>Gestion du stock</h1>
        <p>Gérez vos pièces, visualisez votre inventaire et exportez vos données en toute simplicité.</p>
    </div>
</header>

<main>
    <div class="section-toggle" role="group" aria-label="Navigation du stock">
        <a class="button-link toggle-button" href="dashboard.php?section=ajouter">➕ Ajouter un élément</a>
        <a class="button-link toggle-button" href="dashboard.php?section=consulter">📦 Consulter le stock</a>
    </div>

    <section class="card">
        <h2>Accédez rapidement aux fonctionnalités</h2>
        <p class="muted">Choisissez une action pour créer de nouveaux articles ou vérifier l'état de votre inventaire.</p>
    </section>
</main>

<footer class="page-footer">
    <p>Optimisez la gestion de vos pièces détachées avec les outils de suivi du stock.</p>
</footer>
</body>
</html>
