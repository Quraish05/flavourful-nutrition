# Accessibility Audit — Flavourful (WCAG 2.2 Level AA)

> Site: Flavourful Nutrition (Drupal 11.4, custom `flavourful` theme, Site Studio 8.2) · Audited: 8–15 September 2026 · Last updated: 2026-09-15
>
> **Status: remediation complete except one component API change.** 24 defects found and fixed across eight pull requests; **1 remains open** (O-4, the `button` atom), scoped below. Conformance target is **WCAG 2.2 Level AA**.
>
> This is the outcome document for [Phase 1 of the study plan](../objectives/). It is written in the shape a client procurement team expects — finding, criterion, severity, location, remediation, retest — so it works as a work sample as well as a record. Working notes for each slice live in [`docs/plans/`](../plans/); they are the transient middle state and are not summarised here.

---

## At a glance

| | Count |
|---|---|
| Defects found | **25** |
| **Fixed and retested** | **24** |
| Open, scoped | 1 |
| Criteria assessed and found **not** to apply | 5 |
| Existing behaviour verified correct (no work needed) | 12 |
| Pull requests merged | 8 |
| Net change across the eight remediation PRs | **+1,173 / −207 lines** (includes working notes) |

**Severity of what was fixed:** **20 Level A, 3 Level AA**, and one (O-5) that maps to no criterion at all but was worth fixing anyway — see the note under *Open*. Findings 3 and 16 also implicate 2.4.6 (AA). Level A is the most severe tier — a Level A failure is generally understood as content being unusable for the affected user rather than merely inconvenient. That almost everything here is Level A is the headline: these were not polish items.

**Where the defects lived:** **9 in configuration, 15 in theme code.** That split matters: more than a third of this site's accessibility defects were fixed without writing a line of code, by changing settings an administrator can reach. That is a very different conversation with a client than "the markup is wrong" — and it means a share of the risk is reintroducible by anyone with an admin login, which is a governance problem rather than an engineering one.

---

## What this audit covers

**Pages tested:** `/`, `/recipes`, `/recipes/{cuisine}`, `/recipe-search`, `/glossary`, `/glossary/{letter}`, `/chefs`, `/chefs/{id}/recipes`, `/reports/by-cuisine`, recipe nodes, chef nodes, and the 404 page.

**Methods, in order of how much each found:**

1. **Reading rendered output**, not source templates — repeatedly decisive, and the single most valuable habit established here. Four separate findings were invisible in the template and only appeared in the served page.
2. **Reading the post-JavaScript DOM** via the Chrome DevTools Protocol, for anything a script rewrites.
3. **Reading the accessibility tree** for names, roles and states.
4. **Keyboard-only traversal.**
5. **VoiceOver + Safari** — logged separately in [`screen-reader-test-log.md`](screen-reader-test-log.md).
6. **Reading configuration** (`config/sync/*.yml`) for defects that never reach markup at all.

**Not covered, deliberately:** the Site Studio (Cohesion) component library, which owns the full recipe display; NVDA and JAWS, pending a Windows VM; and automated scanning, which is Week 5–6 work and is discussed below.

---

## Fixed — the original twenty

The twenty found in the first pass. Four more were found and fixed afterwards and are listed under *Closed since the first draft* below, which is where the running total of 24 comes from.

Severity uses WCAG level. "Impact" is our judgement of user consequence, which is what a client actually acts on.

### Content that was announced to nobody

| # | Finding | Criterion | Impact | Location | Remediation | Retest |
|---|---|---|---|---|---|---|
| 1 | Recipe difficulty ("Easy"/"Hard") sat **inside** an `aria-hidden="true"` link, so it reached no screen reader. The `aria-hidden` was itself correct — it suppressed a duplicate link — but it took the badge with it | 1.3.1 (A) | High — a primary filtering attribute, invisible to AT on every card | `recipe-card.twig` | Badge moved out of the hidden container into the card body, with a visually-hidden `Difficulty:` prefix so "Medium" is self-sufficient | Pass — badge present in the tree, named "Difficulty: Medium" |
| 2 | `/chefs` shipped **ten links with no accessible name**. The title field had *Link to the Content* **and** a URL rewrite, emitting nested `<a><a>…</a></a>` | 2.4.4 (A), 4.1.2 (A) | High — ten unnamed tab stops; the deliberate destination was the unnamed one | `views.view.chefs` | Unticked the formatter's link, kept the rewrite | Pass — 20 links/10 empty → **10 links/0 empty** |
| 3 | The `<h1>` on chef pages rendered its own markup as escaped text: `&lt;span class="field…"&gt;Joe H&lt;/span&gt;` | 1.3.1 (A), 2.4.6 (AA) | High — the page's only heading was a blob of HTML, visibly and audibly | `page-title.html.twig` | `|trim` moved inside the emptiness test; it was casting a safe `Markup` object to a plain string and defeating autoescape | Pass — `<h1>Joe H</h1>` |

### Controls that announced the wrong state

| # | Finding | Criterion | Impact | Location | Remediation | Retest |
|---|---|---|---|---|---|---|
| 4 | The category bar marked its "All" link `aria-current="page"` **on every page of the site** — front page, recipe nodes, `/recipe-search`, `/glossary`. It was only true on `/recipes` | 4.1.2 (A) | High — a control asserting a false location everywhere | `PageHooks::addCategoryBar()` | Nothing is current unless the route is `view.recipes.page_1` | Pass — present on `/recipes`, absent on seven other routes |
| 5 | The facet reset control rendered as a **checkbox, checked, when there was nothing to clear** — announcing *"Clear all filters (20), checkbox, checked"* on an unfiltered page | 4.1.2 (A) | High — asserts a filter is applied that is not | `facets.facet.*` | `hide_reset_when_no_selection` | Pass — control absent until a facet is selected |
| 6 | The pager's current page was marked by a CSS class only — no programmatic state | 4.1.2 (A) | Medium | `views-mini-pager.html.twig` | `aria-current="page"`, plus the total re-exposed so it reads *"Page 2 of 3"* | Pass |
| 7 | The glossary's active letter was marked by a CSS class only | 4.1.2 (A) | Medium | `views-view-summary.html.twig` | `aria-current="page"` — placed in the **generic** template, since "this row is current" is true of any summary | Pass |
| 8 | The facets' show-more toggle is built in JavaScript as `<a href="#">` with no `aria-expanded` | 4.1.2 (A) | Medium | contrib `facets/soft-limit` | 40-line theme behaviour mirroring the module's `open` class into `aria-expanded` | Pass — cycles `false`/`true` on activation |

### Structure that did not exist

| # | Finding | Criterion | Impact | Location | Remediation | Retest |
|---|---|---|---|---|---|---|
| 9 | Both `<aside>` landmarks were unnamed — the landmarks list showed two indistinguishable `complementary` entries | 1.3.1 (A) | Medium | `PageHooks` | Named per route: `/recipe-search` → "Search filters", a recipe → "More about this recipe" | Pass |
| 10 | The status-messages region carried `role="contentinfo"`, producing **two** `contentinfo` landmarks | 1.3.1 (A) | Medium | `status-messages.html.twig` | → `role="region"` | Pass — exactly one `contentinfo` |
| 11 | The local-actions block was an unnamed landmark | 1.3.1 (A) | Low | `block--local-actions-block.html.twig` | `aria-label="Actions"` | Pass |
| 12 | `/chefs` rows had **no headings at all** — ten chefs, nothing to navigate by, while `/recipes` gave every card an `<h2>` | 2.4.6 (AA) | Medium | `views.view.chefs` | Title field → `h2` | Pass — 10 chef headings |
| 13 | The glossary A–Z was a run of `<span>`s with a literal ` \| ` separator — **no list semantics whatever**. It only looked like a list because each span fell on its own line | 1.3.1 (A) | Medium | `views.view.glossary` | Summary style Unformatted → List | Pass — real `<ul>/<li>` |
| 14 | The A–Z row sat in no landmark and had no accessible name | 1.3.1 (A) | Medium | scoped summary template | `<nav aria-label="Browse recipes by first letter">` | Pass |
| 15 | Eleven letter links announced as bare **"A"**, **"B"**, **"S"** | 2.4.4 (A) | Medium | scoped summary template | Visually-hidden `Recipes beginning with` inside each link | Pass — "Recipes beginning with S" |
| 16 | The recipe card hardcoded `<h3>` while rendering in two contexts, producing h1 → h3 on listings and a stray `<h3>` on recipe pages | 1.3.1 (A), 2.4.6 (AA) | Medium | `recipe-card.component.yml` | `heading_level` as a required prop; `0` drops the heading where the page `h1` already names the recipe | Pass — no skips on `/recipes` |
| 17 | The mini pager's heading was `h4`, so `/chefs` and `/reports/by-cuisine` read **h1 → h4** — a two-level skip in a heading that is visually hidden, and therefore invisible to everyone except the people it misleads | 1.3.1 (A) | Medium | `views.view.chefs`, `views.view.recipes_per_cuisine` | *Pagination heading level* → `h2` | Pass — both outlines clean |

### Things with no name

| # | Finding | Criterion | Impact | Location | Remediation | Retest |
|---|---|---|---|---|---|---|
| 18 | The `/reports/by-cuisine` table had no `<caption>` | 1.3.1 (A) | Medium | `views.view.recipes_per_cuisine` | Caption: *"Recipes grouped by cuisine"* | Pass |
| 19 | The glossary results table had no `<caption>` | 1.3.1 (A) | Medium | `views.view.glossary` | Caption: *"Recipes, sortable by title and last update"* | Pass |
| 20 | **Every letter page shared one title** — `<title>Glossary</title>` and `<h1>Glossary</h1>` for all eleven letters, because the contextual filter did not override the title | **2.4.2 (A)** | High — eleven distinct pages indistinguishable by title, in history, in tabs and to AT | `views.view.glossary` | `Override title` = `Recipes beginning with {{ arguments.title }}` | Pass — "Recipes beginning with S" in both `<title>` and `<h1>` |

**Finding 20 was not on the original checklist.** It was the strongest defect in its section and it was found only by loading the page. That is the argument for method 1 above, stated as concretely as it can be.

---

## Open — the one

| # | Finding | Criterion | Impact | Location | Proposed fix | Effort |
|---|---|---|---|---|---|---|
| O-4 | The `button` atom is unconditionally an `<a href>`, so anything using it for an **action** exposes the wrong role; with no `url` it renders `href="#"`, a link to the current page | 4.1.2 (A) | Medium — only where used as an action; today's usages are all navigational, so nothing currently announces the wrong role | `components/button/button.twig` | An `as`/`element` prop switching `<a>`/`<button>`, `url` required only for the link variant | Small, but it is a component API change |

Left last deliberately: it is the only item here that changes a contract other code depends on, and it prevents a future defect rather than fixing a present one. Its real value is in the Articles build, where actions are likely.

### Closed since the first draft

| # | Finding | Criterion | Fixed in | Retest |
|---|---|---|---|---|
| O-1 | The mini pager was still `h4` on `views.view.recipes` and `views.view.recipe_search`, so `/recipes` read h2 → h4 and **`/recipe-search` read h1 → h4** — the worst outline on the site | 1.3.1 (A) | [#30](https://github.com/Quraish05/flavourful-nutrition/pull/30) | Sweep: **9 routes, 0 flagged** |
| O-2 | Form control borders measured **1.72:1** against the page, below 1.4.11's 3:1 for a UI component boundary | 1.4.11 (AA) | [#31](https://github.com/Quraish05/flavourful-nutrition/pull/31) | New `$color-rule-control` at **3.86 / 4.11 / 3.64** |
| O-3 | `--fr-bone-faint` used as readable text in four places at **3.41–3.63:1**, below 4.5:1 | 1.4.3 (AA) | [#31](https://github.com/Quraish05/flavourful-nutrition/pull/31) | All four now **7.24:1** or better |
| O-5 | The A–Z row's stylesheet never loaded on the only page with an A–Z row | — (see note) | [#31](https://github.com/Quraish05/flavourful-nutrition/pull/31) | `recipes.css` now served on `/glossary` |

Contrast across the whole palette is now **19 pairs, 0 failing** (was 5 failing of 17), each measured against the ground it is actually painted on.

**O-3's shape is worth keeping.** The theme's own token file already carried the rule — *"BELOW AA for text. Decorative glyphs … and disabled controls only … anything a user has to read takes `$color-bone-muted`"* — and four call sites ignored it. The standard was not missing; it was written down and not followed. The best example was `.cook-mode__steps .method__step`, whose comment says the inactive steps are *"dimmed rather than hidden, so the user keeps the context of where they are in the method"* — an intent that requires them to stay readable.

**O-2 turned on scoping rather than on the number.** `$color-rule-strong` also draws table header rules and section hairlines, which are decorative and outside 1.4.11. A new token was added rather than lightening that one, which would have restyled every table on the site for no accessibility gain.

**O-5 never mapped to a WCAG failure, and the reason is worth keeping.** 1.4.1 Use of Color requires that colour not be the *sole* means of conveying information. There was **no** visual means at all, so 1.4.1 was not triggered. What it was instead is a **parity inversion**: after `aria-current` was added, screen-reader users could tell which letter was current and **sighted users could not.** Remediation had improved the page in one direction and left it worse in the other. That is the reverse of the usual defect and the kind of thing only a human review surfaces — no scanner has an opinion about it.

**Also fixed, though never a WCAG failure:** every page title ended `| Drush Site-Install`, the installer default, and the header rendered it too. The drift ran the *other* way — `config/sync/system.site.yml` already held `The Cookbook` and the database had never caught up — so `drush config:set` fixed the header, all nine titles and a config drift item without touching git. Titles were always unique and descriptive, so **2.4.2 passed either way**; it simply read as unfinished.

**Assessed and deliberately kept:** the glossary's Author column renders "Anonymous (not verified)" on every row, because all 32 nodes are authored by uid 0 or 1. Raised as a content-quality observation, **not** an accessibility finding — no criterion applies — and the site owner has chosen to keep the column, since it will carry real information once the content has real authors. And the site name is still the installer default, `Drush Site-Install`, which appears in every `<title>`; titles are unique and descriptive, so 2.4.2 passes, but it reads as unfinished.

---

## Criteria assessed and found not to apply

An audit that only lists failures is a bug list. Recording what was checked and **cleared** is what makes it a conformance statement — and each of these took real work to establish.

| Criterion | Where | Why it does not apply |
|---|---|---|
| **4.1.3 Status Messages (AA)** | `/recipe-search` | Applying a facet is a **full page reload**, so the new state arrives with a new page. No live region is owed. Citing 4.1.3 here would have been a false finding |
| **4.1.3 Status Messages (AA)** | `/glossary` | Did apply — the view shipped with AJAX on, replacing the table silently. **Resolved by removing the mechanism** (AJAX off) rather than by adding a live region, which makes it inapplicable rather than unaddressed |
| **1.4.1 Use of Color (A)** | glossary A–Z | Colour is not the sole indicator because there is no visual indicator at all. See O-5 |
| **2.5.7 Dragging / 2.5.8 Target Size (AA)** | range slider | **There is no slider.** Two jQuery UI slider packages were required by `composer.json`, but no facet used a slider widget, the page served zero slider assets, and the vendor library was never downloaded. Nothing to audit |
| **2.4.3 Focus Order (A)** | after facet apply | Focus does return to `<body>`, but via an ordinary page load rather than a dropped focus. Revisit only if AJAX is enabled |

The slider entry is the most useful one. It was the workstream's headline finding on paper, and it did not exist. Four independent checks established that before any replacement component was designed — which is the whole return on checking.

---

## Verified correct — no work needed

Recorded because an audit that never says "this is right" is not credible.

- `.visually-hidden` is implemented correctly (clip-rect, 1px, `white-space: nowrap`)
- `@media (prefers-reduced-motion: reduce)` is handled globally
- The skip link is present, focusable and moves focus to `#main-content`
- `scope="col"` is set **unconditionally** by the theme's table template
- `aria-sort` is set by core on the sorted column — it reports the current direction, while the visual arrow reports the *next* one; two different values, both correct
- Facets replaces its server-rendered links with genuine `<input type="checkbox">` and bound `<label for>`; the superseded link is `display: none` and adds no tab stop
- Soft-limited facet items are `display: none` and verified unfocusable — the collapsed state adds no phantom tab stops
- The exposed filter form renders `<fieldset><legend>Filter recipes</legend>` — from core, with no theme override
- `recipe-card__media` uses `aria-hidden="true"` **together with** `tabindex="-1"`, which is the correct mitigation for a duplicate link. Many implementations get exactly this wrong by hiding a still-focusable element
- Pager links have real names ("Page 3") and the pagination nav has an accessible name
- `lang="en"` is present on every page tested
- Page titles are unique and descriptive on all nine routes tested — **2.4.2 passes** outside the glossary defect fixed above

**Two corrections were made to earlier claims in this workstream**, both recorded rather than quietly dropped: `aria-sort` was reported missing when core sets it (the search looked in `views.theme.inc`, but those hooks had moved to `src/Hook/ViewsThemeHooks.php`), and a sort indicator was reported missing when core's own stylesheet supplies it. **Searching the wrong file reads exactly like absence.**

---

## Three findings that expired

The first eight findings in this workstream came from code review before the Cellar redesign landed. Re-measured against the shipped theme, three no longer exist:

| Original finding | Status now |
|---|---|
| "Two of four difficulty badges fail contrast" — white text on filled colour, 2.12:1 and 4.11:1 | **Stale.** The redesign changed both the tokens and the treatment: the badge is now a ruled chip with the difficulty colour as *text* on the dark ground. Re-measured: **6.08, 7.65, 4.96, 4.87 — all four pass** |
| "No visible focus indicator is defined anywhere" | **Stale.** `:focus-visible` now exists for links, inputs, selects and textareas: `outline: 2px solid #c9a24a`, measuring 7.65:1 against the page — well clear of 1.4.11's 3:1 |
| "Links in body copy are distinguished by colour alone" | **Stale.** `p a, li a, blockquote a` are underlined by default, with the underline colour at 3.85:1 |

**This is worth more than the three findings were.** A finding measured against a codebase that has since changed is not a finding, and shipping one in a client document is the fastest way to lose the room. Everything in the Fixed and Open tables above was re-measured on 15 September against the current build.

---

## What automation would have caught

Of the 25 defects in this document, an automated scanner (axe-core, pa11y) would reliably have caught:

- **Finding 2** — empty links on `/chefs`. Unambiguous, and the clearest single argument for adding a scanner.
- **Finding 10** — duplicate `contentinfo` landmark.
- Probably **findings 9 and 11** — unnamed landmarks, depending on ruleset.
- **O-2 and O-3** — contrast, which is exactly what scanners are best at.

That is roughly **six of twenty-five**. The other nineteen required reading configuration, reading the post-JavaScript DOM, or judging whether a name was *true* — and no scanner checks truth. `aria-current="page"` on every page of the site (finding 4) is valid ARIA, correctly formed, and completely wrong; a scanner sees a well-formed attribute.

**That sentence is the argument for paid audit work,** and it is more persuasive because the exceptions are named rather than waved away.

Two of the twenty fixed defects were **regressions introduced during this project** — the category bar (finding 4) and the page title (finding 3). Both would have been caught cheaply by a scanner in CI and neither was caught by review. That is the case for the Week 5–6 gate, made against our own work rather than someone else's.

---

## What the CI gate does and does not cover

Added 16 September. Stated in this much detail because the point of a conformance document is that a reader can tell what was *checked* from what was *assumed*.

**Running on every pull request and every push to `master`:**

| Gate | Catches | Needs a site? |
|---|---|---|
| `scripts/contrast.py` | any design token falling below 1.4.3 or 1.4.11 | no — pure arithmetic over the tokens |
| CSS artefact freshness | an SCSS edit that was never compiled, so the page never changed | no |
| `phpcs` (Drupal, DrupalPractice) | coding standards across the custom module and theme | no |
| `phpstan` level 2 + baseline | new static-analysis errors; the three pre-existing ones are frozen | no |

**Not running, and this is the honest part:**

- **`scripts/a11y-sweep.py`** — heading outlines, landmark names, unnamed links, nested anchors, table captions. It needs the site up. Bringing one up in CI means `drush site-install --existing-config`, and this site's configuration declares **eleven Cohesion (Site Studio) modules**, which require Acquia API credentials, plus `search_api_solr`, which requires a Solr service.
- **axe-core / pa11y-ci** — the same running site, and then **content**. Against an empty install `/chefs` has no rows and `/glossary` no letters, so a scan would pass trivially and prove nothing. Content fixtures are the real cost here, not the scanner.

So the sweep is run by hand against DDEV and its result recorded in this document. **That is a weaker guarantee than a gate and is recorded as such.**

**And the permanent gap, which no amount of CI closes.** These gates check structure; they cannot check *truth*. Finding 4 — `aria-current="page"` asserting the wrong location on every page of the site — is valid, well-formed ARIA that every scanner would accept. So is a landmark named "Search filters" on a page with no filters. Roughly **six of the twenty-five** defects in this document were machine-detectable; the rest needed configuration read, post-JavaScript DOM read, or a judgement about whether a name was true.

### Two things the gate fixed on the way in

Worth recording because they are the reason to distrust a green build:

- **The test step could not fail.** It ran `vendor/bin/phpunit … || true`, `phpunit` was never installed, and every run logged *"No such file or directory"* and reported green. There are no tests in this project; the step has been removed rather than faked, and the workflow says why. A step that cannot fail is worse than no step, because it reads as coverage.
- **Nothing had ever run on push.** The trigger was `branches: [main]` and the default branch is `master`.

---

## Remediation record

| PR | Date | Scope | Change |
|---|---|---|---|
| [#23](https://github.com/Quraish05/flavourful-nutrition/pull/23) | 8 Sep | Glossary: enable, scope, title, caption, list semantics, AJAX off | +89 / −18 |
| [#24](https://github.com/Quraish05/flavourful-nutrition/pull/24) | 9 Sep | Landmark names, singular `contentinfo` | +222 / −9 |
| [#25](https://github.com/Quraish05/flavourful-nutrition/pull/25) | 11 Sep | Facet reset state, soft-limit `aria-expanded`, dead dependency removal | +254 / −156 |
| [#26](https://github.com/Quraish05/flavourful-nutrition/pull/26) | 15 Sep | `aria-current` in three places, page-title escaping | +59 / −10 |
| [#27](https://github.com/Quraish05/flavourful-nutrition/pull/27) | 15 Sep | Chef listing links and headings, cuisine caption, pager levels | +252 / −4 |
| [#28](https://github.com/Quraish05/flavourful-nutrition/pull/28) | 15 Sep | Glossary A–Z names and landmark | +249 / −0 |
| [#30](https://github.com/Quraish05/flavourful-nutrition/pull/30) | 15 Sep | Pager heading levels on the recipes and search views (O-1) | +2 / −2 |
| [#31](https://github.com/Quraish05/flavourful-nutrition/pull/31) | 15 Sep | A–Z stylesheet delivery (O-5); four sub-AA colours and a control-border token (O-2, O-3) | +46 / −8 |

**Deployment note for whoever ships this:** PRs #23, #25, #27 and #30 are **configuration**, and `acli push:artifact` does not import configuration. They require a config import on deploy. #24, #26, #28 and #31 are theme code and do not.

---

## How to re-run this audit

The sweep behind the Fixed and Open tables is reproducible. It checks heading outlines and skips, landmark inventory and names, empty links, nested anchors, `aria-current` counts, table captions and `scope`, `lang`, and page titles across nine routes:

```bash
python3 scripts/a11y-sweep.py     # 9 routes; exits non-zero if anything is flagged
python3 scripts/contrast.py       # 17 token pairs against their painted backgrounds
```

As of 2026-09-15 the sweep flags **two** routes, both the same open finding (O-1), and clears the other seven. The contrast script reports **five** failing pairs, which are O-2 and O-3.

Both scripts encode their own false positives rather than leaving them for the next reader to rediscover. The sweep excludes `<a>` without `href` (the skip-link target is not a link), `<a aria-hidden="true">` (a deliberately hidden duplicate is not an *unnamed* link — it is not exposed at all), and `<header>` inside `<main>` (not a `banner` under HTML-AAM). Each of those was a false failure the script produced before the exclusion was added.

Contrast figures were computed from the tokens in `scss/abstracts/_variables.scss` using the WCAG relative-luminance formula, against the actual painted background for each use (page ground `#17140f`, raised `#1e1a14`, field `#0e0c09`) rather than against an assumed white.

**One rule this audit learned the hard way, and which is now written into the plan template:** *anything produced by the HTML parser or by JavaScript cannot be verified with `curl` and a regular expression.* The `/chefs` check (finding 2) initially reported "links: 12 | empty: 0" on a page carrying ten nameless links, because the page **source** contains one anchor per row — the parser creates the second. A second check reported `0` matches because it grepped for a class Views puts on the parent element. Both were fixed, and both are logged, because a check that silently passes is a worse defect than the one it missed.

---

## Limits of this audit

Stated plainly, because a conformance claim is only as good as its scope statement.

- **This is not a full VPAT.** It covers the criteria that the tested pages exercise, not all 56 Level A and AA criteria.
- **Screen-reader coverage is VoiceOver + Safari only.** NVDA and JAWS — which the WebAIM survey shows most real users run — are untested pending a Windows VM.
- **The Site Studio component library is untested**, and it owns the full recipe display. Any finding there is likely to be *authorable* — reintroducible by an editor — which makes it a governance problem rather than a code one.
- **No user testing.** Everything here is expert evaluation. Conformance is not the same as usability, and no amount of desk work substitutes for a disabled user's judgement.
- **The accessible-name computations for the newest fix (finding 15) are verified from source, not from the browser's computed name.** The distinction is recorded in the plan file rather than papered over.
- **Only part of this audit can be automated, and the reason is the stack.** See *What the CI gate does and does not cover* below. Two of the defects above were our own regressions, which is the measure of that risk.
- **The sweep checks structure, not meaning.** It can tell you a landmark has a name; it cannot tell you the name is *true*. Finding 4 — `aria-current="page"` asserting the wrong location site-wide — is valid, well-formed ARIA that no structural check would question. That gap is permanent and is the reason this document exists alongside the scripts.

---

## Related

- [`screen-reader-test-log.md`](screen-reader-test-log.md) — the six task runs, what was announced against what is on screen
- [`lessons-learned.md`](lessons-learned.md) — cross-cutting gotchas from the whole project
- [`docs/plans/`](../plans/) — per-slice working notes and deviation logs, including every check that turned out to be wrong
