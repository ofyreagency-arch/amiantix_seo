<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\Command;

use Praeviseo\SymfonyBridge\Service\PraeviseoBridgeConfig;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'praeviseo:connect', description: 'Connects this Symfony site to PraeviSEO in under one minute.')]
final class PraeviseoConnectCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PraeviseoBridgeConfig $config,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'Code de connexion affiché dans PraeviSEO')
            ->addOption('praeviseo-url', null, InputOption::VALUE_REQUIRED, 'URL de votre cockpit PraeviSEO')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Préfixe public des pages publiées');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $appUrl = $this->config->normalizedAppUrl();

        if ($appUrl === '') {
            throw new RuntimeException('APP_URL doit être défini avant de connecter le site.');
        }

        $praeviseoUrl = rtrim((string) ($input->getOption('praeviseo-url') ?: $this->config->praeviseoUrl()), '/');
        $prefix = trim((string) ($input->getOption('prefix') ?: $this->config->bridgePrefix()), '/');

        $response = $this->httpClient->request('POST', $praeviseoUrl.'/api/bridge/connect', [
            'json' => [
                'connection_code' => (string) $input->getArgument('code'),
                'app_url' => $appUrl,
                'bridge' => 'symfony_bridge',
                'publication_prefix' => $prefix !== '' ? $prefix : null,
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('Connexion PraeviSEO impossible: '.$response->getContent(false));
        }

        $payload = $response->toArray(false);

        $this->writeEnvLocal([
            'PRAEVISEO_URL' => $praeviseoUrl,
            'PRAEVISEO_BRIDGE_SECRET' => (string) ($payload['bridge_secret'] ?? ''),
            'PRAEVISEO_BRIDGE_SITE_ID' => (string) ($payload['site_id'] ?? ''),
            'PRAEVISEO_BRIDGE_PREFIX' => (string) (($payload['publication_prefix'] ?? '') ?: 'ressources'),
        ]);

        $output->writeln('Site connecté ✅');
        $output->writeln('Publication active ✅');
        $output->writeln('Monitoring actif ✅');
        $output->writeln('');
        $output->writeln('Le bridge Symfony est prêt.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string,string> $pairs
     */
    private function writeEnvLocal(array $pairs): void
    {
        $path = $this->projectDir.'/.env.local';
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
