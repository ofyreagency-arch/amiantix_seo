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
            throw RemoteInstallationException::authentication(
                'Hôte SFTP, utilisateur ou mot de passe manquant.'
            );
        }

        try {
            $this->sftp = new SFTP($host, $port, 20);
        } catch (Throwable $exception) {
            throw RemoteInstallationException::connectivity(
                sprintf(
                    'Connexion SFTP impossible vers %s:%d. Vérifiez l hôte, le port et que le serveur accepte les connexions SFTP.%s',
                    $host,
                    $port,
                    ($detail = trim($exception->getMessage())) !== '' ? ' Détail: '.$detail : ''
                )
            );
        }

        if (! $this->sftp->login($username, $password)) {
            $errors = method_exists($this->sftp, 'getErrors') && is_array($this->sftp->getErrors())
                ? implode(' | ', array_filter(array_map(static fn (mixed $error): string => trim((string) $error), $this->sftp->getErrors())))
                : '';

            throw RemoteInstallationException::authentication(
                sprintf(
                    'Le serveur a refusé la connexion SFTP pour %s@%s:%d.%s',
                    $username,
                    $host,
                    $port,
                    $errors !== '' ? ' Détail: '.$errors : ' Vérifiez le mot de passe et la disponibilité du sous-système SFTP.'
                )
            );
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
