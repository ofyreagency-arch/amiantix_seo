<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class SeoDoctorOpenAiSslCommand extends Command
{
    protected $signature = 'seo:doctor-openai-ssl {--probe : Teste une vraie connexion HTTPS vers OpenAI}';

    protected $description = 'Diagnostique la configuration SSL/TLS PHP cURL/OpenSSL utilisée pour joindre OpenAI.';

    public function handle(): int
    {
        $rows = $this->diagnosticRows();

        $this->components->info('Diagnostic SSL OpenAI');
        $this->table(['Check', 'Value'], $rows);

        $this->newLine();
        $this->components->twoColumnDetail('Interpretation', $this->interpretation($rows));

        if ($this->option('probe')) {
            $this->newLine();
            $this->probeOpenAi();
        } else {
            $this->components->warn('Ajoutez --probe sur le VPS pour tester une vraie connexion HTTPS vers OpenAI.');
        }

        $this->newLine();
        foreach ($this->recommendedActions($rows) as $action) {
            $this->line(' - '.$action);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{0:string,1:string}>
     */
    private function diagnosticRows(): array
    {
        $opensslLocations = function_exists('openssl_get_cert_locations')
            ? openssl_get_cert_locations()
            : [];
        $curlInfo = function_exists('curl_version')
            ? curl_version()
            : [];

        $defaultCaFile = $opensslLocations['default_cert_file'] ?? null;
        $searchedCaFile = $this->firstExistingPath([
            (string) ini_get('curl.cainfo'),
            (string) ini_get('openssl.cafile'),
            (string) ($opensslLocations['default_cert_file'] ?? ''),
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
        ]);

        return [
            ['PHP version', PHP_VERSION],
            ['PHP SAPI', PHP_SAPI],
            ['OpenSSL extension', extension_loaded('openssl') ? 'loaded' : 'missing'],
            ['OpenSSL version', defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'unknown'],
            ['cURL extension', extension_loaded('curl') ? 'loaded' : 'missing'],
            ['cURL SSL backend', (string) ($curlInfo['ssl_version'] ?? 'unknown')],
            ['OPENAI_API_KEY', config('services.openai.api_key') ? 'set' : 'missing'],
            ['curl.cainfo', $this->displayPath((string) ini_get('curl.cainfo'))],
            ['openssl.cafile', $this->displayPath((string) ini_get('openssl.cafile'))],
            ['openssl.capath', $this->displayPath((string) ini_get('openssl.capath'))],
            ['SSL_CERT_FILE', $this->displayPath((string) env('SSL_CERT_FILE'))],
            ['SSL_CERT_DIR', $this->displayPath((string) env('SSL_CERT_DIR'))],
            ['CURL_CA_BUNDLE', $this->displayPath((string) env('CURL_CA_BUNDLE'))],
            ['OpenSSL default CA file', $this->displayPath((string) $defaultCaFile)],
            ['Detected CA bundle', $this->displayPath((string) $searchedCaFile)],
            ['Detected CA readable', $searchedCaFile && is_readable($searchedCaFile) ? 'yes' : 'no'],
        ];
    }

    /**
     * @param  array<int, array{0:string,1:string}>  $rows
     */
    private function interpretation(array $rows): string
    {
        $map = [];
        foreach ($rows as [$check, $value]) {
            $map[$check] = $value;
        }

        if (($map['cURL extension'] ?? 'missing') !== 'loaded') {
            return 'L’extension cURL manque : aucune connexion OpenAI fiable n’est possible.';
        }

        if (($map['OpenSSL extension'] ?? 'missing') !== 'loaded') {
            return 'L’extension OpenSSL manque : la vérification TLS est incomplète ou cassée.';
        }

        if (($map['Detected CA readable'] ?? 'no') !== 'yes') {
            return 'Aucun bundle CA lisible n’a été trouvé : le VPS a probablement une chaîne de certificats incomplète.';
        }

        return 'La pile PHP/cURL/OpenSSL semble présente. Le test --probe dira si la vraie poignée de main TLS vers OpenAI passe.';
    }

    private function probeOpenAi(): void
    {
        $this->components->info('Probe HTTPS OpenAI');

        try {
            $response = Http::acceptJson()
                ->withToken((string) config('services.openai.api_key', ''))
                ->connectTimeout((int) config('services.openai.connect_timeout', 10))
                ->timeout((int) config('services.openai.request_timeout', 20))
                ->get('https://api.openai.com/v1/models');

            $status = $response->status();

            if (in_array($status, [200, 401, 403], true)) {
                $this->components->info('HTTPS OK : la connexion TLS vers OpenAI aboutit. Statut HTTP '.$status.'.');

                return;
            }

            $this->components->warn('HTTPS atteint OpenAI, mais la réponse HTTP est '.$status.'.');
            $this->line($response->body());
        } catch (ConnectionException $exception) {
            $this->components->error('Probe HTTPS échouée : '.$exception->getMessage());
        } catch (Throwable $exception) {
            $this->components->error('Probe inattendue : '.$exception->getMessage());
        }
    }

    /**
     * @param  array<int, array{0:string,1:string}>  $rows
     * @return array<int, string>
     */
    private function recommendedActions(array $rows): array
    {
        $map = [];
        foreach ($rows as [$check, $value]) {
            $map[$check] = $value;
        }

        $actions = ['Actions recommandées sur le VPS :'];

        if (($map['Detected CA readable'] ?? 'no') !== 'yes') {
            $actions[] = 'Installer ou réinstaller le bundle CA système : `sudo apt-get update && sudo apt-get install --reinstall ca-certificates`.';
            $actions[] = 'Régénérer le magasin système : `sudo update-ca-certificates`.';
        }

        if (($map['curl.cainfo'] ?? 'not set') === 'not set' && ($map['openssl.cafile'] ?? 'not set') === 'not set') {
            $actions[] = 'Si le bundle existe mais n’est pas pris, pointer explicitement `curl.cainfo` et `openssl.cafile` vers `/etc/ssl/certs/ca-certificates.crt` dans le `php.ini` actif.';
        }

        $actions[] = 'Comparer le `php.ini` CLI et FPM si le problème n’apparaît que dans le web ou que dans la console.';
        $actions[] = 'Relancer PHP-FPM / le service web après correction des certificats.';
        $actions[] = 'Relancer ensuite `php artisan seo:doctor-openai-ssl --probe` pour confirmer que TLS passe sans `cURL error 60`.';

        return $actions;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function firstExistingPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            $trimmed = trim($path);

            if ($trimmed !== '' && is_file($trimmed)) {
                return $trimmed;
            }
        }

        return null;
    }

    private function displayPath(string $path): string
    {
        $trimmed = trim($path);

        return $trimmed !== '' ? $trimmed : 'not set';
    }
}
