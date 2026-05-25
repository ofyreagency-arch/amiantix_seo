<?php

declare(strict_types=1);

namespace Praeviseo\LaravelBridge\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class PraeviseoConnectCommand extends Command
{
    protected $signature = 'praeviseo:connect
        {code : Code de connexion affiché dans PraeviSEO}
        {--praeviseo-url= : URL de votre cockpit PraeviSEO}
        {--prefix= : Préfixe public des pages publiées}';

    protected $description = 'Connecte ce site Laravel à PraeviSEO en moins d une minute.';

    public function handle(): int
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl === '') {
            throw new RuntimeException('APP_URL doit être défini avant de connecter le site.');
        }

        $praeviseoUrl = rtrim((string) ($this->option('praeviseo-url') ?: config('praeviseo-bridge.praeviseo_url', 'https://app.praeviseo.com')), '/');
        $prefix = trim((string) ($this->option('prefix') ?: config('praeviseo-bridge.prefix', 'ressources')), '/');

        $response = Http::asJson()->post($praeviseoUrl.'/api/bridge/connect', [
            'connection_code' => (string) $this->argument('code'),
            'app_url' => $appUrl,
            'bridge' => 'laravel_bridge',
            'publication_prefix' => $prefix !== '' ? $prefix : null,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Connexion PraeviSEO impossible: '.$response->body());
        }

        $payload = $response->json();

        $this->writeEnv([
            'PRAEVISEO_URL' => $praeviseoUrl,
            'PRAEVISEO_BRIDGE_SECRET' => (string) ($payload['bridge_secret'] ?? ''),
            'PRAEVISEO_BRIDGE_SITE_ID' => (string) ($payload['site_id'] ?? ''),
            'PRAEVISEO_BRIDGE_PREFIX' => (string) (($payload['publication_prefix'] ?? '') ?: 'ressources'),
        ]);

        $this->components->info('Site connecté ✅');
        $this->line('Publication active ✅');
        $this->line('Monitoring actif ✅');
        $this->newLine();
        $this->line('Lance maintenant `php artisan migrate` si la table bridge n existe pas encore.');

        return self::SUCCESS;
    }

    /**
     * @param array<string,string> $pairs
     */
    private function writeEnv(array $pairs): void
    {
        $path = base_path('.env');
        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($pairs as $key => $value) {
            $line = $key.'='.$this->escapeEnvValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';

            if (preg_match($pattern, $contents)) {
                $contents = (string) preg_replace($pattern, $line, $contents);
            } else {
                $contents .= ($contents !== '' && ! str_ends_with($contents, PHP_EOL) ? PHP_EOL : '').$line.PHP_EOL;
            }
        }

        file_put_contents($path, $contents);
    }

    private function escapeEnvValue(string $value): string
    {
        if ($value === '' || preg_match('/\s/', $value)) {
            return '"'.str_replace('"', '\"', $value).'"';
        }

        return $value;
    }
}
