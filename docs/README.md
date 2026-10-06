# Flavourful — docs index

Start here. This page tells you what each folder holds, which era of the project wrote it, and where to begin for any topic.

The repo has had **two planning eras**, and both stay on record:

- **Pre-Notion (Days 1–13).** The repo began as preparation for a Drupal-on-Acquia vetting call. Work was planned as numbered day labs in [`objectives/`](objectives/), and each lab that met the running project got an outcome note in [`actual-outcomes/`](actual-outcomes/).
- **Notion era (Phases and Weeks).** Later work follows a roadmap kept in Notion, numbered by Phase and Week (for example *Phase 1 — Accessibility to Audit Level*, *Phase 2 — Drupal Front-End Depth*). These have no objective file. Each slice is frozen as a plan in [`plans/`](plans/) before execution, and the result lands in an outcome note.

Day numbers and Phase/Week numbers are separate sequences. They do not map onto each other.

---

## Start here

If you only read four documents, read these in order:

1. [`actual-outcomes/lessons-learned.md`](actual-outcomes/lessons-learned.md) — problem → root cause → fix, across deployment, config, Views, theming, modules and Site Studio. This is the densest record of what the project taught.
2. [`actual-outcomes/acquia-deployment-guide.md`](actual-outcomes/acquia-deployment-guide.md) — the real first deployment to Acquia Cloud Next, with every trap hit along the way.
3. [`actual-outcomes/day9-sdc.md`](actual-outcomes/day9-sdc.md) — the component library, and why Site Studio owns the full recipe page.
4. [`actual-outcomes/accessibility-audit.md`](actual-outcomes/accessibility-audit.md) — the WCAG 2.2 AA audit: findings, fixes, retests.

---

## What each folder holds

| Folder | Holds | Era |
|---|---|---|
| [`objectives/`](objectives/) | The plan: day-by-day labs, and the [vetting-prep overview](objectives/README.md) | Pre-Notion |
| [`actual-outcomes/`](actual-outcomes/) | What shipped: objective → outcome maps, deviations, verification | Both |
| [`plans/`](plans/) | Scope frozen from Notion before execution, so mid-build errors don't rewrite the goal. Statuses inside each plan are as of its own *Last updated* date | Notion |
| [`future-scope/`](future-scope/) | Scoped but not built (the former Day 14–17 labs), with blockers | Pre-Notion |

**How to read an outcome note.** Every `day*` outcome is a delta: it does not re-teach what its objective covers. Open the objective first for concepts and steps, then the outcome for what actually happened.

---

## Pre-Notion: Days 1–13

| Day | Topic | Plan (objective) | What shipped (outcome) |
|---|---|---|---|
| — | Overview | [5-day plan](objectives/5-day-plan.md) · [Days 6–9 curriculum](objectives/advanced-plan-days6-9.md) | — |
| 1 | Site building: content types, fields, taxonomy, media, Views | [day1](objectives/day1-site-building.md) | No outcome note |
| 2 | Custom module and Twig subtheme | [day2](objectives/day2-module-and-twig.md) | No outcome note |
| 3 | Composer, Drush, config management, Acquia Cloud | [day3](objectives/day3-acquia-cloud-devops.md) | [Acquia deployment guide](actual-outcomes/acquia-deployment-guide.md) |
| 4 | Site Studio | [day4](objectives/day4-site-studio.md) | [day4-5](actual-outcomes/day4-5-site-studio-nutrition.md) (one note for Days 4 and 5) |
| 5 | Nutrition API, identity mapping | [day5](objectives/day5-integrations-identity-interview.md) | ↑ same note |
| 6 | Hooks and preprocess | [day6](objectives/day6-hooks-preprocess.md) | [day6](actual-outcomes/day6-hooks-preprocess.md) |
| 7 | Advanced Views | [day7](objectives/day7-advanced-views.md) | [day7 REST export](actual-outcomes/day7-rest-export.md) (partial slice) · see also [day7b](actual-outcomes/day7b-advanced-views.md) (Notion era) |
| 8 | Twig best practices | [day8](objectives/day8-twig-best-practices.md) | [day8](actual-outcomes/day8-twig-templates.md) (work in progress; card superseded by Day 9) |
| 9 | Atomic design with SDC | [day9](objectives/day9-atomic-sdc.md) | [day9](actual-outcomes/day9-sdc.md) |
| 10 | Search API, Solr, SearchStax | [day10](objectives/day10-search-solr-searchstax.md) | [day10](actual-outcomes/day10-solr-search.md) (partial: infrastructure only) |
| 11 | Performance, caching, debugging | [day11](objectives/day11-performance-caching-debugging.md) | No outcome note |
| 12 | JavaScript in Drupal | [day12](objectives/day12-javascript-in-drupal.md) | No outcome note |
| 13 | Multisite and governance | [day13](objectives/day13-multisite-governance.md) | No outcome note |
| 14–17 | Canvas, Site Factory, DAM, Canvas AI | — | [future-scope/](future-scope/README.md) |

---

## Notion era: Phases and Weeks

### Phase 1 — Accessibility to Audit Level

| Slice | Frozen scope (plan) | What shipped (outcome) |
|---|---|---|
| Screen-reader Task 5: glossary A–Z | [plan](plans/plan-glossary-a11y-recipes-az.md) | [accessibility audit](actual-outcomes/accessibility-audit.md) · [screen-reader log](actual-outcomes/screen-reader-test-log.md) |
| Screen-reader Task 6: landmarks | [plan](plans/plan-a11y-landmarks-task6.md) | ↑ same |
| Week 3–5: range slider and facets | [plan](plans/plan-a11y-range-slider-and-facets.md) | ↑ same |
| Week 3–5: Views output | [plan](plans/plan-a11y-views-output.md) | ↑ same |
| Global pass and carried items | [plan](plans/plan-a11y-global-and-carried.md) | ↑ same |
| Week 5–6: CI accessibility gates | [plan](plans/plan-ci-a11y-gates.md) | ↑ same |
| Close O-4 and the alt-text decision | [plan](plans/plan-close-o4-and-alt-decision.md) | ↑ same |

### Phase 2 — Drupal Front-End Depth

| Slice | Frozen scope (plan) | What shipped (outcome) |
|---|---|---|
| Articles content model (prerequisite) | [plan](plans/plan-articles-content-model.md) | No outcome note yet |
| Week 4–6: Views to real depth | — | [day7b](actual-outcomes/day7b-advanced-views.md) (named after Day 7, which it revisits) |
| Week 4–6 item 4: Fields versus view modes | [plan](plans/plan-articles-fields-vs-view-modes.md) | ↑ [day7b](actual-outcomes/day7b-advanced-views.md) |
| Week 6–8: Twig Tweak, used once and critiqued | — | [twig-tweak-critique](actual-outcomes/twig-tweak-critique.md) |
| Week 6–8: autoescape and the trust boundary | — | [autoescape-and-the-trust-boundary](actual-outcomes/autoescape-and-the-trust-boundary.md) |
| Week 6–8: one render array of each kind | — | [render-arrays-compared](actual-outcomes/render-arrays-compared.md) |
| Week 6–8: cache layers, and a real context leak | — | [cache-layers-and-a-real-leak](actual-outcomes/cache-layers-and-a-real-leak.md) |

The article SDC components (`article-card`, `prose`, `article-header`) have no doc yet. Their record is the commit history on `feat/article-card` and `feat/article-page`.

---

## Find by topic

| Topic | Where to look |
|---|---|
| Acquia Cloud and deployment | Day 3 · [deployment guide](actual-outcomes/acquia-deployment-guide.md) · [lessons-learned §1](actual-outcomes/lessons-learned.md) |
| Site Studio | Day 4 · [day4-5](actual-outcomes/day4-5-site-studio-nutrition.md) · [day9](actual-outcomes/day9-sdc.md) (why it owns the full display) |
| Custom module and external API | Days 2 and 5 · [day4-5](actual-outcomes/day4-5-site-studio-nutrition.md) |
| Hooks and preprocess | Day 6 · [day6](actual-outcomes/day6-hooks-preprocess.md) |
| Views | Days 1 and 7 · [day7](actual-outcomes/day7-rest-export.md) · [day7b](actual-outcomes/day7b-advanced-views.md) · Phase 1 [Views output plan](plans/plan-a11y-views-output.md) |
| Twig and components (SDC) | Days 8 and 9 · [day8](actual-outcomes/day8-twig-templates.md) · [day9](actual-outcomes/day9-sdc.md) · Phase 2 article components (commits only) |
| Contrib: Twig Tweak | Phase 2 · [twig-tweak-critique](actual-outcomes/twig-tweak-critique.md) — why it was never needed, and the deprecation that settles it |
| Escaping and security | Phase 2 · [autoescape-and-the-trust-boundary](actual-outcomes/autoescape-and-the-trust-boundary.md) — `MarkupInterface` as the one boundary, and the Views rewrite side of it |
| Render pipeline | Phase 2 · [render-arrays-compared](actual-outcomes/render-arrays-compared.md) — `#type` vs `#theme`, `#markup` vs `#plain_text`, and why `#lazy_builder` is a caching tool |
| Caching | Phase 2 · [cache-layers-and-a-real-leak](actual-outcomes/cache-layers-and-a-real-leak.md) — the three layers, and the missing cache context that page headers could not show |
| Search and facets | Day 10 · [day10](actual-outcomes/day10-solr-search.md) · Phase 1 [facets plan](plans/plan-a11y-range-slider-and-facets.md) |
| Accessibility | Phase 1 · [audit](actual-outcomes/accessibility-audit.md) · [screen-reader log](actual-outcomes/screen-reader-test-log.md) |
| CI | Phase 1 [CI gates plan](plans/plan-ci-a11y-gates.md) · [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) |
| Content modelling | Day 1 · Phase 2 [articles content model](plans/plan-articles-content-model.md) |
| Not yet built | [future-scope/](future-scope/README.md) |
| Planned, no outcome note | Days 1, 2, 11, 12, 13 |
