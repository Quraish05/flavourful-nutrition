# Plan — record an article-node trace and its LCP breakdown (Phase 3, Week 8–10 item 4)

> Branch: `perf/measurement-profile` · Created: 2026-10-09 · Last updated: 2026-10-09
>
> **Status: Complete — all 9 steps done, nothing committed yet.** Result: `performance-baseline.md` → *DevTools traces → Article node*.

**Goal:** one DevTools Performance recording of an article-node reload under the
measurement profile, saved next to the baseline doc, with its LCP breakdown
written down.

This file is the working checklist between the plan in Notion (*Phase 3 →
Week 8–10 → item 4*, first checkbox) and the record in
[`docs/actual-outcomes/performance-baseline.md`](../actual-outcomes/performance-baseline.md).
It depends on [`plan-perf-measurement-profile.md`](plan-perf-measurement-profile.md),
which built `ddev measure on|off`.

**Standing rule for this plan: nothing is committed until you say so.**

---

## Why this plan exists

The Performance panel's LCP breakdown is how "LCP is 3 s" becomes "2 s of that
is render delay". This is the first time it gets recorded on this site, so the
settings and the reading need writing down once, exactly.

**Decided:**

- **Page:** `/articles/four-years-they-let-me-make-carbonara`. Checked
  2026-10-09: 200. Its LCP element is `p.article-header__deck`, which is text
  (item 1, verified 2026-10-07). The only `<img>` is the 400×400
  `recipe_hero_800` hero, which is `loading="lazy"`.
- **A text LCP has only two of the four sub-parts.** DevTools splits LCP into
  TTFB, resource load delay, resource load duration and element render delay.
  The middle two exist only when the LCP element is an image. Record them as
  `n/a (text LCP)`, never as `0`.
- **Throttling:** DevTools CPU 4× and network *Slow 4G*. That is the closest
  match to the baseline doc's Lighthouse mobile preset, but it is **applied**
  throttling, while Lighthouse's is **simulated**. The trace's timings are their
  own series. Never compare them with Lighthouse numbers.
- **Five recordings, keep the median.** Single recordings are noisy (item 4's
  callout). Note all five, and save the trace of the median run only.
- **Server cache warm, browser cache cold.** The page cache is primed with two
  anonymous requests first, so TTFB reflects a page-cache `HIT`. DevTools'
  *Disable cache* is on, so every run is a first view for the browser.

**Rejected:**

- **Measuring in the dev profile.** Caches are off and assets unaggregated
  there, so the trace would measure the profile, not the site (item 3).
- **A logged-in window.** The page cache only serves requests with no session
  cookie. A logged-in TTFB is a different, slower path.
- **Reporting a single run.** One run proves nothing.

---

## Who does what

The recording is browser UI work, so steps 3–6 are **yours**. Claude can do
steps 1, 2, 7 (once you hand over the numbers), 8 and 9.

## Steps

| # | Action | Where | Owner | Status |
|---|---|---|---|---|
| 1 | Switch to the measurement profile | `ddev measure on` | Claude or you | Done |
| 2 | Prime the page cache and confirm a `HIT` | `curl` | Claude or you | Done |
| 3 | Set up an incognito window and the Performance panel | Chrome 153 | You | Done |
| 4 | Record and reload, five times; note the breakdown each time | Performance panel | You | Done |
| 5 | Save every run's trace; the median is kept | Performance panel → *Save trace* | You, then Claude | Done |
| 6 | Hand the five readings and the file to Claude | — | You | Done |
| 7 | Write the results into the baseline doc | `performance-baseline.md` | Claude | Done |
| 8 | Switch back to the dev profile | `ddev measure off` | Claude or you | Done |
| 9 | Tick the Notion checkbox, with evidence and the corrected wording | Notion Phase 3, item 4 | Claude | Done |

### Step 1 — measurement profile on

```bash
ddev measure on
```

### Step 2 — prime the page cache

```bash
U=https://foodrecipes-drupal.ddev.site:33001/articles/four-years-they-let-me-make-carbonara
curl -sk -o /dev/null "$U"
curl -sk -o /dev/null -D - "$U" | grep -iE '^x-drupal-cache:'
```

Expect `x-drupal-cache: HIT` on the second request. A `MISS` means the profile
is not on, so go back to step 1.

### Step 3 — browser setup

1. Close **every** Incognito window (Window menu), then open **one new
   Incognito window** (⇧⌘N) and don't log in. Chrome shares cookies across all
   open Incognito windows, so one logged-in window logs them all in.
2. Open DevTools (⌥⌘I). In the **Network** panel, tick **Disable cache**.
   It only applies while DevTools is open.
3. Open the **Performance** panel and its capture settings (the gear icon):
   - **CPU throttling:** 4× slowdown.
   - **Network:** Slow 4G. Open *Network conditions → Throttling profiles*
     and write down the download, upload and latency values it shows. The
     baseline doc needs the exact values, not just the preset name.
   - **Screenshots:** on.
4. Paste the article URL into the address bar, but do not load it yet.
5. **Check you are logged out.** Load the page once, then open Network → the
   document request → Headers: `x-drupal-cache` must be `HIT`. If it says
   `UNCACHEABLE (request policy)`, the window has a session cookie. Check
   Application → Cookies for a `SESS…` cookie, close every Incognito window,
   and open a fresh one.
6. **Check throttling is applied.** Chrome shows a ⚠️ next to the Performance
   and Network tab names while throttling is on. If TTFB comes out in tens of
   milliseconds rather than hundreds, it isn't on.

### Step 4 — five recordings

For each run:

1. Click **Record and reload** (the circular-arrow button). Wait until the
   recording stops by itself. One reload per recording: don't reload again
   while it is recording.
2. In the **Insights** sidebar, open **LCP breakdown**. Note:
   - the LCP element (expect `p.article-header__deck`)
   - LCP total
   - Time to first byte
   - Resource load delay (expect absent, because the LCP is text)
   - Resource load duration (expect absent)
   - Element render delay
3. Note the **CLS** shown in the same sidebar while you are there. It's free
   with the recording.

### Step 5 — save every run, keep the median

1. After each ⟳ recording in step 4, click **Save trace** (the ↓ icon in the
   Performance toolbar) and name it `run1` … `run5`. Leave script content and
   source maps off if offered. Downloads is fine as a holding place.
2. Hand all five to Claude (step 6). Claude reads each trace's LCP breakdown,
   checks it was logged out (`x-drupal-cache: HIT`) and throttled (TTFB in
   hundreds of milliseconds), and sorts the runs by LCP total. The 3rd of
   five is the median.
3. The median run's trace is moved to
   `docs/actual-outcomes/performance-baseline/traces/article-node-2026-10-DD.json`,
   with the date in place of `DD`, and gzipped if over about 5 MB. The other
   four are discarded once their readings are in the doc.

Use the Performance tab's ⟳ **Record and reload**, not the Network tab's
record button: Network records only requests (a `.har`), with no main-thread
work and no LCP breakdown.

### Step 6 — hand over

Send the five trace files and the network values from step 3. Readings typed
by hand are optional: Claude reads them from the traces.

### Step 7 — baseline doc

Add a section **"DevTools traces"** to `performance-baseline.md` with:

- **One header line with the setup:** URL, commit SHA, `ddev measure on`,
  Chrome version, CPU 4×, the network values, and "server cache warm (`HIT`),
  browser cache disabled".
- **A table:** one row per run plus a median row. Columns: LCP element, LCP,
  TTFB, load delay, load duration, render delay, CLS.
- **A link to the trace file.**
- **One sentence** saying these are applied-throttling DevTools timings, a
  separate series from the Lighthouse numbers.

### Step 8 — dev profile back

```bash
ddev measure off
```

Expect 48 `THEME DEBUG` blocks back on `/articles`, and
`ddev drush config:status` showing only the four known Solr differences.

### Step 9 — Notion

- **Tick item 4's first checkbox,** with the median breakdown as evidence.
- **Correct its wording** from "note the four LCP sub-parts" to "note the LCP
  sub-parts that apply". A text LCP has only TTFB and render delay.

---

## Verification

| Check | How | Expect | If not |
|---|---|---|---|
| Profile is on | step 2's second `curl` | `x-drupal-cache: HIT` | Re-run `ddev measure on` |
| Right LCP element | Insights → LCP breakdown | `p.article-header__deck` | If it is the hero `<img>`, the deck rendered late or the hero loaded early. Record what you see, and log it below |
| Text LCP has no resource phases | same panel | load delay and load duration absent | If present, the LCP is an image; see the row above |
| Runs are comparable | spread of the five LCP totals | within about ±20% of the median | Close other apps and tabs, then re-run all five |
| Trace opens | Performance panel → *Load profile* → the saved file | Same LCP and breakdown as the median run | Save again |
| Trace isn't huge | `ls -lh` | under ~5 MB, or gzipped | gzip it |
| Dev profile back | after step 8 | 48 `THEME DEBUG`, 4 config differences | Re-run `ddev measure off` |

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | 4 | Five separate throttled, logged-out recordings | One file, `trace-four-years-before` (9.8 MB, saved to the repo root). It holds five page loads in a single recording. Every load returned `x-drupal-cache: UNCACHEABLE (request policy)` and `cache-control: private`: a session cookie, so the window was logged in. TTFB was 29–53 ms and LCP 58–92 ms (first load 329 ms): no throttling. The measurement profile itself was on (10 aggregated `css_*` files, dynamic cache `HIT`). Confirmed useful: the LCP is the deck text (`type: text`, one candidate, same size in all five loads), and CLS is 0.0000 | Rejected for the baseline. Added the logged-out and throttling checks to step 3, and one reload per recording to step 4. The file is left where it is for the developer to move or delete |
| 2 | 5 | Re-record until one run lands within about 10% of the median | Changed before starting: every run's trace is saved instead, and Claude picks the median from the files | Simpler, and nothing depends on hand-copied numbers. Step 5's original wording was replaced, since it had not started |
| 3 | 3–4 | Logged out, CPU 4× and Slow 4G, on `/articles/four-years-they-let-me-make-carbonara` | `trace-slow-4g` (13 MB, repo root): five loads of `/articles/what-carbonara-taught-me-about-ramen-broth` in one recording. Slow 4G was applied (metadata: 180,000 B/s down, 84,375 B/s up, 562.5 ms latency), but no CPU throttling was recorded. Every load was still `x-drupal-cache: UNCACHEABLE (request policy)`, so the window was logged in. Chrome shares cookies across all open Incognito windows. LCP 1,465–1,529 ms (median 1,484), text in every load, CLS 0. TTFB about 590 ms when read as the renderer's response time. The response's `timing.receiveHeadersStart` omits the throttling delay on reused connections and shows 54–85 ms on loads 2–5 | Rejected for the baseline. Kept as the first observation of the cause: render delay (~890 ms) is 2 CSS and 1 JS render-blocking files, requested only once the HTML arrives, each paying the 562.5 ms latency. Step 3 now says to close every Incognito window and set CPU 4× in the Performance gear menu. Read TTFB from the renderer-side `ResourceReceiveResponse` time, not `timing.receiveHeadersStart` |
| 4 | 4–5 | Five separate recordings, each saved as `run1`…`run5`; the median run's trace kept | `trace-last-time` (10.8 MB): five loads in one recording, with all conditions met (metadata `cpuThrottling: 4`, Slow 4G, `x-drupal-cache: HIT`, `cache-control: max-age=900, public`). The URL was typed as `…33001//articles/…` (double slash), and Drupal served the same page from cache. LCP median 1,493 ms, TTFB 587 ms, render delay 905 ms, CLS 0 | Accepted. Claude split the five loads out of the one file, so separate files were not needed. The whole recording is kept, gzipped, as `docs/actual-outcomes/performance-baseline/traces/article-node-2026-10-09.json.gz` (1.3 MB, identical after `gunzip`). TTFB is read as the renderer's `ResourceReceiveResponse` time (deviation 3). `ddev measure off` was verified: `MISS`, 48 `THEME DEBUG`, 37 stylesheets, 4 config differences |

---

## Out of scope

- **The other three item-4 checkboxes:** the facet click on `/recipe-search`,
  the cost of `recipe-interactions.js`, and the XHGui profile.
- **Lighthouse runs and the baseline tables** (item 6).
- **Pantheon Dev.** This trace is DDEV only.
- **Fixing anything the trace shows.** Week 8–10 measures; it doesn't change
  the site.
