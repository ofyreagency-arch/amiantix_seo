<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\RemoteInstallation;
use App\RemoteInstallation\RemoteInstallationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunRemoteInstallationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $installationId)
    {
        $this->onQueue('remote-installations');
    }

    public function handle(RemoteInstallationService $service): void
    {
        $installation = RemoteInstallation::query()->find($this->installationId);

        if (! $installation) {
            return;
        }

        $service->run($installation);
    }
}
