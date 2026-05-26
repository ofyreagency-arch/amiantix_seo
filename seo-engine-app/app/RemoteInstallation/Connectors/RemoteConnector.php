<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Connectors;

use App\RemoteInstallation\RemoteCommand;
use App\RemoteInstallation\RemoteCommandResult;

interface RemoteConnector
{
    public function connect(): void;

    /**
     * SECURITY: connectors must only execute a whitelisted RemoteCommand.
     * They must never accept or build an arbitrary shell command from user input,
     * otherwise the remote installation layer would become an RCE vector.
     */
    public function run(RemoteCommand $command, int $timeoutSeconds = 60): RemoteCommandResult;

    public function fileExists(string $path): bool;

    public function readFile(string $path): ?string;

    public function disconnect(): void;
}
