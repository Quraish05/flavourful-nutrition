# Plan — Glossary A–Z accessibility (Screen Reader Task 5)

> Branches: `fix/a11y-glossary-recipes-az` (phases 1–2) · `fix/a11y-glossary-az-nav` (phase 3) · Created: 2026-09-08 · Last updated: 2026-09-15
>
> **Status: in progress — 11 of 14 done, 2 dropped, step 6 deferred. Only step 14 (the Notion write-up) is still open.** Phases 1–2 shipped in **[PR #23](https://github.com/Quraish05/flavourful-nutrition/pull/23)** (config only); phase 3 in **PR #28** (theme only). Steps 10 and 11 dropped as unnecessary (deviations 1 and 6); step 6 deferred by the developer (deviation 5). **No a11y defect from this task remains open in code or config.**
>
> Execution plan for Task 5 of the Notion study doc *"Screen Reader Task Scripts — Six Runs on Flavourful"* (§5, *Find every recipe beginning with "S"*). Neither an objective nor an outcome — this is the transient middle state. It feeds an eventual outcome note under [`docs/actual-outcomes/`](../actual-outcomes/).

---

## Why this plan exists

Task 5 in the Notion doc **cannot be run at all**: core's `glossary` view ships disabled and nobody enabled it. `views.view.glossary:status` is `false` in both `config/sync` and the live database, and `view.glossary.page_1` raises `RouteNotFoundException` — `/glossary` is a 404. Every observation written in §5 is a prediction about a page that does not exist, and three of those predictions are wrong on top of that.

**Decided:** enable core's `glossary` view and scope it to recipes. It already has the glossary argument, the A–Z attachment and the sortable table wired up.

**Rejected — do not re-propose:** building a new A–Z display on `views.view.recipes`. Cleaner in principle, but this is a practice repo and the point of the exercise is the accessibility work, not the information architecture.

**Known side effect, accepted:** `page_1` carries `menu: {menu_name: main, title: Glossary}`, so enabling the view adds a **main-menu item on every page**. That changes the landmark/navigation inventory Task 6 depends on. Step 2 renames it; re-run Task 6's landmark list afterwards.

---

## What §5 got wrong (the reason for phases 2 and 3)

| # | §5 claims | Reality |
|---|---|---|
| 1 | "Load the glossary view" | `status: false`, no route. 404. |
| 2 | A–Z row renders via `views-view-summary.html.twig` | The **attachment** overrides the argument (`defaults.arguments: false`, `summary.format: unformatted_summary`), so `views-view-summary-unformatted.html.twig` runs. Third instance of the doc's own trap 1. |
| 3 | List is `<div class="item-list"><ul class="views-summary">` | Inline `<span>`s with a literal ` \| ` separator. **No list semantics at all** — a stronger 1.3.1 finding, and a different fix location. |
| 4 | 1.4.1 Use of Color, "if the active letter is colour-only" | No theme CSS targets `.views-summary` or a summary `.is-active`. The active letter is distinguished **nowhere**. 1.4.1 does not apply; 1.3.1 / 4.1.2 get stronger. |
| 5 | (not mentioned) | `use_ajax: true`. Sorting and paging AJAX-replace the table, so **4.1.3 does apply here** — the mirror of Task 1, where it correctly did not. |
| 6 | (not mentioned) | No content-type filter. 21 recipes + 11 chefs; `/glossary/s` would return `Seafood Paella`, `Spaghetti Carbonara` **and the chef `Sofía Herrera`**. The task's goal is unanswerable as configured. |
| 7 | (not mentioned) | `title_enable: false` → every letter page is `<title>Glossary</title>` / `<h1>Glossary</h1>`. **2.4.2 Page Titled**, Level A — identical to the chef-page defect already fixed in Task 4, and the strongest finding in the task. |

**Verified correct in §5 — credit these, do not "fix" them:** `scope="col"` is set on header cells; `<caption>` renders only under `{% if caption_needed %}` and none is configured; sort headers are links with `title="sort by X"`; the count `(3)` sits outside the link; letter links announce as bare "A"/"B"; no `aria-current`; no `aria-sort` anywhere. The sort **state** genuinely is not exposed — core computes the indicator as `$initial = ($order == 'asc') ? 'desc' : 'asc'`, so its visually-hidden text names the *next action*, not the current state.

**Retracted (deviation 1).** We initially recorded that no visual sort indicator renders, because the theme's `css/components/tablesort.css` styles `th.is-active img` while core emits `<span class="tablesort tablesort--asc">`. That theme rule *is* dead code, but the arrow renders anyway: `/core/misc/components/tablesort.module.css` is loaded on the page and supplies `.tablesort--asc` / `--desc` background SVGs. So the indicator is visible and step 11 is unnecessary.

What survives: the arrow and the visually-hidden text **both describe the next click**, not the current state — core sets `$initial` to the opposite direction on the active column. The current sort is conveyed only by an `is-active` class. That is why `aria-sort` (step 10) is still the right fix and still genuinely missing.

---

## Phase 1 — make the page exist

Nothing in phases 2–3 is observable until this phase is done; `/glossary` 404s until step 1.

| # | Step | Where | Status |
|---|---|---|---|
| 1 | Enable the view | `/admin/structure/views` → **Disabled** section at the bottom → **Glossary** → dropdown → **Enable** | **Done** |
| 2 | Rename the menu link to **Recipes A–Z** | View edit → **Page** display → `Menu: Normal: Glossary` → **Title** → Apply | **Done** |
| 3 | Add filter **Content: Content type = Recipe** | **Default** display (**For: All displays**) → *Filter criteria* → **Add** → search `Content type` → *Add and configure* → tick **Recipe** → Apply | **Done** |

Verify: `/glossary/s` returns 200 and lists exactly `Seafood Paella` and `Spaghetti Carbonara` — no chef.

**Verified 2026-09-08.** The A–Z row reads `A(1) B(1) C(4) G(4) K(1) L(1) M(3) P(2) R(1) S(2) V(1)` = **21**, exactly the published recipe count, so the content-type filter is doing its job and no chef appears. Captured markup, which confirms the phase-2/3 findings:

```html
<div class="view-content">
  <span class="views-summary views-summary-unformatted">
    <a href="/glossary/a">A</a>
      (1)
  </span>
  <span class="views-summary views-summary-unformatted">
   |   <a href="/glossary/b">B</a>
      (1)
  </span>
```

No `<ul>`, no `<li>`, no wrapping landmark, no `aria-current`; the ` | ` is literal text *inside* each span and the count sits outside the link. It renders one letter per line, so it **looks** like a list while being semantically a run of spans — the gap between appearance and structure is the finding.

## Phase 2 — accessibility fixes in config

All in the Views UI at `/admin/structure/views/view/glossary`. Watch the **For:** dropdown on every one.

| # | Step | Where | Criterion | Status |
|---|---|---|---|---|
| 4 | Tick **Override title**, set `Recipes beginning with {{ arguments.title }}` | **Default** display → *Advanced* → *Contextual filters* → **Content: Title** → section **"When the filter value IS in the URL or a default is provided"** (a direct checkbox there, **not** inside *More*) | **2.4.2** | **Done** |
| 5 | Set **Caption for the table** — e.g. `Recipes, sortable by title and last update` | *Format:* **Table** → **Settings** → field **"Caption for the table"**. Leave *Summary title* and *Table description* blank | 1.3.1 | **Done** |
| 6 | Remove the **Author** column | *Fields* → `Content: Authored by` → **Remove** | — | **Deferred** — developer's call at PR time; still reads "Anonymous (not verified)" on every row |
| 7 | Set **Use AJAX** to **No** | *Advanced* → *Other* → **Use AJAX: Yes** → No | **4.1.3 assessed → N/A** | **Done** |
| 8 | Change the summary style **Unformatted → List** | **Attachment** display → *Advanced* → *Contextual filters* → **Content: Title** → **"When the filter value is NOT in the URL"** → *Display a summary* → style **Unformatted** → **List** | 1.3.1 | **Done** |

Notes on the tricky ones:

- **Step 4** — the token must render `S`, not `s` (the argument sets `case: upper` but `path_case: lower`). If it comes out lowercase, log a deviation and try the `raw_arguments` variant or a title callback. This is the same one-change-fixes-both mechanism used for `views.view.chef_recipes`: a Views page display sets the route title, so `<title>` and `<h1>` are both covered.
- **Step 6** — all 32 nodes are authored by uid 0 or 1, so the column reads Anonymous/admin on every row. It is a table column carrying no information.
- **Step 7** — turning AJAX off is what makes 4.1.3 genuinely inapplicable, matching `/recipe-search`. Record *that reasoning*, not just the toggle. Keeping AJAX on means owning a live region and an announcement.
- **Step 8** — one dropdown buys real `<ul>/<li>` semantics from `views-view-summary.html.twig` and drops the literal ` | ` pipes. Same move as the Unformatted → HTML List change already made for `chef_recipes`. Afterwards confirm the pipes are gone and `<ul class="views-summary">` is present; also check the summary still lists every letter (the old `items_per_page: 25` sat close to 26).

## Phase 3 — theme

| # | Step | File | Criterion | Status |
|---|---|---|---|---|
| 9 | Scoped summary template: wrap in `<nav aria-label="Browse recipes by first letter">`, add visually-hidden `Recipes beginning with` to each link, keep `aria-current="page"` on the active letter | `docroot/themes/custom/flavourful/templates/views/views-view-summary--glossary--attachment-1.html.twig` | 1.3.1, **2.4.4**, 4.1.2 | **Done** — filename confirmed against `buildThemeFunctions()` before writing, not assumed (deviation 7) |
| 10 | ~~`preprocess_views_view_table` that sets `aria-sort`~~ | — | — | **Dropped** — core already sets it (deviation 6) |
| 11 | ~~Replace the dead `th.is-active img` rule with a real indicator~~ | `docroot/themes/custom/flavourful/css/components/tablesort.css` | — | **Dropped** — core already renders the arrow (deviation 1). Optional cleanup only: the two rules in that file match nothing |

Notes:

- **Step 9** — the suggestion cascade *does* exist for summaries: `DefaultSummary::render()` uses `'#theme' => $this->themeFunctions()` → `PluginBase::themeFunctions()` → `ViewExecutable::buildThemeFunctions()`. Verified in core, not assumed. Enable Twig debug and confirm the scoped template is the winning one before trusting it — that is trap 1 exactly. Note the filename follows step 8: it targets `views_view_summary`, so **step 8 must land first**.
- **Step 10** — the template alone cannot do this. The active column reaches it only as an `is-active` string inside `fields`, and the indicator's direction is the *next* action, not the current state. The preprocess must recompute from `$view->style_plugin->active` and `->order`.

## Phase 4 — verify, export, document

| # | Step | Status |
|---|---|---|
| 12 | Re-run §5's two console one-liners (letter names + `aria-current`; caption + `scope` + `aria-sort`), plus a heading/landmark pass on `/glossary` and `/glossary/s` | **Done** — results below |
| 13 | Selective config export — **`views.view.glossary.yml` only**, via a temp dir, then copy that one file across. A plain `config:export` sweeps in the five unrelated drift items | **Done for phase 2** — `views.view.glossary.yml` only, via a temp dir. **No re-run needed:** phase 3 is theme code and changed no config, so PR #28 needs no config import on deploy (deviation 8) |
| 14 | Notion §5 — correct the predictions **in place** (no prediction-vs-outcome split), add the 2.4.2 finding and the `use_ajax`/4.1.3 mirror, then add §7 log rows **including the pass rows** | Not started |

---

## Verification

```bash
# The route exists at all (this is what fails today)
ddev drush php:eval 'echo \Drupal::service("router.route_provider")->getRouteByName("view.glossary.page_1")->getPath(), "\n";'

# Right content, right count
ddev drush sqlq "SELECT type, title FROM node_field_data WHERE status=1 AND title LIKE 'S%' ORDER BY type, title;"
```

```javascript
// Letters: name, list semantics, and how "active" is conveyed
document.querySelectorAll('.views-summary a').forEach(a => {
  console.log(JSON.stringify(a.textContent.trim()), '| aria-current:', a.getAttribute('aria-current'), '| in li:', a.closest('li') !== null);
});

// Table: accessible name and sort state
const t = document.querySelector('table');
console.log('caption:', t.caption ? t.caption.textContent.trim() : 'NONE');
t.querySelectorAll('th').forEach(th => console.log('scope:', th.getAttribute('scope'), '| aria-sort:', th.getAttribute('aria-sort'), '|', th.textContent.trim()));
```

Expected after all phases: every letter link reads "Recipes beginning with S", the active one carries `aria-current="page"`, links sit in `<li>` inside a named `nav`, the table has a caption, and the sorted column carries `aria-sort` **and** a visible indicator.

### Step 12 results — run 2026-09-15, after phase 3

All four expectations met. Measured from the rendered source, which is the correct layer here: nothing on this page is created by the HTML parser or by JavaScript, unlike the `/chefs` nested-anchor defect in PR #27.

**`/glossary/s` — A–Z row**

| | Before (2026-09-08) | After |
|---|---|---|
| Wrapper | `<div class="item-list">`, no landmark | `<nav class="item-list" aria-label="Browse recipes by first letter">` |
| Link names | `A` `B` `C` `G` `K` `L` `M` `P` `R` `S` `V` — eleven bare letters | `Recipes beginning with A` … `Recipes beginning with V` |
| `aria-current` | 1 (added in PR #26) | 1, unchanged — the regression guard |
| Count `(2)` | outside the link | outside the link, deliberately |

`/glossary` with no letter renders the same `nav` and `aria-current: 0`, which is correct — no letter is current there.

**`/glossary/s` — table**

```
caption: Recipes, sortable by title and last update
th: id="view-title-table-column" aria-sort="ascending" class="… is-active" scope="col"
th: id="view-name-table-column"  class="views-field views-field-name"     scope="col"
th: id="view-changed-table-column" class="views-field views-field-changed" scope="col"
```

Caption present (step 5), `scope="col"` on all three (core, credit not work), `aria-sort="ascending"` on the sorted column (core, deviation 6).

**Headings:** `h2 Main navigation` · `h2 Breadcrumb` · `h1 Recipes beginning with S`. The 2.4.2 fix from step 4 holds. The two visually-hidden `h2`s preceding the `h1` are the site-wide landmark-heading pattern settled in Task 6, not a defect introduced here.

**Not verified by desk check:** the *computed* accessible name. The template emits `<span class="visually-hidden">Recipes beginning with</span>` followed by whitespace and the letter, so the name depends on the browser collapsing that whitespace into a separator. Source says it should read "Recipes beginning with S"; confirm in DevTools → Accessibility → **Name**, and in VoiceOver's `VO+U` → Links. Marked unverified rather than claimed.

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | 11 | No visual sort indicator renders, because the theme's `tablesort.css` targets `th.is-active img` and core emits a `<span>` | The arrow **does** render — `/core/misc/components/tablesort.module.css` is loaded and supplies `.tablesort--asc` / `--desc` background SVGs. The theme rule is dead but harmless. Confirmed by the visible `TITLE ▲` in the browser | Dropped step 11, retracted the finding above. Step 10 (`aria-sort`) unaffected — the arrow describes the *next* click, not the current state |
| 2 | 1–3 | `/glossary/s` shows two recipes and no chef | Confirmed. Letter counts total 21, matching the published recipe count exactly | None; proceeded to phase 2 |
| 3 | 4 | The title token might render lowercase `s` (the argument sets `case: upper` but `path_case: lower`), needing `raw_arguments` or a title callback | Renders correctly as **"Recipes beginning with S"** in both `<title>` and `<h1>` | None. The anticipated risk did not materialise |
| 4 | 1–8, 13 | Steps 1–8 were done and the work was ready to commit | The config changes existed **only in the database** — never exported. Discovered at PR time: the working tree was clean and `config/sync/views.view.glossary.yml` still read `status: false`. Meanwhile the Cellar redesign had merged, putting `origin/master` 36 commits ahead and the old branch fully merged | Branched fresh off the updated `master`, exported `views.view.glossary` selectively (the drift list had grown to 12 items), and shipped config-only as PR #23 |
| 5 | 6 | Remove the Author column before the PR | Developer chose to ship without it | Step 6 marked **Deferred**; recorded as a known gap in the PR body |
| 6 | 10 | No `aria-sort` anywhere, so a theme preprocess is needed to expose the current sort | **Core already sets it.** `ViewsThemeHooks.php:742` — `if ($active == $field) { $variables['header'][$field]['attributes']['aria-sort'] = ($order == 'asc') ? 'ascending' : 'descending'; }`. The rendered page carries `<th ... aria-sort="ascending">`. The original grep looked in `views.theme.inc`, but these hooks moved to `src/Hook/ViewsThemeHooks.php` — searching the wrong file read as absence | Dropped step 10. Corrected the PR body and the Notion §5 claim, which both said no `aria-sort` existed. `aria-sort` reports the *current* direction (`$order`); the indicator reports the *next* one (`$initial`) — two different values, both correct |
| 7 | 9 | The scoped template filename `views-view-summary--glossary--attachment-1.html.twig` was written into this plan from memory of how Views suggestions are built | Correct, but only checked at execution time. `ViewExecutable::buildThemeFunctions()` emits `$hook__$id__$display_id` first, and the A–Z really does render from `attachment_1` on **both** `/glossary` and `/glossary/s` (`default_action: summary`, `format: default_summary`, `inherit_arguments: false`) — so one template covers both routes | Verified against core and the exported config **before** creating the file, rather than creating it and seeing whether it won. Cheaper than a `cr`-and-retry loop, and it is the plan's own trap 1 |
| 8 | 13 | Step 13 said "re-run the selective export after phase 3" | Phase 3 turned out to be **theme code only** — one new Twig file, no config touched. `views.view.glossary` is unchanged since PR #23 | No export. Recorded on step 13 and in the PR body, because PRs #25 and #27 both *did* need a config import on deploy and the difference is easy to miss |
| 9 | 9 | The template would be committed as written | The editor reformatted it on save — tabs for indentation, and the `<a>` attributes split across lines. Renders identically; the whitespace between `</span>` and the letter is what the accessible name needs anyway | Left as saved. Worth knowing that this repo's Twig files are not whitespace-stable across editors, so a diff may show more than was intended |
| 10 | — | Enabling the view adds a main-menu item, so Task 6's landmark inventory needed re-running (noted at the top of this plan) | Phase 3 adds a **second** landmark change: a new `navigation` landmark inside `main` on every glossary page, named "Browse recipes by first letter" | Task 6's landmark list needs re-running again before the §6 write-up is finalised. The name was chosen to not collide with "Main navigation" or the category bar |

---

## Out of scope

Deliberately excluded — do not widen this plan to cover them:

- `aria-current="page"` in `templates/navigation/pager.html.twig` (Task 3, still open, affects `frontpage` and admin views)
- The `h2 → h4` pagination-heading skip on `/recipes` (Task 3, open)
- `aria-label="Related content"` on `sidebar_second` being false on `/recipe-search` (shared by Tasks 1, 4 and 6 — one naming decision, not three)
- The EVA contextual filter's `default_action: 'not found'` → should be **Hide view** (Task 4, open)
- `field.storage.node.field_recipe_ingredients` cardinality drift (git `1` vs DB `-1`) — **data-destructive on import**, needs its own branch
- `image.style.recipe_hero_800` never exported; `system.performance` drift
- The by-ear VoiceOver pass. Everything above is desk-verified only, and desk-verified is not an audit
