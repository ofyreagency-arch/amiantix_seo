<?php

declare(strict_types=1);

namespace App\SeoPresets\Amiantix;

use App\Services\Preset\BlockSelectionStrategy;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;
use Ofyre\SeoEngine\Services\Composition\NarrativeAssembler;

final class AmiantixContentProfile implements NicheContentProvider
{
    public function __construct(
        private readonly BlockSelectionStrategy $blockSelection,
        private readonly NarrativeAssembler $narrative,
    ) {}

    public function fallbackPayload(string $keyword, string $cluster, array $blueprint, array $context = []): array
    {
        $title = $this->buildTitle($keyword, $cluster, $blueprint);
        $description = $this->buildMetaDescription($keyword, $cluster, $blueprint);
        $h1 = $this->buildH1($keyword, $cluster, $blueprint);
        $links = $context['internal_links'] ?? [];
        $catalog = $this->sectionCatalog($blueprint, is_array($links) ? $links : []);
        $content = $this->narrative->assembleHtml(
            $this->blockSelection->primaryHeadings($blueprint, $catalog),
            $catalog,
            $blueprint
        );

        return [
            'title' => $title,
            'meta_description' => $description,
            'h1' => $h1,
            'content' => $this->ensureContentDepth($content, $blueprint, $context + [
                'keyword' => $keyword,
                'cluster' => $cluster,
            ]),
            'faq' => array_map(static fn (array $item): array => [
                'question' => (string) $item['question'],
                'answer' => (string) $item['answer'],
            ], $blueprint['faq'] ?? []),
        ];
    }

    public function extraSection(string $keyword, array $blueprint, array $context = []): string
    {
        $content = (string) (($context['page']->content ?? null) ?: $context['content'] ?? '');

        return implode('', $this->missingStructuralBlocks($content, $blueprint, $context));
    }

    public function ensureContentDepth(string $content, array $blueprint, array $context = []): string
    {
        if ($context['preserve_ai_narrative'] ?? false) {
            return $content;
        }

        $links = $context['internal_links'] ?? (($context['page']->internal_links_json ?? null) ?: []);

        foreach ($this->missingStructuralBlocks($content, $blueprint, $context) as $block) {
            if (! $this->contentContainsMarker($content, $this->sectionMarker('', $block))) {
                $content .= $block;
            }
        }

        $catalog = $this->sectionCatalog($blueprint, is_array($links) ? $links : []);
        $enrichmentHeadings = $this->blockSelection->enrichmentHeadings($blueprint, $catalog, $content);
        $selectedEnrichmentHeadings = [];
        $previewContent = $content;

        foreach ($enrichmentHeadings as $heading) {
            if ($this->contentWordCount($previewContent) >= 1325) {
                break;
            }

            $block = (string) ($catalog[$heading] ?? '');

            if ($block === '') {
                continue;
            }

            if ($this->contentContainsMarker($content, $this->sectionMarker($heading, $block))) {
                continue;
            }

            $selectedEnrichmentHeadings[] = $heading;
            $previewContent .= $block;
        }

        if ($selectedEnrichmentHeadings !== []) {
            $enrichmentHtml = $this->narrative->assembleHtml(
                $selectedEnrichmentHeadings,
                $catalog,
                $blueprint,
                $content
            );

            if (trim($enrichmentHtml) !== '') {
                $content .= $enrichmentHtml;
            }
        }

        if ($context['preserve_ai_narrative'] ?? false) {
            return $content;
        }

        $cycle = 1;
        while ($this->contentWordCount($content) < 1450 && $cycle <= 2) {
            $content .= $this->deepeningBlock($blueprint, $cycle);
            $cycle++;
        }

        return $content;
    }

    /**
     * @param  array<string,mixed>  $blueprint
     * @param  array<string,mixed>  $context
     * @return array<int,string>
     */
    private function missingStructuralBlocks(string $content, array $blueprint, array $context = []): array
    {
        $links = $context['internal_links'] ?? (($context['page']->internal_links_json ?? null) ?: []);
        $catalog = $this->sectionCatalog($blueprint, is_array($links) ? $links : []);
        $requiredHeadings = $this->blockSelection->requiredHeadings($blueprint, $catalog);

        return collect($requiredHeadings)
            ->filter(function (string $heading) use ($content, $catalog): bool {
                $block = (string) ($catalog[$heading] ?? '');
                $marker = $this->sectionMarker($heading, $block);

                return $block !== '' && ! $this->contentContainsMarker($content, $marker);
            })
            ->map(fn (string $heading): string => (string) ($catalog[$heading] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{label:string,url:string,reason:string}>  $links
     * @return array<string,string>
     */
    private function sectionCatalog(array $blueprint, array $links): array
    {
        return [
            'Contexte et obligations' => $this->openingSituationBlock($blueprint),
            'Repérage, SS3, SS4 et responsabilites de coordination' => $this->regulatoryScopeBlock($blueprint),
            'Tableau de priorisation des risques' => $this->riskTableBlock($blueprint),
            'Situations a risque sur le terrain' => $this->terrainRisksBlock($blueprint),
            'Processus d intervention et coordination' => $this->interventionFlowBlock($blueprint),
            'Checklist operationnelle avant intervention' => $this->operationalChecklistBlock($blueprint),
            'Documents et preuves a conserver' => $this->documentsBlock($blueprint),
            'Points de vigilance pour le donneur d ordre' => $this->donneurOrdreBlock($blueprint),
            'Cas pratiques terrain a cadrer' => $this->practicalCasesBlock($blueprint),
            'Erreurs frequentes et blocages evitables' => $this->mistakesBlock($blueprint),
            'Blocages, sanctions et signaux d alerte a ne pas banaliser' => $this->sanctionsBlock($blueprint),
            'Copropriete, ERP et site occupe : ce qui change vraiment' => $this->erpOccupationBlock($blueprint),
            'Ce qui rend la demarche defendable' => $this->evidenceBlock($blueprint),
            'Couts, delais et arbitrages chantier' => $this->costsDelaysBlock($blueprint),
            'Matrice de controle documentaire et terrain' => $this->controlMatrixBlock($blueprint),
            'Questions terrain qui reviennent souvent' => $this->faqPreviewBlock($blueprint),
            'Ressources et pages utiles a croiser' => $this->internalLinksBlock($links, $blueprint),
            'Passer du constat a une intervention maitrisée' => $this->finalActionBlock($blueprint),
            'Site occupe, acces sensibles et zones grises' => $this->siteOccupationBlock($blueprint),
            'Routine documentaire et trace utile' => $this->documentRoutineBlock($blueprint),
        ];
    }

    private function sectionMarker(string $heading, string $block): string
    {
        if (preg_match('/<h2>(.*?)<\/h2>/i', $block, $matches) === 1) {
            return html_entity_decode(trim(strip_tags((string) ($matches[1] ?? ''))));
        }

        return $heading;
    }

    private function contentContainsMarker(string $content, string $marker): bool
    {
        $normalizedMarker = $this->normalizeStructuralText($marker);

        if ($normalizedMarker === '') {
            return false;
        }

        return str_contains($this->normalizeStructuralText($content), $normalizedMarker);
    }

    private function normalizeStructuralText(string $value): string
    {
        return Str::of(strip_tags($value))
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/u', ' ')
            ->replace(['&nbsp;', "\r", "\n", "\t"], ' ')
            ->squish()
            ->value();
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function openingSituationBlock(array $blueprint): string
    {
        $cases = $blueprint['cases'] ?? [];
        $constraints = implode(', ', array_slice($blueprint['daily_constraints'] ?? [], 0, 5));
        $firstCase = (string) ($cases[0] ?? 'Le risque amiante se joue autant dans le cadrage que dans le geste technique.');

        return '<section><h2>Contexte et obligations</h2><p>'.$firstCase.'</p><p>Sur un sujet amiante, la qualite de la decision depend rarement d une seule piece. Il faut relier le repérage, le contexte de site, les hypotheses de travaux, la circulation des personnes, la sequence chantier et les preuves documentaires. Les contraintes les plus sensibles reviennent souvent autour de '.$constraints.'.</p><p>Le lecteur attend donc plus qu un rappel reglementaire. Il veut comprendre qui doit verifier quoi, a quel moment, avec quelle piece et comment eviter qu un flou documentaire se transforme en retard, en surcout ou en exposition mal maitrisee.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function regulatoryScopeBlock(array $blueprint): string
    {
        return '<section><h2>Repérage, SS3, SS4 et responsabilites de coordination</h2><p>Un contenu expert doit clarifier ce qui releve du repérage avant travaux, de la strategie d intervention et de la coordination entre acteurs. Le lecteur ne cherche pas seulement une definition: il veut savoir a quel moment la logique SS3 ou SS4 devient pertinente, comment elle s articule avec le perimetre de travaux et qui doit refermer les angles morts avant diffusion d un ordre d intervention.</p><h3>Repérage avant travaux</h3><p>Le repérage doit coller a l hypothese de travaux reelle, pas a une description trop large ou trop abstraite. Sans ce cadrage, le chantier part sur une base fragile.</p><h3>SS3 et SS4</h3><p>La page doit aider a distinguer les logiques de retrait ou encapsulage d un cote, et les interventions susceptibles d exposer a l amiante de l autre. Cette distinction change les methodes, les validations et la vigilance documentaire.</p><h3>MOA, MOE et coordination SPS</h3><p>Dans les contextes complexes, la maitrise d ouvrage, la maitrise d oeuvre et la coordination SPS doivent lire les memes hypothese de travaux, les memes limites de zone et les memes preuves. C est souvent la que se joue la solidite pratique du dispositif.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function riskTableBlock(array $blueprint): string
    {
        $rows = collect($blueprint['risk_rows'] ?? [])
            ->map(function (array $row, int $index): string {
                $gravity = ['Forte', 'Forte', 'Moyenne a forte', 'Forte', 'Moyenne a forte'][$index] ?? 'Moyenne';
                $frequency = ['Avant travaux', 'Selon intervention', 'A chaque diffusion', 'A chaque coordination', 'Selon phasage'][$index] ?? 'Reguliere';
                $owner = ['Donneur d ordre', 'Entreprise et coordination', 'Maitrise documentaire', 'MOA / MOE / exploitation', 'Pilotage chantier'][$index] ?? 'Donneur d ordre';
                $priority = ['Immediate', 'Haute', 'Haute', 'Haute', 'Planifiee'][$index] ?? 'Haute';
                $consequence = $this->riskConsequence((string) ($row[0] ?? 'Risque'));

                return '<tr><td>'.$row[0].'</td><td>'.$row[1].'</td><td>'.$consequence.'</td><td>'.$gravity.'</td><td>'.$frequency.'</td><td>'.$row[2].'</td><td>'.$owner.'</td><td>'.$priority.'</td></tr>';
            })
            ->implode('');

        return '<section><h2>Tableau de priorisation des risques</h2><p>Ce tableau sert a transformer le sujet amiante en decisions pilotables. Il relie le risque, la situation reelle, la consequence plausible, les mesures attendues, le responsable et la priorite documentaire ou terrain.</p><table><thead><tr><th>Risque</th><th>Situation reelle</th><th>Consequence plausible</th><th>Gravite</th><th>Frequence</th><th>Mesures</th><th>Responsable</th><th>Priorite</th></tr></thead><tbody>'.$rows.'</tbody></table></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function terrainRisksBlock(array $blueprint): string
    {
        $items = collect($blueprint['risk_rows'] ?? [])
            ->take(4)
            ->map(function (array $row): string {
                return '<h3>'.$row[0].'</h3><p>'.$row[1].'. La vraie valeur du contenu est de montrer ensuite la mesure utile: '.$row[2].'.</p>';
            })
            ->implode('');

        return '<section><h2>Situations a risque sur le terrain</h2><p>Le risque amiante n apparait pas seulement au moment du retrait. Il monte aussi quand une hypothese de travaux est mal posee, quand une zone sensible est mal lue ou quand la circulation sur site occupe est traitee trop tard.</p>'.$items.'</section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function interventionFlowBlock(array $blueprint): string
    {
        $steps = collect($blueprint['work_units'] ?? [])
            ->take(5)
            ->map(function (string $unit, int $index) use ($blueprint): string {
                $control = $blueprint['inspection_focus'][$index % max(1, count($blueprint['inspection_focus'] ?? ['controle']))] ?? 'controle terrain';

                return '<li><strong>'.Str::title($unit).'</strong> : '.$control.'.</li>';
            })
            ->implode('');

        return '<section><h2>Processus d intervention et coordination</h2><p>Une page amiante utile doit suivre le deroule reel: cadrage documentaire, visite, hypothese de travaux, coordination des acteurs, intervention, verification et cloture des preuves. Si un seul de ces maillons reste flou, la credibilite du chantier baisse tres vite.</p><ul>'.$steps.'</ul><p>C est souvent dans ces passages de relais que se jouent les erreurs de perimetre, les blocages chantier et les surcouts evitables.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function operationalChecklistBlock(array $blueprint): string
    {
        $items = [
            'Verifier que l hypothese de travaux correspond bien a la realite technique du site.',
            'Croiser le repérage, le DTA ou les pieces existantes avec les zones reelles a ouvrir, decouper ou maintenir.',
            'Identifier qui valide les limites de zone, les diffusions documentaires et les conditions d acces.',
            'Prevoir les impacts d un site occupe, d un ERP ou d une copropriete avant le lancement des operations.',
            'Conserver une preuve simple des arbitrages pris quand le contexte de chantier evolue.',
        ];

        $list = collect($items)
            ->map(static fn (string $item): string => '<li>'.$item.'</li>')
            ->implode('');

        return '<section><h2>Checklist operationnelle avant intervention</h2><p>Cette checklist donne de l air au contenu et aide a convertir une lecture expert en verification immediate. Elle peut etre relue par un donneur d ordre, un syndic, un responsable technique ou un conducteur d operations.</p><ul>'.$list.'</ul></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function documentsBlock(array $blueprint): string
    {
        $items = collect($blueprint['evidence_examples'] ?? [])
            ->map(static fn (string $item): string => '<li>'.$item.'</li>')
            ->implode('');

        return '<section><h2>Documents et preuves a conserver</h2><p>Le sujet amiante est aussi un sujet de preuve. Il faut pouvoir montrer ce qui a ete repere, ce qui a ete transmis, ce qui a ete valide et ce qui a change entre l hypothese de depart et le terrain reel.</p><ul>'.$items.'</ul><p>Ces traces ne remplacent pas la methode. Elles la rendent defensable et limitent les zones d interpretation entre les acteurs.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function donneurOrdreBlock(array $blueprint): string
    {
        $items = collect($blueprint['obligations'] ?? [])
            ->map(static fn (string $item): string => '<li>'.$item.'</li>')
            ->implode('');

        return '<section><h2>Points de vigilance pour le donneur d ordre</h2><p>Le donneur d ordre a besoin d un contenu qui l aide a verifier le perimetre, les pieces, les hypotheses et les validations avant de lancer une action qui engage d autres intervenants.</p><ul>'.$items.'</ul><p>Dans un contenu trop generique, cette responsabilite disparait derriere la seule idee de diagnostic. Ici, elle doit redevenir lisible.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function practicalCasesBlock(array $blueprint): string
    {
        $family = (string) ($blueprint['family'] ?? 'default');
        $cases = collect($blueprint['cases'] ?? [])
            ->values()
            ->map(function (string $item, int $index) use ($family): string {
                $labels = match ($family) {
                    'appel_offre' => [
                        'Scenario DCE incomplet ou mal borne',
                        'Scenario clarifications tardives pendant consultation',
                        'Scenario attribution fragile et reserves mal maitrisees',
                    ],
                    'reperage' => [
                        'Scenario perimetre de repérage mal cadre',
                        'Scenario hypotheses de travaux qui bougent',
                        'Scenario contradiction entre pieces techniques',
                    ],
                    default => [
                        'Scenario copropriete ou site occupe',
                        'Scenario maintenance ou intervention contrainte',
                        'Scenario decouverte tardive et blocage chantier',
                    ],
                };

                return '<h3>'.($labels[$index] ?? 'Scenario terrain').'</h3><p>'.$item.'</p>';
            })
            ->implode('');

        return '<section><h2>Cas pratiques terrain a cadrer</h2><p>Ces cas aident le lecteur a reconnaitre ce qui ressemble a son propre contexte. Le but n est pas de dramatiser, mais de montrer ou se logent les vrais arbitrages et les vraies pertes de maitrise.</p>'.$cases.'</section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function mistakesBlock(array $blueprint): string
    {
        $items = collect($blueprint['mistakes'] ?? [])
            ->map(static fn (string $item): string => '<li>'.$item.'</li>')
            ->implode('');

        return '<section><h2>Erreurs frequentes et blocages evitables</h2><p>Les blocages amiante viennent souvent d un melange de theorie juste et d execution mal preparee. Les erreurs ci-dessous reviennent souvent quand la page ou le chantier restent trop abstraits.</p><ul>'.$items.'</ul><p>Les nommer clairement aide a differencier un contenu expert d un contenu seulement informatif.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function sanctionsBlock(array $blueprint): string
    {
        return '<section><h2>Blocages, sanctions et signaux d alerte a ne pas banaliser</h2><p>Les consequences d une mauvaise lecture du risque amiante ne se limitent pas a un simple retard. On peut aller vers une suspension d intervention, une reprise de repérage, une recoordination d urgence, un blocage d entreprise ou une tension forte avec le maitre d ouvrage et les occupants.</p><h3>Quand le chantier se fige</h3><p>Le blocage arrive souvent quand une zone n est pas clairement couverte, quand une hypothese de travaux change sans mise a jour documentaire ou quand les validations ne sont pas partagees au bon moment.</p><h3>Pourquoi ce point doit apparaitre dans le contenu</h3><p>Un article expert doit montrer les consequences pratiques d une preparation faible: surcout, perte de planning, exposition mal maitrisee et responsabilites mal distribuees. C est cette couche qui rend la lecture vraiment decisionnelle.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function erpOccupationBlock(array $blueprint): string
    {
        $family = (string) ($blueprint['family'] ?? 'default');

        if ($family === 'appel_offre') {
            return '<section><h2>Consultation, phasage et contraintes d acces : ce qui change vraiment</h2><p>Un appel d offre amiante solide ne peut pas rester au niveau d une simple description de principe. Les contraintes d acces, d occupation, de sequence chantier, de variantes et de diffusion des pieces changent directement la façon dont les entreprises lisent le risque, chiffrent la methode et posent leurs reserves.</p><h3>Ce qu un bon contenu doit expliciter</h3><ul><li>Quelles hypotheses de travaux sont fermes et lesquelles restent a clarifier.</li><li>Comment les zones sensibles, acces et contraintes d occupation influencent la consultation.</li><li>Quels jalons documentaires doivent etre stabilises avant attribution.</li><li>Comment eviter que le flou du DCE devienne un probleme d execution plus tard.</li></ul></section>';
        }

        return '<section><h2>Copropriete, ERP et site occupe : ce qui change vraiment</h2><p>Les environnements occupes demandent des arbitrages plus fins qu un simple rappel technique. En copropriete, il faut souvent composer avec les parties communes, les occupations successives, les zones mal documentees et la communication avec plusieurs interlocuteurs. En ERP, la circulation du public, la continuite de service et les restrictions d acces changent directement la facon de preparer l intervention.</p><h3>Ce qu un bon contenu doit expliciter</h3><ul><li>Qui est informe et a quel moment.</li><li>Quelles zones doivent etre securisees ou requalifiees avant intervention.</li><li>Comment le phasage limite la coactivite, la circulation et les reprises de chantier.</li><li>Quelles preuves documentaires doivent suivre le chantier jusqu a la cloture.</li></ul></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function evidenceBlock(array $blueprint): string
    {
        $legalTerms = implode(', ', array_slice($blueprint['risk_terms'] ?? [], 0, 6));

        return '<section><h2>Ce qui rend la demarche defendable</h2><p>Une page amiante devient solide quand elle relie les obligations a des situations verifiables: '.$legalTerms.'. Le lecteur doit pouvoir comprendre ce qui releve du repérage, du contexte de site, de la coordination et de la preuve finale a produire.</p><p>Cette precision fait la difference entre un contenu qui rassure vaguement et un contenu qui aide vraiment a piloter une intervention sans angle mort inutile.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function faqPreviewBlock(array $blueprint): string
    {
        $items = collect($blueprint['faq'] ?? [])
            ->take(3)
            ->map(static fn (array $item): string => '<h3>'.($item['question'] ?? '').'</h3><p>'.($item['answer'] ?? '').'</p>')
            ->implode('');

        return '<section><h2>Questions terrain qui reviennent souvent</h2><p>La FAQ traite les hesitations concretes du donneur d ordre, du syndic ou du responsable technique quand il doit arbitrer vite sans perdre la maitrise du risque.</p>'.$items.'</section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function costsDelaysBlock(array $blueprint): string
    {
        $constraints = implode(', ', array_slice($blueprint['daily_constraints'] ?? [], 0, 4));

        return '<section><h2>Couts, delais et arbitrages chantier</h2><p>Le risque amiante ne se pilote pas seulement en cout direct. Les retards, reprises de documents, immobilisations de zones, modifications de phasage et recoordination des intervenants pesent souvent autant que la technique elle-meme.</p><p>Dans la pratique, ces arbitrages se tendent surtout autour de '.$constraints.'. C est pourquoi une page forte doit montrer comment anticiper les pertes de temps avant qu elles ne se transforment en blocage.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function controlMatrixBlock(array $blueprint): string
    {
        $rows = collect($blueprint['work_units'] ?? [])
            ->take(5)
            ->map(function (string $unit, int $index) use ($blueprint): string {
                $control = $blueprint['inspection_focus'][$index % max(1, count($blueprint['inspection_focus'] ?? ['controle']))] ?? 'controle terrain';
                $proof = $blueprint['evidence_examples'][$index % max(1, count($blueprint['evidence_examples'] ?? ['preuve']))] ?? 'preuve documentaire';
                $frequency = ['Avant diffusion', 'Avant intervention', 'Pendant coordination', 'Apres arbitrage', 'En cloture'][$index % 5];
                $owner = ['Donneur d ordre', 'Referent technique', 'MOE', 'Entreprise', 'Pilotage documentaire'][$index % 5];

                return '<tr><td>'.Str::title($unit).'</td><td>'.$control.'</td><td>'.$frequency.'</td><td>'.$owner.'</td><td>'.$proof.'</td></tr>';
            })
            ->implode('');

        return '<section><h2>Matrice de controle documentaire et terrain</h2><p>Cette matrice sert a lier preparation, execution et preuve. Elle est utile quand plusieurs acteurs interviennent et que chacun a besoin de savoir quoi verifier, quand, et avec quelle trace.</p><table><thead><tr><th>Bloc</th><th>Controle attendu</th><th>Moment</th><th>Responsable</th><th>Preuve</th></tr></thead><tbody>'.$rows.'</tbody></table></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function siteOccupationBlock(array $blueprint): string
    {
        return '<section><h2>Site occupe, acces sensibles et zones grises</h2><p>Les interventions en site occupe, en copropriete ou dans des environnements techniques contraints demandent une lecture plus fine que le simple couple diagnostic / travaux. Il faut penser circulation des tiers, acces, bruit, confinement, jalons de validation et reprise possible d exploitation.</p><p>Quand cette couche disparait du contenu, la page reste topicalement juste mais perd sa vraie valeur terrain.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function documentRoutineBlock(array $blueprint): string
    {
        $mistakes = collect($blueprint['mistakes'] ?? [])
            ->take(3)
            ->map(static fn (string $item): string => '<li>'.$item.'</li>')
            ->implode('');

        return '<section><h2>Routine documentaire et mises a jour utiles</h2><p>Un dossier amiante se fragilise vite si personne ne surveille les versions, les diffusions, les hypotheses de travaux et les validations de coordination. Cette routine documentaire doit etre simple mais visible.</p><ul>'.$mistakes.'</ul><p>C est souvent ce suivi qui evite qu une piece juste au depart devienne trompeuse une fois le chantier rephase ou la zone requalifiee.</p></section>';
    }

    /**
     * @param  array<int,array{label:string,url:string,reason:string}>  $links
     * @param  array<string,mixed>  $blueprint
     */
    private function internalLinksBlock(array $links, array $blueprint): string
    {
        if ($links === []) {
            return '';
        }

        $items = collect($links)
            ->take(6)
            ->map(static fn (array $link): string => '<li><a href="'.$link['url'].'">'.$link['label'].'</a></li>')
            ->implode('');

        return '<section><h2>Ressources et pages utiles a croiser</h2><p>Certains sujets gagnent a etre relus avec des pages sur le repérage, la coordination, le DTA, les obligations ou les contextes chantier voisins. Le but est de renforcer la lecture utile, pas de surcharger le parcours.</p><ul>'.$items.'</ul></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function finalActionBlock(array $blueprint): string
    {
        return '<section><h2>Passer du constat a une intervention maitrisée</h2><p>Avant de lancer un chantier, une maintenance ou une coordination plus lourde, il faut reprendre les hypotheses de travaux, verifier les pieces disponibles, aligner les interlocuteurs et fermer les zones grises documentaires. C est cette discipline qui rend la decision plus defendable et l execution plus maitrisée.</p><p>Une bonne page Amiantix aide justement a faire ce tri entre information utile, preuve disponible et action a cadrer tout de suite.</p></section>';
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function deepeningBlock(array $blueprint, int $cycle): string
    {
        $risks = $blueprint['risk_rows'] ?? [];
        $risk = $risks[($cycle - 1) % max(1, count($risks))] ?? ['Risque amiante', 'Situation terrain', 'Mesure utile'];
        $control = $blueprint['inspection_focus'][($cycle - 1) % max(1, count($blueprint['inspection_focus'] ?? ['controle terrain']))] ?? 'controle terrain';
        $proof = $blueprint['evidence_examples'][($cycle - 1) % max(1, count($blueprint['evidence_examples'] ?? ['preuve']))] ?? 'preuve documentaire';
        $checkpoints = [
            'Verifier que la zone et l hypothese de travaux sont toujours alignees.',
            'Confirmer la diffusion des pieces utiles avant intervention.',
            'Tracer la validation ou la reserve qui modifie le deroule du chantier.',
        ];
        $list = collect($checkpoints)
            ->map(static fn (string $item): string => '<li>'.$item.'</li>')
            ->implode('');

        $cases = $blueprint['cases'] ?? [];
        $caseTitle = (string) ($cases[($cycle - 1) % max(1, count($cases))] ?? $risk[1]);
        $heading = Str::limit($caseTitle, 72, '…');
        if (strlen($heading) < 12) {
            $heading = 'Situation chantier : '.$risk[0];
        }

        return '<section><h2>'.$heading.'</h2><p>'.$risk[1].'. Ce point devient critique quand la preparation documentaire, la coordination des acteurs ou la lecture du site ne suivent pas le rythme reel des travaux ou de la maintenance.</p><p>La mesure attendue doit rester visible: '.$risk[2].'. Le point de controle utile est souvent '.$control.', avec une preuve concrete comme '.$proof.'. C est ce niveau de detail qui rend un contenu amiante exploitable par un responsable technique, un syndic ou un donneur d ordre.</p><ul>'.$list.'</ul></section>';
    }

    private function contentWordCount(string $content): int
    {
        return str_word_count(Str::ascii(strip_tags($content)));
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function buildTitle(string $keyword, string $cluster, array $blueprint): string
    {
        $normalizedKeyword = Str::lower(Str::ascii($keyword));
        $headlineKeyword = Str::headline($keyword);

        return match (true) {
            str_contains($normalizedKeyword, 'diagnostic') => $headlineKeyword.' : obligations, coordination chantier et points de vigilance terrain',
            str_contains($normalizedKeyword, 'reperage') => $headlineKeyword.' : comment cadrer le perimetre et eviter les angles morts',
            str_contains($normalizedKeyword, 'dta') => $headlineKeyword.' : role, transmission et points de vigilance pour un pilotage utile',
            default => $headlineKeyword.' : obligations, preuves et coordination du risque amiante',
        };
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function buildMetaDescription(string $keyword, string $cluster, array $blueprint): string
    {
        $normalizedKeyword = Str::lower(Str::ascii($keyword));
        $topic = (string) ($blueprint['topic'] ?? $keyword);

        return match (true) {
            str_contains($normalizedKeyword, 'diagnostic') => 'Guide Amiantix sur '.$topic.' avec obligations, documents, coordination chantier, blocages evitables et preuves a conserver.',
            str_contains($normalizedKeyword, 'reperage') => 'Comprendre '.$topic.' : perimetre, hypothèses, coordination, site occupe et pieces a verifier.',
            default => 'Contenu Amiantix sur '.$topic.' avec situations terrain, preuves documentaires, coordination et arbitrages utiles avant intervention.',
        };
    }

    /**
     * @param  array<string,mixed>  $blueprint
     */
    private function buildH1(string $keyword, string $cluster, array $blueprint): string
    {
        $normalizedKeyword = Str::lower(Str::ascii($keyword));
        $headlineKeyword = Str::headline($keyword);

        return match (true) {
            str_contains($normalizedKeyword, 'diagnostic') => $headlineKeyword.' : ce qu il faut verifier avant de lancer une intervention',
            str_contains($normalizedKeyword, 'reperage') => $headlineKeyword.' : ce qui doit etre cadré pour une intervention maitrisée',
            default => $headlineKeyword.' : points de vigilance et coordination utile',
        };
    }

    private function riskConsequence(string $risk): string
    {
        $normalized = Str::lower(Str::ascii($risk));

        return match (true) {
            str_contains($normalized, 'exposition') => 'exposition non maitrisee, suspension d intervention ou reprise documentaire',
            str_contains($normalized, 'empouss') => 'dispersion de fibres, arret de zone ou adaptation lourde de methode',
            str_contains($normalized, 'document') => 'mauvaise decision, diffusion incomplete ou contradiction entre acteurs',
            str_contains($normalized, 'desorganisation') => 'retard, confusion de responsabilites ou reprise de coordination',
            str_contains($normalized, 'surcout'), str_contains($normalized, 'blocage') => 'allongement planning, immobilisation et arbitrage en urgence',
            default => 'blocage, surcout ou maitrise insuffisante du risque',
        };
    }
}
