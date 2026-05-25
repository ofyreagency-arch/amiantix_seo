<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Connectors;

use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\RemoteCommandResult;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use Throwable;

class SshRemoteConnector implements RemoteConnector
{
    private ?SSH2 $ssh = null;

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
        $secret = (string) ($this->credentials['secret'] ?? '');

        if ($host === '' || $username === '' || $secret === '') {
            throw RemoteInstallationException::authentication();
        }

        try {
            $this->ssh = new SSH2($host, $port, 20);
            $this->sftp = new SFTP($host, $port, 20);
        } catch (Throwable) {
            throw RemoteInstallationException::connectivity('Serveur SSH inaccessible.');
        }

        $authSecret = str_contains($secret, 'BEGIN') ? PublicKeyLoader::load($secret) : $secret;

        if (! $this->ssh->login($username, $authSecret)) {
            throw RemoteInstallationException::authentication('Impossible de se connecter en SSH avec les identifiants fournis.');
        }

        if (! $this->sftp->login($username, $authSecret)) {
            throw RemoteInstallationException::authentication('Connexion SFTP refusée avec les identifiants fournis.');
        }
    }

    public function run(string $command, int $timeoutSeconds = 60): RemoteCommandResult
    {
        if (! $this->ssh) {
            throw RemoteInstallationException::connectivity();
        }

        $this->ssh->setTimeout($timeoutSeconds);
        $output = $this->ssh->exec($command);
        $exitCode = $this->ssh->getExitStatus();

        return new RemoteCommandResult(
            successful: $exitCode === 0 || $exitCode === null,
            output: trim((string) $output),
            errorOutput: '',
            exitCode: $exitCode,
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
        $this->ssh?->disconnect();
        $this->sftp?->disconnect();
        $this->ssh = null;
        $this->sftp = null;
    }
}
