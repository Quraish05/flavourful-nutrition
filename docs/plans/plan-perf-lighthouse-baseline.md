# Plan — Lighthouse baseline of the frozen URL set (Phase 3, Week 8–10 item 6)

> Branch: `perf/lighthouse-baseline` · Created: 2026-10-12 · Last updated: 2026-10-12
>
> **Status: Complete — not yet committed.** Result: `performance-baseline.md` → *Lighthouse baseline — 2026-10-12*.

**Goal:** for each of the six frozen URLs, under the measurement profile:
anonymous TTFB cold and warm, LCP and its element, CLS, TBT, transfer bytes,
request count, `X-Drupal-Cache` and `X-Drupal-Dynamic-Cache`. Lighthouse
numbers are the median of 5 runs, recorded with the SHA and the profile.

This file is the working checklist between the plan in Notion (*Phase 3 →
Week 8–10 → item 6, third checkbox*) and the record in
[`docs/actual-outcomes/performance-baseline.md`](../actual-outcomes/performance-baseline.md).
It covers that one checkbox. The page-cache "before", the jQuery UI decision and
the commit are separate checkboxes and are out of scope here.

**Standing rule for this plan: nothing is committed until you say so.**

---

## Why this plan exists

This is the table every Week 9–16 "after" is compared with. It has three
parts, measured by three different tools, and each one can mislead in its own
way:

| What | Tool | Why not Lighthouse |
|---|---|---|
| TTFB cold and warm | `curl -w '%{time_starttransfer}'` | Lighthouse clears the browser cache but not Drupal's, so it only ever sees a warm server |
| `X-Drupal-Cache`, `X-Drupal-Dynamic-Cache` | `curl -D -` | Lighthouse doesn't report response headers in its summary |
| LCP, its element, CLS, TBT, bytes, requests | Lighthouse 13.5.0 CLI, mobile preset | — |

**Decided:**

- **Lighthouse 13.5.0, pinned** (`npx -y lighthouse@13.5.0`), mobile preset
  with its default simulated throttling, performance category only, headless
  Chrome. These match the settings the baseline doc's first paragraph already
  states. Pinning the version keeps a later re-run comparable.
- **URL base: `https://foodrecipes-drupal.ddev.site:33001`.** The portless URL
  returns 404 on this machine (checked 2026-10-12).
- **Median per metric, with the min–max range beside it.** Each column's
  median is taken separately from the five runs. Bytes and request count
  should barely vary; if they do, that is a finding.
- **"Cold" means after `drush cr`:** all caches empty, including the
  container and router. **"Warm" means the next request, a page-cache `HIT`.**
  Five of each per URL, median reported.
- **The dynamic page cache is checked on its own.** On a page-cache `HIT`, the
  stored response replays the `X-Drupal-Dynamic-Cache` header it was stored
  with (`MISS`), which says nothing. So clear only the page bin and request
  again: that request misses the page cache and shows what the dynamic cache
  does.
- **All 30 Lighthouse JSON reports are kept, gzipped,** under
  `docs/actual-outcomes/performance-baseline/lighthouse/2026-10-12/`, so the
  medians can be audited. If they total more than 5 MB, keep only each URL's
  median run and say so.

**Rejected:**

- **Lighthouse in the DevTools panel.** Thirty runs by hand, with no fixed
  version, and an extension or a stray login can leak into it.
- **Lighthouse's `server-response-time` as TTFB.** It is recorded, as a
  cross-check, but it is always a warm-server number.
- **Pantheon Dev.** Cloudflare challenges automated browsers (WebPageTest got a
  403 on 2026-10-12), and it is a separate series anyway.

---

## Who does what

Every step is a CLI command, so Claude can run all of them. Step 1 restarts
DDEV, so don't use the local site while it runs (about 15 minutes in total).
Step 7 switches the dev profile back.

## Steps

| # | Action | Where | Owner | Status |
|---|---|---|---|---|
| 1 | Switch to the measurement profile and check it is on | `ddev measure on` | You | Done |
| 2 | Record the SHA and confirm only docs are uncommitted | `git` | You | Done |
| 3 | TTFB cold and warm, 5 each per URL | `curl`, saved to `ttfb.txt` | You | Done |
| 4 | Page-cache and dynamic-cache headers per URL | `curl` | You | Done |
| 5 | Lighthouse × 5 per URL, then extract and gzip | `npx lighthouse@13.5.0` | You | Done |
| 6 | Write the table into the baseline doc | `performance-baseline.md` | Claude | Done |
| 7 | Switch back to the dev profile and check it | `ddev measure off` | You | Done |
| 8 | Tick item 6's third checkbox in Notion, with this plan as its `**Plan:**` line | Notion | Claude | Done |

Every command below runs from the repo root and starts with these variables:

```bash
B=https://foodrecipes-drupal.ddev.site:33001
OUT=docs/actual-outcomes/performance-baseline/lighthouse/2026-10-12
URLS=(home=/ articles=/articles article-node=/articles/four-years-they-let-me-make-carbonara recipes=/recipes recipe-node=/recipes/spaghetti-carbonara recipe-search=/recipe-search)
mkdir -p "$OUT"
echo "${#URLS[@]} URLs → $OUT"   # expect: 6 URLs → docs/actual-outcomes/…/2026-10-12
```

Run this block in the **same terminal** as every step below. If the echo prints
`0 URLs →` with no path, the loops will run once against the bare base URL and
`tee` will fail to write `/ttfb.txt`.

### Step 1 — measurement profile on

```bash
ddev measure on
curl -sk -o /dev/null "$B/articles"
curl -sk -o /dev/null -D - "$B/articles" | grep -i '^x-drupal-cache:'
curl -sk "$B/articles" | grep -c 'THEME DEBUG'
```

Expect `HIT` and `0`. In the dev profile, today, they are `MISS` and 48.

### Step 2 — SHA

```bash
git rev-parse --short HEAD          # 899039d on 2026-10-12
git status --short -- ':!docs'      # must print nothing
```

The SHA goes into the doc's table heading. If anything outside `docs/` is
modified, stop: the numbers would not belong to that SHA.

### Step 3 — TTFB cold and warm

```bash
for e in "${URLS[@]}"; do s=${e%%=*}; u=${e#*=}
  for i in 1 2 3 4 5; do
    ddev drush cr -q
    cold=$(curl -sk -o /dev/null -w '%{time_starttransfer}' "$B$u")
    warm=$(curl -sk -o /dev/null -w '%{time_starttransfer}' "$B$u")
    echo "$s $i cold=$cold warm=$warm"
  done
done | tee "$OUT/ttfb.txt"
```

Times are in seconds. Report the median of the five, in milliseconds. DDEV on
macOS adds Docker overhead to every TTFB, so these compare only with later DDEV
numbers.

### Step 4 — cache headers

```bash
for e in "${URLS[@]}"; do s=${e%%=*}; u=${e#*=}
  curl -sk -o /dev/null "$B$u"
  page=$(curl -sk -o /dev/null -D - "$B$u" | grep -i '^x-drupal-cache:' | tr -d '\r')
  ddev drush cache:clear bin page -q
  dyn=$(curl -sk -o /dev/null -D - "$B$u" | grep -i '^x-drupal-dynamic-cache:' | tr -d '\r')
  echo "$s | $page | $dyn"
done | tee "$OUT/headers.txt"
```

Expected: `X-Drupal-Cache: HIT` on all six. `X-Drupal-Dynamic-Cache: HIT` on
five, and `UNCACHEABLE (poor cacheability)` on `/recipe-search`. The last
request of each URL also re-fills the page cache, so step 5's runs all start
from a warm server.

### Step 5 — Lighthouse × 5

```bash
for e in "${URLS[@]}"; do s=${e%%=*}; u=${e#*=}
  for i in 1 2 3 4 5; do
    npx -y lighthouse@13.5.0 "$B$u" --only-categories=performance \
      --output=json --output-path="$OUT/$s-$i.json" \
      --chrome-flags="--headless=new" --quiet
  done
done

for f in "$OUT"/*-[1-5].json; do
  jq -r --arg run "$(basename "$f" .json)" '[$run,
    (.audits["largest-contentful-paint"].numericValue | round),
    (.audits["cumulative-layout-shift"].numericValue * 1000 | round / 1000),
    (.audits["total-blocking-time"].numericValue | round),
    (.audits["total-byte-weight"].numericValue | round),
    (.audits["network-requests"].details.items | length),
    (.audits["server-response-time"].numericValue | round),
    ([.audits["lcp-breakdown-insight"].details | .. | objects | select(.type == "node")][0].selector | split(" > ") | last),
    .lighthouseVersion] | @tsv' "$f"
done | tee "$OUT/summary.tsv"

gzip -9 "$OUT"/*-[1-5].json
du -sh "$OUT"
```

`summary.tsv` columns: run, LCP ms, CLS, TBT ms, bytes, requests, server
response ms, LCP element selector, Lighthouse version.

### Step 6 — the doc

Under a new section, **"Lighthouse baseline — 2026-10-12"**:

- **The setup line:** SHA, profile, Lighthouse version, preset, Chrome
  version, 5 runs, median per metric.
- **One table per measurement:** TTFB (cold and warm), cache headers, and
  Lighthouse (LCP, element, CLS, TBT, bytes, requests), with min–max beside
  each median.
- **`/recipes`' LCP element,** the one *The frozen URL set* has not named yet.
  Fill in that row too.
- **"What it says",** in three or four bullets, and no fixes.
- **Update the status blockquote** at the top of the doc.

### Step 7 — dev profile back

```bash
ddev measure off
curl -sk -o /dev/null "$B/articles"
curl -sk -o /dev/null -D - "$B/articles" | grep -i '^x-drupal-cache:'
curl -sk "$B/articles" | grep -c 'THEME DEBUG'
ddev drush config:status
```

Expect `MISS`, 48, and only the four known `search_api_solr.solr_field_type.*`
differences.

### Step 8 — Notion

Tick item 6's third checkbox, with the medians as short evidence, and add this
plan as the item's `**Plan:**` line, marked "not yet pushed" until it is.

---

## Verification

| Check | How | Expect | If not |
|---|---|---|---|
| Measurement profile on | step 1 | `HIT`, 0 `THEME DEBUG` | Re-run `ddev measure on`; check `.ddev/config.measure.yaml` exists |
| Numbers belong to the SHA | step 2 | Nothing modified outside `docs/` | Stop, and commit or stash first |
| Cold really is cold | `ttfb.txt` | Cold several times warm on every URL | If equal, `drush cr` didn't run: check its exit code |
| Warm really is a page-cache hit | step 4 | `X-Drupal-Cache: HIT` on all six | A `MISS` or `UNCACHEABLE` means a session cookie or a request policy. Check `curl` sends no cookie |
| Lighthouse ran under the right settings | `summary.tsv` and one report's `configSettings` | `formFactor: mobile`, `throttlingMethod: simulate`, version 13.5.0 | Re-run without extra flags |
| Lighthouse reached the page | `summary.tsv` | Requests > 1, LCP element not `?` | A certificate error: add `--ignore-certificate-errors` to `--chrome-flags` and log it below |
| Same page every run | the five rows per URL | Same LCP element, bytes within a few % | Record the spread as a finding; don't drop runs |
| Dev profile restored | step 7 | `MISS`, 48, 4 differences | Re-run `ddev measure off` |

---

## Effect on the codebase

**Experiment — revert, plus additive.** `ddev measure on` switches the local
profile for the run, and `ddev measure off` restores it (step 7). The baseline
doc, this plan and the gzipped reports are added. No code or config changes.

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | 3 | 30 lines in `ttfb.txt`, 5 per URL | The variables block wasn't run in that terminal: `B` was set, `OUT` and `URLS` were empty. The loop ran once against `$B` alone (the homepage), printed 5 unlabelled rows (cold 586–1,112 ms, warm 14–44 ms), and `tee` failed with `/ttfb.txt: Read-only file system` | Discarded the rows, since they weren't saved. Added an echo check to the variables block, then re-ran step 3 |
| 2 | 5 | The LCP element selector in column 8 of `summary.tsv` | `?` in all 30 rows. Lighthouse 13 has no `largest-contentful-paint-element` audit; the element is in `lcp-breakdown-insight`. Every run still reached the page (9–11 requests) | Regenerated `summary.tsv` from the gzipped reports with the corrected path, and fixed the command in step 5 |

---

## Out of scope

- **The page-cache "before"** (`cache.page.max_age` 0), the jQuery UI
  "before", and the hero-image "before": separate item 6 checkboxes.
- **Committing,** and the last checkbox's commit of the reports.
- **Any fix,** including `/recipe-search`'s cacheability and the `/` hero's
  `loading="lazy"`.
- **Pantheon Dev and Thyme.**
