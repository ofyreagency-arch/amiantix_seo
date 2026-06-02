<?php

declare(strict_types=1);

namespace App\RemoteInstallation;

final class InstallationReadinessReport
{
    /**
     * @param array<int,array{key:string,label:string,detail:string}> $validated
     * @param array<int,array{key:string,label:string,detail:string}> $warnings
     * @param array<int,array{key:string,label:string,detail:string,autofixable:bool}> $blockers
     * @param array<int,array{key:string,label:string,detail:string}> $autofixable
     * @param array<int,array{key:string,label:string,detail:string}> $manualActions
     * @param array<string,string|null> $detected
     */
    public function __construct(
        public readonly int $score,
        public readonly array $validated,
        public readonly array $warnings,
        public readonly array $blockers,
        public readonly array $autofixable,
        public readonly array $manualActions,
        public readonly array $detected = [],
    ) {
    }

    public function isReady(): bool
    {
        return $this->blockers === [];
    }

    /**
     * @return array{
     *   score:int,
     *   status:string,
     *   summary:string,
     *   validated:array<int,array{key:string,label:string,detail:string}>,
     *   warnings:array<int,array{key:string,label:string,detail:string}>,
     *   blockers:array<int,array{key:string,label:string,detail:string,autofixable:bool}>,
     *   autofixable:array<int,array{key:string,label:string,detail:string}>,
     *   manual_actions:array<int,array{key:string,label:string,detail:string}>,
     *   detected:array<string,string|null>
     * }
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'status' => $this->isReady() ? 'ready' : 'blocked',
            'summary' => $this->summary(),
            'validated' => $this->validated,
            'warnings' => $this->warnings,
            'blockers' => $this->blockers,
            'autofixable' => $this->autofixable,
            'manual_actions' => $this->manualActions,
            'detected' => $this->detected,
        ];
    }

    private function summary(): string
    {
        if ($this->isReady()) {
            if ($this->warnings !== []) {
                return 'L installation peut continuer. Quelques points restent simplement à surveiller.';
            }

            return 'Tout le nécessaire est validé. PraeviSEO peut lancer l installation.';
        }

        $autofixableCount = count(array_filter($this->blockers, static fn (array $item): bool => $item['autofixable'] === true));

        if ($autofixableCount > 0) {
            return 'L installation est encore bloquée, mais PraeviSEO pourra corriger automatiquement une partie des points restants.';
        }

        return 'L installation ne peut pas continuer tant que les blocages ci-dessous ne sont pas levés.';
    }
}
