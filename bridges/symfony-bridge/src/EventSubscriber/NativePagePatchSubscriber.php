<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\EventSubscriber;

use Praeviseo\SymfonyBridge\Service\NativePageHtmlPatcher;
use Praeviseo\SymfonyBridge\Service\NativePagePatchRepository;
use Praeviseo\SymfonyBridge\Service\PraeviseoBridgeConfig;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class NativePagePatchSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NativePagePatchRepository $patches,
        private readonly NativePageHtmlPatcher $htmlPatcher,
        private readonly PraeviseoBridgeConfig $config,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -512],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getMethod() !== 'GET') {
            return;
        }

        $path = $request->getPathInfo();

        if ($this->shouldSkipPath($path)) {
            return;
        }

        $response = $event->getResponse();

        if ($response->getStatusCode() !== 200) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return;
        }

        $patch = $this->patches->findPublishedPatchForPath($path);

        if ($patch === null) {
            return;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return;
        }

        $response->setContent($this->htmlPatcher->apply($content, $patch));
        $response->headers->set('X-Praeviseo-Native-Patch', 'applied');
    }

    private function shouldSkipPath(string $path): bool
    {
        if (str_starts_with($path, '/api/praeviseo')) {
            return true;
        }

        $prefix = '/'.$this->config->bridgePrefix();

        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }
}
