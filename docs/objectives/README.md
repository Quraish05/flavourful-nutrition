# Drupal / Acquia Vetting Prep

This folder and its companion [`../actual-outcomes/`](../actual-outcomes/) hold everything prepared to get ready for a **vetting call and project engagement** on an enterprise Drupal-on-Acquia role. They use the `foodrecipes-drupal` project as the practice ground.

## Objectives

Prepare — over ~5 days at 8 hrs/day — to confidently pass a technical vetting call for a mid/senior Drupal developer role, coming from a background of ~5 years' web development that is **strong on frontend** with **some Drupal theming and Twig** experience, and gaps in backend site-building, Acquia Cloud/DevOps, and Site Studio.

The plan is built around one running practice project, **Flavorful** (a recipe platform), so a single build exercises every skill the role tests. The goal is *competence plus candour*: real hands-on wins to talk about, and an honest story about where the strengths and growth areas are.

### The role, in brief

Enterprise Drupal solutions on **Acquia Cloud**: building content types, fields, views, taxonomies and custom modules; developing themes, Twig templates, and **Acquia Site Studio** components; working from a front-end developer's assets; using Composer, Drush, configuration management and environment-based deployment; supporting external-API integrations and user-identity/profile mapping; and upholding performance, security, SEO and accessibility (WCAG) — all within a cross-functional, code-reviewed, CI/CD workflow.

## How the two folders differ

- **`objectives/` (this folder)** — the **preparation guideline**: the objectives, the high-level 5-day plan, and the detailed day-by-day hands-on labs. Start here.
- **`../actual-outcomes/`** — **what actually happened** when the plan met reality: the real Acquia deployment runbook and a **lessons-learned** log of the mistakes hit during the build and how they were solved.

## Index

### Guideline & labs (this folder)

- [`5-day-plan.md`](5-day-plan.md) — the full 5-day study plan: positioning, priority map, day-by-day topics, likely vetting questions, the honesty talk-track, and a quick-reference cheat sheet.
- [`advanced-plan-days6-9.md`](advanced-plan-days6-9.md) — the extension curriculum overview for deeper Drupal fluency (Days 6–9); the day-by-day labs below implement it.

**Core (Days 1–5):**

- [`day1-site-building.md`](day1-site-building.md) — content types, fields, taxonomy, media, roles, and the three core Views (click-by-click).
- [`day2-module-and-twig.md`](day2-module-and-twig.md) — the `flavorful_nutrition` custom module and `flavorful_theme` Twig subtheme, with full file contents.
- [`day3-acquia-cloud-devops.md`](day3-acquia-cloud-devops.md) — Composer, Drush, config management, GitHub CI, and a real Acquia Cloud deployment (grounded in the runbook below).
- [`day4-site-studio.md`](day4-site-studio.md) — building a Site Studio Recipe Card component, styles, and layout (with a no-licence fallback).
- [`day5-integrations-identity-interview.md`](day5-integrations-identity-interview.md) — wiring a real nutrition API, chef-as-user identity/profile mapping, the quality bar, and interview-day drills.

**Advanced (Days 6–9) — deeper Drupal fluency:**

- [`day6-hooks-preprocess.md`](day6-hooks-preprocess.md) — the hook system, form/entity hooks, preprocess deep dive, theme suggestions, and the modern `#[Hook]` OOP style.
- [`day7-advanced-views.md`](day7-advanced-views.md) — aggregation, EVA, attachments, grouped exposed filters + sorts, and a JSON feed.
- [`day8-twig-best-practices.md`](day8-twig-best-practices.md) — inheritance (`extends`/`block`), `include`, `embed`, macros, and the Drupal-specific Twig toolkit.
- [`day9-atomic-sdc.md`](day9-atomic-sdc.md) — atomic design with Single Directory Components: button/tag atoms, a recipe-card molecule (props + slots), and SDC vs Site Studio vs Paragraphs.

### Actual outcomes ([`../actual-outcomes/`](../actual-outcomes/))

- [`../actual-outcomes/acquia-deployment-guide.md`](../actual-outcomes/acquia-deployment-guide.md) — the original, real deployment runbook for this project (Drupal 11, DDEV, Acquia Cloud Next). Day 3 and the lessons-learned log are grounded in it.
- [`../actual-outcomes/lessons-learned.md`](../actual-outcomes/lessons-learned.md) — problem → root cause → fix log across deployment, config, Views, theming, modules, and Site Studio.
- [`../actual-outcomes/day4-5-build-outcomes.md`](../actual-outcomes/day4-5-build-outcomes.md) — objective→outcome map for the Day 4 Site Studio install and Day 5 nutrition-API build, with a deviation log for where reality diverged from the plan.
- [`../actual-outcomes/day6-build-outcomes.md`](../actual-outcomes/day6-build-outcomes.md) — objective→outcome map for the Day 6 hooks & preprocess build (form alter, presave, preprocess, theme suggestion), noting the British-spelling rename, the fields that had to be created, and the `#[Hook]` swap that didn't land. A 2026-07-13 update logs the rename-cascade fallout (boot-breaking stale container cache, a preprocess hook that never fired, the OOP swap finally wired up) and the `field_total_time` backfill.
- [`../actual-outcomes/day7-build-outcomes.md`](../actual-outcomes/day7-build-outcomes.md) — objective→outcome map for the Day 7 REST-export slice (`/api/recipes`): the empty-`field_total_time` backfill, why titles/cuisines arrived as escaped HTML links, and the per-display field override that cleans the JSON without touching the page displays.

## Practice project: Flavorful

A recipe platform modelled to touch every skill area:

| Skill area | Where it's built |
|---|---|
| Content types, fields, entity reference | Recipe + Chef, ingredient/chef references |
| Taxonomy | Cuisine / Course / Dietary / Ingredients vocabularies |
| Views | listing with exposed filters; related-by-cuisine block; chef's-recipes page |
| Media | recipe hero & step images |
| Custom module + Drupal APIs | `flavorful_nutrition` (service, controller, block plugin) |
| External API integration | Open Food Facts nutrition lookup (no key/signup) |
| Identity / profile mapping | Chef as User + OpenID Connect claim mapping |
| Theming + Twig | `node--recipe.html.twig` + preprocess |
| Site Studio | reusable Recipe Card component + listing layout |
| Performance / SEO / a11y | Pathauto, image styles, metatag, accessible listing |

## Environment notes (current as of the build)

- Drupal 11.4 is the stable release; Drupal 12 is due late 2026; Drupal 10 reaches end-of-life Dec 2026 — think in Drupal 10/11 terms.
- **BLT is end-of-life** (archived early 2025); Acquia points to Drupal Recommended Settings + Composer/CI automation.
- Site Studio is Acquia's low-code layer (formerly Cohesion); deploys need `cohesion:import` + `cohesion:rebuild` after config import.
