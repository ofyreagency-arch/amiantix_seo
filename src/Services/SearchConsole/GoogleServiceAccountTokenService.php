<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\SearchConsole;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Ofyre\SeoEngine\Contracts\SearchConsoleTokenProvider;
use RuntimeException;

class GoogleServiceAccountTokenService implements SearchConsoleTokenProvider
{
    public function accessToken(): ?string
    {
        $path = config('services.google_search_console.credentials', config('seo-engine.search_console.credentials'));

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        return Cache::remember('google_search_console.service_account_token', now()->addMinutes(50), function () use ($path): string {
            $credentials = json_decode((string) file_get_contents($path), true);

            if (! is_array($credentials)) {
                throw new RuntimeException('Invalid Google service account JSON.');
            }

            $clientEmail = (string) ($credentials['client_email'] ?? '');
            $privateKey = (string) ($credentials['private_key'] ?? '');

            if ($clientEmail === '' || $privateKey === '') {
                throw new RuntimeException('Google service account JSON is missing client_email or private_key.');
            }

            $now = time();
            $assertion = $this->base64UrlEncode(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ], JSON_THROW_ON_ERROR)).'.'.$this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));

            openssl_sign($assertion, $signature, $privateKey, OPENSSL_ALGO_SHA256);

            $response = rescue(
                fn () => Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion.'.'.$this->base64UrlEncode($signature),
                ]),
                report: false,
            );

            if (! $response || ! $response->successful()) {
                throw new RuntimeException('Unable to fetch Google access token for Search Console.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
