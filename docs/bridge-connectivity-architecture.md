# Bridge Connectivity Architecture

PraeviSEO is designed as a centralized SEO infrastructure, not as a full SEO engine installed inside every client project.

The client site only installs a light bridge. All heavy intelligence stays inside PraeviSEO.

## Product goal

The client should be able to connect a site in under one minute, then immediately see in one cockpit:

- synchronized pages
- indexed pages
- weak content
- SEO opportunities
- technical issues
- pages to optimize
- traffic losses
- rewrite candidates

The visible product is:

- the cockpit
- monitoring
- publication
- optimization actions
- supervised automation

The bridge itself should stay almost invisible.

## Bridge role

The bridge has 3 responsibilities.

### 1. Content synchronization

The bridge exposes the site content to PraeviSEO:

- pages
- posts
- metas
- canonical
- noindex
- structural SEO signals

PraeviSEO then:

- analyzes content
- scores quality and indexability
- builds semantic clusters
- detects cannibalization
- finds weak pages
- generates suggestions and rewrites

### 2. Remote publication

PraeviSEO must not only read the site. It must also be able to act on it.

The bridge receives remote publication payloads from PraeviSEO and applies:

- title updates
- meta updates
- content rewrites
- FAQ updates
- schema updates
- internal linking updates
- new SEO pages
- publication status changes

The bridge then returns a clear success or failure status.

### 3. Runtime and SEO signal reporting

The bridge can also surface local runtime signals:

- HTTP state
- canonical state
- noindex state
- crawl observations
- publication state
- internal technical flags

Google Search Console remains centralized in PraeviSEO so the platform can aggregate:

- clicks
- impressions
- CTR
- positions
- indexation signals
- query opportunities

## What stays inside PraeviSEO

The bridge must stay lightweight.

It must not contain:

- AI generation
- embeddings
- queues
- advanced SEO decision logic
- clustering logic
- monitoring orchestration
- rewrite intelligence

PraeviSEO keeps:

- the SEO engine
- the scoring engine
- the rewrite engine
- Search Console aggregation
- indexation backlog logic
- semantic intelligence
- autopilot decisions
- monitoring and reopen rules

## Supported connection modes

The product should start with official first-party connectors:

- Laravel Bridge
- Symfony Bridge
- WordPress later

Advanced webhook/API mode can still exist, but it is not the primary client experience.

## Simple client UX

The client must never have to understand:

- HMAC
- webhook internals
- JSON payloads
- auth headers
- routing details
- connector plumbing

Target UX:

1. install bridge package
2. run `praeviseo:connect`
3. paste the connection code
4. see:
   - Site connected ✅
   - Publication active ✅
   - Monitoring active ✅

## Publication control modes

PraeviSEO must support multiple levels of control:

### Suggestions only

PraeviSEO analyzes and recommends changes, but does not publish automatically.

### Supervised publishing

PraeviSEO prepares a change, a human validates it, then the platform publishes it through the bridge.

### Fully automated mode

PraeviSEO can optimize and publish directly when the site policy allows it.

## Honest technical boundaries

PraeviSEO must stay honest about what it can actually control.

Actionable by the engine:

- content
- structure
- internal linking
- SEO enrichment
- optimization
- publication
- monitoring
- intelligent reopening

Still human technical review:

- DNS
- hosting
- infra
- server robots issues
- broken redirects
- CMS bugs
- runtime outages

The cockpit must never pretend the AI fixed those cases.

## Final workflow

The end state is:

1. problem detection
2. engine decision
3. human validation when needed
4. real CMS/site publication
5. real post-publication monitoring
6. intelligent reopening only on meaningful drift

This turns PraeviSEO into a centralized multi-site SEO operating system.
