<?php

declare(strict_types=1);

namespace App\RemoteInstallation;

final readonly class RemoteCommandResult
{
    public function __construct(
        public bool $successful,
        public string $output = '',
        public string $errorOutput = '',
        public ?int $exitCode = null,
    ) {
    }
}
