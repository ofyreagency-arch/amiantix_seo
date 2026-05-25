<?php

declare(strict_types=1);

namespace App\RemoteInstallation;

final readonly class RemoteEnvironment
{
    public function __construct(
        public string $framework,
        public string $phpVersion,
        public string $composerVersion,
        public bool $projectWritable,
        public string $projectPath,
    ) {
    }
}
