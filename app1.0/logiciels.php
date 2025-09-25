<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$userRole = $_SESSION['role'] ?? 'user';
$isAdmin = $userRole === 'admin';

require __DIR__ . '/bootstrap.php';

use App\Database;
use App\FtpClient;

$db = new Database($config['database']);
$pdo = $db->pdo();
$baseUrl = '.';

$catalogue = [
    'Numériseur' => ['CR CLASSIC', 'CR VITA', 'CR VITAFLEX'],
    'Capteur' => ['LUX FOCUS', 'DRXPLUS'],
    'Reprograph' => ['DV5700', 'DV5950', 'DV6950'],
];

$dmTypes = array_keys($catalogue);

function e(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$errors = [];
$successMessage = $_SESSION['software_success'] ?? null;
if ($successMessage !== null) {
    unset($_SESSION['software_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        if (!$isAdmin) {
            $errors[] = 'Vous n\'êtes pas autorisé à supprimer des logiciels.';
        } else {
            $softwareId = filter_input(INPUT_POST, 'software_id', FILTER_VALIDATE_INT);
            if (!$softwareId) {
                $errors[] = 'Le logiciel à supprimer est invalide.';
            } else {
                $statement = $pdo->prepare('SELECT file_name FROM device_software WHERE id = :id');
                $statement->execute([':id' => $softwareId]);
                $software = $statement->fetch();

                if (!$software) {
                    $errors[] = 'Le logiciel demandé est introuvable.';
                } else {
                    $ftpConfig = $config['ftp'];
                    $softwareBasePath = trim((string) ($ftpConfig['software_base_path'] ?? ''));
                    if ($softwareBasePath !== '') {
                        $ftpConfig['base_path'] = $softwareBasePath;
                    }

                    $ftpClient = new FtpClient($ftpConfig);
                    $fileDeletionIssue = false;

                    try {
                        if (!empty($software['file_name'])) {
                            if (!$ftpClient->delete($software['file_name'])) {
                                $fileDeletionIssue = true;
                            }
                        }
                    } catch (\RuntimeException $exception) {
                        $fileDeletionIssue = true;
                    } finally {
                        $ftpClient->disconnect();
                    }

                    try {
                        $deleteStatement = $pdo->prepare('DELETE FROM device_software WHERE id = :id');
                        $deleteStatement->execute([':id' => $softwareId]);

                        $_SESSION['software_success'] = $fileDeletionIssue
                            ? 'La fiche a été supprimée, mais le fichier n\'a pas pu être retiré du serveur FTP.'
                            : 'Le logiciel a été supprimé avec succès.';

                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit();
                    } catch (\PDOException $exception) {
                        $errors[] = 'Une erreur de base de données est survenue lors de la suppression du logiciel.';
                    }
                }
            }
        }
    } else {
        if (!$isAdmin) {
            $errors[] = 'Vous n\'êtes pas autorisé à ajouter des logiciels.';
        } else {
            $dmType = trim($_POST['dm_type'] ?? '');
            $dmModel = trim($_POST['dm_model'] ?? '');
            $version = trim($_POST['version'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!in_array($dmType, $dmTypes, true)) {
                $errors[] = 'Le type de dispositif médical sélectionné est invalide.';
            }

            if ($dmType !== '' && (!isset($catalogue[$dmType]) || !in_array($dmModel, $catalogue[$dmType], true))) {
                $errors[] = 'Le modèle sélectionné ne correspond pas au type de dispositif.';
            }

            if ($version === '') {
                $errors[] = 'La version du logiciel est obligatoire.';
            }

            $fileInfo = $_FILES['software_file'] ?? null;
            $remoteFilename = '';
            $remoteFileType = 'application/octet-stream';

            if (!$fileInfo || $fileInfo['error'] === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Vous devez sélectionner un fichier logiciel à téléverser.';
            } elseif ($fileInfo['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Une erreur est survenue lors du téléversement du fichier.';
            } else {
                $originalName = $fileInfo['name'] ?? 'logiciel';
                $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', strtolower($originalName));
                if ($safeName === '' || $safeName === false) {
                    $safeName = 'logiciel';
                }

                $remoteFilename = date('Ymd_His') . '_' . $safeName;
                $remoteFileType = $fileInfo['type'] ?? 'application/octet-stream';

                $ftpConfig = $config['ftp'];
                $softwareBasePath = trim((string) ($ftpConfig['software_base_path'] ?? ''));
                if ($softwareBasePath !== '') {
                    $ftpConfig['base_path'] = $softwareBasePath;
                }

                $ftpClient = new FtpClient($ftpConfig);
                $uploadSuccess = false;

                try {
                    $uploadSuccess = $ftpClient->upload($fileInfo['tmp_name'], $remoteFilename);
                } catch (\RuntimeException $exception) {
                    $errors[] = 'Erreur FTP : ' . $exception->getMessage();
                } finally {
                    $ftpClient->disconnect();
                }

                if (!$uploadSuccess) {
                    $errors[] = 'Impossible de téléverser le fichier sur le serveur FTP.';
                }
            }

            if (empty($errors)) {
                try {
                    $insertStatement = $pdo->prepare(
                        'INSERT INTO device_software (dm_type, dm_model, version, description, file_name, file_type, added_at) '
                        . 'VALUES (:dm_type, :dm_model, :version, :description, :file_name, :file_type, NOW())'
                    );

                    $insertStatement->execute([
                        ':dm_type' => $dmType,
                        ':dm_model' => $dmModel,
                        ':version' => $version,
                        ':description' => $description !== '' ? $description : null,
                        ':file_name' => $remoteFilename,
                        ':file_type' => $remoteFileType,
                    ]);

                    $_SESSION['software_success'] = 'Le logiciel a été enregistré avec succès.';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                } catch (\PDOException $exception) {
                    $errors[] = 'Une erreur de base de données est survenue lors de l\'enregistrement du logiciel.';

                    if ($remoteFilename !== '') {
                        $ftpConfig = $config['ftp'];
                        $softwareBasePath = trim((string) ($ftpConfig['software_base_path'] ?? ''));
                        if ($softwareBasePath !== '') {
                            $ftpConfig['base_path'] = $softwareBasePath;
                        }

                        $cleanupClient = new FtpClient($ftpConfig);
                        try {
                            $cleanupClient->delete($remoteFilename);
                        } catch (\RuntimeException $cleanupException) {
                            // Ignorer les erreurs lors du nettoyage
                        } finally {
                            $cleanupClient->disconnect();
                        }
                    }
                }
            }
        }
    }
}

$softwareStatement = $pdo->query('SELECT id, dm_type, dm_model, version, description, file_name, file_type, added_at FROM device_software ORDER BY dm_type ASC, dm_model ASC, added_at DESC');
$softwareEntries = $softwareStatement->fetchAll();
$catalogueJson = json_encode($catalogue, JSON_UNESCAPED_UNICODE);
if ($catalogueJson === false) {
    $catalogueJson = '{}';
}

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Logiciels des dispositifs médicaux</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<?php require __DIR__ . '/partials/top_nav.php'; ?>
<header class="page-header">
    <img src="assets/images/logo.png" alt="Logo IMAlliance" class="page-logo">
    <div class="page-header-content">
        <h1>Logiciels des dispositifs médicaux</h1>
        <p>Retrouvez les logiciels officiels pour chaque dispositif médical et téléchargez-les en un clic.</p>
    </div>
</header>

<main class="software-layout">
    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success">
            <?= e($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <h2>Veuillez corriger les éléments suivants :</h2>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <section class="card software-card-form">
            <h2>Ajouter un logiciel</h2>
            <form action="<?= e($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" class="software-form">
                <input type="hidden" name="action" value="create">
                <div class="form-grid">
                    <div class="form-field">
                        <label for="dm_type">Type de dispositif <span class="required">*</span></label>
                        <select name="dm_type" id="dm_type" required>
                            <option value="">-- Sélectionnez --</option>
                            <?php foreach ($dmTypes as $type): ?>
                                <option value="<?= e($type); ?>" <?= (isset($_POST['dm_type']) && $_POST['dm_type'] === $type) ? 'selected' : ''; ?>><?= e($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="dm_model">Modèle <span class="required">*</span></label>
                        <select name="dm_model" id="dm_model" required>
                            <option value="">-- Sélectionnez --</option>
                            <?php
                            $selectedType = $_POST['dm_type'] ?? '';
                            $selectedModel = $_POST['dm_model'] ?? '';
                            if (isset($catalogue[$selectedType])) {
                                foreach ($catalogue[$selectedType] as $model) {
                                    $isSelected = $selectedModel === $model ? 'selected' : '';
                                    echo '<option value="' . e($model) . '" ' . $isSelected . '>' . e($model) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="version">Version <span class="required">*</span></label>
                        <input type="text" name="version" id="version" value="<?= e($_POST['version'] ?? ''); ?>" required>
                    </div>
                    <div class="form-field">
                        <label for="software_file">Fichier logiciel <span class="required">*</span></label>
                        <input type="file" name="software_file" id="software_file" required>
                    </div>
                </div>
                <div class="form-field">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="4" placeholder="Ajoutez des notes sur le logiciel (optionnel)"><?= e($_POST['description'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="button-link">Enregistrer le logiciel</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="card">
        <div class="software-filters">
            <div class="filter-field">
                <label for="filter-type">Type de dispositif</label>
                <select id="filter-type">
                    <option value="">Tous</option>
                    <?php foreach ($dmTypes as $type): ?>
                        <option value="<?= e($type); ?>"><?= e($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label for="filter-model">Modèle</label>
                <select id="filter-model" disabled>
                    <option value="">Tous</option>
                </select>
            </div>
        </div>

        <?php if (empty($softwareEntries)): ?>
            <p class="empty-state">Aucun logiciel n'a encore été enregistré.</p>
        <?php else: ?>
            <div class="software-grid" data-catalogue='<?= e($catalogueJson); ?>'>
                <?php foreach ($softwareEntries as $software): ?>
                    <?php
                    $softwareId = (int) ($software['id'] ?? 0);
                    $downloadUrl = 'download_software.php?id=' . $softwareId;
                    ?>
                    <article class="software-card" data-type="<?= e($software['dm_type']); ?>" data-model="<?= e($software['dm_model']); ?>">
                        <header class="software-card__header">
                            <h3><?= e($software['dm_model']); ?></h3>
                            <span class="software-card__type"><?= e($software['dm_type']); ?></span>
                        </header>
                        <dl class="software-card__meta">
                            <div>
                                <dt>Version</dt>
                                <dd><?= e($software['version']); ?></dd>
                            </div>
                            <div>
                                <dt>Date d'ajout</dt>
                                <dd><?= e(date('d/m/Y', strtotime($software['added_at']))); ?></dd>
                            </div>
                        </dl>
                        <?php if (!empty($software['description'])): ?>
                            <p class="software-card__description"><?= nl2br(e($software['description'])); ?></p>
                        <?php endif; ?>
                        <div class="software-card__actions">
                            <a href="<?= e($downloadUrl); ?>" class="button-link">Télécharger</a>
                            <?php if ($isAdmin): ?>
                                <form method="post" action="<?= e($_SERVER['PHP_SELF']); ?>" onsubmit="return confirm('Supprimer ce logiciel ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="software_id" value="<?= e((string) $softwareId); ?>">
                                    <button type="submit" class="button-link secondary">Supprimer</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
    (function () {
        const gridElement = document.querySelector('.software-grid');
        let catalogue = {};

        if (gridElement && gridElement.dataset.catalogue) {
            try {
                catalogue = JSON.parse(gridElement.dataset.catalogue);
            } catch (error) {
                catalogue = {};
            }
        }

        const typeSelect = document.getElementById('filter-type');
        const modelSelect = document.getElementById('filter-model');
        const cards = Array.from(document.querySelectorAll('.software-card'));

        function populateModels(type) {
            modelSelect.innerHTML = '<option value="">Tous</option>';
            modelSelect.disabled = !type;

            if (!type || !catalogue[type]) {
                return;
            }

            catalogue[type].forEach(function (model) {
                const option = document.createElement('option');
                option.value = model;
                option.textContent = model;
                modelSelect.appendChild(option);
            });
        }

        function filterCards() {
            const selectedType = typeSelect.value;
            const selectedModel = modelSelect.value;

            cards.forEach(function (card) {
                const matchesType = !selectedType || card.dataset.type === selectedType;
                const matchesModel = !selectedModel || card.dataset.model === selectedModel;

                card.style.display = (matchesType && matchesModel) ? '' : 'none';
            });
        }

        if (typeSelect && modelSelect) {
            typeSelect.addEventListener('change', function () {
                populateModels(typeSelect.value);
                modelSelect.value = '';
                filterCards();
            });

            modelSelect.addEventListener('change', filterCards);
        }

        filterCards();
    })();
</script>
</body>
</html>
