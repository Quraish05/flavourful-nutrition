# Plan — views caching, and the stale utility-bar date (Phase 3, Week 9–12 item 2)

> Branch: `perf/views-caching` (from `master` at `74b8af3`) · Created: 2026-10-15 · Last updated: 2026-10-15
>
> **Status: Complete — not yet committed.** A confirmed (`expire = -1`) and fixed: the date is now written client-side (steps 7–8). B: no measurable difference between `tag` and `none` on `/articles`. Both decisions recorded in Part 5 of the write-up: `tag` for every view, and the mega menu keeps `node_list`.

**Goal:** two checks under the measurement profile.

- **A. The stale date:** does Internal Page Cache keep the homepage past
  midnight, so anonymous visitors see yesterday's date?
- **B. What views caching buys:** the TTFB of `/articles` with the `articles`
  view on `tag` caching, against `none`.

Then record both in `docs/actual-outcomes/performance-baseline.md` (B) and
`cache-layers-and-a-real-leak.md` (A).

This file is the working checklist for Notion (*Phase 3 → Week 9–12 → item
2*). **The date fix was added as steps 7–8** once A was confirmed (deviation 4).

**Standing rule for this plan: nothing is committed until you say so.**

---

## Why this plan exists

### A. The utility-bar date may be stale after midnight

`PageHooks::addUtilityDate()` (`web/themes/custom/flavourful/src/Hook/PageHooks.php:201`)
prints today's date in the utility bar of every page, and sets the page's
max-age to the seconds left until midnight. That max-age reaches the dynamic
page cache and the render cache. **It doesn't reach Internal Page Cache,**
which serves every anonymous visitor:

1. `FinishResponseSubscriber::setResponseCacheable()` (`core/lib/Drupal/Core/EventSubscriber/FinishResponseSubscriber.php:250`)
   gives every cacheable response an `Expires` date of 19 Nov 1978, unless one
   is already set.
2. `PageCache::storeResponse()` (`core/modules/page_cache/src/StackMiddleware/PageCache.php:268-293`)
   takes the entry's expiry from `Expires`, and stores one in the past as
   `Cache::PERMANENT`. Its own comment: *"page cache ignores max age"*.

So a page cached at 23:50 can still carry that day's date the next morning.
It only changes when something clears the page: any node save does, because
the mega menu puts `node_list` on every page (`NavigationHooks.php:274-278`).
On a quiet site that can be days.

**What proves it:** the homepage's `cache_page` row has `expire = -1`
(permanent) after it is cached. Waiting past midnight would show it too, but
the row and the code path are enough.

### B. What views caching is worth

Item 2 asks for a tag-or-time decision per view, and that needs a number.
Views' `tag` plugin caches the view's results and rendered output until a list
tag clears them. The saving only shows when the page and dynamic page caches
miss, so those two bins are cleared before each request. The view's own cache
is left alone.

**Decided:**

- **Measurement profile for both.** In the dev profile the cache bins are
  `cache.backend.null`, so nothing is stored to read or compare.
- **Clear only the page and dynamic page bins in B** (`drush cache:clear bin
  page,dynamic_page_cache`). A full `drush cr` would also empty the render
  cache, and measure the cold page instead.
- **One view, `articles`, on `/articles`.** It's the one listing whose page
  shows nothing but that view, so the difference is the view's.

**Rejected:**

- **Nulling bins in `settings.local.php`.** That needs a file edit and a
  restart; clearing two bins per request does the same job.

---

## Steps

| # | Action | Where | Owner | Status |
|---|---|---|---|---|
| 1 | Measurement profile on, then check A: headers in DevTools, and the homepage's `cache_page` row | CLI, admin UI, DevTools | You | Done: **`expire = -1`**, confirmed |
| 2 | B, with `tag`: TTFB of `/articles` × 5, page and dynamic bins cleared before each | `curl` | You | Done: median 92 ms |
| 3 | B, with `none`: switch the view's cache, repeat step 2, switch back | Views UI, CLI | You | Done: median 59 ms, switched back |
| 4 | Revert check and profile off | Configuration synchronization page, CLI | You | Done: 4 known differences |
| 5 | Record A and B in the write-ups | `cache-layers-and-a-real-leak.md`, `performance-baseline.md` | Claude | Done |
| 6 | Tick Notion item 2's two checks | Notion | Claude | Done |
| 7 | Fix the date: remove `addUtilityDate()`, render an empty `<time data-utility-date hidden>`, fill it in `js/utility-date.js` | `PageHooks.php`, `page.html.twig`, `js/utility-date.js`, `flavourful.libraries.yml` | You | Done |
| 8 | Check: the cached homepage holds no date; `X-Drupal-Cache-Max-Age` is no longer a countdown | measurement and dev profiles | Claude | Done |

Each step uses whichever is clearer, the admin UI or the CLI. A CLI line is
used only where there is no admin screen, or where it saves five rounds of
clicking, and each one says what it does.

**Two windows throughout:** a normal window, logged in, for the admin
screens; and a **Guest window**, logged out, for loading and measuring pages.
A logged-in browser bypasses the page cache (`UNCACHEABLE (request policy)`),
so it would measure the wrong thing.

### Step 1 — check A: is the homepage cached permanently? (you)

1. **CLI: switch to the measurement profile.** There's no admin screen for
   this, because it's a container setting plus a restart:

   ```bash
   ddev measure on
   ddev exec printenv FLAVOURFUL_PROFILE   # must print: measure
   ```

2. **Admin UI: clear all caches,** so the test starts from nothing:
   *Configuration → Development → Performance* (`/admin/config/development/performance`)
   → **Clear all caches**.

3. **Guest window + DevTools: cache the homepage and read its headers.**
   Open `https://foodrecipes-drupal.ddev.site:33001/` and reload it once, so
   the second load comes from the cache. Then DevTools (⌥⌘I) → **Network** →
   click the first row (the document) → **Headers** → *Response Headers*:

   | Header | Expect |
   |---|---|
   | `x-drupal-cache` | `HIT` |
   | `expires` | `Sun, 19 Nov 1978 05:00:00 GMT` |
   | `cache-control` | `max-age=900, public` |

   Also note the date shown in the utility bar at the top of the page.

4. **CLI: read the homepage's cache row.** Core has no screen that shows
   cache entries, so this one is a database query. It prints the row's
   `expire` column, and `-1` means "keep until a cache tag clears it":

   ```bash
   ddev drush sqlq "SELECT cid, expire FROM cache_page WHERE cid LIKE 'https://foodrecipes-drupal.ddev.site:33001/:%'"
   ```

**Reading it:** `expire = -1` confirms the stale date. The homepage, with
today's date in it, stays cached past midnight. Any timestamp would mean it
expires on its own, and the date can't go stale.

### Step 2 — B with `tag`: what does `/articles` cost with its view cached? (you)

**CLI,** because each of the five requests needs the page and dynamic page
caches emptied first, and the admin UI can only clear *all* caches. Clearing
all of them would also throw away the view's own cache, which is the thing
being measured.

```bash
B=https://foodrecipes-drupal.ddev.site:33001
curl -sk -o /dev/null "$B/articles"     # one request first, to fill the view's own cache
for i in 1 2 3 4 5; do
  ddev drush cache:clear bin page,dynamic_page_cache -q          # empty only the two page-level caches
  echo "tag  $i $(curl -sk -o /dev/null -w '%{time_starttransfer}' "$B/articles")"   # seconds to first byte
done
```

### Step 3 — B with `none`: the same, with the view's cache off (you)

1. **Admin UI: switch the view's caching off.** *Structure → Views →
   Articles → Edit* (`/admin/structure/views/view/articles`) → open
   **Advanced** on the right → under *Other*, click **Caching: Tag based** →
   choose **None** → **Apply (all displays)** → **Save** (top right). Keep
   the dialog's **For** dropdown on **All displays**: the setting lives on the
   default display, and "This page (override)" would leave a config
   difference behind. Nothing changes until you click Save.
2. **CLI: repeat step 2's block,** changing `tag` to `none` in the `echo` line.
3. **Admin UI: switch it back.** The same path, **For: All displays**, choose
   **Tag based** → **Apply (all displays)** → **Save**. The *Caching* line should read **Tag based** again.

### Step 4 — check nothing is left behind (you)

1. **Admin UI: compare active config with `config/sync`.** *Configuration →
   Development → Configuration synchronization* (`/admin/config/development/configuration`).
   Expect only the four `search_api_solr.solr_field_type.*` items. If
   `views.view.articles` is listed, step 3's switch back wasn't saved.
2. **CLI: back to the dev profile.**

   ```bash
   ddev measure off
   ```

### Steps 7–8 — the date fix (2026-10-16)

**Step 7 (you):** `PageHooks::addUtilityDate()`, its call, and the
`DateFormatterInterface` it alone used are removed. `page.html.twig` renders
`<time class="utility-bar__date" data-utility-date hidden></time>` and attaches
`flavourful/utility-date`. The new `js/utility-date.js` writes the visitor's
date ("Friday, 16 October 2026") and the `datetime` attribute, then unhides
the element. phpcs flagged one extra blank line, fixed with `phpcbf`.

**Step 8 (Claude), results:**

| Check | Profile | Result |
|---|---|---|
| Homepage still page-cached | measurement | `x-drupal-cache: HIT` |
| Cached HTML holds no date | measurement | Only `<time class="utility-bar__date" data-utility-date hidden></time>`; no "Weekday, day Month 2026" text |
| Midnight max-age gone | dev | `X-Drupal-Cache-Max-Age: -1 (Permanent)` on `/`, `/articles`, `/recipes`, both nodes. `/recipe-search` stays `0`, for item 5's reason |
| Nothing broken | dev | All six frozen URLs 200; date visible in the browser; `php -l`, phpcs, phpstan clean |

---

## Verification

| Check | How | Expect | If not |
|---|---|---|---|
| Profile on | `printenv` | `measure` | Run `ddev measure on`: in the dev profile `cache_page` stays empty |
| A: the page was cached | step 1's headers | `x-drupal-cache: HIT` | Without a `HIT` there's no row to read. Check `curl` sends no cookie |
| A: the row exists | step 1's query | One row for `$B/:` | Run `ddev drush sqlq "SELECT cid FROM cache_page LIMIT 5"` to see the cid format |
| B: the page cache was bypassed | add `-D -` to one request | `x-drupal-cache: MISS`, `x-drupal-dynamic-cache: MISS` | The bins didn't clear. Check the `drush cc bin` output |
| B: the view really switched | the *Caching* line in the Views UI | **None**, then **Tag based** | It wasn't saved: click **Save**, not just Apply |
| Nothing left behind | step 4 | Only the 4 known differences | Switch the view back to **Tag based** and Save |

---

## Effect on the codebase

**Read-only (A), Experiment — revert (B).** A reads one database row. B
changes the `articles` view's cache plugin in active config for the length of
step 3, and step 3 sets it back. `config/sync` is never touched, and step 4's
`config:status` proves it.

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | 1–4 | All-CLI steps | You asked for the admin UI where it's clearer, before any step ran | Rewrote steps 1–4 in place: DevTools for headers, Views UI for the cache switch, the Configuration synchronization page for the revert check. The CLI stays for the profile switch, the cache-row query and the two-bin clear, which have no admin screen |
| 2 | 1 | A homepage row in `cache_page` after the Guest-window loads | The query printed nothing: only `/articles` was in the page cache. The homepage was likely loaded before *Clear all caches*, or in a logged-in window | Claude cached `/` with two anonymous `curl` requests (nothing changed) and read the row: `expire = -1`. `/articles`, cached by the header check, was `-1` too |
| 3 | 2–3 | Proof each B request missed the page caches | The runs didn't show headers, and 34–47 ms is near a warm `HIT` | Checked one request under the profile after clearing the two bins: `MISS` / `MISS`. The B numbers stand |
| 4 | — | The date fix as a separate change | A was confirmed, and you asked for the fix steps | Added steps 7–8 to this plan, so one PR carries the finding and the fix. The date is now in the visitor's timezone, not Asia/Kolkata |

---

## Out of scope

- **The mega menu's `node_list`.** Whether every node save should clear every
  page is a decision, recorded in Notion, not tested here.
- **Time-based caching on any view.** Step 3 compares `tag` with `none`, which
  is the cost of no caching. A `time` setting only changes when the cache
  refreshes, not what it saves.
