# Plan — component props that drop cache metadata (Phase 3, Week 9–12 item 1)

> Branch: `perf/cache-audit-props` (from `master` at `dfbc263`) · Created: 2026-10-14 · Last updated: 2026-10-15
>
> **Status: Complete — merged in PR #57 (`ca68012`, 2026-10-15), re-test record in PR #58 (`74b8af3`).** The missing tag was confirmed (step 3), fixed (step 7) and re-tested clean under the measurement profile (step 8): renamed terms now show at once on both cards and in the article's pills. Result: Part 3 of `cache-layers-and-a-real-leak.md`, PR #57.

**Goal:** find out whether a recipe card or an article header keeps showing a
term's old name after the term is renamed, because the preprocess code that
builds their props drops the term's cache tag. Record the result in Part 3 of
[`docs/actual-outcomes/cache-layers-and-a-real-leak.md`](../actual-outcomes/cache-layers-and-a-real-leak.md).

This file is the working checklist between Notion (*Phase 3 → Week 9–12 →
item 1, the SDC components checkbox*) and that write-up. It follows
[`plan-perf-cache-audit-articles.md`](plan-perf-cache-audit-articles.md), and
reuses its idea: watch the cache serve something wrong, don't assume it.
**Fixing is out of scope.**

**Standing rule for this plan: nothing is committed until you say so.**

---

## Why this plan exists

The components can't leak by themselves. All 26 component templates under
`web/themes/custom/flavourful/components/` call no function but `include()`.
They read no route, user, date or entity, and show only the props they are
given. So the question moves one step back, to the preprocess code that builds
those props.

### What the code shows (2026-10-14)

`NodeFieldTrait` turns referenced entities into plain strings, and nothing
adds the entity's cache tag:

| Prop | Built from | Metadata dropped |
|---|---|---|
| `recipe_eyebrow`, the cuisine on a recipe card | cuisine term `label()` (`src/Hook/NodeHooks.php:145`) | The term's tag, `taxonomy_term:<id>` |
| `chef_name`, `article_author_name` | chef node `label()` (`NodeHooks.php:142`, `src/ArticlePreprocessTrait.php:79`) | The chef's tag, `node:<id>` |
| `article_tags`, the topic pills | `termChips()`: label and `toUrl()->toString()` (`src/NodeFieldTrait.php:70-71`) | Each term's tag, and the URL's metadata |
| Author link | `accessibleUrl()`: `access('view')` as a plain bool (`NodeFieldTrait.php:49`) | What the access result varies by |

**The suspect:** a cached card or article carries the node's own tag, not the
term's. Renaming the term clears everything tagged with it, but not the card,
so the card keeps the old name until the recipe itself is saved.

**Why the test can't be a header check:** the page as a whole may still be
cleared, because the category strip depends on every cuisine term
(`PageHooks.php:250`). The card's own render-cache entry survives that, and the
rebuilt page reuses it. So the test reads the card's markup, not
`X-Drupal-Cache`.

**Decided:**

- **Rename terms, not chefs.** Terms have no path aliases here (their links
  are `/taxonomy/term/<id>`), so renaming one and back leaves nothing behind.
  A chef is a node, and renaming it could rewrite its alias. It's the same
  mechanism, so the chef rows are recorded as "same cause, untested".
- **Targets:** cuisine term 35 "Greek", shown on moussaka's card on `/` and
  `/recipes`; topic term 77 "Family", shown as a pill in article 68's header.
- **Measurement profile.** With the render cache off, every page is rebuilt
  and the test would pass falsely.

**Rejected:**

- **Reading `cache_render` tags directly.** It shows what is missing, but not
  that a visitor would see a stale name. The rename shows both.

---

## Steps

| # | Action | Where | Owner | Status |
|---|---|---|---|---|
| 1 | Check the 26 components for anything they read themselves | `components/*/*.twig` | Claude | Done: only `include()` |
| 2 | List the prop builders that read referenced entities, and what they drop | `src/` | Claude | Done: table above |
| 3 | Warm the pages, rename the two terms, reload, and read the card and header | measurement profile | You | Done: **stale on all three** |
| 4 | Rename both terms back, and check | `drush php:eval` | You | Done: names restored, `config:status` unchanged |
| 5 | Record the verdict in Part 3 of the write-up | `cache-layers-and-a-real-leak.md` | Claude | Done |
| 6 | Tick Notion's SDC checkbox; add a fix checkbox if confirmed | Notion | Claude | Done |
| 7 | Fix the prop builders: register each referenced chef and term, and keep the access result's and URL's metadata | `src/NodeFieldTrait.php`, `src/ArticlePreprocessTrait.php`, `src/Hook/NodeHooks.php` | You and Claude | Done |
| 8 | Re-run the rename test: all three "after" lines now print `yes` | measurement profile | You | Done: all three `yes` |
| 9 | Record the re-test in the write-up, tick Notion's fix checkbox | write-up, Notion | Claude | Done |

### Step 3 — the rename test (you)

Run from the repo root. `inpart URL COMPONENT MATCH TEXT` finds the component
whose markup contains `MATCH`, and prints `yes` if `TEXT` is in it.
**Checked 2026-10-14 in the dev profile:** `Greek` yes on both pages,
`Family` yes, and `ZZTEST` no, so it can see a label and a missing one.

```bash
B=https://foodrecipes-drupal.ddev.site:33001
A=/articles/four-years-they-let-me-make-carbonara
inpart() { curl -sk "$1" | python3 -c '
import sys
html, comp, match, text = sys.stdin.read(), sys.argv[1], sys.argv[2], sys.argv[3]
for c in html.split("data-component-id=\"flavourful:%s\"" % comp)[1:]:
    c = c.split("</article>")[0] if comp.endswith("card") else c[:6000]
    if match in c:
        print("yes" if text in c else "no"); break
else:
    print("not found")' "$2" "$3" "$4"; }

ddev measure on
ddev exec printenv FLAVOURFUL_PROFILE   # must print: measure

# Warm: build the card and the article header with the current names
ddev drush cr
echo "before  /recipes card Greek:    $(inpart "$B/recipes" recipe-card 'href="/recipes/moussaka"' Greek)"     # yes
echo "before  /        card Greek:    $(inpart "$B/" recipe-card 'href="/recipes/moussaka"' Greek)"            # yes
echo "before  article  pill Family:   $(inpart "$B$A" article-header tag-pill Family)"                          # yes

# Rename both terms (a real save, so their cache tags are invalidated)
ddev drush php:eval '
use Drupal\taxonomy\Entity\Term;
Term::load(35)->setName("Greek ZZTEST")->save();
Term::load(77)->setName("Family ZZTEST")->save();
echo "renamed\n";'

# Reload, without clearing caches
echo "after   /recipes card ZZTEST:   $(inpart "$B/recipes" recipe-card 'href="/recipes/moussaka"' ZZTEST)"    # yes = updated, no = STALE
echo "after   /        card ZZTEST:   $(inpart "$B/" recipe-card 'href="/recipes/moussaka"' ZZTEST)"           # yes = updated, no = STALE
echo "after   article  pill ZZTEST:   $(inpart "$B$A" article-header tag-pill ZZTEST)"                          # yes = updated, no = STALE
```

**Reading it:** `no` on an "after" line means the page shows the old name
after the term was saved. That's the missing tag, confirmed. `yes` means
something else on that card or header already carries the term's tag.

**Result, 2026-10-15 (`measure` confirmed):** all three "before" lines `yes`,
all three "after" lines **`no`**. Moussaka's card on `/recipes` and `/`, and
article 68's topic pills, kept the old names after both terms were saved.
**Missing tag confirmed.** `X-Drupal-Cache` wasn't captured, so which layer
served the stale article header isn't known.

### Step 4 — rename back (you)

```bash
ddev drush php:eval '
use Drupal\taxonomy\Entity\Term;
Term::load(35)->setName("Greek")->save();
Term::load(77)->setName("Family")->save();
echo Term::load(35)->label(), " / ", Term::load(77)->label(), "\n";'   # Greek / Family
ddev drush cr
ddev drush config:status    # only the 4 known search_api_solr.solr_field_type.* items
ddev measure off
```

### Step 7 — the fix (done 2026-10-15)

- **`NodeFieldTrait::accessibleUrl()` and `termChips()`** take a
  `CacheableMetadata $cache`, the same pattern as `RecipeStats::count()`.
  `accessibleUrl()` records the access result (`access('view', NULL, TRUE)`)
  and the URL's metadata (`toUrl()->toString(TRUE)`). `termChips()` also
  records each term.
- **`NodeHooks::addRecipeProps()`** registers the chef and the cuisine term,
  passes `$cache` to both helpers, and applies it at the end of the function.
  Each registration is guarded, because `addCacheableDependency(NULL)` would
  set the card's max-age to 0. This also restores `chef_url`, which a first
  edit had dropped.
- **`ArticlePreprocessTrait::addArticleProps()`** does the same for the chef
  and the topic terms.
- **Checked in the dev profile:** `php -l`, phpcs and phpstan are clean. All
  six frozen URLs return 200. With the asset cache-busting tokens ignored, the
  rendered HTML of `/recipes` and article 68 is byte-identical before and after
  the fix: it adds cache metadata only.

### Step 8 — re-test (you)

The helper and variables from step 3, then the same sequence:

```bash
ddev measure on
ddev exec printenv FLAVOURFUL_PROFILE   # must print: measure

ddev drush cr
echo "before  /recipes card Greek:    $(inpart "$B/recipes" recipe-card 'href="/recipes/moussaka"' Greek)"     # yes
echo "before  /        card Greek:    $(inpart "$B/" recipe-card 'href="/recipes/moussaka"' Greek)"            # yes
echo "before  article  pill Family:   $(inpart "$B$A" article-header tag-pill Family)"                          # yes

ddev drush php:eval '
use Drupal\taxonomy\Entity\Term;
Term::load(35)->setName("Greek ZZTEST")->save();
Term::load(77)->setName("Family ZZTEST")->save();
echo "renamed\n";'

echo "after   /recipes card ZZTEST:   $(inpart "$B/recipes" recipe-card 'href="/recipes/moussaka"' ZZTEST)"    # must be yes now
echo "after   /        card ZZTEST:   $(inpart "$B/" recipe-card 'href="/recipes/moussaka"' ZZTEST)"           # must be yes now
echo "after   article  pill ZZTEST:   $(inpart "$B$A" article-header tag-pill ZZTEST)"                          # must be yes now
```

Then step 4 to rename both terms back and switch the profile off.

**Result, 2026-10-15 (`measure` confirmed):** all three "before" lines `yes`,
and all three "after" lines **`yes`**. Moussaka's card on `/recipes` and `/`,
and article 68's topic pills, showed the new names straight after the save.
**The fix works.**

---

## Verification

| Check | How | Expect | If not |
|---|---|---|---|
| Render cache is on | `printenv` in step 3 | `measure` | Run `ddev measure on`; the test would pass falsely |
| The helper sees the labels | the three "before" lines | `yes` | Fix the helper first, or "after" means nothing |
| The rename really saved | the "after" lines plus step 4's echo | `Greek / Family` after reverting | Re-run step 4 |
| Nothing left behind | step 4 | Names restored, `config:status` unchanged | Rename again by hand at `/taxonomy/term/35/edit` and `/77/edit` |

---

## Effect on the codebase

**Experiment — revert.** Two taxonomy term names in the local DDEV database
change for the length of step 3, and step 4 puts them back. Term names are
content, not config, so `config:status` is unaffected. The plan and the
write-up are additive.

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|

---

## Out of scope

- **Fixing the prop builders.** If the leak is confirmed, the fix is to add
  each referenced entity as a cacheable dependency, and to use
  `toUrl()->toString(TRUE)` so URL metadata is kept. That's a separate change.
- **Renaming chefs,** for the alias reason above.
- **The `accessibleUrl()` access result.** It only matters once roles see
  different chefs or terms, which they don't today.
