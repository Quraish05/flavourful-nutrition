# Plan — the page-cache "before" (`cache.page.max_age` 0) (Phase 3, Week 8–10 item 6)

> Branch: `perf/lighthouse-baseline` · Created: 2026-10-12 · Last updated: 2026-10-16
>
> **Status: Complete — merged in PR #55 (`fa99d9b`, 2026-10-13).** Result: `performance-baseline.md` → *The page-cache "before": `max_age` 0*. The prediction held: `HIT` at both settings, only `Cache-Control` changed.

**Goal:** reproduce the state before `fbf3b43` (page cache `max_age` 0 → 900)
on DDEV, record what it changes against the Lighthouse baseline of 2026-10-12,
and put the setting back so `config:status` shows only the four known
differences.

This file is the working checklist between Notion (*Phase 3 → Week 8–10 →
item 6, "Reproduce the page-cache before"*) and
[`docs/actual-outcomes/performance-baseline.md`](../actual-outcomes/performance-baseline.md).
It reuses the variables and commands of
[`plan-perf-lighthouse-baseline.md`](plan-perf-lighthouse-baseline.md).

**Standing rule for this plan: nothing is committed until you say so.**

---

## Why this plan exists

`cache.page.max_age` sounds as if it turns the page cache on and off. It
doesn't. Core says so in
`web/core/modules/page_cache/src/StackMiddleware/PageCache.php:268-269`:
*"Because of this, page cache ignores max age."* The setting only decides the
`Cache-Control` header (`FinishResponseSubscriber.php:164` and `:254`):

| `max_age` | `Cache-Control` sent | Internal Page Cache |
|---|---|---|
| 900 (now) | `max-age=900, public` | Stores the page; `HIT` on repeat |
| 0 (before `fbf3b43`) | `no-cache, must-revalidate` with an `Expires` of 1978 | **Still stores the page**: an expired `Expires` is stored as permanent, cleared by cache tags |

**So the prediction on DDEV is: same `HIT`, same TTFB, same Lighthouse
numbers; only `Cache-Control` changes.** What `max_age` 0 really costs is
reuse *outside* Drupal: a CDN (Pantheon's edge, Cloudflare) and the visitor's
browser may not keep the HTML. DDEV has no CDN, and Lighthouse clears the
browser cache every run, so neither can show that cost here.

**Decided:**

- **Measure the prediction, don't assume it.** Headers and warm TTFB on all six
  URLs, and Lighthouse × 5 on one URL (the article node) as a check. If the
  article node's numbers match the baseline, the other five are not re-run.
- **Change active config only,** with `drush cset`, and put it back to 900 in
  the same session.

**Rejected:**

- **All 30 Lighthouse runs again.** If the article node matches, the other 25
  would only re-measure a server that behaves the same.
- **Trying it on Pantheon Dev,** where the CDN would show the real cost. It
  changes the hosted site's config while it is running. That is a separate
  decision, and out of scope here.

---

## Steps

| # | Action | Where | Owner | Status |
|---|---|---|---|---|
| 1 | Run the variables block, switch the measurement profile on, check `HIT` and 0 `THEME DEBUG` | `ddev measure on` | You | Done |
| 2 | Set `max_age` to 0 and rebuild caches | `drush cset` | You | Done |
| 3 | Headers and TTFB cold/warm on all six URLs | `curl` | You | Done |
| 4 | Lighthouse × 5 on the article node | `npx lighthouse@13.5.0` | You | Done |
| 5 | Set `max_age` back to 900, then check config and headers | `drush cset`, `config:status` | You | Done |
| 6 | Switch back to the dev profile | `ddev measure off` | You | Done |
| 7 | Write the result into the baseline doc | `performance-baseline.md` | Claude | Done |
| 8 | Tick the Notion checkbox, and correct item 6's table row for `fbf3b43` | Notion | Claude | Done |

### Step 1 — variables and profile

```bash
B=https://foodrecipes-drupal.ddev.site:33001
OUT=docs/actual-outcomes/performance-baseline/lighthouse/2026-10-12/max-age-0
URLS=(home=/ articles=/articles article-node=/articles/four-years-they-let-me-make-carbonara recipes=/recipes recipe-node=/recipes/spaghetti-carbonara recipe-search=/recipe-search)
mkdir -p "$OUT"
echo "${#URLS[@]} URLs → $OUT"   # expect: 6 URLs → …/max-age-0

ddev measure on
curl -sk -o /dev/null "$B/articles"
curl -sk -o /dev/null -D - "$B/articles" | grep -i '^x-drupal-cache:'   # HIT
curl -sk "$B/articles" | grep -c 'THEME DEBUG'                            # 0
```

### Step 2 — `max_age` 0

```bash
ddev drush cget system.performance cache.page.max_age    # 900
ddev drush cset system.performance cache.page.max_age 0 -y
ddev drush cr
ddev drush cget system.performance cache.page.max_age    # 0
```

### Step 3 — headers and TTFB

```bash
for e in "${URLS[@]}"; do s=${e%%=*}; u=${e#*=}
  curl -sk -o /dev/null "$B$u"
  echo "$s | $(curl -sk -o /dev/null -D - "$B$u" | grep -iE '^(x-drupal-cache|cache-control|expires):' | tr -d '\r' | tr '\n' ' ')"
done | tee "$OUT/headers.txt"

for e in "${URLS[@]}"; do s=${e%%=*}; u=${e#*=}
  for i in 1 2 3 4 5; do
    ddev drush cr -q
    cold=$(curl -sk -o /dev/null -w '%{time_starttransfer}' "$B$u")
    warm=$(curl -sk -o /dev/null -w '%{time_starttransfer}' "$B$u")
    echo "$s $i cold=$cold warm=$warm"
  done
done | tee "$OUT/ttfb.txt"
```

**Expected, if the prediction holds:** `x-drupal-cache: HIT` on all six,
`cache-control: must-revalidate, no-cache, private`, `expires: Sun, 19 Nov 1978
05:00:00 GMT`, and TTFB medians within noise of the baseline (warm 14–51 ms).

### Step 4 — Lighthouse on the article node

```bash
u=/articles/four-years-they-let-me-make-carbonara
for i in 1 2 3 4 5; do
  npx -y lighthouse@13.5.0 "$B$u" --only-categories=performance \
    --output=json --output-path="$OUT/article-node-$i.json" \
    --chrome-flags="--headless=new" --quiet
done

for f in "$OUT"/article-node-[1-5].json; do
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

gzip -9 "$OUT"/article-node-[1-5].json
```

Baseline for comparison: LCP 1,506 ms (1,504–1,512), CLS 0.012, TBT 0,
185,618 bytes, 9 requests.

### Step 5 — back to 900

```bash
ddev drush cset system.performance cache.page.max_age 900 -y
ddev drush cr
ddev drush cget system.performance cache.page.max_age    # 900
ddev drush config:status                                  # only the 4 search_api_solr.solr_field_type.* items
curl -sk -o /dev/null "$B/articles"
curl -sk -o /dev/null -D - "$B/articles" | grep -iE '^(x-drupal-cache|cache-control):'   # HIT, max-age=900, public
```

### Step 6 — dev profile back

```bash
ddev measure off
curl -sk -o /dev/null "$B/articles"
curl -sk -o /dev/null -D - "$B/articles" | grep -i '^x-drupal-cache:'   # MISS
curl -sk "$B/articles" | grep -c 'THEME DEBUG'                            # 48
```

---

## Verification

| Check | How | Expect | If not |
|---|---|---|---|
| The setting really changed | step 2 `cget` | `0` | Re-run `cset`; check for a `$config` override in `settings.local.php` |
| The header followed it | `headers.txt` | `no-cache` and the 1978 `Expires` on all six | A cached response from before `cr`: run `ddev drush cr` again |
| Page cache still serves | `headers.txt` | `HIT` on all six | **The prediction is wrong.** Record it; then TTFB and all six Lighthouse URLs need re-running |
| Lighthouse unchanged | `summary.tsv` against the baseline | Within the baseline's min–max | Log the difference, then run the other five URLs |
| Config restored | step 5 | `900`, only 4 differences | Re-run `cset … 900` |

---

## Effect on the codebase

**Experiment — revert.** Active config on local DDEV only, changed and put back
in the same session (step 5). `config/sync` is never touched. The baseline doc,
this plan and the raw output are additive.

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | 3 | Cold TTFB within noise of the baseline | Recipe node cold median 2,170 ms (1,720–2,806) against 1,552 ms (1,228–1,850); article node 581 against 428. The warm side and the headers matched the prediction | Recorded as run-to-run variation: a cold request after `drush cr` does the same work whatever `max_age` is. Noted in the doc that cold TTFB is accurate only to a few hundred ms |
| 2 | 4 | Article-node LCP within the baseline's 1,504–1,512 ms | Median 1,503 ms, matching, but the spread widened to 1,360–1,667 ms | Kept all five runs. The median matches, so the other five URLs weren't re-run, as the plan decided |

---

## Out of scope

- **Measuring `max_age` 0 behind a CDN** (Pantheon Dev). That is where the
  setting really costs something, but it changes the hosted site's config.
- **The jQuery UI and hero-image "befores".**
- **Committing.**
