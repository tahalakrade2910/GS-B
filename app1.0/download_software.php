<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

require __DIR__ . '/bootstrap.php';

use App\Database;
use App\FtpClient;

$softwareId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$softwareId) {
    http_response_code(400);
    echo 'Identifiant de logiciel invalide.';
    exit();
}

try {
    $database = new Database($config['database']);
    $pdo = $database->pdo();
} catch (\PDOException $exception) {
    http_response_code(500);
    echo 'Impossible de se connecter à la base de données.';
    exit();
}

$statement = $pdo->prepare('SELECT file_name, file_type FROM device_software WHERE id = :id');
$statement->execute([':id' => $softwareId]);
$software = $statement->fetch();

if (!$software) {
    http_response_code(404);
    echo 'Logiciel introuvable.';
    exit();
}

$fileName = $software['file_name'] ?? '';
if ($fileName === '') {
    http_response_code(404);
    echo 'Fichier logiciel introuvable.';
    exit();
}

$ftpConfig = $config['ftp'];
$softwareBasePath = trim((string) ($ftpConfig['software_base_path'] ?? ''));
if ($softwareBasePath !== '') {
    $ftpConfig['base_path'] = $softwareBasePath;
}

$ftpClient = new FtpClient($ftpConfig);
$tempFile = tempnam(sys_get_temp_dir(), 'software_');

try {
    if (!$ftpClient->download($fileName, $tempFile)) {
        throw new \RuntimeException('Le fichier n\'a pas pu être téléchargé depuis le serveur FTP.');
    }
} catch (\RuntimeException $exception) {
    http_response_code(500);
    echo 'Erreur lors de la récupération du fichier logiciel.';
    $ftpClient->disconnect();
    @unlink($tempFile);
    exit();
}

$downloadName = basename($fileName);
$mimeType = !empty($software['file_type']) ? $software['file_type'] : 'application/octet-stream';

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($tempFile));
header('Cache-Control: must-revalidate');
header('Pragma: public');

readfile($tempFile);

$ftpClient->disconnect();
@unlink($tempFile);
exit();
