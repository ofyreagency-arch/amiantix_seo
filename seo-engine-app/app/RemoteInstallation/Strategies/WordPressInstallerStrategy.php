<?php

declare(strict_types=1);

namespace App\RemoteInstallation\Strategies;

use App\Models\RemoteInstallation;
use App\Models\SeoSite;
use App\RemoteInstallation\Connectors\RemoteConnector;
use App\RemoteInstallation\Exceptions\RemoteInstallationException;
use App\RemoteInstallation\RemoteEnvironment;

class WordPressInstallerStrategy implements InstallerStrategy
{
    /**
     * SECURITY: WordPress stays behind the same remote-install boundary.
     * This strategy must keep using predefined backend commands only once
     * WordPress automation is implemented for real.
     */
    public function install(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        throw RemoteInstallationException::unsupported(
            'Le support WordPress distant sera activé dans une prochaine version sécurisée de PraeviSEO.'
        );
    }

    public function configure(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        throw RemoteInstallationException::unsupported(
            'Le support WordPress distant sera activé dans une prochaine version sécurisée de PraeviSEO.'
        );
    }

    public function activate(RemoteConnector $connector, RemoteInstallation $installation, SeoSite $site, RemoteEnvironment $environment): void
    {
        throw RemoteInstallationException::unsupported(
            'Le support WordPress distant sera activé dans une prochaine version sécurisée de PraeviSEO.'
        );
    }
}
