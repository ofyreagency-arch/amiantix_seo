<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PraeviseoConnectCommand extends Command
{
    protected $signature = 'praeviseo:connect
        {code : Code de connexion affiché dans Praeviseo}
        {--praeviseo-url=https://app.praeviseo.com : URL de votre cockpit Praeviseo}
        {--prefix=ressources : Préfixe public des pages publiées}';

    protected $description = 'Connecte un site Laravel à Praeviseo en moins d une minute.';

    public function handle(): int
    {
        $baseUrl = rtrim((string) $this->option('praeviseo-url'), '/');
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl === '') {
            $this->error('APP_URL doit être défini avant de connecter le site.');

            return self::FAILURE;
        }

        $response = Http::asJson()->post($baseUrl.'/api/bridge/connect', [
            'connection_code' => (string) $this->argument('code'),
            'app_url' => $appUrl,
            'bridge' => 'laravel_bridge',
            'publication_prefix' => (string) $this->option('prefix'),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Connexion Praeviseo impossible: '.$response->body());
        }

        $payload = $response->json();

        $this->components->info('Site connecté ✅');
        $this->line('Publication active ✅');
        $this->line('Monitoring actif ✅');
        $this->newLine();
        $this->line('Ajoutez maintenant dans votre .env :');
        $this->line('PRAEVISEO_BRIDGE_SECRET='.$payload['bridge_secret']);
        $this->line('PRAEVISEO_BRIDGE_SITE_ID='.$payload['site_id']);
        $this->line('PRAEVISEO_BRIDGE_PREFIX='.($payload['publication_prefix'] ?: 'ressources'));

        return self::SUCCESS;
    }
}
