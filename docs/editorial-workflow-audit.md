## Editorial Workflow Audit

Date: 2026-05-21  
Status: Active  
Scope: `Preset + Editorial Workflow Stabilization`

### 1. Objective

This audit maps the real current editorial workflow and identifies:

- which layer owns each step
- which model is the source of truth
- where the asbestos expert preset can be degraded
- where generic fallback can take control
- which states are currently confusing in product UX

This document is intentionally product-first.

### 2. Official Workflow Target

The intended official workflow is:

`Generate -> Pending -> Approve -> Publish -> Visible -> Monitoring`

The current codebase does not yet implement this flow as one clean product line.

### 3. Current Source of Truth by Layer

#### A. Editorial Content Layer

Primary model:

- `SeoPage`

Current role:

- generated content
- pending review state
- publish state
- rewrites and suggestions
- page-level audits and scores

Current product reality:

- this is still the main source of truth for editorial workflow
- all page detail actions still operate on `SeoPage`

Relevant code:

- [AdminPagesController.php](C:/Users/donov/Desktop/ofyre-seo-engine-main/seo-engine-app/app/Http/Controllers/Admin/AdminPagesController.php:25)
- [SeoPage.php](C:/Users/donov/Desktop/ofyre-seo-engine-main/seo-engine-app/app/Models/SeoPage.php:11)

#### B. Observed Site Layer

Primary model:

- `SeoSitePage`

Current role:

- real crawled page
- live URL state
- semantic graph source
- health source
- strategy source

Current product reality:

- crawl, semantic graph, and observed health already depend on this layer
- visibility and structure are far more trustworthy here than in `SeoPage`

Relevant code:

- [SeoSitePage.php](C:/Users/donov/Desktop/ofyre-seo-engine-main/seo-engine-app/app/Models/SeoSitePage.php:9)
- [SiteCrawlerService.php](C:/Users/donov/Desktop/ofyre-seo-engine-main/seo-engine-app/app/ObservedSite/SiteCrawlerService.php:463)

#### C. Action Layer

Primary models:

- `SeoSuggestion`
- `SeoRecommendation`
- `SeoStrategyItem`

Current role:

- `SeoSuggestion`: editorial actions linked to `SeoPage`
- `SeoRecommendation`: observed recommendations linked to site analysis
- `SeoStrategyItem`: UI projection of recommendations

Current product reality:

- suggestions and recommendations are conceptually different
- this distinction is not obvious enough in UX today

### 4. Real Current Workflow in Code

#### Step 1: Generate

Entry point:

- [AdminPagesController::generate()](C:/Users/donov/Desktop/ofyre-seo-engine-main/seo-engine-app/app/Http/Controllers/Admin/AdminPagesController.php:52)

Actual flow:

1. admin submits keyword + optional status
2. `SeoGeneratePageRunner` is called
3. generation delegates to `SeoGenerationService`
4. `SeoPage` is created or updated

Truth owner:

- `SeoPage`

Important observation:

- this step is still editorial-first, not observed-first

#### Step 2: Pending

Current implementation:

- a page may be created directly as `draft`
- suggestion apply may move a `draft` page to `review`
- quick-fix can force `status = review`

Relevant code:

- [SeoSuggestionWorkflowService.php](C:/Users/donov/Desktop/ofyre-seo-engine-main/seo-engine-app/app/ActionLayer/SeoSuggestionWorkflowService.php:83)
- [AdminPagesController::quickFix()](C:/Users/donov/Desktop/ofyre-seo-engine-main/seo-engine-app/app/Http/Controllers/Admin/AdminPagesController.php:196)

Current issue:

- the product does not present one canonical “pending” state clearly
- `draft`, `review`, `pending_review`, and `published` still coexist in the publication logic

#### Step 3: Approve

Current implementation:

- suggestion approval means “apply suggestion payload to `SeoPage`”
- this is not the same thing as editorial approval for publication

Relevant code:

- [SeoSuggestionWorkflowService::apply()](C:/Users/donov/Desktop/ofyre-seo-engine-main/seo-engine-app/app/ActionLayer/SeoSuggestionWorkflowService.php:20)

Current issue:

- “approve” currently refers to suggestion application more than editorial workflow
- product semantics are not obvious to the user

#### Step 4: Publish

Entry point:

- [AdminPagesController::publish()](C:/Users/donov/Desktop/ofyre-seo-engine-main/seo-engine-app/app/Http/Controllers/Admin/AdminPagesController.php:170)

Actual flow:

1. summarize page state using `SeoPageStatusService`
2. if blocking reasons exist, refuse publication
3. otherwise set:
   - `status = published`
   - `published_at = now()`

Truth owner:

- `SeoPage`

Current issue:

- publish currently means internal editorial state change
- it does **not** guarantee that the content is really live in the target CMS or visible to the crawler

#### Step 5: Visible

Current implementation:

- visibility belongs to `SeoSitePage` and observed crawl
- this is **not** automatically connected to the publish action

Truth owner:

- `SeoSitePage`

Current issue:

- the product still lacks a clean “published vs visible” explanation
- a page can be “published” in `SeoPage` and still not be confirmed in `SeoSitePage`

#### Step 6: Monitoring

Current implementation:

- legacy monitoring still acts mainly on `SeoPage`
- observed health and observed strategy already act on `SeoSitePage`

Current issue:

- monitoring is split between editorial and observed reality
- the product does not yet make the distinction obvious

### 5. Where the Expert Preset Can Be Degraded

#### A. Initial Generation

Relevant code:

- [SeoGenerationService::generatePayload()](C:/Users/donov/Desktop/ofyre-seo-engine-main/src/Services/Generation/SeoGenerationService.php:32)

Actual behavior:

- resolve cluster
- resolve blueprint
- try AI generation
- fallback to `fallbackPayload()` if AI fails
- then `ensurePremiumDepth()`

Risk:

- if AI returns partial payload, generation falls back silently
- fallback can remain structurally strong, but may still be less specific than the best expert output

Critical signal:

- `askAi()` returns `null` on:
  - missing API key
  - network error
  - HTTP error
  - empty payload
  - invalid JSON
  - partial payload

Relevant code:

- [SeoGenerationService::askAi()](C:/Users/donov/Desktop/ofyre-seo-engine-main/src/Services/Generation/SeoGenerationService.php:172)

#### B. Improvement / Enrich

Relevant code:

- [SeoGenerationService::improvePayload()](C:/Users/donov/Desktop/ofyre-seo-engine-main/src/Services/Generation/SeoGenerationService.php:50)

Actual behavior:

- try AI improvement
- if improvement fails, rebuild from fallback payload and append extra section

Risk:

- a failed enrich pass can partially reset tone or structure
- existing expert content can be mixed with fallback material that feels more templated

#### C. Rewrite

Relevant code:

- [SeoRewriteService::createSuggestion()](C:/Users/donov/Desktop/ofyre-seo-engine-main/src/Services/Rewrite/SeoRewriteService.php:24)

Actual behavior:

- try AI rewrite
- fallback to `PromptProfileProvider::fallbackRewrite()`
- merge pending signal context into suggestions
- synthesize `proposed_content` if needed

Risk:

- rewrite does not guarantee expert preset depth on its own
- fallback rewrite is structurally valid but may flatten domain specificity
- pending signal sections can add utility but not necessarily restore expert asbestos tone

#### D. Publish

Current behavior:

- publish does not rewrite content
- but publish can bless already degraded content if review gates are insufficiently domain-specific

#### E. Monitoring Refresh

Current behavior:

- monitoring and suggestion systems may queue actions based on technical or structural weakness
- they do not yet guarantee preservation of expert asbestos editorial depth

### 6. Concrete Preset Risks Identified

#### Risk 1: Partial OpenAI payload means silent fallback

Relevant code:

- [SeoGenerationService::askAi()](C:/Users/donov/Desktop/ofyre-seo-engine-main/src/Services/Generation/SeoGenerationService.php:278)

Current behavior:

- partial JSON from OpenAI is treated as failure
- generation then falls back

Impact:

- can produce a sudden drop from expert-rich article to safer templated material

#### Risk 2: Improvement fallback can dilute strong existing pages

Relevant code:

- [SeoGenerationService::improvePayload()](C:/Users/donov/Desktop/ofyre-seo-engine-main/src/Services/Generation/SeoGenerationService.php:56)

Impact:

- enrich may not behave as pure expert enrichment
- it can become “fallback plus extra section”

#### Risk 3: Rewrite fallback can flatten expert depth

Relevant code:

- [SeoRewriteService::createSuggestion()](C:/Users/donov/Desktop/ofyre-seo-engine-main/src/Services/Rewrite/SeoRewriteService.php:48)

Impact:

- rewrite may preserve signals but lose preset-specific richness

#### Risk 4: Quality gate is strong structurally, but not yet strict enough semantically

Relevant code:

- [SeoQualityGateService.php](C:/Users/donov/Desktop/ofyre-seo-engine-main/src/Services/Quality/SeoQualityGateService.php:35)

What it already checks:

- FAQ count
- table presence
- editorial section coverage
- risk terms
- generic phrase warnings

What still needs strengthening:

- terrain-case density
- chantier/phasing specificity
- SS3/SS4 signal density
- documentary proof density
- anti-generic thresholds specific to asbestos preset

### 7. Product Confusions Confirmed

#### Confusion A: Approve vs Publish

- approve currently means apply a suggestion
- publish means move `SeoPage` to `published`
- user-facing product semantics are not explicit enough

#### Confusion B: Published vs Visible

- published is editorial state
- visible is observed crawl state
- these are different realities and currently too easy to confuse

#### Confusion C: Strategy vs Suggestions

- strategy is observed recommendation
- suggestions are editorial rewrite/apply payloads
- both are “actions”, but not the same layer

### 8. Immediate Critical Regressions to Fix First

1. expert preset losing to generic fallback during generate / enrich / rewrite
2. unclear editorial statuses (`draft`, `review`, `pending_review`, `published`)
3. publish meaning internal-only state instead of visibly tracked state
4. page detail not clearly explaining:
   - generated version
   - approved state
   - published state
   - observed visibility
5. legacy runtime references still leaking into current UX

### 9. Stabilization Order

#### Phase 1: Preset Integrity Audit

Work:

- trace generate path
- trace improve path
- trace rewrite path
- record all fallback entrypoints
- identify any generic profile takeover

Output:

- deterministic map of where expert asbestos depth can be lost

#### Phase 2: Editorial Workflow Canonicalization

Work:

- define exact allowed page statuses
- define one canonical pending state
- define what approve means
- define what publish means
- define what visible means

Output:

- one readable lifecycle for `SeoPage`

#### Phase 3: Product UI Simplification

Work:

- simplify page detail around editorial lifecycle
- show observed visibility next to published state
- make recommendations and suggestions clearly separate

Output:

- DUERP-style usability again

### 10. Recommended Next Implementation Block

Next block:

`Preset integrity + canonical page lifecycle`

Concrete tasks:

1. audit preset resolution and prompt profile selection
2. add regression tests for asbestos expert content depth
3. map and simplify `SeoPage.status`
4. rewrite page detail UI around:
   - generated
   - pending
   - approved
   - published
   - visible

This is the highest-leverage move because it restores product trust without adding new system complexity.
