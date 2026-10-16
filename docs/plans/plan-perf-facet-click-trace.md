# Plan — record a facet click on /recipe-search and attribute its long tasks (Phase 3, Week 8–10 item 4)

> Branch: `perf/measurement-profile` · Created: 2026-10-10 · Last updated: 2026-10-16
>
> **Status: Complete — merged in PR #53 (`a829d0d`, 2026-10-12).** Result: `performance-baseline.md` → *DevTools traces → Facet click on /recipe-search*.

**Goal:** DevTools recordings of one facet click on `/recipe-search`, with every
long task (over 50 ms) listed and attributed to the script that owns it.

This file is the working checklist between the plan in Notion (*Phase 3 →
Week 8–10 → item 4*, second checkbox) and the record in
[`docs/actual-outcomes/performance-baseline.md`](../actual-outcomes/performance-baseline.md).
It reuses the setup proven in
[`plan-perf-article-node-trace.md`](plan-perf-article-node-trace.md). Read that
plan's deviation log first: logged-in browsers and missing CPU throttling cost
two recordings there.

**Standing rule for this plan: nothing is committed until you say so.**

---

## Why this plan exists

`/recipe-search` is the only page where a click does real work. This
recording is how "the search feels slow" becomes "this task, in this file, for
this many milliseconds".

**What a facet click actually is on this site (checked 2026-10-10):**

1. The facets are checkbox widgets (`widget.type: checkbox`) for `diet_type`
   and `recipe_cuisine_type`, both with `show_numbers: true`.
2. Ticking a checkbox runs a jQuery `change` handler in
   `facets/js/checkbox-widget.js`, which triggers `facets_filter`.
3. `facets/js/base-widget.js` handles that event and sets
   `window.location = url`.
4. **So the click is a full page navigation,** not an in-place update.
   `views.view.recipe_search` has no `use_ajax`.
5. The new page is **uncacheable for everyone:** `X-Drupal-Cache-Max-Age: 0`
   and `X-Drupal-Dynamic-Cache: UNCACHEABLE (poor cacheability)`, with
   `facets_filter:f` among its cache contexts. Every click makes Drupal rebuild
   the page and query Solr.

That splits the recording into two phases, and the plan reports each one
separately:

| Phase | From → to | Who owns the work |
|---|---|---|
| **A. The click** | Pointer down → navigation starts | The facets `change` handler and anything listening for `facets_filter` / `facets_filtering` |
| **B. The new page** | Navigation start → the new page is idle | Server time (Drupal + Solr) as TTFB, then parsing, script execution and the facets widget rebuilding links into checkboxes |

**Decided:**

- **Click:** *Diet type → Vegetarian* (`diet_type:62`, 5 results), from an
  unfiltered `/recipe-search` every time. The resulting URL is
  `/recipe-search?f%5B0%5D=diet_type%3A62`.
- **Same conditions as the article trace:** DDEV with `ddev measure on`,
  logged out, CPU 4×, Slow 4G, browser cache disabled.
- **The record button, not *Record and reload*.** The click has to happen
  during the recording, so use ● **Record**, click the checkbox, wait for the
  new page to go idle, then stop. Chrome keeps recording across the
  navigation.
- **Five recordings, one click each,** each saved as `run1` … `run5`.
- **Attribution by function name.** Under the measurement profile, scripts are
  bundled into `js_*.js` aggregates, but Drupal's minifier (Peast `Compact`)
  keeps function names. Long tasks are attributed through their call stacks,
  for example `checkbox-widget.js` → `Drupal.facets.makeCheckbox`, and checked
  against the source files in `web/`.

**Rejected:**

- **Recording in the dev profile for the timings.** It has unaggregated scripts
  (easier attribution) but no caches, so the timings would describe the
  profile. The dev profile is allowed only as a fallback, for attribution
  alone, if a function name can't be resolved (step 6).
- **Reading INP for this interaction as the result.** The click unloads the
  page, so the interaction's INP may be reported only partially, or not at all.
  INP on this page was already measured under item 1. Here the deliverables
  are the long tasks and their owners.

---

## Who does what

Steps 3–4 are browser UI work, so they're **yours**. Claude does steps 1–2 and
5–8.

## Steps

| # | Action | Where | Owner | Status |
|---|---|---|---|---|
| 1 | Switch to the measurement profile and check Solr is up | `ddev measure on` | Claude | Done (by the developer) |
| 2 | Check the page and the facet before recording | `curl` | Claude | Done |
| 3 | Set up a logged-out Guest window with throttling | Chrome 153 | You | Done |
| 4 | Record five facet clicks, saving each trace | Performance panel → ● Record | You | Done (deviation 1) |
| 5 | Check each trace's conditions, and list and attribute every long task | the five trace files | Claude | Done |
| 6 | *Only if a function name can't be resolved:* one dev-profile recording, for attribution only | Performance panel | You, then Claude | Skipped: no long tasks, so nothing to attribute |
| 7 | Write the results into the baseline doc, then switch back to the dev profile | `performance-baseline.md`, `ddev measure off` | Claude | Done |
| 8 | Tick the Notion checkbox, with evidence and the corrected wording | Notion Phase 3, item 4 | Claude | Done. Also corrected "uncacheable for everyone" in item 6, Week 9–12 and Finding 5 |

### Step 1 — measurement profile on, Solr up

```bash
ddev measure on
ddev describe | grep -i solr
```

Solr must show as running. Without it, `/recipe-search` returns no results and
the facets don't render.

### Step 2 — check before recording

```bash
B=https://foodrecipes-drupal.ddev.site:33001
curl -sk -o /dev/null -D - "$B/recipe-search" | grep -iE '^x-drupal-(cache|dynamic-cache):'
curl -sk "$B/recipe-search?f%5B0%5D=diet_type%3A62" | grep -c 'class="views-row'
```

- **Expect the page cache *not* to hit.** The page is uncacheable for everyone.
  That's the finding, not a setup fault.
- **Expect 5 rows** for the Vegetarian filter. Checked 2026-10-10: Solr `OK`
  in `ddev describe`, and 5 `views-row` elements for `diet_type:62`.

### Step 3 — browser setup

1. Open a **Guest window**: Chrome profile menu → **Guest**. It always starts
   with no cookies.
2. Open DevTools (⌥⌘I). In **Network**, tick **Disable cache**.
3. In **Performance**, open **Environment settings** (or the panel's ⚙ gear)
   and set **CPU: 4× slowdown** and **Network: Slow 4G**. Check for the ⚠️ on
   the Performance and Network tab names.
4. Load `https://foodrecipes-drupal.ddev.site:33001/recipe-search`, with a
   single slash after the port.
5. **Check you're logged out with cookies, not cache headers.** This page
   never returns `HIT` for anyone, so the article-trace check can't work here.
   Open **Application → Cookies → the site**: there must be **no `SSESS…`
   cookie**.

### Step 4 — five recordings

For each run:

1. Load the unfiltered `/recipe-search` and **wait until it is fully idle**:
   no spinner, and the facet checkboxes drawn (the widget turns links into
   checkboxes with JavaScript).
2. Click ● **Record**, the plain record button, not ⟳.
3. Click the **Vegetarian** checkbox under **Diet type**.
4. Wait until the filtered page has loaded and shows 5 recipes, then about
   two more seconds.
5. Click **Stop**.
6. Click ↓ **Save trace**, and name it `facet-run1` … `facet-run5`.

Send all five files.

### Step 5 — analysis (Claude)

For each trace:

- **Conditions:** `cpuThrottling: 4` and Slow 4G in the metadata, and no
  session cookie on the requests. A logged-out response to this page still
  isn't a page-cache `HIT`, so the conditions check is metadata plus the
  absence of a `SSESS` cookie in the request headers, where the trace records
  them.
- **Phase A, the click:** the interaction's event dispatch (`pointerdown` →
  `click` → `change`), each task over 50 ms, and its owning function and
  source file.
- **Phase B, the new page:** TTFB (server time: Drupal plus Solr, uncached),
  LCP and its element, then every task over 50 ms with its owning function and
  source file.
- **Summary per run:** the number of long tasks, total blocking time over
  50 ms per phase, and the top owner.

The median run is the one with the median total long-task time. All five runs
go in the doc. The median run's trace is kept, gzipped, as
`docs/actual-outcomes/performance-baseline/traces/facet-click-2026-10-DD.json.gz`.

### Step 6 — only if needed

If a long task's call stack shows only an aggregate (`js_….js`) with a name
that can't be matched to a source file, make one recording in the dev profile
(`ddev measure off`, same click) and use it **only** to name the owner. Its
timings are discarded.

### Step 7 — doc, then dev profile back

Add **"DevTools traces → Facet click on /recipe-search"** to the baseline doc:

- **The setup line.**
- **A five-run table:** columns for phase A long tasks, phase B TTFB, LCP,
  phase B long tasks and total long-task time.
- **A long-task table for the median run:** phase, start, duration, owning
  function, source file.
- **A link to the trace.**
- **A short "what it says".**

Then run `ddev measure off`, and check: `MISS`, 48 `THEME DEBUG` blocks on
`/articles`, 4 config differences.

### Step 8 — Notion

- **Tick item 4's second checkbox,** with the median run's long tasks and
  owners as evidence.
- **Reword it** so the checkbox says the click is a navigation, with phase A
  (handler) and phase B (new page) reported separately.
- **Add this plan's `**Plan:**` line** to item 4, marked "not yet pushed"
  until it is.

---

## Verification

| Check | How | Expect | If not |
|---|---|---|---|
| Solr up | `ddev describe` | Solr running | `ddev restart`. Without Solr the page has no facets |
| Facet result | step 2's second `curl` | 5 rows | Recheck the term: `diet_type:62` is Vegetarian |
| Logged out | Application → Cookies | No `SSESS…` cookie | Close the window; use a Guest window |
| Throttled | trace metadata | `cpuThrottling: 4`, `networkThrottling: Slow 4G` | Set both in Environment settings and re-record |
| The click is in the trace | trace events | One `click`/`change` dispatch, then a `navigationStart` for the `diet_type:62` URL | Recording started too late or stopped too early |
| New page finished | trace | An LCP candidate on the new page, followed by about 2 s with no network activity | Wait longer before stopping |
| Owners named | step 5 | Every long task has a source file | Step 6 |
| Dev profile back | after step 7 | `MISS`, 48 `THEME DEBUG`, 4 config differences | Re-run `ddev measure off` |

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | 4 | Five separate recordings of the same Vegetarian click from an unfiltered page | One file, `trace-facets` (19 MB): a single ● Record session with three facet clicks (Vegetarian on, Vegetarian off, Vegan on) plus two full-text searches and a visit to `/`. Conditions met: metadata `cpuThrottling: 4`, Slow 4G; logged out (every response `cache-control: max-age=900, public`, none `UNCACHEABLE (request policy)`); measurement profile on (aggregated `js_…`) | Accepted. The question was whether long tasks exist and who owns them. No task reached 50 ms in any of the six page loads (max 37.6 ms, rendering), so repeating the same click could not change the answer. Kept, gzipped, as `traces/facet-click-2026-10-10.json.gz` (2.3 MB, identical after `gunzip`) |
| 2 | Why | The new page is uncacheable for everyone (`max-age 0`) | Only the dynamic page cache refuses it. Internal Page Cache ignores the bubbled max-age: the first anonymous request per filter combination is a `MISS` (about 200 ms), and repeats are a `HIT` (11–21 ms), sent with `cache-control: max-age=900, public`. Every facet URL in the trace was a `MISS` only because it was its first request | Recorded in the baseline doc. The plan's "uncacheable for everyone" premise is corrected in Notion (step 8). Staleness of cached facet pages is a Week 9–12 question |
| 3 | 5 | TTFB carries the server time | DevTools' 562.5 ms throttling latency hides a server time of 164–253 ms inside a TTFB of about 600 ms | Server time is read as `receiveHeadersStart − sendEnd`, and TTFB is reported separately |

---

## Out of scope

- **Fixing anything,** including the uncacheable search page and
  `show_numbers: true`. Those are Week 9–12 items, and this trace is their
  "before".
- **Counting Solr queries per page load.** That is Week 9–12 and Finding 5,
  using XHGui or Solr logs. This trace shows only the total server time, as
  TTFB.
- **Checkbox 3** (the cost of `recipe-interactions.js` on pages that don't use
  it) and **checkbox 4** (XHGui).
- **The homepage image.**
