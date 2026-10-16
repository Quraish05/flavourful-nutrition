# Plan — cache audit of the Articles side (Phase 3, Week 9–12 item 1)

> Branch: `perf/cache-audit-articles` (from `master` at `fa99d9b`) · Created: 2026-10-13 · Last updated: 2026-10-16
>
> **Status: Complete — merged in PR #56 (`dfbc263`, 2026-10-14).** Both leaks confirmed (steps 3, 4, 7), fixed (step 8) and re-tested clean under the measurement profile (step 9). Result: Part 3 of `cache-layers-and-a-real-leak.md`.

**Goal:** for the `articles` view, the article node display and the theme's
preprocess hooks, list the cache tags, contexts and max-age each one adds,
prove or rule out each suspected leak, and record what is found in
[`docs/actual-outcomes/cache-layers-and-a-real-leak.md`](../actual-outcomes/cache-layers-and-a-real-leak.md).

This file is the working checklist between Notion (*Phase 3 → Week 9–12 →
item 1, "Audit the Articles side"*) and that outcome doc. It audits and
records; **fixing is out of scope** (see the end).

**Standing rule for this plan: nothing is committed until you say so.**

---

## Why this plan exists

A cache leak is invisible on one request: the page that renders first is
always correct. It only shows when a second page reuses that cached markup.
So reading headers isn't enough. Each suspect needs a two-page test, in both
orders, with the render cache on.

### What step 1 found in the code (2026-10-13)
| `articles` view (`config/sync/views.view.articles.yml`, `page_1`) | Article nodes; exposed filters `field_story_type_value` and `field_topics_target_id`; mini pager | `tag` cache plugin: `node_list`, `config:views.view.articles`. Contexts `url`, `url.query_args`, `languages:language_content`, `languages:language_interface`, `user.node_grants:view`, `user.permissions` (dev-profile headers, 2026-10-14) | Complete. Coarse: `node_list` means saving any node, of any type, clears it (Week 9–12 item 2) |
| Article node display (`config/sync/core.entity_view_display.node.article.default.yml`) | Body and takeaways (text formats), hero image (`recipe_hero_800`, `loading="lazy"`), chef and topic labels, **`field_recipes` rendered as recipe `teaser`s** | Core formatters declare their own: `config:filter.format.basic_html`, `file:N`, `config:image.style.recipe_hero_800`, and `node:N`, `taxonomy_term:N`, `user:N` for referenced entities. The page adds `layout_builder_is_active:node` and `url.path`. Max-age until midnight, from `PageHooks` | Complete for the core formatters. **But `field_recipes` puts recipe teasers on the article page, so `setCardContext()` runs here.** Article 68 renders `margherita-pizza` as `compact h3`, which makes the article node a third page for suspects A and B. Its other recipe, `spaghetti-carbonara` (node 46), renders no card |

| Source | What it reads | Cache metadata it adds | Verdict |
|---|---|---|---|
| `PageHooks::preprocessPage()` (`src/Hook/PageHooks.php`) | Site config, today's date, cuisine terms, the cuisine query arg, route name, footer menu | Config dependency, max-age until midnight (`:201`), `taxonomy_term_list:cuisine`, `url.query_args:field_recipe_cuisine_type_target_id` (`:231`), `route.name` (`:238`, `:334`), `config:system.menu.footer` | Complete. Every read has a matching tag or context |
| `NavigationHooks::preprocessMenu()` | Permissions, view metadata, terms, entities | `user.permissions`, `user.node_grants:view`, view and term tags | Complete |
| `RecipeStats::count()` (`src/RecipeStats.php`) | Count of recipes, `accessCheck(TRUE)` | `node_list:recipe` | Tag present. The count is access-checked, but no user context is added: harmless while every role sees every recipe |
| **`NodeHooks::setCardContext()`** (`src/Hook/NodeHooks.php:120`), called for **article `card`** (`src/ArticlePreprocessTrait.php:33`) and recipe `teaser` | **The route name**: heading level 2 on the bundle's own listing (`view.articles.page_1`), 3 anywhere else | **None** | **Suspect A.** The card's markup is cached per node and view mode, not per route. The first page to render a card may fix its heading level everywhere |
| **`ListingHooks::preprocessViewsViewUnformatted()`** (`src/Hook/ListingHooks.php:124`, `:128`) | Which row is first | **None.** It sets `#card_variant: stacked` and `#card_heading_level: 2` on row 0's render array | **Suspect B.** Those keys aren't part of the node's render-cache keys, so a lead card's "stacked" markup may be reused where the same node is a plain card, or the reverse |

Templates that consume both: `templates/content/node--article--card.html.twig:42`
and `node--recipe--teaser.html.twig:42` (`heading_level: card_heading_level|default(3)`).

**Decided:**

- **Test in the measurement profile.** The dev profile sends the `render` bin
  to `cache.backend.null`, so a render-cache leak can't happen there and every
  test would pass.
- **Test both orders** (listing first, then the other page; then reversed,
  after `drush cr`). A leak shows as "whichever loaded first wins".
- **Read headers in the dev profile.** `X-Drupal-Cache-Tags` and `-Contexts`
  only exist there.

**Rejected:**

- **Auditing by headers alone.** A page's headers show what bubbled up, not
  what a cached fragment *should* have varied by. Suspect A adds no context, so
  nothing shows in any header.

---

## Steps

| # | Action | Where | Owner | Status |
|---|---|---|---|---|
| 1 | Read every theme hook on the Articles side and list what it reads against what cache metadata it adds | `web/themes/custom/flavourful/src` | Claude | Done |
| 2 | Find a page pair for each suspect: a node that renders as a card on two pages with different expected output | `curl` | Claude | Done |
| 3 | Test suspect A, both orders, on `moussaka` (`/` against `/recipes`) | measurement profile | You | Done: **leak confirmed** |
| 4 | Test suspect B, both orders, on `margherita-pizza` (`/` against `/recipes?page=1`) | measurement profile | You | Done: **leak confirmed** (both orders) |
| 5 | Record the inventory and each verdict in the cache write-up | `cache-layers-and-a-real-leak.md` | Claude | Done |
| 6 | Tick Notion's item 1 checkbox; add a checkbox per confirmed leak to fix | Notion | Claude | Done |
| 7 | Test the article node as the third page: load `/` or `/recipes?page=1` first, then article 68 | measurement profile | You | Done: **leak confirmed** (order 2) |
| 8 | Fix both leaks: `route.name` context in `NodeHooks::setCardContext()`, and a `lead` render-cache key on the lead row in `ListingHooks` | `web/themes/custom/flavourful/src/Hook/` | Claude | Done |
| 9 | Re-run steps 3, 4 and 7 under the measurement profile: every card shows its own page's value in every order | measurement profile | You | Done: all 9 lines match |
| 10 | Tick Notion's two fix checkboxes, and add the fix to Part 3 of the write-up | Notion, `cache-layers-and-a-real-leak.md` | Claude | Done |

### Step 2 — page pairs (Claude, done 2026-10-13)

**Neither suspect has a second page on the Articles side today:**

- **Article cards (`card` view mode) render on one page only:** `/articles`
  (`articles:page_1`). The view's `page_2` (`/articles/fields-test`) is a 403
  test display, `/articles/topic/%` renders fields, and the recipe page's
  `recipe_articles` view renders title links. So suspect A is **latent** for
  articles: correct today, and it breaks as soon as article cards render on a
  second route.
- **The lead-row override (suspect B) applies only to `frontpage:page_1`**
  (`ListingHooks::LEAD_ROW_DISPLAYS`, line 30). That's the homepage, and recipe
  cards, not article cards.

**Both suspects have a live pair on recipe cards, on two frozen URLs.**
Expected values, read in the dev profile, where nothing is cached:

| Suspect | Recipe | On `/` | On `/recipes` |
|---|---|---|---|
| A: heading level from the route | `moussaka` | `compact h3` | `compact h2` (page 1) |
| B: lead-row variant | `margherita-pizza` | `stacked h2` (the lead) | `compact h2` (`?page=1`) |

**The article node is a third page for `margherita-pizza`:** article 68 renders it
through `field_recipes` as `compact h3`. Correct values on the three pages are
`stacked h2` on `/`, `compact h2` on `/recipes?page=1`, and `compact h3` on
`/articles/four-years-they-let-me-make-carbonara`. Step 7 tests it.

### Step 3 — suspect A, both orders (you)

Run from the repo root. `cardinfo URL SLUG` prints that recipe card's variant
and heading tag. It splits the page card by card, so it can't read a
neighbouring card.

```bash
B=https://foodrecipes-drupal.ddev.site:33001
cardinfo() { curl -sk "$1" | python3 -c '
import sys, re
html, slug = sys.stdin.read(), sys.argv[1]
for chunk in html.split("<article data-component-id=\"flavourful:recipe-card\"")[1:]:
    chunk = chunk.split("</article>")[0]
    if "href=\"/recipes/%s\"" % slug in chunk:
        v = re.search(r"data-component-variant=\"(\w+)\"", chunk)
        h = re.search(r"<(h[1-6])\b", chunk)
        print(v.group(1) if v else "?", h.group(1) if h else "?")
        break
else:
    print("not found")' "$2"; }

ddev measure on
ddev exec printenv FLAVOURFUL_PROFILE   # must print: measure — otherwise the render cache is off and no leak can show

# Order 1: /recipes first
ddev drush cr
echo "/recipes: $(cardinfo "$B/recipes" moussaka)"   # expect compact h2
echo "/:        $(cardinfo "$B/" moussaka)"          # expect compact h3 — h2 is the leak

# Order 2: / first
ddev drush cr
echo "/:        $(cardinfo "$B/" moussaka)"          # expect compact h3
echo "/recipes: $(cardinfo "$B/recipes" moussaka)"   # expect compact h2 — h3 is the leak
```

**Result, 2026-10-13 (measurement profile on, `FLAVOURFUL_PROFILE=measure`):**

| Order | First page | Its card | Second page | Its card | Should be |
|---|---|---|---|---|---|
| 1 | `/recipes` | `compact h2` | `/` | **`compact h2`** | `compact h3` |
| 2 | `/` | `compact h3` | `/recipes` | **`compact h3`** | `compact h2` |

**Leak confirmed:** the first page to render the card fixes its heading level
for every page after it. Uncached (dev profile), both pages are correct.

### Step 4 — suspect B, both orders (you)

Same terminal, still in the measurement profile.

```bash
ddev exec printenv FLAVOURFUL_PROFILE   # must print: measure — otherwise the render cache is off and no leak can show

# Order 1: / first (the lead renders stacked)
ddev drush cr
echo "/:               $(cardinfo "$B/" margherita-pizza)"                # expect stacked h2
echo "/recipes?page=1: $(cardinfo "$B/recipes?page=1" margherita-pizza)"  # expect compact h2 — stacked is the leak

# Order 2: /recipes?page=1 first
ddev drush cr
echo "/recipes?page=1: $(cardinfo "$B/recipes?page=1" margherita-pizza)"  # expect compact h2
echo "/:               $(cardinfo "$B/" margherita-pizza)"                # expect stacked h2 — compact is the leak
```

**Result, re-run 2026-10-14 with `FLAVOURFUL_PROFILE=measure` checked:**

| Order | First page | Its card | Second page | Its card | Should be |
|---|---|---|---|---|---|
| 1 | `/` | `stacked h2` | `/recipes?page=1` | **`stacked h2`** | `compact h2` |
| 2 | `/recipes?page=1` | `compact h2` | `/` | **`compact h2`** | `stacked h2` |

**Leak confirmed in both orders.**

### Step 7 — the article node as the third page (you)

Same `cardinfo` helper. Run it under `ddev measure on`, then `ddev measure off`
afterwards.

```bash
ddev measure on
ddev exec printenv FLAVOURFUL_PROFILE   # must print: measure — otherwise the render cache is off and no leak can show
A=/articles/four-years-they-let-me-make-carbonara

# Order 1: the homepage's stacked lead first
ddev drush cr
echo "/:       $(cardinfo "$B/" margherita-pizza)"     # expect stacked h2
echo "article: $(cardinfo "$B$A" margherita-pizza)"   # expect compact h3 — stacked h2 is the leak

# Order 2: /recipes first
ddev drush cr
echo "/recipes?page=1: $(cardinfo "$B/recipes?page=1" margherita-pizza)"  # expect compact h2
echo "article:         $(cardinfo "$B$A" margherita-pizza)"              # expect compact h3 — h2 is the leak

# Order 3: the article first
ddev drush cr
echo "article: $(cardinfo "$B$A" margherita-pizza)"   # expect compact h3
echo "/:       $(cardinfo "$B/" margherita-pizza)"     # expect stacked h2 — compact h3 is the leak
```

**Result, 2026-10-14, with `FLAVOURFUL_PROFILE=measure` checked:** order 2,
`/recipes?page=1` first, gave `compact h2` there and **`compact h2` on article
68**, which should be `compact h3`. **Leak confirmed on the article node:**
the card built for `/recipes` is reused on the article page. Orders 1 and 3
weren't re-run with the check.

### Step 8 — the fix (Claude, done 2026-10-14)

- **Leak A:** `NodeHooks::setCardContext()` adds the `route.name` cache context
  whenever it derives the heading level from the route. Core bubbles `#cache`
  set in a preprocess function onto the element being rendered
  (`ThemeManager.php:352-367`), so the card's cache entry now varies by route.
  When a listing passes `#card_heading_level`, the route isn't read and no
  context is added.
- **Leak B:** `ListingHooks::preprocessViewsViewUnformatted()` appends `lead`
  to the lead row's `#cache` keys. Row content is still an unrendered render
  array at that point (`ViewsThemeHooks.php:1073`), so the lead gets its own
  cache entry. The key is only added when the row already has cache keys, so
  an uncacheable row is never made cacheable.
- **Checked in the dev profile (render cache off), no regression:** moussaka
  `compact h3` on `/` and `compact h2` on `/recipes`; margherita `stacked h2`
  on `/`, `compact h2` on `/recipes?page=1`, `compact h3` on article 68. All
  frozen URLs return 200. `php -l`, phpcs and phpstan are clean.

### Step 9 — re-test (you)

The helper from step 3, then:

```bash
ddev measure on
ddev exec printenv FLAVOURFUL_PROFILE   # must print: measure

# Leak A, /recipes first
ddev drush cr -q
echo "/recipes moussaka:   $(cardinfo "$B/recipes" moussaka)          want compact h2"
echo "/        moussaka:   $(cardinfo "$B/" moussaka)          want compact h3"

# Leak A, / first
ddev drush cr -q
echo "/        moussaka:   $(cardinfo "$B/" moussaka)          want compact h3"
echo "/recipes moussaka:   $(cardinfo "$B/recipes" moussaka)          want compact h2"

# Leak B, / first
ddev drush cr -q
echo "/        margherita: $(cardinfo "$B/" margherita-pizza)          want stacked h2"
echo "/recipes margherita: $(cardinfo "$B/recipes?page=1" margherita-pizza)          want compact h2"

# Leak B and the article page, /recipes first
ddev drush cr -q
echo "/recipes margherita: $(cardinfo "$B/recipes?page=1" margherita-pizza)          want compact h2"
echo "/        margherita: $(cardinfo "$B/" margherita-pizza)          want stacked h2"
echo "article  margherita: $(cardinfo "$B/articles/four-years-they-let-me-make-carbonara" margherita-pizza)          want compact h3"

ddev measure off
```

Every line should match its `want`. Any line that doesn't is a leak still open.

**Result, 2026-10-14:** `measure` confirmed, and all nine lines matched their
`want`. Both leaks are fixed.

### Step 6 — afterwards

```bash
ddev measure off
```

Check `MISS` and 48 `THEME DEBUG` on `/articles`, as in the baseline plan.

---

## Verification

| Check | How | Expect | If not |
|---|---|---|---|
| The test can see the card | `cardinfo` in the dev profile | The values in step 2's table (checked 2026-10-13) | Fix the parser against the real markup first |
| Render cache is on | `ddev measure on` was run; `/articles` second request is `HIT` | `HIT` | A leak can't happen with the `render` bin null: the test would pass falsely |
| Each order starts cold | `ddev drush cr` before each order | — | Re-run that order |
| The verdict holds in both orders | Steps 3–4 | Same expected tags in both orders = no leak. "First page wins" = leak | Record exactly which order showed it |

---

## Effect on the codebase

**Read-only.** It reads code and headers, and toggles the local DDEV profile
with `ddev measure on|off`. The outcome doc is additive. A fix, if a leak is
confirmed, is a separate change in `web/themes/custom/flavourful`.

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | 2 | Article 68 rendered as a card on `/articles` and on a second page | Article cards render only on `/articles`. The `recipe_articles` view on the recipe page renders title links, `page_2` is a 403 test display, and topic pages use fields. The lead-row override applies only to the homepage (`frontpage:page_1`), on recipe cards | Recorded both suspects as latent on the Articles side. Moved the live tests to recipe cards on `/` and `/recipes`, which hit the same code (`setCardContext()`, `ListingHooks:124`). Replaced the `grep` with a card-by-card parser, because a fixed-length window can read the next card |
| 2 | 1 | The article node display uses core formatters only | `field_recipes` renders recipe teasers, so `setCardContext()` also runs on the article page. Found while adding the view and display rows on 2026-10-14 | Added step 7: the article node as a third page for `margherita-pizza` |
| 3 | 4, 7 | A leak on `margherita-pizza` like the one on `moussaka`: the same code, cache key (`entity_view:node:<id>:teaser`, no route) and view mode on all three pages | Reported as matching the expected values, with no leak. Step 7 order 2 pasted: `/recipes?page=1` `compact h2`, then article `compact h3`. Step 7's block had no `ddev measure on` and ran after step 6's `ddev measure off`, so whether the render cache was on is unknown | Added a `printenv FLAVOURFUL_PROFILE` check to steps 3, 4 and 7, and `ddev measure on` to step 7. Re-running step 7 order 2 with the check before recording a verdict for suspect B and the article page Re-run 2026-10-14 with `measure` confirmed: article showed `compact h2`, so the leak **is** there. The first step 7 run was without the render cache. Step 4 is being re-run the same way Step 4 re-run with the check: leak in both orders. The first step 4 report wasn't pasted, so it can't be placed in either profile |
| 4 | 8 | Fixing was out of scope for this plan | You asked for both leaks to be fixed on 2026-10-14 | Added steps 8–10 rather than opening a second plan, because the re-test reuses this plan's helper and page pairs |

---

## Out of scope

- **The other fix shape** (the listing passes the level down through the
  cache key, and the card never reads the route). Steps 8–10 use the context
  for leak A and a cache key for leak B. The two options were: add `route.name` to the card (splits its cache per route), or have the
  container pass the heading level down so the card never reads the route
  (the `ListingHooks` pattern, but with the value in the cache keys).
- **Other places recipe cards render,** such as chef pages. Steps 3–4 test
  only the frozen URLs.
- **SDC components:** a separate Week 9–12 item 1 checkbox.
