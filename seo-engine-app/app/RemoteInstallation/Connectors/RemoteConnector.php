<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Connectors;

use App\RemoteInstallation\RemoteCommandResult;

interface RemoteConnector
{
    public function connect(): void;

    public function run(string $command, int $timeoutSeconds = 60): RemoteCommandResult;

    public function fileExists(string $path): bool;

    public function readFile(string $path): ?string;

    public function disconnect(): void;
}
