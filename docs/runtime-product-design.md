# SEO Engine Runtime Product Design

## Purpose

This document fixes the product/runtime rules for the multi-site SEO Engine host app.

The goal is to keep the engine:

- multi-site
- data-isolated by `site_id`
- centered on observed signals
- conservative in automation
- extensible through explicit bridges

This is a runtime document, not a package-core document.

## Core runtime principles

1. `site_id` is the absolute runtime boundary.
2. The semantic graph is computed from the observed site layer, not generated drafts.
3. Search Console credentials and quotas are scoped per site.
4. Publishing is performed through an explicit CMS bridge per site.
5. An action is not "done" until it is observed again or confirmed by a bridge.
6. Autopilot stays conservative in V1 and does not auto-publish directly to production.

## Runtime layers

The host runtime should stay structured around these layers:

1. Observed Site Layer
2. Embeddings Layer
3. Semantic Graph Engine
4. Site Understanding Service
5. Recommendation Engine
6. Engine Action Layer
7. Dashboard / Runtime UI

The dashboard only visualizes these layers. It must not become the source of truth.

## Multi-site runtime model

Each `SeoSite` is an isolated runtime tenant.

### Minimum per-site runtime config

`seo_sites`

- `site_id`
- `name`
- `url`
- `niche`
- `locale`
- `preset`
- `is_active`
- `api_token_hash`

### Runtime rules

- Every table carrying business or crawl data must include `site_id` unless the relation is already strictly site-bound through a parent record.
- Every job payload must include `site_id`.
- Every log line produced by engine jobs should include `site_id`.
- Every quota decision should be evaluated per `site_id`.

### Scheduling

V1 scheduling is shared infrastructure with site-scoped jobs:

- crawl jobs
- GSC sync jobs
- embeddings jobs
- monitoring jobs
- recommendation jobs

V2 can add queue partitioning if throughput requires it.

## Search Console connection model

Search Console must be connected per site, never globally.

### V1 decision

Support one active GSC connection per site.

Supported connection modes:

- `service_account`
- `oauth_google`

V1 recommended default:

- `service_account`

This is simpler and safer when the operator controls Search Console access.

### Proposed fields

Either extend `seo_sites` or create `seo_site_google_connections`.

Suggested fields:

- `site_id`
- `connection_mode`
- `property_url`
- `property_label`
- `google_account_email`
- `refresh_token_encrypted`
- `access_token_expires_at`
- `credentials_path`
- `connection_status`
- `last_validated_at`
- `last_sync_at`
- `last_error`

### Runtime rules

- tokens/credentials must be isolated per `site_id`
- no shared Google token across multiple sites
- ownership/access must be validated before sync
- sync state must be visible in runtime UI

### V1 behavior

- one property per site
- explicit property URL
- explicit validation button/check
- sync blocked if unauthorized

### V2 extensions

- multiple properties per site
- property grouping
- more advanced ownership checks
- query by section or property slice

## CMS bridge model

The engine must not guess where or how it can publish.

Publishing must happen through an explicit per-site bridge.

### Bridge types

- `manual`
- `wordpress`
- `laravel_api`
- `headless`

### Proposed fields

Create `seo_site_publish_bridges` or extend `seo_sites`.

Suggested fields:

- `site_id`
- `bridge_type`
- `bridge_status`
- `publish_mode`
- `blog_base_path`
- `api_base_url`
- `api_credentials_encrypted`
- `editor_url`
- `supports_draft`
- `supports_publish`
- `supports_update`
- `supports_delete`
- `supports_reverify`
- `last_validated_at`
- `last_error`

### Publish modes

- `draft`
- `review`
- `publish`

V1 recommended default:

- `draft`

### Runtime rules

- the bridge determines how an action is executed
- the bridge never becomes the source of truth for SEO state
- after publish/update, the site should be re-crawled or explicitly reverified

### V1 behavior

- manual blog path configuration
- explicit publish bridge selection
- no auto-discovery as a source of truth
- optional passive hints from crawl/sitemap only

### V2 extensions

- richer WordPress integration
- headless CMS adapters
- more advanced post type mapping
- media/image bridge support

## Action lifecycle model

The engine needs a strict lifecycle or it will accumulate noise and ambiguity.

### Action states

Suggested canonical states:

- `pending`
- `approved`
- `rejected`
- `executing`
- `applied`
- `verified`
- `obsolete`
- `failed`

For recommendations that are not yet actionable:

- `pending` remains valid

For strategic items:

- `done` may still be useful as a UI alias, but runtime should map clearly to lifecycle states.

### Required timestamps

- `decided_at`
- `approved_at`
- `executed_at`
- `verified_at`
- `invalidated_at`
- `failed_at`

### Required actor/source fields

- `decision_source`
- `trigger_source`
- `approved_by`
- `execution_bridge`
- `verification_source`

### Verification rule

An action only becomes `verified` when:

- a publish bridge confirms the change, or
- the crawl/observed layer confirms the change, or
- both, depending on action sensitivity

### Invalidation rule

An action becomes `obsolete` when:

- the site state changed and the recommendation no longer applies
- a human action superseded it
- a newer rewrite replaced it
- the graph/query/crawl no longer supports it

## Semantic graph long-term model

The graph must be historical, not just current.

### Source of graph truth

The graph is derived from:

- observed pages
- observed links
- observed snapshots
- query matches
- embeddings
- Search Console signals

Not from generated drafts.

### Historical needs

We need to preserve:

- crawl snapshots
- page snapshots
- link structure per crawl window
- cluster labels over time
- tension scores over time

### Important longitudinal signals

- `authority_score`
- `orphan_score`
- `overlap_score`
- `pillar_likelihood`
- `cluster_label`
- `indexability_state`

### V1 requirement

Store enough snapshot history to compare:

- crawl N vs crawl N-1
- cluster drift
- new orphan pages
- resolved overlaps
- improved/declined authority zones

### V2 extensions

- timeline view of graph tension
- cluster evolution history
- page state delta explorer

## Autopilot guardrails

Autopilot must stay limited in V1.

### Safe automatic actions in V1

- trigger crawl
- trigger GSC sync
- compute graph
- compute recommendations
- generate draft rewrites
- generate draft internal linking suggestions

### Human approval required in V1

- publish to production
- merge pages
- canonicalize
- remove content
- rewrite strong pages already performing well
- structural actions with traffic risk

### Runtime guardrails

- max actions per site per day
- max rewrites per page per period
- cooldown per page after an action
- no duplicate pending rewrite for same mode
- no autopilot publish without explicit bridge support
- no autopilot action if last verification failed
- no autopilot loop if observed layer has not changed

### Anti-loop rules

- do not rewrite a page already holding a pending rewrite of the same mode
- do not regenerate the same strategic action without new observed evidence
- do not approve engine decisions based only on previously generated content

## Dashboard principles

The dashboard must reflect engine truth, not compensate for weak engine logic.

### High-value runtime views

1. Action Center
- pending recommendations
- pending rewrites
- blocked rewrites
- feedback/signal queues

2. Site Health
- weak observed pages
- orphan pages
- crawl freshness
- indexability issues

3. Graph Tensions
- overlaps
- cannibalization
- weak clusters
- pillar gaps

4. Opportunity Feed
- create page
- refresh page
- strengthen pillar
- internal links

5. Runtime Status
- GSC connection
- publish bridge status
- crawl status
- quotas/errors

### Empty-state rule

The dashboard should not fill the screen with large empty boxes.

When a site has no observed data yet, the UI should clearly say:

- no crawl launched yet
- no observed pages yet
- next action: run crawl
- next action: connect GSC
- next action: generate strategy

## Proposed runtime tables

This is a product/runtime view, not a full migration plan.

### Already aligned or partially aligned

- `seo_sites`
- `seo_pages`
- `seo_suggestions`
- `seo_audits`
- `seo_search_console_metrics`
- `seo_vectors`
- `seo_semantic_links`
- `seo_site_crawls`
- `seo_site_pages`
- `seo_site_page_snapshots`
- `seo_site_links`
- `seo_site_sitemaps`
- `seo_site_schemas`
- `seo_site_crawl_issues`
- `seo_recommendations`

### Missing or likely needed next

- `seo_site_google_connections`
- `seo_site_publish_bridges`
- `seo_action_events`
- `seo_action_verifications`
- `seo_runtime_quotas`

## Orchestration flow

### Runtime observation loop

1. crawl site
2. persist observed snapshots
3. compute embeddings
4. compute graph
5. compute understanding
6. emit recommendations

### Action loop

1. recommendation decided
2. optional human approval
3. bridge execution or draft generation
4. post-action re-crawl or verification
5. action marked `verified` or `failed`
6. stale actions marked `obsolete`

## V1 vs V2

### V1

- one GSC connection per site
- explicit CMS bridge per site
- draft-first publishing
- conservative autopilot
- site-scoped scheduling
- observed layer as graph truth
- verification before claiming success

### V2

- multiple GSC properties per site
- richer CMS adapters
- deeper quotas/scheduling partitioning
- historical graph timelines
- more aggressive automation with per-site trust levels

## Open decisions

These still need explicit product choices later:

1. Should GSC connections live directly on `seo_sites` or in a dedicated table?
2. Should publish bridges be one-per-site or many-per-site?
3. Do we want page-level trust scores before enabling broader autopilot?
4. Do we want per-site action caps configurable from admin?
5. Do we want a dedicated event log table for every engine action transition?

## Immediate next design steps

1. formalize Search Console connection model
2. formalize CMS bridge model
3. formalize action lifecycle and verification
4. formalize autopilot guardrails
5. only then enrich runtime automation and dashboard views further
