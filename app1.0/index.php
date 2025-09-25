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
$defaultStatus = 'Stored';

$formData = [
    'equipment' => '',
    'designation' => '',
    'numero_serie' => '',
    'client' => '',
    'fournisseur' => '',
    'date_backup' => '',
    'file_date' => '',
    'commentaire' => '',
    'status' => $defaultStatus,
];

$errors = [];
$successMessage = $_SESSION['success'] ?? null;
if ($successMessage) {
    unset($_SESSION['success']);
}

$isEditing = false;
$formDataFromPost = false;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: null;
$currentFileName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $formDataFromPost = true;
     $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        if (!$isAdmin) {
            $errors[] = 'Vous n\'êtes pas autorisé à supprimer des sauvegardes.';
        } else {
            $deleteId = filter_input(INPUT_POST, 'backup_id', FILTER_VALIDATE_INT);
            if (!$deleteId) {
                $errors[] = 'La sauvegarde à supprimer est invalide.';
            } else {
                $statement = $pdo->prepare('SELECT id, file_name FROM backups WHERE id = :id');
                $statement->execute([':id' => $deleteId]);
                $backupToDelete = $statement->fetch();

                if (!$backupToDelete) {
                    $errors[] = 'La sauvegarde demandée est introuvable.';
                } else {
                    $fileDeletionIssue = false;

                    if (!empty($backupToDelete['file_name'])) {
                        $ftpClient = new FtpClient($config['ftp']);

                        try {
                            $deleted = $ftpClient->delete($backupToDelete['file_name']);
                            if (!$deleted) {
                                $fileDeletionIssue = true;
                            }
                        } catch (\RuntimeException $exception) {
                            $fileDeletionIssue = true;
                        } finally {
                            if (isset($ftpClient)) {
                                $ftpClient->disconnect();
                            }
                        }
                    }

                    try {
                        $statement = $pdo->prepare('DELETE FROM backups WHERE id = :id');
                        $statement->execute([':id' => $deleteId]);

                        $_SESSION['success'] = $fileDeletionIssue
                            ? 'La sauvegarde a été supprimée, mais le fichier n\'a pas pu être retiré du serveur FTP.'
                            : 'La sauvegarde a été supprimée avec succès.';

                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } catch (\PDOException $exception) {
                        $errors[] = 'Une erreur de base de données est survenue lors de la suppression de la sauvegarde.';
                    }
                }
            }
        }
    } else {
        $isEditAction = $action === 'update';
        $backupId = $isEditAction ? filter_input(INPUT_POST, 'backup_id', FILTER_VALIDATE_INT) : null;
        $existingBackup = null;

        $skipEditProcessing = $isEditAction && !$isAdmin;
        if ($skipEditProcessing) {
            $errors[] = 'Vous n\'êtes pas autorisé à modifier des sauvegardes.';
        } else {
            if ($isEditAction) {
                if (!$backupId) {
                    $errors[] = 'La sauvegarde à modifier est invalide.';
                } else {
                    $statement = $pdo->prepare('SELECT * FROM backups WHERE id = :id');
                    $statement->execute([':id' => $backupId]);
                    $existingBackup = $statement->fetch();

                    if (!$existingBackup) {
                        $errors[] = 'La sauvegarde demandée est introuvable.';
                    } else {
                        $isEditing = true;
                        $editId = $backupId;
                        $currentFileName = $existingBackup['file_name'] ?? '';
                    }
                }
            }

            $formData = [
                'equipment' => trim($_POST['equipment'] ?? ''),
                'designation' => trim($_POST['designation'] ?? ''),
                'numero_serie' => trim($_POST['numero_serie'] ?? ''),
                'client' => trim($_POST['client'] ?? ''),
                'fournisseur' => trim($_POST['fournisseur'] ?? ''),
                'date_backup' => $_POST['date_backup'] ?? '',
                'file_date' => $_POST['file_date'] ?? '',
                'commentaire' => trim($_POST['commentaire'] ?? ''),
                'status' => $existingBackup['status'] ?? $defaultStatus,
            ];

            if ($formData['equipment'] === '') {
                $errors[] = 'Le champ « Équipement » est obligatoire.';
            }

            if ($formData['designation'] === '') {
                $errors[] = 'Le champ « Désignation » est obligatoire.';
            }

            if ($formData['client'] === '') {
                $errors[] = 'Le champ « Client » est obligatoire.';
            }

            if ($formData['date_backup'] === '') {
                $errors[] = 'La date de sauvegarde est obligatoire.';
            }

            $fileInfo = $_FILES['backup_file'] ?? null;
            $remoteFilename = $existingBackup['file_name'] ?? '';
            $remoteFileType = $existingBackup['file_type'] ?? 'application/octet-stream';
            $newFileUploaded = false;
            $oldRemoteFilename = $existingBackup['file_name'] ?? '';

            if ($fileInfo && $fileInfo['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Une erreur est survenue lors du téléversement du fichier.';
                } else {
                    $originalName = $fileInfo['name'] ?? 'backup';
                    $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', strtolower($originalName));
                    if ($safeName === '' || $safeName === false) {
                        $safeName = 'backup';
                    }
                    $remoteFilename = date('Ymd_His') . '_' . $safeName;
                    $remoteFileType = $fileInfo['type'] ?? 'application/octet-stream';

                    $ftpClient = new FtpClient($config['ftp']);
                    $uploadSuccess = false;

                    try {
                        $uploadSuccess = $ftpClient->upload($fileInfo['tmp_name'], $remoteFilename);
                    } catch (\RuntimeException $exception) {
                        $errors[] = 'Erreur FTP : ' . $exception->getMessage();
                    } finally {
                        if (isset($ftpClient)) {
                            $ftpClient->disconnect();
                        }
                    }

                    if (!$uploadSuccess) {
                        $errors[] = 'Impossible de téléverser le fichier de sauvegarde sur le serveur FTP.';
                    } else {
                        $newFileUploaded = true;
                    }
                }
            } elseif (!$isEditAction) {
                $errors[] = 'Un fichier de sauvegarde doit être sélectionné.';
            }

            if (empty($errors)) {
                if ($isEditAction) {
                    try {
                        $statement = $pdo->prepare('UPDATE backups SET equipment = :equipment, designation = :designation, numero_serie = :numero_serie, client = :client, fournisseur = :fournisseur, date_backup = :date_backup, commentaire = :commentaire, status = :status, file_name = :file_name, file_type = :file_type, file_date = :file_date WHERE id = :id');

                        $statement->execute([
                            ':equipment' => $formData['equipment'],
                            ':designation' => $formData['designation'],
                            ':numero_serie' => $formData['numero_serie'] !== '' ? $formData['numero_serie'] : null,
                            ':client' => $formData['client'],
                            ':fournisseur' => $formData['fournisseur'] !== '' ? $formData['fournisseur'] : null,
                            ':date_backup' => $formData['date_backup'],
                            ':commentaire' => $formData['commentaire'] !== '' ? $formData['commentaire'] : null,
                            ':status' => $formData['status'],
                            ':file_name' => $remoteFilename,
                            ':file_type' => $remoteFileType,
                            ':file_date' => $formData['file_date'] !== '' ? $formData['file_date'] : null,
                            ':id' => $backupId,
                        ]);

                        $message = 'La sauvegarde a été mise à jour avec succès.';

                        if ($newFileUploaded && $oldRemoteFilename !== '' && $oldRemoteFilename !== $remoteFilename) {
                            $cleanupClient = new FtpClient($config['ftp']);
                            $cleanupIssue = false;

                            try {
                                if (!$cleanupClient->delete($oldRemoteFilename)) {
                                    $cleanupIssue = true;
                                }
                            } catch (\RuntimeException $exception) {
                                $cleanupIssue = true;
                            } finally {
                                $cleanupClient->disconnect();
                            }

                            if ($cleanupIssue) {
                                $message .= ' Cependant, l\'ancien fichier n\'a pas pu être retiré du serveur FTP.';
                            }
                        }

                        $_SESSION['success'] = $message;
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } catch (\PDOException $exception) {
                        $errors[] = 'Une erreur de base de données est survenue lors de la mise à jour de la sauvegarde.';

                        if ($newFileUploaded && $remoteFilename !== '') {
                            $cleanupClient = new FtpClient($config['ftp']);
                            try {
                                $cleanupClient->delete($remoteFilename);
                            } catch (\RuntimeException $cleanupException) {
                                // Ignorer les erreurs de nettoyage
                            } finally {
                                $cleanupClient->disconnect();
                            }
                        }
                    }
                } else {
                    try {
                        $statement = $pdo->prepare('INSERT INTO backups (equipment, designation, numero_serie, client, fournisseur, date_backup, commentaire, status, file_name, file_type, file_date, uploaded_at) VALUES (:equipment, :designation, :numero_serie, :client, :fournisseur, :date_backup, :commentaire, :status, :file_name, :file_type, :file_date, NOW())');

                        $statement->execute([
                            ':equipment' => $formData['equipment'],
                            ':designation' => $formData['designation'],
                            ':numero_serie' => $formData['numero_serie'] !== '' ? $formData['numero_serie'] : null,
                            ':client' => $formData['client'],
                            ':fournisseur' => $formData['fournisseur'] !== '' ? $formData['fournisseur'] : null,
                            ':date_backup' => $formData['date_backup'],
                            ':commentaire' => $formData['commentaire'] !== '' ? $formData['commentaire'] : null,
                            ':status' => $formData['status'],
                            ':file_name' => $remoteFilename,
                            ':file_type' => $remoteFileType,
                            ':file_date' => $formData['file_date'] !== '' ? $formData['file_date'] : null,
                        ]);

                        $_SESSION['success'] = 'La sauvegarde a été enregistrée avec succès.';
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } catch (\PDOException $exception) {
                        $errors[] = 'Une erreur de base de données est survenue lors de l\'enregistrement de la sauvegarde.';

                        if ($newFileUploaded && $remoteFilename !== '') {
                            $cleanupClient = new FtpClient($config['ftp']);
                            try {
                                $cleanupClient->delete($remoteFilename);
                            } catch (\RuntimeException $cleanupException) {
                                // Ignorer les erreurs de nettoyage
                            } finally {
                                $cleanupClient->disconnect();
                            }
                        }
                    }
                }
            } else {
                if ($isEditAction && $existingBackup) {
                    $isEditing = true;
                    $currentFileName = $existingBackup['file_name'] ?? '';
                }
            }
        }
    }
}

if (!$formDataFromPost && $editId) {
    if (!$isAdmin) {
        $errors[] = 'Vous n\'êtes pas autorisé à modifier des sauvegardes.';
        $editId = null;
    } else {
        $statement = $pdo->prepare('SELECT * FROM backups WHERE id = :id');
        $statement->execute([':id' => $editId]);
        $backupToEdit = $statement->fetch();

        if ($backupToEdit) {
            $isEditing = true;
            $currentFileName = $backupToEdit['file_name'] ?? '';
            $formData = [
                'equipment' => $backupToEdit['equipment'] ?? '',
                'designation' => $backupToEdit['designation'] ?? '',
                'numero_serie' => $backupToEdit['numero_serie'] ?? '',
                'client' => $backupToEdit['client'] ?? '',
                'fournisseur' => $backupToEdit['fournisseur'] ?? '',
                'date_backup' => $backupToEdit['date_backup'] ?? '',
                'file_date' => $backupToEdit['file_date'] ?? '',
                'commentaire' => $backupToEdit['commentaire'] ?? '',
                'status' => $backupToEdit['status'] ?? $defaultStatus,
            ];
        } else {
            $errors[] = 'La sauvegarde demandée est introuvable.';
            $editId = null;
        }
    }
}

$backupsStatement = $pdo->query('SELECT id, equipment, designation, numero_serie, client, fournisseur, date_backup, commentaire, file_name, file_type, file_date, uploaded_at FROM backups ORDER BY date_backup DESC, id DESC');
$backups = $backupsStatement->fetchAll();

$clientNames = array_map(
    static function (array $backup): string {
        return trim((string) ($backup['client'] ?? ''));
    },
    $backups
);
$uniqueClients = array_values(array_filter(array_unique($clientNames), static fn(string $name): bool => $name !== ''));
natcasesort($uniqueClients);
$uniqueClients = array_values($uniqueClients);
$defaultSection = ($isEditing || $formDataFromPost) ? 'create' : 'list';

function e(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function toSearchIndex(array $values): string
{
    $filtered = array_filter($values, static fn($value): bool => $value !== null && $value !== '');
    if (empty($filtered)) {
        return '';
    }

    $string = implode(' ', array_map(static fn($value): string => (string) $value, $filtered));

    return function_exists('mb_strtolower')
        ? mb_strtolower($string, 'UTF-8')
        : strtolower($string);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des sauvegardes</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <?php require __DIR__ . '/partials/top_nav.php'; ?>
    <header class="page-header">
        <img src="assets/images/logo.png" alt="Logo IMAlliance" class="page-logo">
        <div class="page-header-content">
            <h1>Gestion des sauvegardes</h1>
            <p>Stockez vos sauvegardes en toute sécurité et retrouvez-les facilement, partout et à tout instant.</p>
        </div>
    </header>

    <main data-default-section="<?= e($defaultSection); ?>">
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

        <div class="section-toggle" role="group" aria-label="Navigation des sauvegardes">
            <button type="button" class="toggle-button<?= $defaultSection === 'create' ? ' is-active' : ''; ?>" data-section-toggle="create" aria-pressed="<?= $defaultSection === 'create' ? 'true' : 'false'; ?>">➕ Ajouter une sauvegarde</button>
            <button type="button" class="toggle-button<?= $defaultSection === 'list' ? ' is-active' : ''; ?>" data-section-toggle="list" aria-pressed="<?= $defaultSection === 'list' ? 'true' : 'false'; ?>">📂 Consulter les sauvegardes</button>
        </div>

        <section class="card<?= $defaultSection === 'create' ? '' : ' hidden'; ?>" id="create-section" aria-hidden="<?= $defaultSection === 'create' ? 'false' : 'true'; ?>">
            <h2><?= $isEditing ? 'Modifier une sauvegarde' : 'Enregistrer une nouvelle sauvegarde'; ?></h2>
            <form action="<?= e($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data" class="backup-form">
                <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create'; ?>">
                <?php if ($isEditing && $editId): ?>
                    <input type="hidden" name="backup_id" value="<?= e((string) $editId); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="equipment">Équipement <span class="required">*</span></label>
                    <input type="text" name="equipment" id="equipment" value="<?= e($formData['equipment']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="designation">Désignation <span class="required">*</span></label>
                    <input type="text" name="designation" id="designation" value="<?= e($formData['designation']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="numero_serie">Numéro de série</label>
                    <input type="text" name="numero_serie" id="numero_serie" value="<?= e($formData['numero_serie']); ?>">
                </div>

                <div class="form-group">
                    <label for="client">Client <span class="required">*</span></label>
                    <input type="text" name="client" id="client" value="<?= e($formData['client']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="fournisseur">Fournisseur</label>
                    <input type="text" name="fournisseur" id="fournisseur" value="<?= e($formData['fournisseur']); ?>">
                </div>

                <div class="form-group">
                    <label for="date_backup">Date de sauvegarde <span class="required">*</span></label>
                    <input type="date" name="date_backup" id="date_backup" value="<?= e($formData['date_backup']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="file_date">Date du fichier</label>
                    <input type="date" name="file_date" id="file_date" value="<?= e($formData['file_date']); ?>">
                </div>

                <div class="form-group">
                    <label for="commentaire">Commentaires</label>
                    <textarea name="commentaire" id="commentaire" rows="3"><?= e($formData['commentaire']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="backup_file">
                        <?= $isEditing ? 'Nouveau fichier de sauvegarde' : 'Fichier de sauvegarde'; ?>
                        <?php if (!$isEditing): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>
                    <input type="file" name="backup_file" id="backup_file" <?= $isEditing ? '' : 'required'; ?>>
                    <?php if ($isEditing && $currentFileName !== ''): ?>
                        <small>Fichier actuel : <?= e($currentFileName); ?>. Laissez ce champ vide pour conserver le fichier existant.</small>
                    <?php else: ?>
                        <small>Les fichiers sont téléversés sur le serveur FTP configuré.</small>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit"><?= $isEditing ? 'Mettre à jour la sauvegarde' : 'Enregistrer la sauvegarde'; ?></button>
                    <?php if ($isEditing): ?>
                        <a href="<?= e($_SERVER['PHP_SELF']); ?>" class="button-link secondary">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="card<?= $defaultSection === 'list' ? '' : ' hidden'; ?>" id="list-section" aria-hidden="<?= $defaultSection === 'list' ? 'false' : 'true'; ?>">
            <h2>Sauvegardes enregistrées</h2>
            <?php if (empty($backups)): ?>
                <p class="muted">Aucune sauvegarde n'a encore été enregistrée.</p>
            <?php else: ?>
                <div class="backup-filters">
                    <div class="filter-field">
                        <label for="backup-search">Recherche</label>
                        <input type="search" id="backup-search" placeholder="Rechercher par équipement, client…">
                    </div>
                    <div class="filter-field">
                        <label for="client-filter">Client</label>
                        <select id="client-filter">
                            <option value="">Tous les clients</option>
                            <?php foreach ($uniqueClients as $client): ?>
                                <option value="<?= e($client); ?>"><?= e($client); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="button-link secondary" id="clear-filters">Réinitialiser</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Équipement</th>
                                <th>Désignation</th>
                                <th>Client</th>
                                <th>Date de sauvegarde</th>
                                <th>Téléversé le</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <?php
                                    $backupId = (string) ($backup['id'] ?? '');
                                    $clientValue = trim((string) ($backup['client'] ?? ''));
                                    $searchIndex = toSearchIndex([
                                        $backupId,
                                        $backup['equipment'] ?? '',
                                        $backup['designation'] ?? '',
                                        $backup['numero_serie'] ?? '',
                                        $clientValue,
                                        $backup['fournisseur'] ?? '',
                                        $backup['commentaire'] ?? '',
                                        $backup['file_name'] ?? '',
                                        $backup['file_date'] ?? '',
                                        $backup['date_backup'] ?? '',
                                    ]);
                                ?>
                                <tr class="backup-row" data-backup-id="<?= e($backupId); ?>" data-client="<?= e($clientValue); ?>" data-search="<?= e($searchIndex); ?>">
                                    <td><?= e($backupId); ?></td>
                                    <td><?= e($backup['equipment']); ?></td>
                                    <td><?= e($backup['designation']); ?></td>
                                    <td><?= e($backup['client']); ?></td>
                                    <td><?= e($backup['date_backup']); ?></td>
                                    <td><?= e($backup['uploaded_at']); ?></td>
                                    <td class="table-actions">
                                        <a href="download.php?id=<?= e($backupId); ?>" class="button-link">Télécharger</a>
                                        <?php if ($isAdmin): ?>
                                            <a href="index.php?edit=<?= e($backupId); ?>" class="button-link secondary">Modifier</a>
                                            <form action="<?= e($_SERVER['PHP_SELF']); ?>" method="post" class="inline-form" onsubmit="return confirm('Confirmez-vous la suppression de cette sauvegarde ?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="backup_id" value="<?= e($backupId); ?>">
                                                <button type="submit" class="button-link danger">Supprimer</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="details-row" data-backup-id="<?= e($backupId); ?>">
                                    <td colspan="7">
                                        <strong>Numéro de série :</strong> <?= e($backup['numero_serie']); ?>
                                        <strong>Fournisseur :</strong> <?= e($backup['fournisseur']); ?>
                                        <strong>Date du fichier :</strong> <?= e($backup['file_date']); ?>
                                        <strong>Nom du fichier :</strong> <?= e($backup['file_name']); ?>
                                        <?php if (!empty($backup['commentaire'])): ?>
                                            <div><strong>Commentaires :</strong> <?= e($backup['commentaire']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="no-results-row" class="hidden">
                                <td colspan="7" class="no-results">Aucune sauvegarde ne correspond aux critères de recherche.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mainElement = document.querySelector('main');
            const defaultSection = mainElement && mainElement.dataset.defaultSection ? mainElement.dataset.defaultSection : 'list';
            const sections = {
                create: document.getElementById('create-section'),
                list: document.getElementById('list-section')
            };
            const toggleButtons = document.querySelectorAll('[data-section-toggle]');

            function activateSection(sectionName) {
                toggleButtons.forEach(function (button) {
                    const isActive = button.dataset.sectionToggle === sectionName;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });

                Object.keys(sections).forEach(function (name) {
                    const element = sections[name];
                    if (!element) {
                        return;
                    }
                    const isActive = name === sectionName;
                    element.classList.toggle('hidden', !isActive);
                    element.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                });
            }

            toggleButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    activateSection(button.dataset.sectionToggle);
                });
            });

            activateSection(defaultSection);

            const searchInput = document.getElementById('backup-search');
            const clientFilter = document.getElementById('client-filter');
            const clearFiltersButton = document.getElementById('clear-filters');
            const backupRows = Array.from(document.querySelectorAll('.backup-row'));
            const detailRows = new Map();

            backupRows.forEach(function (row) {
                const backupId = row.dataset.backupId || '';
                if (!backupId) {
                    return;
                }
                const detailRow = document.querySelector('.details-row[data-backup-id="' + backupId + '"]');
                if (detailRow) {
                    detailRows.set(backupId, detailRow);
                }
            });

            const noResultsRow = document.getElementById('no-results-row');

            function applyFilters() {
                const query = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
                const client = clientFilter ? clientFilter.value : '';
                let visibleCount = 0;

                backupRows.forEach(function (row) {
                    const searchValue = row.dataset.search || '';
                    const matchesSearch = !query || searchValue.indexOf(query) !== -1;
                    const matchesClient = !client || (row.dataset.client || '') === client;
                    const isVisible = matchesSearch && matchesClient;

                    row.classList.toggle('hidden', !isVisible);
                    const detailRow = detailRows.get(row.dataset.backupId || '');
                    if (detailRow) {
                        detailRow.classList.toggle('hidden', !isVisible);
                    }

                    if (isVisible) {
                        visibleCount++;
                    }
                });

                if (noResultsRow) {
                    noResultsRow.classList.toggle('hidden', visibleCount !== 0);
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }
            if (clientFilter) {
                clientFilter.addEventListener('change', applyFilters);
            }
            if (clearFiltersButton) {
                clearFiltersButton.addEventListener('click', function () {
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    if (clientFilter) {
                        clientFilter.value = '';
                    }
                    applyFilters();
                    if (searchInput) {
                        searchInput.focus();
                    }
                });
            }

            applyFilters();
        });
    </script>

    <footer class="page-footer">
        <p>Configurez le fichier <code>config.php</code> avec vos identifiants MySQL et FTP pour utiliser l'application.</p>
    </footer>
</body>
</html>
