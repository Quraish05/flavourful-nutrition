# Plan — Landmark navigation (Screen Reader Task 6)

> Branch: `fix/a11y-landmark-names` (off `master@7b3695e`) · Created: 2026-09-08 · Last updated: 2026-09-09
>
> **Status: all five findings fixed and verified. 6 of 7 steps done; only step 7 (Notion write-up) remains.** All five were confirmed against the live DDEV site, anonymous and authenticated, then re-verified after the fix by the same harness — which now passes on all five pages, including a page carrying a real status message. Three fixes were theme code, two were config. None of the five was a regression from the Cellar redesign; F3 and F4 predate it and F5 is Facets' default behaviour.
>
> Execution plan for Task 6 of the Notion study doc *"Screen Reader Task Scripts — Six Runs on Flavourful"* (§6, *Navigate the whole site by landmarks alone*). Neither an objective nor an outcome — this is the transient middle state. It feeds an eventual outcome note under [`docs/actual-outcomes/`](../actual-outcomes/).

---

## Why this plan exists

§6's desk-check ran clean on the parts it predicted would fail, and failed on parts it did not consider. The landmark **skeleton** is correct — one `banner`, one `main`, one `contentinfo`, every `navigation` uniquely named, and nothing outside a landmark but the skip link. What is wrong is the **naming**, and in one case the *count*.

**Decided:** fix the two `<aside>` names route-aware in `PageHooks`, downgrade the status-message landmark from `contentinfo` to `region`, name the local-actions `<nav>`, and turn off the duplicated facet titles in config.

**Rejected — do not re-propose:** deleting the `aria-label` from `.layout-sidebar-second` and leaving the region unnamed. It is a genuine one-line fix and it does remove the false statement — a single `complementary` region needs no unique name — but it trades a wrong name for no name, and 2.4.6 wants the name. Recorded here because it is the obvious shortcut and someone will suggest it.

**Rejected — do not re-propose:** moving the related-recipes block back to `sidebar_first` to match `config/sync`. The database is the intent here; the export is what is stale (see F2). Fix the export, not the site.

**Known side effect, accepted:** step 5 exports config, and `drush config:status` currently lists **eleven** drifted items, only six of which are ours. Export selectively, exactly as step 13 of the glossary plan did.

---

## What §6 got wrong, and what it missed

| # | §6 claims | Reality |
|---|---|---|
| 1 | The two `<aside>`s are now named, so "the original finding here is fixed" | Half true. They **are** named, but `sidebar_first` renders on **no page at all**, so one label is dead code and the only live label is the wrong one. §6 checked that labels exist, not that the regions do. |
| 2 | "The replacement finding is that a name can be worse than none" — `/recipe-search`'s second region holds the facet filters under "Related content" | **Correct, confirmed verbatim.** The region reads `Diet Type … Clear all filters (20) … Recipe Cuisine Type`. The strongest of the five, and §6 called it. |
| 3 | `highlighted` and `help` render outside `<main>`, so "check whether a status message is reachable by landmark navigation at all. If it is not, that is a considerably more serious finding" | **The prediction inverts.** Messages *are* reachable — core's `status-messages.html.twig` gives them their own `role="contentinfo"`. But that is the bug: the page then has **two** `contentinfo` landmarks. Reachable, and wrong. |
| 4 | `region.html.twig` wraps regions in a plain `<div class="region region-…">`, so "nothing there is inside any landmark" | True of the wrapper, false of the outcome. Every block actually placed in `highlighted` supplies its own role — messages → `contentinfo`, local tasks → `<nav aria-label="Tabs">`. Nothing is orphaned **today**; it holds by luck, not design. |
| 5 | 2.4.1 — "verify the skip link exists, is the first tab stop, and actually moves focus" | **Passes all three.** First tab stop, visible on focus, `Enter` moves focus to `a#main-content` and scrolls. The target is an empty zero-size `<a tabindex="-1">` and accepts focus anyway. |
| 6 | (not mentioned) | The facet sidebar emits **`h2 "Diet Type"` immediately followed by `h3 "Diet Type"`** — the block label and the facet's own title, same text, twice. Also `Recipe Cuisine Type`. 2.4.6. |
| 7 | (not mentioned) | `block--local-actions-block.html.twig` emits `<nav class="action-links">` with **no accessible name**. Latent — it rendered on none of the five pages tested. |
| 8 | (not mentioned) | `config/sync` and the database disagree on six blocks. This is what makes F2 invisible to anyone reading the repo. |

**Verified correct — log these as passes, do not "fix" them:** exactly one `banner`/`main`/`contentinfo` per page; `<header class="recipes-header">` inside `<main>` and `<footer class="node__meta">` inside `<article>` correctly do **not** become landmarks; navigation names are unique across `User account menu`, `Main navigation`, `Recipe categories`, `Breadcrumb`, `Pagination`; empty sidebars are suppressed rather than emitted unnamed; the admin toolbar's own landmarks are named (credit core); one `<h1>` per page.

---

## The five findings

| # | Finding | Where it renders | Criterion |
|---|---|---|---|
| **F1** | `complementary "Related content"` holds the **facet filters** on `/recipe-search`, and *Estimated nutrition* on recipe pages. The name is false, not vague | `.layout-sidebar-second` | 1.3.1 |
| **F2** | `sidebar_first` renders on no page, so `"Recipe details"` is unreachable — while the region that *is* used carries the other label. Root cause: config drift | `page.html.twig` + config | 1.3.1 |
| **F3** | **Two `contentinfo` landmarks** on any page carrying a status message | `status-messages.html.twig` | 1.3.1 / 4.1.2 |
| **F4** | `<nav class="action-links">` has no accessible name (latent) | `block--local-actions-block.html.twig` | 1.3.1 |
| **F5** | `h2 "Diet Type"` + `h3 "Diet Type"`, adjacent and identical | Facets config | 2.4.6 |

### F2 in full, because the root cause is not in the code

`config/sync` says the related-recipes block lives in `sidebar_first`. The database says `sidebar_second`. Somebody dragged blocks in the UI and never exported. The observable consequences:

- `.layout-sidebar-first` is empty on every page, so `{% if sidebar_first|trim is not empty %}` never fires and `"Recipe details"` is dead code.
- `.layout-sidebar-second` therefore carries *Related Recipes* **and** *Estimated nutrition* **and** *More from this chef*, all under `"Related content"`. Two of the three fit that name; nutrition is a property of the recipe on screen, not related content.
- Anyone reading `config/sync` to understand the page gets the wrong answer.

```
views_block__related_recipes_block_1   active=sidebar_second   exported=sidebar_first
recipenutritionfacts                   active=sidebar_second   exported=sidebar_second   (weight differs)
chefrecipes                            active=sidebar_second   exported=sidebar_second   (weight differs)
diettype                               active=sidebar_second   exported=sidebar_second   (weight differs)
recipecuisinetype                      active=sidebar_second   exported=sidebar_second   (weight differs)
```

One region difference, five weight differences.

### F3, and the irony worth recording

`components/site-footer/site-footer.component.yml` already documents the care taken here:

> Emits the inner row only, not a `<footer>` element — `page.html.twig` owns the contentinfo landmark, and nesting a second `<footer>` inside it would create a …

The theme was deliberate about keeping one `contentinfo`, then inherited a core template that adds a second one on every page that flashes a message. Proven on `/user/login` with a bad password:

```
contentinfo    Error message      <div class="messages messages--error">   70ch
main           (no name)          <main class="page-main">                 24ch
contentinfo    (no name)          <footer>                                 36ch
```

`role="contentinfo"` on line 31 of our override came verbatim from `core/modules/system/templates/status-messages.html.twig`. Because we already override the template, the fix is ours to make and costs one word.

**Note on the tooling, not the site:** the probe's duplicate detector keyed on role **+ name** and reported "no duplicates" here, because the two names differ. The collision is in the role alone. Any re-run must compare roles independently of names.

---

## Phase 1 — theme

| # | Step | File | Criterion | Status |
|---|---|---|---|---|
| 1 | Replace both hardcoded sidebar `aria-label`s with route-derived variables, set in `PageHooks::addSidebarLabels()` — `Search filters` on `view.recipe_search.page_1`, `More about this recipe` on a recipe node page, falling back to `Secondary information` | `templates/layout/page.html.twig`, `src/Hook/PageHooks.php` | 1.3.1, 2.4.6 | **Done** — two variables, not one (deviation 4) |
| 2 | `role="contentinfo"` → `role="region"` on the message wrapper | `templates/misc/status-messages.html.twig:31` | 1.3.1, 4.1.2 | **Done** — docblock rewritten too, so nobody re-syncs it back to core |
| 3 | Add `aria-label="{{ 'Actions'\|t }}"` to the `<nav>` | `templates/block/block--local-actions-block.html.twig` | 1.3.1 | **Done — unverified.** No front-end route renders a local action; all such routes use Claro |

Notes on the tricky ones:

- **Step 1 is the biggest of the five and I under-quoted it.** I told the developer "three lines of code"; steps 2 and 3 are one line each, but this one is a new `PageHooks` method of roughly a dozen lines plus a route-name match. Quote it honestly. Both `<aside>`s should read the same variable, so whichever region a site builder fills is named correctly — do **not** fix only the one that currently renders, or this recurs the next time someone drags a block.
- **Step 1, on naming `sidebar_first`:** leave the element in place. An empty region is not a defect and a future site builder may well use it; what was wrong was asserting what it contains.
- **Step 2** — safe. Core's `message.js` finds the container by `[data-drupal-messages]`, never by the role (`core/misc/message.js:43`). The inner `role="alert"` that carries the announcement is untouched, so the message still announces; it stops being a *landmark* of the singular kind. `region` keeps it in the landmark rotor because the wrapper already has `aria-label`. Grep confirmed nothing in `docroot/themes/custom` or `docroot/modules/custom` selects on `contentinfo`.
- **Step 3** — cannot be verified on the front end without a route that renders a local action; all such routes use Claro. Fix it on the reasoning, and record that it was unverified rather than claiming a tested pass. This is trap 1 in the other direction: a real defect in a template that never ran.

## Phase 2 — config

| # | Step | Where | Criterion | Status |
|---|---|---|---|---|
| 4 | Set **Show title** to off on both facets | `/admin/config/search/facets` → **Diet Type** → *Edit* → untick **Show title of facet**; repeat for **Recipe Cuisine Type** | 2.4.6 | **Done** |

Keep the **block** label (the `h2`) and drop the **facet's** own title (the `h3`). That direction, not the reverse: the sibling sidebar blocks title themselves at `h2` (`Related Recipes`), so keeping the block label is what holds the outline flat and consistent. The `h3` is the redundant one.

`facets.facet.diet_type:show_title` is `true` today; `block.block.flavourful_diettype:settings.label_display` is `visible` with label `Diet Type`. Both render, hence the pair.

## Phase 3 — verify, export, document

| # | Step | Status |
|---|---|---|
| 5 | Selective config export via a temp dir, then copy across. A plain `config:export` sweeps in `core.extension`, `system.performance`, `system.site`, `field.storage.node.field_recipe_ingredients` and two DB-only items | **Done — the two facet files only** (deviation 5). One line each, `show_title: true` → `false`. The six drifted blocks were left alone deliberately |
| 6 | Re-run the landmark probe on all five pages plus one carrying a status message. Compare roles independently of names this time | **Done — all checks pass.** Singular-role counting added; F3 also confirmed out-of-band with a curl POST |
| 7 | Notion §6 — correct predictions 1, 3 and 4 **in place**, add findings 6, 7 and 8, and record that 2.4.1 passed outright. Then add §7 log rows, **including the pass rows** | Not started |

---

## Verification

```bash
# F2 — the drift that makes the whole finding invisible in the repo
ddev drush config:status
ddev drush cget block.block.flavourful_views_block__related_recipes_block_1 region
grep -m1 '^region:' config/sync/block.block.flavourful_views_block__related_recipes_block_1.yml

# F5 — two headings, same text, before the fix
# Prints: h2 | Diet Type / h3 | Diet Type / h2 | Recipe Cuisine Type / h3 | Recipe Cuisine Type
curl -sk https://foodrecipes-drupal.ddev.site:33001/recipe-search | python3 -c "
import sys, re
h = sys.stdin.read()
i = h.find('layout-sidebar-second')
for m in re.finditer(r'<(h[1-6])[^>]*>(.*?)</\1>', h[i:i+6000], re.S):
    print(m.group(1), '|', re.sub(r'<[^>]+>', '', m.group(2)).strip()[:60])
"

# F3 — force a status message as an anonymous user, then count contentinfo
#   (browser: submit /user/login with a bad password, then run)
#   document.querySelectorAll('[role=contentinfo], footer:not(article footer)').length   // expect 1, currently 2
```

The full landmark probe used for this audit is a Node + CDP harness, not a browser extension — it implements the HTML-AAM scoping rules so that `<header>` inside `<main>` and `<footer>` inside `<article>` are correctly excluded. Worth committing under `scripts/` if Task 6 is ever re-run; it lived in the session scratchpad this time.

---

## Deviations log

| # | What changed from the plan as written | Why |
|---|---|---|
| 1 | §6's "serious finding" (status messages in no landmark) does not hold; it is replaced by the opposite defect (two `contentinfo`) | Messages carry their own role from core. Verified on `/user/login`, not inferred from `page.html.twig` |
| 2 | 2.4.1 needed no fix at all | Skip link passes both halves. An earlier run reported focus landing on `<body>`; that was the harness dispatching `rawKeyDown`, which fires no default action. Re-run with `keyDown` — the site was never at fault. Recorded because it is exactly the class of false positive this log exists to catch |
| 3 | "Three lines of code" became two one-liners plus a ~dozen-line `PageHooks` method | Step 1 needs route awareness; there is no single truthful static name for a region that holds facets on one route and nutrition on another |
| 4 | Step 1 shipped **two** variables (`sidebar_first_label`, `sidebar_second_label`), not the single `sidebar_label` the plan specified | One shared name would have recreated the original defect — two `complementary` regions with identical names — the moment a builder filled both sidebars. Caught while writing the implementation steps, before any code was written |
| 5 | Step 5 exported the **two facet files only**, not the six drifted blocks the plan listed alongside them | The block drift (F2's root cause) is a repo-vs-database mismatch unrelated to these five findings, and folding it in would have made this branch's diff argue two separate cases. Still open — see below |
| 6 | 13 phpcs errors appeared after the edits and were cleared with `phpcbf` | All whitespace: the new docblock's `/**` landed at 4 spaces instead of 2, cascading into 12 asterisk-alignment errors, plus one double blank line. Nothing semantic. `phpcs` over both CI paths now exits 0 |
| 7 | F3 was verified twice, and the harness gained a check it did not have | The original probe keyed duplicates on role **+ name** and so reported "no duplicates" on the very page that had two `contentinfo` landmarks. Singular roles are now counted independently of names. The fix was also confirmed out-of-band with a curl POST to `/user/login`, because a defect the harness had once missed should not be signed off by that harness alone |

---

## Still open

- **Step 7** — Notion §6 write-up: correct predictions 1, 3 and 4 in place, add findings 6–8, record that 2.4.1 passed outright, then add §7 log rows including the pass rows.
- **The six drifted `block.block.flavourful_*.yml`** — one region difference, five weight differences (see F2). Fixing the export does not change a single rendered page; it changes whether the repo tells the truth about the site. Worth its own commit, and a decision about whether `config:status` should be a CI gate so this cannot drift silently again.
- **The probe harness** lives in the session scratchpad and will be lost. If Task 6 is ever re-run, commit it under `scripts/`.
