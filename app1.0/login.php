<?php
declare(strict_types=1);

session_start();

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: tableau_de_bord.php');
    exit();
}

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/gestion_stock/connexion.php';

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errorMessage = "Veuillez saisir votre nom d'utilisateur et votre mot de passe.";
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            $hashedPassword = sha1($password);
            if ($user && hash_equals($user['password'], $hashedPassword)) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'] ?? 'user';

                header('Location: tableau_de_bord.php');
                exit();
            }

            $errorMessage = "Identifiants incorrects. Veuillez réessayer.";
        } catch (PDOException $exception) {
            $errorMessage = "Une erreur est survenue lors de la vérification de vos identifiants.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link rel="icon" type="image/png" href="gestion_stock/favicon.png" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 1.5rem;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 18px;
            padding: 2.5rem 2.25rem;
            box-shadow: 0 30px 80px rgba(30, 64, 175, 0.18);
            position: relative;
            overflow: hidden;
        }

        .login-card::after {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.15), transparent 55%),
                        radial-gradient(circle at bottom left, rgba(96, 165, 250, 0.12), transparent 40%);
            pointer-events: none;
        }

        .login-card__header {
            position: relative;
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-card__logo {
            width: 120px;
            height: auto;
            margin-bottom: 1rem;
        }

        .login-card__header h1 {
            margin: 0;
            font-size: 1.9rem;
            color: #1e3a8a;
        }

        .login-card__header p {
            margin: 0.5rem 0 0;
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .login-form {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 1.1rem;
            z-index: 1;
        }

        .login-form label {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.35rem;
        }

        .login-form input {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(30, 64, 175, 0.25);
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .login-form input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.35);
        }

        .login-form button {
            border: none;
            border-radius: 999px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            font-weight: 600;
            padding: 0.95rem;
            font-size: 1.05rem;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .login-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 30px rgba(37, 99, 235, 0.25);
        }

        .login-error {
            margin-bottom: 1rem;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            background: rgba(220, 38, 38, 0.1);
            color: #b91c1c;
            font-weight: 500;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-card__header">
                <img src="gestion_stock/logo.png" alt="Logo" class="login-card__logo">
                <h1>Espace sécurisé</h1>
                <p>Accédez à la gestion du stock et aux sauvegardes DM avec un seul compte.</p>
            </div>

            <?php if ($errorMessage !== null): ?>
                <div class="login-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" class="login-form" autocomplete="on">
                <div>
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required value="<?php echo isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div>
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Se connecter</button>
            </form>
        </div>
    </div>
</body>
</html>
