<?php
declare(strict_types=1);

namespace App;

use RuntimeException;

class FtpClient
{
    private $connection;
    private bool $connected = false;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        if (!function_exists('ftp_connect')) {
            throw new RuntimeException('FTP extension is not enabled in PHP.');
        }

        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 21;
        $timeout = $this->config['timeout'] ?? 90;

        $this->connection = @ftp_connect($host, $port, $timeout);
        if ($this->connection === false) {
            return false;
        }

        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';
        if (!@ftp_login($this->connection, $username, $password)) {
            $this->disconnect();
            return false;
        }

        if (!empty($this->config['passive'])) {
            ftp_pasv($this->connection, true);
        }

        $this->connected = true;
        return true;
    }

    public function upload(string $localPath, string $remoteFilename): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $remotePath = $this->buildRemotePath($remoteFilename);
        $directory = rtrim(dirname($remotePath), '.');
        $this->ensureRemoteDirectory($directory);

        return ftp_put($this->connection, $remotePath, $localPath, FTP_BINARY);
    }

    public function download(string $remoteFilename, string $localPath): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $remotePath = $this->buildRemotePath($remoteFilename);
        return ftp_get($this->connection, $localPath, $remotePath, FTP_BINARY);
    }

    public function delete(string $remoteFilename): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $remotePath = $this->buildRemotePath($remoteFilename);
        return @ftp_delete($this->connection, $remotePath);
    }

    public function disconnect(): void
    {
        if ($this->connection) {
            ftp_close($this->connection);
            $this->connection = null;
            $this->connected = false;
        }
    }

    private function buildRemotePath(string $filename): string
    {
        $base = $this->config['base_path'] ?? '';
        $base = trim($base);
        if ($base === '' || $base === '.') {
            return $filename;
        }

        return rtrim($base, '/') . '/' . ltrim($filename, '/');
    }

    private function ensureRemoteDirectory(string $directory): void
    {
        if ($directory === '' || $directory === '.') {
            return;
        }

        $parts = explode('/', trim($directory, '/'));
        $path = '';
        foreach ($parts as $part) {
            $path .= '/' . $part;
            @ftp_mkdir($this->connection, $path);
        }
    }
}
