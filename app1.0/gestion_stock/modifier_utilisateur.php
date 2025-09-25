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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

if (!$id) {
    header('Location: consulter_utilisateurs.php');
    exit();
}

$stmt = $pdo->prepare('SELECT id, username, password, role FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: consulter_utilisateurs.php');
    exit();
}

$username = $user['username'];
$role = $user['role'] ?? 'user';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? '';

    if ($username === '') {
        $errors[] = "Le nom d'utilisateur est obligatoire.";
    }

    if (!in_array($role, ['admin', 'user'], true)) {
        $errors[] = "Le rôle sélectionné est invalide.";
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
        $check->execute([$username, $id]);
        if ($check->fetch()) {
            $errors[] = "Ce nom d'utilisateur est déjà utilisé.";
        }
    }

    if (!$errors) {
        $hashedPassword = $user['password'];
        if ($password !== '') {
            $hashedPassword = sha1($password);
        }

        $update = $pdo->prepare('UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?');
        if ($update->execute([$username, $hashedPassword, $role, $id])) {
            header('Location: consulter_utilisateurs.php?updated=1');
            exit();
        } else {
            $errors[] = "Une erreur est survenue lors de l'enregistrement.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier un utilisateur</title>
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
            max-width: 500px;
            margin: 3rem auto;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            margin-top: 0;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 1rem;
        }

        .helper-text {
            font-size: 0.85rem;
            color: #555;
            margin-top: 0.3rem;
        }

        button {
            width: 100%;
            padding: 0.9rem;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }

        button:hover {
            background-color: #0056b3;
        }

        .message {
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .back-link {
            display: block;
            margin-top: 1rem;
            text-align: center;
            color: #007bff;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<?php $baseUrl = '..'; require __DIR__ . '/../partials/top_nav.php'; ?>
    <div class="container">
        <h1>Modifier un utilisateur</h1>

        <?php if ($errors): ?>
            <div class="message error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars((string) $user['id']); ?>">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Nouveau mot de passe</label>
                <input type="password" id="password" name="password" placeholder="Laisser vide pour conserver le mot de passe actuel">
                <div class="helper-text">Le mot de passe est stocké de manière chiffrée. Laissez ce champ vide pour ne pas le modifier.</div>
            </div>
            <div class="form-group">
                <label for="role">Rôle</label>
                <select id="role" name="role">
                    <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>Utilisateur</option>
                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                </select>
            </div>
            <button type="submit">Enregistrer</button>
        </form>

        <a href="consulter_utilisateurs.php" class="back-link">⬅ Retour à la liste</a>
    </div>
</body>
</html>
