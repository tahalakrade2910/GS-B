<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$baseUrl = '.';
$userRole = $_SESSION['role'] ?? 'user';
$isAdmin = $userRole === 'admin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord</title>
    <link rel="icon" type="image/png" href="gestion_stock/favicon.png" />
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<?php require __DIR__ . '/partials/top_nav.php'; ?>

<main class="portal-wrapper">
    <section class="card">
        <h1>Bienvenue dans l'application unifiée</h1>
        <p>Retrouvez toutes vos opérations de sauvegarde DM et de gestion de stock dans un espace unique, cohérent et sécurisé.</p>
    </section>

    <section class="portal-grid">
        <article class="portal-card">
            <h2>Sauvegardes DM</h2>
            <p>Enregistrez et suivez les sauvegardes de vos documents médicaux. Téléversez les fichiers vers le serveur FTP, consultez l'historique et téléchargez les sauvegardes à tout moment.</p>
            <a class="button-link" href="index.php">Accéder aux sauvegardes</a>
        </article>
        <article class="portal-card">
            <h2>Gestion de stock</h2>
            <p>Consultez l'inventaire, ajoutez ou modifiez des pièces et administrez les utilisateurs autorisés. Toutes les actions respectent désormais la même identité visuelle.</p>
            <a class="button-link" href="gestion_stock/accueil.php">Accéder au stock</a>
        </article>
        <?php if ($isAdmin): ?>
            <article class="portal-card">
                <h2>Gestion des utilisateurs</h2>
                <p>Ajoutez, mettez à jour ou supprimez les comptes qui accèdent à l'application unifiée depuis un espace centralisé.</p>
                <a class="button-link" href="users/index.php">Administrer les utilisateurs</a>
            </article>
        <?php endif; ?>
    </section>
</main>

<footer class="page-footer">
    &copy; <?php echo date('Y'); ?> - Gestion de stock &amp; Backups DM
</footer>
</body>
</html>
