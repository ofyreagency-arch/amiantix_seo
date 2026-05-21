# SEO Engine Runtime Capability Matrix

## Goal

This document answers a practical question:

What is the runtime already capable of today, what is partial, and what still needs work before the engine is truly reliable in production?

It is intentionally product-oriented and runtime-oriented.

## Reading guide

- `Capable`: already usable in real runtime conditions
- `Partial`: present, but not yet complete or still brittle
- `Missing`: not yet implemented as a real product capability

---

## 1. Observed Site Layer

### Status

`Capable`

### What already works

- site crawl entrypoint exists
- `robots.txt` discovery
- `sitemap.xml` and sitemap index discovery
- observed page persistence
- page snapshot persistence
- internal/outbound link persistence
- schema extraction and persistence
- crawl issue persistence
- per-site observed tables

### What is still partial

- large-site crawl strategy is still basic
- crawl quotas/limits per site are not yet productized
- retry/error policies are not yet fully hardened

### Real-world test to run

1. pick one real site
2. launch a crawl
3. confirm that these tables fill correctly:
   - `seo_site_crawls`
   - `seo_site_pages`
   - `seo_site_page_snapshots`
   - `seo_site_links`
   - `seo_site_sitemaps`
   - `seo_site_schemas`
   - `seo_site_crawl_issues`
4. verify one crawl with:
   - a canonical page
   - a noindex page
   - internal links
   - schema markup

---

## 2. Blog Generation / Engine Action Layer

### Status

`Capable` for generation and rewrite drafts  
`Partial` for real publication lifecycle

### What already works

- page generation
- rewrite suggestions
- rewrite signal context
- suggestion lifecycle basics
- noise cleanup
- pending rewrite replacement

### What is still partial

- no true CMS bridge yet
- no per-site publishing strategy
- no reliable “published and verified” loop
- no draft-vs-published bridge workflow yet

### Real-world test to run

1. generate 3 real pages on one site
2. verify:
   - keyword
   - slug
   - status
   - persisted scores
3. trigger rewrites on 1 weak page
4. confirm:
   - no duplicate rewrite spam
   - pending rewrite replaced correctly
   - signal-based rationale survives

### Key question

The engine already knows how to generate and rewrite.  
It does **not yet** know reliably where or how to publish per client.

---

## 3. Search Console

### Status

`Partial`

### What already works

- Search Console configuration exists
- historical importer exists
- runtime context can inject GSC config per site
- per-site GSC connection model is now formalized
- query metrics can feed semantic/query analyzers

### What is still partial

- full OAuth lifecycle not implemented
- validation flow per client not productized
- refresh token lifecycle not complete
- multiple properties per site not handled as product behavior
- admin UX for connection state is still minimal

### Real-world test to run

1. pick one site with a real GSC property
2. configure:
   - property URL
   - credentials path or connection mode
3. validate API access
4. run historical import
5. verify `seo_search_console_metrics` is filled
6. verify query opportunities and query matches start appearing

### Failure modes to watch

- unauthorized property
- wrong credentials path
- wrong `sc-domain:` format
- quotas or partial syncs

---

## 4. Semantic Graph Engine

### Status

`Capable`

### What already works

- observed page embeddings
- observed query embeddings
- vector storage
- semantic neighbors
- overlap detection
- cannibalization detection
- internal linking suggestions
- query/page opportunities
- cluster/pillar/orphan/authority analysis

### What is still partial

- long-term graph evolution views are not productized
- graph timeline/diff is not yet surfaced
- some analyzers still need more real-world edge-case testing

### Real-world test to run

1. crawl a real site with at least 20–50 URLs
2. run embeddings and graph analyzers
3. confirm:
   - semantic neighbors look plausible
   - overlaps are not random
   - internal link suggestions are coherent
   - pillar candidates make editorial sense
   - query opportunities connect to real pages

### Failure modes to watch

- overly generic neighbors
- cluster leakage
- too many false overlap signals
- recommendations based on thin observed data

---

## 5. Site Understanding

### Status

`Capable`

### What already works

- site understanding synthesis exists
- weak pages
- orphan pages
- authority
- pillar pages
- content gaps
- internal linking weakness
- query opportunities included in understanding output

### What is still partial

- value is limited if the observed layer is empty
- not yet fully exposed as a polished operator workflow

### Real-world test to run

1. run a crawl on a real site
2. trigger understanding
3. inspect whether it correctly identifies:
   - weak pages
   - noindex pages
   - orphan pages
   - pillar candidates
   - content gaps

### Key truth

Understanding is only as good as the observed site data.  
If crawl/GSC are empty, this layer will look “weak” even if the engine code is good.

---

## 6. Recommendations

### Status

`Capable`

### What already works

- structured recommendations exist
- recommendation persistence exists
- priority / impact / difficulty fields exist
- backlog can be visualized in admin

### What is still partial

- recommendation verification lifecycle is not complete
- some recommendations are still easier to inspect than to execute

### Real-world test to run

1. crawl a real site
2. run site understanding + recommendation generation
3. verify if backlog contains realistic actions:
   - refresh page
   - strengthen pillar
   - create page
   - add links
   - resolve overlap

### Failure modes to watch

- good recommendation structure but weak grounding
- duplicates if crawl state changes little

---

## 7. Monitoring / Prioritization / Feedback

### Status

`Capable`

### What already works

- score refresh pipeline
- monitoring
- prioritization
- feedback loop
- signal suggestion queue
- stale suggestion cleanup
- action abstention behavior

### What is still partial

- long-term production behavior still needs runtime observation
- per-client quota/pacing not yet fully productized

### Real-world test to run

1. create:
   - one weak page
   - one stronger page
2. run monitoring
3. confirm:
   - weak page creates useful action
   - strong page stays quiet
   - old pending noise gets cleaned

---

## 8. Dashboard / Admin Runtime

### Status

`Partial`

### What already works

- dashboard reads real runtime data
- action queues are visible
- site health is visible
- cold start state is handled better than before

### What is still partial

- UX still needs iteration
- visual hierarchy still depends on real runtime data quality
- some views should be hidden or compacted more aggressively when empty

### Real-world test to run

1. compare dashboard before and after a real crawl
2. verify whether:
   - actions become more meaningful
   - observed pages appear
   - graph hotspots appear
   - query opportunities appear
   - site health becomes less empty

### Key truth

The dashboard is now connected to reality, but it still needs product refinement.

---

## 9. Multi-site Runtime

### Status

`Partial to capable`

### What already works

- site model exists
- API token per site exists
- per-site runtime context exists
- per-site GSC connection model has started
- admin/API per site exists

### What is still partial

- quotas per client are not finished
- queue/scheduler partitioning is not fully productized
- publish bridge per site does not exist yet

### Real-world test to run

1. create at least 2 sites
2. crawl only site A
3. import/query data only for site A
4. confirm site B remains isolated
5. generate pages on both
6. confirm data and queues stay scoped to `site_id`

---

## Capability summary

### Already strong

- observed crawl
- graph
- understanding
- recommendations
- scoring
- monitoring
- feedback loop
- rewrite behavior

### Strong but still needing real-world validation

- query opportunities on live GSC data
- graph quality on real crawled sites
- recommendation quality on real client content

### Not yet a finished product capability

- GSC client-grade connection lifecycle
- CMS publishing bridge
- verified action lifecycle after publication
- autopilot with hard production guardrails

---

## Real-world validation roadmap

This should happen before adding too many new features.

### Phase A — Crawl reality

1. choose one real site
2. run crawl
3. inspect observed tables
4. inspect graph outputs

### Phase B — Blog generation reality

1. generate 3 pages
2. rewrite 1 page
3. inspect action queues
4. confirm no duplicate rewrite noise

### Phase C — Search Console reality

1. connect one real GSC property
2. import historical metrics
3. inspect query matches and opportunities
4. inspect failures/quotas

### Phase D — Multi-site isolation reality

1. create two sites
2. run different actions per site
3. verify isolation in DB and admin

---

## Recommended immediate next step

Before building more runtime features:

1. test crawl on a real site
2. test generation on a real site
3. test GSC on a real site
4. list the actual runtime issues found

Only after that should we decide whether the next block is:

- `CMS bridge`
- `GSC lifecycle`
- `action verification`
- `autopilot guardrails`
