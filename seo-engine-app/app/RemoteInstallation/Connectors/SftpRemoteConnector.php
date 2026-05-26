<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Connectors;

use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\RemoteCommand;
use App\RemoteInstallation\RemoteCommandResult;
use phpseclib3\Net\SFTP;
use Throwable;

class SftpRemoteConnector implements RemoteConnector
{
    private ?SFTP $sftp = null;

    /**
     * @param array<string,mixed> $credentials
     */
    public function __construct(private readonly array $credentials)
    {
    }

    public function connect(): void
    {
        $host = (string) ($this->credentials['host'] ?? '');
        $port = (int) ($this->credentials['port'] ?? 22);
        $username = (string) ($this->credentials['username'] ?? '');
        $password = (string) ($this->credentials['password'] ?? '');

        if ($host === '' || $username === '' || $password === '') {
            throw RemoteInstallationException::authentication();
        }

        try {
            $this->sftp = new SFTP($host, $port, 20);
        } catch (Throwable) {
            throw RemoteInstallationException::connectivity('Serveur SFTP inaccessible.');
        }

        if (! $this->sftp->login($username, $password)) {
            throw RemoteInstallationException::authentication('Impossible de se connecter en SFTP avec les identifiants fournis.');
        }
    }

    public function run(RemoteCommand $command, int $timeoutSeconds = 60): RemoteCommandResult
    {
        throw RemoteInstallationException::unsupported(
            'L installation automatique distante demande un accès SSH pour executer Composer et PHP sur ce serveur.'
        );
    }

    public function fileExists(string $path): bool
    {
        if (! $this->sftp) {
            throw RemoteInstallationException::connectivity();
        }

        return $this->sftp->file_exists($path);
    }

    public function readFile(string $path): ?string
    {
        if (! $this->sftp) {
            throw RemoteInstallationException::connectivity();
        }

        $contents = $this->sftp->get($path);

        return $contents === false ? null : $contents;
    }

    public function disconnect(): void
    {
        $this->sftp?->disconnect();
        $this->sftp = null;
    }
}
