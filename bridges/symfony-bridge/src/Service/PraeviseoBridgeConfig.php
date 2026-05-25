<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\Service;

final class PraeviseoBridgeConfig
{
    public function __construct(
        private readonly ?string $praeviseoUrl,
        private readonly ?string $appUrl,
        private readonly ?string $bridgeSecret,
        private readonly ?string $bridgeSiteId,
        private readonly ?string $bridgePrefix,
    ) {}

    public function praeviseoUrl(): string
    {
        return rtrim((string) ($this->praeviseoUrl ?? 'https://app.praeviseo.com'), '/');
    }

    public function normalizedAppUrl(): string
    {
        return rtrim((string) ($this->appUrl ?? ''), '/');
    }

    public function bridgeSecret(): string
    {
        return trim((string) ($this->bridgeSecret ?? ''));
    }

    public function bridgeSiteId(): string
    {
        return trim((string) ($this->bridgeSiteId ?? ''));
    }

    public function bridgePrefix(): string
    {
        $prefix = trim((string) ($this->bridgePrefix ?? 'ressources'), '/');

        return $prefix !== '' ? $prefix : 'ressources';
    }
}
