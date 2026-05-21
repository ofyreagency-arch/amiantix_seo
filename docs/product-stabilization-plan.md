## Product Stabilization Plan

Date: 2026-05-21  
Status: Active  
Goal: return the product to a DUERP-style workflow that is clear, stable, and trustworthy before adding new runtime layers.

### 1. Official Product Workflow

The official workflow becomes:

`Generate -> Pending -> Approve -> Publish -> Visible -> Monitoring`

Everything in the product must support this flow directly, or remain secondary and temporarily hidden.

### 2. Product Truth Model

We freeze the meaning of the main entities:

- `SeoPage`
  - source of truth for editorial lifecycle
  - generated, reviewed, approved, published
- `SeoSitePage`
  - source of truth for observed live reality
  - crawled, visible, linked, structured
- `SeoSuggestion`
  - source of truth for engine-proposed editorial actions
- `SeoRecommendation`
  - source of truth for observed strategic actions

### 3. What We Keep

- observed crawl
- semantic graph
- observed health
- page generation
- pending / approve / publish
- observed strategy
- monitoring

### 4. What We Freeze Temporarily

- new large autopilot features
- new runtime API surfaces
- new dashboard intelligence panels
- new automation layers beyond current flows
- complex feedback-loop expansion outside current tests

### 5. What We Hide or De-emphasize

- ambiguous autopilot actions
- advanced runtime counters without user-facing meaning
- screens that mix `SeoPage` and `SeoSitePage` without explicit labels
- secondary technical views that compete with the main editorial flow

### 6. Stabilization Priorities

#### Priority A: Editorial Workflow

Objective: make content lifecycle obvious again.

Work:

- make `Generate` deterministic and visibly tied to the active preset
- make `Pending` a clear review queue
- make `Approve` a real state transition
- make `Publish` a real action with clear success/failure feedback
- show whether a page is:
  - generated
  - pending
  - approved
  - published
  - visible in crawl

Acceptance criteria:

- no ambiguous buttons
- no hidden state transitions
- each page has one readable lifecycle

#### Priority B: Expert Asbestos Preset Lock

Objective: stop regression into generic GPT-like content.

Work:

- identify where expert preset can be overridden
- identify fallback paths in generation or rewrite
- identify partial payload paths that degrade output quality
- add editorial regression tests around:
  - chantier phasing
  - empoussierement
  - coordination documentaire
  - site occupe
  - SS3 / SS4
  - preuves terrain
  - arbitrages
  - structured tables

Acceptance criteria:

- expert preset always wins over generic fallback
- rewrite does not flatten expert content
- generated articles consistently contain domain depth

#### Priority C: Visibility and Observation

Objective: make published reality visible.

Work:

- clearly distinguish:
  - generated page
  - published page
  - observed page
- show if a generated page is visible in crawl
- show if an observed page has no corresponding editorial object
- keep `SeoSitePage` as truth for live site state

Acceptance criteria:

- user can tell what is live
- user can tell what is only internal
- user can tell what the crawler actually confirmed

#### Priority D: Monitoring and Recommendations

Objective: recommendations become readable and useful.

Work:

- keep health on `SeoSitePage`
- keep strategy on observed signals
- deduplicate recommendations
- display target page or target pair clearly
- show reason and evidence behind each recommendation

Acceptance criteria:

- no opaque machine-only labels
- no obvious duplicates
- each recommendation answers:
  - what page?
  - what problem?
  - what action?

### 7. Screen-by-Screen Product Plan

#### A. Site Overview

Keep:

- quick access to crawler, health, strategy, semantic

Simplify:

- emphasize workflow over technical modules
- show a short site summary:
  - generated pages
  - pending pages
  - published pages
  - observed pages
  - active recommendations

#### B. Page Detail

This becomes the core editorial cockpit.

Must show clearly:

- current content state
- approval state
- publish state
- latest crawl visibility
- latest monitoring signals
- latest rewrite or recommendation affecting that page

#### C. Strategy

Must become:

- deduplicated
- contextualized
- evidence-based

Must not:

- show raw analyzer noise
- depend on vague machine labels

#### D. Health

Already reconnected to observed truth.

Next expectations:

- surface weak pages explicitly
- surface poor indexability explicitly
- surface fragile clusters explicitly

#### E. Autopilot

Temporarily secondary.

Keep only as a visible queue, not as a product centerpiece, until:

- strategy is stable
- publish flow is stable
- suggestion lifecycle is fully trustworthy

### 8. Critical Regression List

Fix these before any major expansion:

- generic fallback overriding expert asbestos generation
- broken approve / publish UX
- views still calling legacy incompatible methods
- runtime commands referenced but unavailable in production
- MySQL-incompatible queries or migrations
- actions that appear valid but are not really executable

### 9. Execution Order

The execution order is now frozen:

1. lock expert asbestos generation quality
2. stabilize `Generate -> Pending -> Approve -> Publish`
3. clarify `Published -> Visible -> Monitoring`
4. simplify page and site cockpit UX
5. keep strategy observed clean and actionable
6. reopen autopilot only after the above are stable

### 10. Stop Rule

Do not add a new major runtime, API, or autopilot layer until all of the following are true:

- official workflow is clear in UI
- expert preset is reliable
- publish state is trustworthy
- crawl visibility is trustworthy
- recommendations are readable
- production errors are no longer frequent

### 11. Immediate Next Block

The next implementation block should be:

`Preset + Editorial Workflow Stabilization`

Concrete tasks:

- audit generation driver
- audit rewrite driver
- identify expert preset entrypoints
- identify fallback generic entrypoints
- map current page states and publish buttons
- document the exact official page lifecycle

This is the highest-value move because it restores trust in the product itself, not just in the backend.
