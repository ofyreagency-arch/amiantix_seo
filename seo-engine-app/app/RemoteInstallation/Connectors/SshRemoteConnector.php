<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Connectors;

use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\RemoteCommand;
use App\RemoteInstallation\RemoteCommandResult;
use Illuminate\Support\Facades\Log;
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
            $this->logDiagnostic('missing_credentials', $host, $port, $username, [
                'secret_present' => $secret !== '',
            ]);

            throw RemoteInstallationException::authentication(
                'Hôte SSH, utilisateur ou mot de passe / clé privée manquant.'
            );
        }

        try {
            $this->ssh = new SSH2($host, $port, 20);
        } catch (Throwable $exception) {
            $this->logDiagnostic('ssh_transport_connect_failed', $host, $port, $username, [
                'original_exception_class' => $exception::class,
                'original_exception_message' => $exception->getMessage(),
                'original_exception_trace' => $exception->getTraceAsString(),
            ]);

            throw RemoteInstallationException::connectivity(
                $this->connectivityMessage('SSH', $host, $port, $exception)
            );
        }

        try {
            $this->sftp = new SFTP($host, $port, 20);
        } catch (Throwable $exception) {
            $this->logDiagnostic('sftp_transport_connect_failed', $host, $port, $username, [
                'original_exception_class' => $exception::class,
                'original_exception_message' => $exception->getMessage(),
                'original_exception_trace' => $exception->getTraceAsString(),
            ]);

            throw RemoteInstallationException::connectivity(
                $this->connectivityMessage('SFTP', $host, $port, $exception)
            );
        }

        $usesPrivateKey = str_contains($secret, 'BEGIN');

        try {
            $authSecret = $usesPrivateKey ? PublicKeyLoader::load($secret) : $secret;
        } catch (Throwable $exception) {
            $this->logDiagnostic('ssh_private_key_invalid', $host, $port, $username, [
                'uses_private_key' => $usesPrivateKey,
                'original_exception_class' => $exception::class,
                'original_exception_message' => $exception->getMessage(),
                'original_exception_trace' => $exception->getTraceAsString(),
            ]);

            throw RemoteInstallationException::authentication(
                'La clé privée SSH fournie est invalide ou incomplète. '.$this->detailSuffix($exception->getMessage())
            );
        }

        if (! $this->ssh->login($username, $authSecret)) {
            $this->logDiagnostic('ssh_login_failed', $host, $port, $username, [
                'uses_private_key' => $usesPrivateKey,
                'ssh_errors' => $this->transportErrors($this->ssh),
            ]);

            throw RemoteInstallationException::authentication(
                $this->authenticationMessage(
                    protocol: 'SSH',
                    host: $host,
                    port: $port,
                    username: $username,
                    usesPrivateKey: $usesPrivateKey,
                    errors: $this->transportErrors($this->ssh),
                )
            );
        }

        if (! $this->sftp->login($username, $authSecret)) {
            $this->logDiagnostic('sftp_login_failed', $host, $port, $username, [
                'uses_private_key' => $usesPrivateKey,
                'sftp_errors' => $this->transportErrors($this->sftp),
            ]);

            throw RemoteInstallationException::authentication(
                $this->authenticationMessage(
                    protocol: 'SFTP',
                    host: $host,
                    port: $port,
                    username: $username,
                    usesPrivateKey: $usesPrivateKey,
                    errors: $this->transportErrors($this->sftp),
                )
            );
        }
    }

    public function run(RemoteCommand $command, int $timeoutSeconds = 60): RemoteCommandResult
    {
        if (! $this->ssh) {
            throw RemoteInstallationException::connectivity();
        }

        $this->ssh->setTimeout($timeoutSeconds);
        $output = $this->ssh->exec($this->wrapCommand($command->command));
        $exitCode = $this->ssh->getExitStatus();

        return $this->parseCommandResult((string) $output, $exitCode);
    }

    private function wrapCommand(string $command): string
    {
        $token = bin2hex(random_bytes(8));
        $stdoutFile = "/tmp/praeviseo_stdout_{$token}";
        $stderrFile = "/tmp/praeviseo_stderr_{$token}";

        return sprintf(
            "stdout_file=%s; stderr_file=%s; (%s) 1>\"$stdout_file\" 2>\"$stderr_file\"; exit_code=$?; ".
            "printf '__PRAEVISEO_EXIT__=%s\n' \"$exit_code\"; ".
            "printf '__PRAEVISEO_STDOUT_BEGIN__\n'; if [ -f \"$stdout_file\" ]; then cat \"$stdout_file\"; fi; printf '\n__PRAEVISEO_STDOUT_END__\n'; ".
            "printf '__PRAEVISEO_STDERR_BEGIN__\n'; if [ -f \"$stderr_file\" ]; then cat \"$stderr_file\"; fi; printf '\n__PRAEVISEO_STDERR_END__\n'; ".
            "rm -f \"$stdout_file\" \"$stderr_file\"",
            escapeshellarg($stdoutFile),
            escapeshellarg($stderrFile),
            $command,
            '%d',
        );
    }

    private function parseCommandResult(string $output, ?int $fallbackExitCode): RemoteCommandResult
    {
        $exitCode = $fallbackExitCode;
        $stdout = trim($output);
        $stderr = '';

        if (preg_match('/__PRAEVISEO_EXIT__=(\d+)/', $output, $exitMatches) === 1) {
            $exitCode = (int) $exitMatches[1];
        }

        if (preg_match('/__PRAEVISEO_STDOUT_BEGIN__\R(?P<stdout>.*?)\R__PRAEVISEO_STDOUT_END__/s', $output, $stdoutMatches) === 1) {
            $stdout = trim((string) ($stdoutMatches['stdout'] ?? ''));
        }

        if (preg_match('/__PRAEVISEO_STDERR_BEGIN__\R(?P<stderr>.*?)\R__PRAEVISEO_STDERR_END__/s', $output, $stderrMatches) === 1) {
            $stderr = trim((string) ($stderrMatches['stderr'] ?? ''));
        }

        return new RemoteCommandResult(
            successful: $exitCode === 0 || $exitCode === null,
            output: $stdout,
            errorOutput: $stderr,
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

    private function connectivityMessage(string $protocol, string $host, int $port, Throwable $exception): string
    {
        return sprintf(
            'Connexion %s impossible vers %s:%d. Vérifiez l hôte, le port et que le serveur accepte les connexions %s.%s',
            $protocol,
            $host,
            $port,
            $protocol,
            $this->detailSuffix($exception->getMessage()),
        );
    }

    /**
     * @param array<int,string> $errors
     */
    private function authenticationMessage(
        string $protocol,
        string $host,
        int $port,
        string $username,
        bool $usesPrivateKey,
        array $errors = [],
    ): string {
        $credentialLabel = $usesPrivateKey ? 'La clé privée SSH' : 'Le mot de passe SSH';
        $baseMessage = $protocol === 'SSH'
            ? sprintf('%s a été refusé pour %s@%s:%d.', $credentialLabel, $username, $host, $port)
            : sprintf('Le serveur a refusé la connexion SFTP pour %s@%s:%d.', $username, $host, $port);

        $detail = $this->detailSuffix($this->normalizeErrors($errors));

        if ($detail !== '') {
            return $baseMessage.' '.$detail;
        }

        return $protocol === 'SSH'
            ? $baseMessage.' Vérifiez le mot de passe, la clé privée, ou les règles PermitRootLogin / PasswordAuthentication du serveur.'
            : $baseMessage.' SSH répond, mais le sous-système SFTP ou son authentification a été refusé.';
    }

    /**
     * @return array<int,string>
     */
    private function transportErrors(object $transport): array
    {
        if (! method_exists($transport, 'getErrors')) {
            return [];
        }

        $errors = $transport->getErrors();

        if (! is_array($errors)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $error): string => trim((string) $error), $errors)));
    }

    /**
     * @param array<int,string> $errors
     */
    private function normalizeErrors(array $errors): string
    {
        $messages = array_values(array_unique(array_filter(array_map(static fn (string $error): string => trim($error), $errors))));

        return implode(' | ', $messages);
    }

    private function detailSuffix(string $detail): string
    {
        $detail = trim($detail);

        return $detail === '' ? '' : 'Détail: '.$detail;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function logDiagnostic(string $stage, string $host, int $port, string $username, array $context = []): void
    {
        Log::error('Remote installation SSH diagnostic', array_merge([
            'stage' => $stage,
            'target_host' => $host,
            'target_port' => $port,
            'ssh_username' => $username,
            'equivalent_test_command' => sprintf('ssh -p %d %s@%s', $port, $username !== '' ? $username : '<missing-user>', $host !== '' ? $host : '<missing-host>'),
        ], $context));
    }
}
