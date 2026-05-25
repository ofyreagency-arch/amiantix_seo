<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Strategies;

use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\RemoteConnector;
use App\RemoteInstallation\RemoteEnvironment;

interface InstallerStrategy
{
    public function install(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void;

    public function configure(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void;

    public function activate(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void;
}
