# Plan — profile /recipe-search with XHGui and name its three most expensive functions (Phase 3, Week 8–10 item 4)

> Branch: `perf/measurement-profile` · Created: 2026-10-11 · Last updated: 2026-10-12
>
> **Status: Complete — nothing committed yet.** Result: `performance-baseline.md` → *XHGui profile of /recipe-search*.

**Goal:** XHGui profiles of a facet page being built on the server, with the
three most expensive functions named and explained.

This file is the working checklist between the plan in Notion (*Phase 3 →
Week 8–10 → item 4*, fourth checkbox) and the record in
[`docs/actual-outcomes/performance-baseline.md`](../actual-outcomes/performance-baseline.md).
It follows on from [`plan-perf-facet-click-trace.md`](plan-perf-facet-click-trace.md),
which found that a facet click costs **server time** (164–253 ms for a new
filter combination), not browser time. This plan splits that server time into
functions.

**Standing rule for this plan: nothing is committed until you say so.**

---

## Why this plan exists

The facet trace says *where* the time goes: the server. XHGui says *what* the
server spends it on, down to the PHP function. This is the "trace it to a
handler" skill item 4 names, done once on purpose.

**Facts the plan rests on (checked 2026-10-11):**

- **XHGui is available.** DDEV v1.25.3, `xhprof_mode: xhgui` (the default).
  The service is stopped until `ddev xhgui on`. The UI is at
  `https://foodrecipes-drupal.ddev.site:8142`.
- **Profiling is on for every PHP request while XHGui is on,** through DDEV's
  prepend file `.ddev/xhprof/xhprof_prepend.php` (DDEV-generated, unchanged).
  It slows every request, so it is switched off again at the end.
- **Only a page-cache `MISS` is worth profiling.** For anonymous visitors,
  Internal Page Cache stores each facet URL after its first request. A repeat
  is a `HIT` in 11–21 ms, and Drupal barely runs. The expensive case, the one a
  real click pays, is the **first** request for a filter combination.

**Decided:**

- **Profile under the measurement profile** (`ddev measure on`), so the render
  cache, dynamic page cache and aggregation behave as `config/sync` sets them.
  The dev profile's null cache bins would make every request rebuild
  everything.
- **Warm the shared parts first, then profile unseen URLs.** `ddev measure on`
  flushes all caches. Two anonymous warm-up requests then fill the render cache
  for what every page shares (header, menus, blocks). Each profiled request is
  a facet URL nobody has requested since the flush, so it is a page-cache
  `MISS` with a warm render cache: what a real visitor's click costs.
- **Three profiled requests, three different filters:**
  - `diet_type:62` (Vegetarian, 5 results)
  - `diet_type:59` (Vegan, 3 results)
  - `diet_type:60` (Non Vegetarian, 12 results)

  The functions that stay at the top across all three are the answer. A
  function that tops only one is noise.
- **One page-cache `HIT` profile for contrast** (a repeat of `diet_type:62`).
  It shows how little runs when the cache answers.
- **Rank two ways, and report both:**
  - **Exclusive wall time** (the function's own time): where the time
    actually burns, for example a database driver, the HTTP call to Solr, or
    Twig.
  - **Inclusive wall time** for application-level functions: which Drupal or
    Search API step owns that burn. Framework wrappers such as `index.php`,
    `DrupalKernel::handle` and `HttpKernel` are excluded, because they are
    inclusive of everything.
- **XHProf times are inflated by the profiler itself.** They are used to rank
  functions and as shares of the request, and are never compared with the
  DevTools trace's server times.

**Rejected:**

- **Profiling a page-cache `HIT`** as the result. It would show the cache
  middleware and nothing else.
- **Profiling right after `drush cr`** with no warm-up. Every render-cache entry
  would be cold, so the profile would describe a post-deploy request, not a
  click.
- **Profiling logged in.** A logged-in visitor is a different path (the dynamic
  page cache refuses this page). It is worth a profile of its own, but it isn't
  this checkbox.

---

## Who does what

Everything runs inside DDEV from the command line, so Claude can do every step.
Step 6 has an optional part for you: opening the XHGui UI to look at the same
runs.

## Steps

| # | Action | Where | Owner | Status |
|---|---|---|---|---|
| 1 | Measurement profile on (this also flushes all caches) | `ddev measure on` | Claude | Done (by the developer) |
| 2 | Warm the shared render cache with two anonymous requests | `curl` | Claude | Done (by the developer) |
| 3 | Start XHGui and check profiling is on | `ddev xhgui on` / `status` | Claude | Done (by the developer) |
| 4 | Make three profiled requests to unseen facet URLs, each a `MISS`, plus one `HIT` | `curl` | Claude | Done (by the developer, 12:10:52) |
| 5 | Stop XHGui | `ddev xhgui off` | Claude | Done |
| 6 | Pull the four runs and rank functions by exclusive and inclusive wall time | XHGui storage, or the UI at `:8142` | Claude; you optionally view the UI | Done |
| 7 | Write the results into the baseline doc, then switch back to the dev profile | `performance-baseline.md`, `ddev measure off` | Claude | Done |
| 8 | Tick the Notion checkbox, with evidence | Notion Phase 3, item 4 | Claude | Not started |

### Step 1 — measurement profile on

```bash
ddev measure on
```

`theme:dev off` runs `drupal_flush_all_caches()`, so every cache is now empty.

### Step 2 — warm the shared parts

Run from the repo root, after step 1 and before step 3:

```bash
B=https://foodrecipes-drupal.ddev.site:33001
curl -sk -o /dev/null -w "warm-up unfiltered   %{http_code}  ttfb %{time_starttransfer}s\n" "$B/recipe-search"
curl -sk -o /dev/null -w "warm-up cuisine:31   %{http_code}  ttfb %{time_starttransfer}s\n" "$B/recipe-search?f%5B0%5D=recipe_cuisine_type%3A31"
```

Expect `200` on both, with the first the slowest, because `ddev measure on`
left every cache cold.

These URLs are **not** among the profiled ones. They exist only to fill the
render cache for the header, menus and blocks that every search page shares.
`curl` sends no cookies, so both are anonymous requests, like the profiled
ones. Don't run `drush cr` or `ddev measure on` between this step and step 4,
because either empties the caches just warmed.

### Step 3 — XHGui on

```bash
ddev xhgui on
ddev xhgui status
```

Expect "XHProf is enabled" and "XHGui is enabled".

### Step 4 — the profiled requests

Run from the repo root, after step 3 (XHGui on). It sets `B` again, so it also
works in a fresh terminal:

**Don't test this command early.** Once steps 1 and 3 are done, every request
is cached and profiled, so a trial run spoils the URL it hits. Check the state
first with `ls .ddev/config.measure.yaml && ddev xhgui status`.

```bash
B=https://foodrecipes-drupal.ddev.site:33001
profile() {  # $1 = label, $2 = path
  curl -sk -o /dev/null -D - "$B$2" \
    -w "ttfb %{time_starttransfer}s\n" \
  | awk -v l="$1" 'tolower($0) ~ /^x-drupal-cache:/ {gsub("\r",""); c=$2} /^ttfb/ {printf "%-22s %-5s %s\n", l, c, $0}'
}
profile "Vegetarian (62)"     "/recipe-search?f%5B0%5D=diet_type%3A62"
profile "Vegan (59)"          "/recipe-search?f%5B0%5D=diet_type%3A59"
profile "Non Vegetarian (60)" "/recipe-search?f%5B0%5D=diet_type%3A60"
profile "repeat 62 (contrast)" "/recipe-search?f%5B0%5D=diet_type%3A62"
```

Expected output: four lines of label, cache result and TTFB.

```
Vegetarian (62)        MISS  ttfb 0.2…s
Vegan (59)             MISS  ttfb 0.2…s
Non Vegetarian (60)    MISS  ttfb 0.2…s
repeat 62 (contrast)   HIT   ttfb 0.0…s
```

- **`MISS` on the first three, then `HIT` on the repeat.** A `HIT` on any of
  the first three means that URL was requested after the flush, so its run is
  not a real build. Pick a different term ID and log it below.
- **The TTFBs include XHProf's overhead,** so they are slower than without
  profiling. They are not compared with any other series.
- **Running it again later:** start from step 1. `ddev measure on` flushes the
  page cache, so these three URLs are unseen again. Without step 1 they are
  already cached, and all four lines come back `HIT`.

### Step 5 — XHGui off

```bash
ddev xhgui off
```

Profiles already stored stay readable. Only new requests stop being profiled.

### Step 6 — read the runs

XHGui keeps runs in DDEV's db container, database `xhgui`, table `results`
(checked 2026-10-12). Each row has the request `url`, `request_ts`, `main_wt`
(wall time, µs), `main_pmu` (peak memory) and the full call graph as JSON in
`profile`. The rows survive `ddev xhgui off`, but not `ddev delete`. To list
the runs:

```bash
ddev mysql xhgui -e "SELECT id, url, FROM_UNIXTIME(request_ts) ts, main_wt, main_pmu FROM results ORDER BY request_ts"
```

CLI PHP is profiled too, so `drush` commands run while XHGui is on show up as
rows. Pick the runs by URL and timestamp. Each run's `profile` JSON maps
`parent==>child` to `{ct, wt, cpu, mu, pmu}`. A function's inclusive time is
the sum of `wt` over its incoming edges, and its exclusive time is that minus
the inclusive time of its children. Claude reads the four runs by URL and
ranks the functions:

- **Per run:** total wall time, peak memory, and the function count.
- **Top 10 by exclusive wall time,** with call counts.
- **Top 10 application-level functions by inclusive wall time,** framework
  wrappers excluded.
- **The three most expensive functions:** the ones near the top of both
  rankings across all three `MISS` runs, each with what it is and which module
  owns it.

**Optional for you:** run `ddev xhgui launch` (opens
`https://foodrecipes-drupal.ddev.site:8142`) to see the same runs. Do this
**before** step 7: step 7 turns XHGui off, and the UI service stops with it.
Click a run, then sort *Exclusive Wall Time*.

### Step 7 — doc, then dev profile back

Add **"XHGui profile of /recipe-search"** to the baseline doc:

- **The setup line:** commit, measurement profile, warm-up, URLs, XHProf on.
- **A per-run table:** URL, `x-drupal-cache`, wall time, peak memory.
- **The top functions:** exclusive and inclusive, from the median `MISS` run,
  with how consistent they were across all three.
- **The three named functions,** each with what it is and which module owns it.
- **"What it says"**, with the caveat that XHProf inflates times.

Then:

```bash
ddev measure off
```

Expect `MISS`, 48 `THEME DEBUG` blocks on `/articles`, and 4 config
differences.

### Step 8 — Notion

- **Tick item 4's fourth checkbox** with the three functions, their share of
  the request, and the evidence.
- **Add this plan to item 4's `**Plan:**` line,** marked "not yet pushed"
  until it is.

---

## Verification

| Check | How | Expect | If not |
|---|---|---|---|
| Profiling on | `ddev xhgui status` | XHProf and XHGui enabled | `ddev xhgui on` again |
| Profiled requests were real builds | step 4 headers | `MISS` ×3 then `HIT` | A `HIT` means the URL was warm: use a different term ID |
| Runs were stored | step 6 | Four runs whose URLs match step 4 | Check the prepend file still sends runs to XHGui (`xhprof_mode: xhgui`) |
| Ranking is stable | step 6 | The same top functions in all three `MISS` runs | Report the variation; one-run toppers are noise |
| `HIT` run is small | step 6 | Total wall time a small fraction of a `MISS` run | Not a page-cache hit: check the header was `HIT` |
| XHGui off | `ddev xhgui status` | Disabled | `ddev xhgui off` |
| Dev profile back | after step 7 | `MISS`, 48 `THEME DEBUG`, 4 config differences | `ddev measure off` again |

---

## Effect on the codebase

**Read-only.** Profiles are stored in XHGui's storage inside DDEV, and no repo
file changes except the baseline doc and this plan. `ddev xhgui on/off` and
`ddev measure on/off` are reversible, and both are switched back at the end.

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | 4 | `diet_type:62` unseen until step 4 | On 2026-10-12, at about 12:09, Claude ran a format test of step 4's command against `diet_type:62`, assuming the dev profile was on. The measurement profile and XHGui were both already on, so the test cached that URL and created **two stray XHGui runs** (one `MISS`, one `HIT`) | Cleared only the page-cache bin (`ddev drush cache:clear bin page`, 12:10:02; `cache_page` back to 0 rows), so all three URLs are unseen again and the warm render cache is kept. **In step 6, ignore any `diet_type:62` run from before 12:10:02.** Rule for next time: check `ls .ddev/config.measure.yaml` and `ddev xhgui status` before making any test request |
| 2 | 4–6 | Four runs | Seven extra rows besides the four: the two stray runs from deviation 1, plus three `drush` commands (CLI PHP is profiled too). The four runs are at 12:10:52: 62 `MISS` 245.0 ms, 59 `MISS` 143.8 ms, 60 `MISS` 173.3 ms, 62 `HIT` 3.0 ms | Picked by URL and timestamp. The storage location and the inclusive/exclusive computation are now written into step 6 |

---

## Out of scope

- **Fixing anything,** including the uncacheable dynamic-cache result,
  `show_numbers: true` and Solr query count. Those are Week 9–12; this profile
  is their "before".
- **Counting Solr queries per page load** (Finding 5). The profile may show the
  calls, but counting and costing them is a Week 9–12 item of its own.
- **A logged-in profile** of the same page.
