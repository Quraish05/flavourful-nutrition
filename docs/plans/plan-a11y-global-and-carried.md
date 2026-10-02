# Plan — Global accessibility pass and carried items

> Branch: `fix/a11y-global-and-carried` (off `master`) · Created: 2026-09-15 · Last updated: 2026-09-15
>
> **Status: in progress — 12 of 15 done, 1 skipped.** Phase 1 shipped as **PR #30**; Phases 2 and 3 as **PR #31**. Remaining: step 12 (the `button` atom API) and step 14 (the five block config exports). Step 3 dropped by the developer.
>
> Closes the **Global** section of Week 3–5 plus the small carried items left over from Tasks 3, 5 and 6. Neither an objective nor an outcome — the transient middle state. It feeds [`docs/actual-outcomes/accessibility-audit.md`](../actual-outcomes/accessibility-audit.md), whose open findings **O-1 to O-5** are the numbered work here.

---

## Why this plan exists

The audit has five open findings and the Notion Global section has three unticked items. They overlap, so doing them as one pass avoids touching the same tokens twice.

**Decided:** fix the two config clicks first (they are free), then the dead CSS, then the palette work, and leave the `button` atom API change for last because it is the only item that changes a component contract.

**Rejected — do not re-propose:** a blanket "raise every token until it passes". Three of the eight original code-review findings were contrast findings measured against the **pre-redesign palette** and all three are now stale. The current palette mostly passes. Change only the tokens that measurably fail, at the use sites that actually carry text.

**Known constraint:** `--fr-bone-faint` is used in seven places, and the theme's own comments already describe it as "below AA and reserved for" non-text use. So the token is not wrong — some of its call sites are. That makes this triage, not a find-and-replace.

---

## Phase 1 — the two free config wins

*(Step 3 was dropped by the developer — see the status note on that row. Phase 1 is now the two pager settings.)*

| # | Step | Where | Criterion | Status |
|---|---|---|---|---|
| 1 | `views.view.recipes` → *Pagination heading level* → **h2** | `/admin/structure/views/view/recipes` → **Page** display → *Pager* → **Mini** → *Paged output, mini pager* settings → **Pagination heading level** → `h2` → Apply → **blue Save** | 1.3.1 (A) | **Done** — PR #30 |
| 2 | `views.view.recipe_search` → same | `/admin/structure/views/view/recipe_search`, same path | 1.3.1 (A) | **Done** — PR #30. Both saved first time, unlike the three passes this step took in `plan-a11y-views-output.md` |
| 3 | ~~Remove the glossary **Author** column~~ | — | — | **Skipped** — the developer wants the author names kept in the table. They read "Anonymous (not verified)" today only because all 32 nodes are uid 0 or 1; with real authors the column carries real information. Never an accessibility finding. The `uid` relationship stays with it |

**Save each view before navigating away.** *Apply* closes the modal and changes nothing; only the blue **Save** commits. This exact step took three passes last time because two views were edited and only one was saved — see deviation 4 in [`plan-a11y-views-output.md`](plan-a11y-views-output.md). Treat "Save view N" as its own action, not a clause at the end of another step.

**Verify (before → after):**

```bash
# Expect h4 today, h2 after. Run before you start, so you know the baseline is real.
grep -n "pagination_heading_level" config/sync/views.view.recipes.yml config/sync/views.view.recipe_search.yml
# currently: both h4

curl -sk https://foodrecipes-drupal.ddev.site:33001/recipes | \
  python3 -c 'import sys,re; h=sys.stdin.read(); print([l for l,_ in re.findall(r"<(h[1-6])[^>]*>(.*?)</\1>",h,re.S)])'
# before: [... h2 x10, h4]   after: [... h2 x10, h2]
```

Same for `/recipe-search`, which currently reads **h1 → h4** — a three-level skip, the worst outline on the site.

## Phase 2 — the A–Z stylesheet that never loads (O-5)

`.views-summary` — the flex row, the uppercase letters, and the `a[aria-current]` colour and border — lives in `css/recipes.css`, which is attached from `node--recipe`, `views-view--frontpage` and `views-view--recipes--page-1`. **`/glossary` is the only page with a `.views-summary` element, and it loads `global.css` only.** The whole block is dead code, and the consequence is that screen-reader users can tell which letter is current while sighted users cannot.

| # | Step | Where | Status |
|---|---|---|---|
| 4 | Attach `flavourful/recipes` on the glossary view | `src/Hook/ListingHooks.php` → `preprocessViewsView()` — split the existing `!$view || $view->id() !== 'frontpage'` guard so the glossary is handled before the frontpage early-return, then `$variables['#attached']['library'][] = 'flavourful/recipes';` | **Done** — PR #31 |
| 5 | `ddev drush cr`, then confirm the letters render as a horizontal row and the active one is brass with a bottom border | `/glossary/s` | **Done** — `recipes.css` now served on `/glossary/s` |

**Do this in the preprocess hook, not a template.** `ListingHooks` already implements `preprocess_views_view`, so this is four lines in an existing method. The template route would mean duplicating the theme's own **96-line** `views-view.html.twig` to add one line, and inheriting every future core change to it. Noted because the first draft of this plan said to duplicate the template — see deviation 4.

**Decide at step 4, and write down which you chose:** attaching `flavourful/recipes` pulls the whole recipes-listing stylesheet in for one ~15-line block. The alternative is moving the `.views-summary` rules out of `scss/components/_recipes-listing.scss` into a global partial, which puts the CSS where the element actually is — the element appears on `/glossary` and nowhere else, so its rules living in a file named *recipes-listing* is the root cause rather than an accident. **Recommended: attach now, note the debt**, because the listing CSS is already cached on most journeys into the glossary and the move touches the build.

**Verify:**

```bash
curl -sk https://foodrecipes-drupal.ddev.site:33001/glossary/s | grep -o 'flavourful/css/[a-z-]*\.css' | sort -u
# before: global.css          after: global.css, recipes.css
```

Then visually: the eleven letters should sit on one row, not stack, and "S" should be brass with an underline rather than identical to its neighbours.

## Phase 3 — contrast, measured not assumed (O-2, O-3)

Everything below was computed from `scss/abstracts/_variables.scss` against the **actual painted background** for each use, not against white. Re-run the numbers before changing anything — the script is in step 6.

| Use | Colour | On | Ratio | Needs | Verdict |
|---|---|---|---|---|---|
| Form control border | `#453d30` | field `#0e0c09` | **1.83** | 3.0 (1.4.11) | **Fail** |
| Form control border | `#453d30` | page `#17140f` | **1.72** | 3.0 (1.4.11) | **Fail** |
| Input placeholder | `#6f6a5f` | field `#0e0c09` | **3.63** | 4.5 (1.4.3) | **Fail** |
| `bone-faint` as text | `#6f6a5f` | page `#17140f` | **3.41** | 4.5 (1.4.3) | **Fail** |
| Body text | `#ede7da` | `#17140f` | 14.91 | 4.5 | Pass |
| `bone-muted` | `#a9a294` | `#17140f` | 7.24 | 4.5 | Pass |
| Brass link | `#c9a24a` | `#17140f` | 7.65 | 4.5 | Pass |
| Focus outline | `#c9a24a` | `#17140f` | 7.65 | 3.0 (1.4.11) | Pass |
| Difficulty easy/med/hard/expert | — | `#17140f` | 6.08 / 7.65 / 4.96 / 4.87 | 4.5 | **All pass** |

| # | Step | Where | Criterion | Status |
|---|---|---|---|---|
| 6 | Re-run the contrast script and confirm the failures still stand | was **5 failing of 17** | — | **Done** |
| 7 | Introduce a **control-border** token distinct from the decorative rule token | `$color-rule-control: #7a7263` — **3.86 / 4.11 / 3.64** against page, field and card. Picked over `#736b5c` (3.29 on the card ground) for margin | 1.4.11 (AA) | **Done** |
| 8 | Point `input, select, textarea` borders at the new token; leave decorative rules alone | `scss/base/_typography.scss` — `$color-rule-strong` also draws the `<th>` bottom border, so changing it would have restyled every table for no gain | 1.4.11 (AA) | **Done** |
| 9 | Raise `::placeholder` to `--fr-bone-muted` | `scss/base/_typography.scss` — **3.63 → 7.70** | 1.4.3 (AA) | **Done** |
| 10 | Triage the `bone-faint` call sites | **Nine, not seven** (deviation 8). Raised 4: `.powered-by`, `.recipe-page__submitted`, `::placeholder`, `.cook-mode__steps .method__step`. Kept 5 as exempt: two `·` separators, two `:disabled` controls, the unfilled rating star | 1.4.3 (AA) | **Done** |
| 11 | Rebuild the compiled CSS and confirm the artefacts changed | `global.css` and `recipes.css` both changed; values verified in the compiled output, not just the source | — | **Done** |

**This theme has two CSS systems, and step 10 touches both.** Getting them the wrong way round means editing a file that feeds nothing:

| | Source | Build | Examples |
|---|---|---|---|
| Page and layout CSS | `scss/**` | **compiled** by `npm run build` (`sass scss:css`) into `css/*.css` | `scss/base/_typography.scss` → `css/global.css` |
| SDC component CSS | `components/<name>/<name>.css` | **hand-written, not compiled** — edit in place | `components/rating-stars/rating-stars.css` |

So: the placeholder and control-border changes (steps 7–9) are SCSS and **need step 11**; `recipe-tools` and `rating-stars` in step 10 are hand-written and **must not** be looked for in `scss/`.

**Do not skip step 11 for the SCSS half.** `css/global.css` ships as a single minified line. Editing SCSS without rebuilding changes nothing on the page, and the diff will look convincing — the same class of error as editing a template that never renders.

**Verify:**

```bash
# The four failures, recomputed
python3 scripts/contrast.py    # expect 0 FAIL rows after phases 7-10

# Call sites to triage in step 10 -- note which tree each hit is in
grep -rn "bone-faint" docroot/themes/custom/flavourful/scss docroot/themes/custom/flavourful/components \
  | grep -v "_variables.scss"
# 7 hits today: 4 in scss/ (compiled), 3 in components/ (hand-written)

# Prove the rebuild actually changed something
git diff --stat docroot/themes/custom/flavourful/css/
# must be non-empty after step 11, or the SCSS edits are not live
```

**Focus indicator, while you are in here.** `:focus-visible` exists for `a`, `input`, `select` and `textarea` — it does **not** cover `<button>`, `[tabindex]` or `summary`. Tab the whole site once and record every control where focus is invisible or ambiguous; that list decides whether this needs a step of its own. Do not add a blanket `*:focus-visible` before looking — it will fight the existing rules.

## Phase 4 — the button atom (O-4)

| # | Step | Where | Criterion | Status |
|---|---|---|---|---|
| 12 | Add an `as` prop (`link` \| `button`, no default) to the component contract; render `<a href>` or `<button type>` accordingly, with `url` required only for `link`. Update every call site to state its choice | `components/button/button.twig`, `button.component.yml` | 4.1.2 (A) | Not started |

**Last, and separable.** This is an API change, not a bug fix — today's usages are all navigational, so nothing is currently announcing the wrong role. Ship it on its own branch if phases 1–3 are ready before it is. The value is preventing the defect in the Articles build, where actions are likely.

## Phase 5 — housekeeping

| # | Step | Where | Status |
|---|---|---|---|
| 13 | Commit the audit tooling: the nine-route sweep and the contrast calculator | `scripts/a11y-sweep.py`, `scripts/contrast.py` | **Done** — shipped with the audit documents so every command in this plan runs as written |
| 14 | Export the five drifted `block.block.flavourful_*` items **selectively** via a temp dir | see below | Not started |
| 15 | Site name → remove the installer default from every page title and the header | `ddev drush config:set system.site name 'The Cookbook'` | **Done** — no export needed, see below |

**Step 14 needs care.** `config:status` currently lists **ten** drifted items. Six are ours (five `Different`, one `Only in DB`). Four must **not** be swept in:

- `field.storage.node.field_recipe_ingredients` — cardinality `1` in git vs `-1` in DB. **Data-destructive on import.** Needs its own branch and a migration decision.
- `image.style.recipe_hero_800` — never exported; belongs with a theme PR, not this one.
- `system.performance` — unrelated.
- ~~`system.site` — export it in its own commit~~ → **done, and it needed no export at all.** The drift ran the *other* way: `config/sync/system.site.yml` already held `name: "The Cookbook"` and the **database** was still on the installer's `Drush Site-Install`. So the fix was to make the running site match the repo — `ddev drush config:set system.site name 'The Cookbook'` — which removed the keyword from the header and all nine page titles **and** cleared a drift item without touching git. Check which side is stale before reaching for an export.

```bash
# NOTE the absolute container path -- a relative --destination silently
# exports nothing and reports "The active configuration is identical to the
# configuration in the export directory". See deviation 6.
mkdir -p .cex-tmp && ddev drush config:export --destination=/var/www/html/.cex-tmp -y
diff -rq config/sync .cex-tmp | grep '^Files'         # differing only; ignore 'Only in'
cp .cex-tmp/block.block.flavourful_*.yml config/sync/
rm -rf .cex-tmp
git status --short config/sync                        # expect exactly 6 files
```

---

## Verification — the whole-site sweep

Re-run after every phase. Nine routes, checking heading outlines and skips, landmark names, empty links, nested anchors, `aria-current` counts, table captions and `scope`, `lang`, and page titles.

```bash
python3 scripts/a11y-sweep.py
```

**Baseline today (2026-09-15), so you can tell a fixed page from a broken one:**

| Route | `<h1>` | Skips | Landmarks named | Notes |
|---|---|---|---|---|
| `/` | Latest recipes | none | yes | 2 "empty links" are false positives — see below |
| `/recipes` | Recipes | **h2 → h4** | yes | step 1 fixes the skip |
| `/recipe-search` | Recipe search | **h1 → h4** | yes | step 2; worst outline on the site |
| `/glossary` | Recipes beginning with A | none | yes | |
| `/glossary/s` | Recipes beginning with S | none | yes | |
| `/chefs` | Chefs | none | yes | |
| `/reports/by-cuisine` | Recipes per Cuisine | none | yes | |
| `/node/67` | Joe H | none | yes | |
| 404 | Page not found | none | yes | |

**Two false positives the sweep reports, and why they are not defects** — both were checked and cleared, and re-flagging them next time would be wasted work:

- **`<a id="main-content" tabindex="-1"></a>`** counts as an "empty link" in a naive regex. It has **no `href`**, so it maps to `generic`, not `link`, and never appears in a links rotor. It is the skip-link *target*. Not a defect.
- **A second `<header>` on `/recipes`** looks like a duplicate `banner`. It is inside `<main>`, and per HTML-AAM `<header>` maps to `banner` only when it is *not* inside `article`, `aside`, `main`, `nav` or `section`. Not a landmark at all.

**Rule that governs every check in this plan:** anything created by the **HTML parser** or by **JavaScript** cannot be verified from source. Everything in phases 1–3 *is* source-visible, so `curl` is legitimate here — but Phase 2's real outcome (does the row look different?) is visual, and needs eyes or a screenshot, not a grep.

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | Verification tooling | The sweep would report only real defects | It flagged **one unnamed link on every page** and, on the front page, a second. Both were false: `<a id="main-content" tabindex="-1">` has no `href` so it is not a link at all, and `<a class="recipe-card__media" aria-hidden="true">` is removed from the accessibility tree — a hidden duplicate is not an *unnamed* link | Added both exclusions to `links()` and documented why in the docstring. A check that reports a defect where none exists trains you to ignore it, which is the same failure mode as a check that silently passes |
| 2 | Baseline sweep | `/glossary` would return 200 | **500 on both glossary routes.** Switching from the PR #28 branch to `master` removed `views-view-summary--glossary--attachment-1.html.twig` from the tree while the theme registry still pointed at it | `ddev drush cr`. **Rebuild the cache after any branch switch that adds or removes a template** — the failure looks like a broken page, not a stale cache, and it will happen again during review |
| 3 | 3 | The glossary's author column would appear as `Content: Authored by` | It is **`(author) User: Name (Author)`** — the view reads the name through a `uid` **relationship** rather than the node's own author field. The developer could not find the step's label in the UI and reasonably assumed the column had already been removed | Corrected the step. Also added removing the now-orphaned `uid` relationship, which the original step missed entirely. **Verify a UI label against config before writing it into a plan** — a wrong label reads as "already done" |
| 4 | 4 | A glossary-scoped `views-view--glossary--page-1.html.twig` would be the way to attach the library | `ListingHooks` **already implements `preprocess_views_view`**, so four lines in an existing method do the same job. The template route meant duplicating the theme's own 96-line override to add one line, and owning every future core change to it | Rewrote step 4 to use the hook. Checked what already existed before prescribing a new file — the plan reached for a template because that is what the previous two steps in this workstream used |
| 5 | 3 | The Author column would be removed as tidy-up | The developer wants it kept — the names are only "Anonymous" because the seed content is all uid 0 or 1, and the column will carry real information once it has real authors | Step 3 skipped, and deviation 3's relationship advice withdrawn with it: `uid` is not orphaned if the field using it stays. **A step justified only by "this looks untidy" is the first one to drop** — it was never a criterion |
| 6 | 14 | `ddev drush config:export --destination=.cex-tmp -y` would export active config to the temp dir, as it has four times before | It wrote **zero files** and exited `[success]`, reporting *"The active configuration is identical to the configuration in the export directory"* — against an empty directory. The relative path does not resolve to the host directory from inside the container | Used the absolute container path `/var/www/html/.cex-tmp`; 279 files. **A success message is not evidence the work happened** — the file count is. Corrected the snippet above |
| 7 | 1–2 | The sweep would be runnable on the fix branch | `scripts/a11y-sweep.py` exists only on `docs/a11y-audit-and-test-log` (PR #29, unmerged), so it is absent from any branch cut off `master` | Ran it via `git show docs/a11y-audit-and-test-log:scripts/a11y-sweep.py`. **Merge PR #29 first** — shared tooling parked in an open PR has to be fetched by hand on every branch that needs it |
| 8 | 10 | Seven `bone-faint` call sites to triage | **Nine.** The grep that produced the figure ended in `head -12`, and the twelve lines included comment matches — so `meta-list.css` fell off the end. It turned out to be a decorative `·` separator and needed no change, but the count in this plan and in the audit was wrong either way | Re-ran without `head`. **Do not size a piece of work from a truncated command** — the number looked precise and was not |
| 9 | 7 | `#736b5c` would be the control-border colour | It clears 3:1 on the page and field grounds but reaches only **3.29** on the raised card ground — a 0.29 margin on a criterion, which is not margin | Used `#7a7263`: 3.86 / 4.11 / 3.64. **Check a border against every ground it is drawn on**, not just the common one |

---

## Out of scope

- **Site Studio / Cohesion components** — struck through on the Notion plan by decision. Any finding there is likely *authorable* and belongs in a governance conversation.
- **`field.storage.node.field_recipe_ingredients`** — data-destructive on import; its own branch.
- **axe-core / pa11y-ci in CI** — Week 5–6. Note the workflow's `on: push` targets `main` while the default branch is `master`, so that must be fixed first or nothing runs.
- **NVDA on Windows** — pending a VM.
- **Re-running zoom and reflow with figures recorded** — belongs to [`screen-reader-test-log.md`](../actual-outcomes/screen-reader-test-log.md), not here.
