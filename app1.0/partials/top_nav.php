<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$baseUrl = $baseUrl ?? '.';
$baseUrl = rtrim($baseUrl, '/');
if ($baseUrl === '') {
    $baseUrl = '.';
}

$dashboardUrl = $baseUrl . '/tableau_de_bord.php';
$backupsUrl = $baseUrl . '/index.php';
$stockUrl = $baseUrl . '/gestion_stock/accueil.php';
$logoutUrl = $baseUrl . '/logout.php';
$logoPath = $baseUrl . '/gestion_stock/logo.png';
$usersUrl = $baseUrl . '/users/index.php';

$currentUser = $_SESSION['username'] ?? '';
$currentRole = $_SESSION['role'] ?? 'user';
$isAdminUser = $currentRole === 'admin';
?>
<nav class="top-nav">
    <div class="top-nav__brand">
        <img class="top-nav__logo" src="<?php echo htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" />
        <span class="top-nav__title">Gestion de stock &amp; Backups DM</span>
    </div>
    <div class="top-nav__links">
        <a href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>">Tableau de bord</a>
        <a href="<?php echo htmlspecialchars($backupsUrl, ENT_QUOTES, 'UTF-8'); ?>">Sauvegardes DM</a>
        <a href="<?php echo htmlspecialchars($stockUrl, ENT_QUOTES, 'UTF-8'); ?>">Gestion de stock</a>
        <?php if ($isAdminUser): ?>
            <a href="<?php echo htmlspecialchars($usersUrl, ENT_QUOTES, 'UTF-8'); ?>">Utilisateurs</a>
        <?php endif; ?>
    </div>
    <div class="top-nav__user">
        <?php if ($currentUser !== ''): ?>
            <span class="top-nav__welcome">Bienvenue <?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?> 👋</span>
        <?php endif; ?>
        <a class="top-nav__logout" href="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8'); ?>">Déconnexion</a>
    </div>
</nav>
