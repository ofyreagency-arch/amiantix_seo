<?php

declare(strict_types=1);

namespace App\Command;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'praeviseo:connect', description: 'Connecte un site Symfony à Praeviseo en moins d une minute.')]
final class PraeviseoConnectCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $appUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'Code de connexion affiché dans Praeviseo')
            ->addOption('praeviseo-url', null, InputOption::VALUE_REQUIRED, 'URL de votre cockpit Praeviseo', 'https://app.praeviseo.com')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Préfixe public des pages publiées', 'ressources');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (trim($this->appUrl, '/') === '') {
            throw new RuntimeException('APP_URL doit être défini avant de connecter le site.');
        }

        $response = $this->httpClient->request('POST', rtrim((string) $input->getOption('praeviseo-url'), '/').'/api/bridge/connect', [
            'json' => [
                'connection_code' => (string) $input->getArgument('code'),
                'app_url' => $this->appUrl,
                'bridge' => 'symfony_bridge',
                'publication_prefix' => (string) $input->getOption('prefix'),
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new RuntimeException('Connexion Praeviseo impossible: '.$response->getContent(false));
        }

        $payload = $response->toArray();

        $output->writeln('Site connecté ✅');
        $output->writeln('Publication active ✅');
        $output->writeln('Monitoring actif ✅');
        $output->writeln('');
        $output->writeln('Ajoutez maintenant dans votre .env.local :');
        $output->writeln('PRAEVISEO_BRIDGE_SECRET='.$payload['bridge_secret']);
        $output->writeln('PRAEVISEO_BRIDGE_SITE_ID='.$payload['site_id']);
        $output->writeln('PRAEVISEO_BRIDGE_PREFIX='.($payload['publication_prefix'] ?: 'ressources'));

        return Command::SUCCESS;
    }
}
