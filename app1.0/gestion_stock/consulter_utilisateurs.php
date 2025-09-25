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

$stmt = $pdo->query('SELECT id, username, password, role FROM users ORDER BY username');
$users = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$alert = '';
$alertType = '';
if (isset($_GET['deleted'])) {
    $alert = "L'utilisateur a été supprimé avec succès.";
    $alertType = 'success';
} elseif (isset($_GET['updated'])) {
    $alert = "L'utilisateur a été mis à jour avec succès.";
    $alertType = 'success';
} elseif (isset($_GET['error']) && $_GET['error'] === 'self_delete') {
    $alert = "Vous ne pouvez pas supprimer votre propre compte.";
    $alertType = 'error';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Utilisateurs</title>
    <link rel="icon" type="image/png" href="favicon.png" />
    <link rel="stylesheet" href="../assets/css/styles.css" />
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: #eaf4fc;
            color: #002b5c;
        }

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        h1 {
            margin-top: 0;
            text-align: center;
            color: #003366;
        }

        .actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .actions a {
            background-color: #007bff;
            color: #fff;
            padding: 0.7rem 1.4rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .actions a:hover {
            background-color: #0056b3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background-color: #f8fbff;
        }

        th, td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid #dbe7ff;
        }

        th {
            background-color: #007bff;
            color: #fff;
        }

        tr:nth-child(even) {
            background-color: #eef5ff;
        }

        .alert {
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .table-actions {
            display: flex;
            gap: 0.6rem;
        }

        .table-actions a,
        .table-actions button {
            border: none;
            background: none;
            cursor: pointer;
            color: #007bff;
            font-size: 1rem;
            text-decoration: none;
            padding: 0.2rem 0.4rem;
            transition: color 0.2s;
        }

        .table-actions a:hover,
        .table-actions button:hover {
            color: #0056b3;
        }

        .table-actions .danger {
            color: #dc3545;
        }

        .table-actions .danger:hover {
            color: #a71d2a;
        }

        @media (max-width: 700px) {
            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
<?php $baseUrl = '..'; require __DIR__ . '/../partials/top_nav.php'; ?>
    <div class="container">
        <h1>Gestion des utilisateurs</h1>

        <?php if ($alert): ?>
            <div class="alert <?= htmlspecialchars($alertType) ?>"><?php echo htmlspecialchars($alert); ?></div>
        <?php endif; ?>

        <div class="actions">
            <a href="ajouter_utilisateur.php">➕ Ajouter un utilisateur</a>
            <a href="dashboard.php?section=consulter">📦 Retour au stock</a>
            <a href="accueil.php">🏠 Accueil</a>
        </div>

        <?php if (empty($users)): ?>
            <p style="text-align:center;">Aucun utilisateur n'est enregistré.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Login</th>
                        <th>Mot de passe (hashé)</th>
                        <th>Rôle</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td style="font-family: 'Courier New', monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($user['password']); ?></td>
                            <td><?php echo htmlspecialchars($user['role'] ?? ''); ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="modifier_utilisateur.php?id=<?php echo urlencode($user['id']); ?>">✏️ Modifier</a>
                                    <a class="danger" href="supprimer_utilisateur.php?id=<?php echo urlencode($user['id']); ?>" onclick="return confirm('Supprimer cet utilisateur ?');">🗑️ Supprimer</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
