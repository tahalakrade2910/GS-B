<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit();
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: accueil.php');
    exit();
}

require_once 'connexion.php';

$username = '';
$role = 'user';
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($username === '' || $password === '') {
        $errors[] = "Le nom d'utilisateur et le mot de passe sont obligatoires.";
    }

    if (!in_array($role, ['admin', 'user'], true)) {
        $errors[] = "Le rôle sélectionné est invalide.";
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = "Ce nom d'utilisateur est déjà utilisé.";
        }
    }

    if (empty($errors)) {
        $hashedPassword = sha1($password);
        $stmt = $pdo->prepare('INSERT INTO users (username, password, role) VALUES (?, ?, ?)');

        if ($stmt->execute([$username, $hashedPassword, $role])) {
            $success = "L'utilisateur a été créé avec succès.";
            $username = '';
            $role = 'user';
        } else {
            $errors[] = "Une erreur est survenue lors de l\'enregistrement.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un utilisateur</title>
    <link rel="icon" type="image/png" href="favicon.png" />
    <link rel="stylesheet" href="../assets/css/styles.css">
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
            margin-bottom: 1.5rem;
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

        button {
            width: 100%;
            padding: 0.9rem;
            background-color: var(--primary-color, #1d4ed8);
            color: #fff;
            border: none;
            border-radius: 30px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s ease, box-shadow 0.3s ease;
        }

        button:hover {
            background-color: var(--primary-dark, #1e3a8a);
            box-shadow: 0 6px 15px rgba(29, 78, 216, 0.25);
        }

        .message {
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
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
        <h1>Ajouter un utilisateur</h1>

        <?php if ($success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="role">Rôle</label>
                <select id="role" name="role">
                    <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>Utilisateur</option>
                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                </select>
            </div>
            <button type="submit">Créer l'utilisateur</button>
        </form>

        <a href="accueil.php" class="back-link">⬅ Retour à l'accueil</a>
    </div>
</body>
</html>
