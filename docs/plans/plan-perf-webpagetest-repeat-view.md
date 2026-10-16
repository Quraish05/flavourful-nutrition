# Plan — WebPageTest first and repeat view on Pantheon Dev (Phase 3, Week 8–10 item 5)

> Branch: `perf/measurement-profile` · Created: 2026-10-12 · Last updated: 2026-10-16
>
> **Status: Complete — merged in PR #53 (`a829d0d`, 2026-10-12).** WebPageTest was blocked (deviation 1). The first view comes from headers, and the repeat view was measured in Chrome. Result: `performance-baseline.md` → *Repeat view on Pantheon Dev*.

**Goal:** one WebPageTest result for the article node on Pantheon Dev, with
first view and repeat view, and a record of what the repeat view saves in
bytes and in requests.

This file is the working checklist between the plan in Notion (*Phase 3 →
Week 8–10 → item 5*) and the record in
[`docs/actual-outcomes/performance-baseline.md`](../actual-outcomes/performance-baseline.md).

**Standing rule for this plan: nothing is committed until you say so.**

---

## Why this plan exists

The repeat view answers one question: when a visitor comes back, what does
their browser cache save? On a Drupal site with aggregated CSS and JS, that is
the payoff of aggregation's stable, hashed file names. A file the browser may
reuse costs nothing on the second visit.

**What Pantheon Dev actually sends (checked 2026-10-12, before the test):**

| Response | `cache-control` | What the browser can do on a repeat visit |
|---|---|---|
| The article HTML | `max-age=900, public, stale-if-error=0` | Reuse it for 15 minutes |
| Both CSS aggregates, the JS aggregate, the hero image, both fonts | **`no-cache, stale-if-error=0`**, with an `ETag` | Must **ask the server every time**. Each answers `304 Not Modified` (checked with `If-None-Match`) |
| `robots.txt`, for comparison | `max-age=86400` | Reuse it for a day |

The first request for one CSS aggregate returned `no-store, private`. That
was Drupal generating the file on demand. After that, the file is served as a
plain file with `no-cache`.

**So the expected result is: bytes saved, requests not saved.** Every asset is
requested again on the repeat view. The server answers with a small `304`
instead of the file, which saves the bytes, but on a slow connection each of
those requests still costs a full round trip.

**Why Pantheon Dev sends `no-cache` on static files is not yet known.** It
could be a rule specific to Dev environments, or a setting this site lacks.
Record what is sent, and leave the cause as an open question (see Out of
scope).

**Decided:**

- **Target:** Pantheon Dev, `https://dev-the-cookbook.pantheonsite.io/articles/four-years-they-let-me-make-carbonara`.
  This is written in the baseline doc as item 5's first checkbox. Pantheon
  runs `ad21c90`, the same files as `master` (`af026f4`).
- **Logged out by default.** WebPageTest's agents never have a session.
- **Three runs, first view and repeat view each.** WebPageTest reports the
  median run. Use its numbers rather than picking one by hand.
- **A mobile profile close to the DevTools traces,** but recorded exactly as
  WebPageTest names it. This is a separate series and is never compared with
  the DDEV traces.

**Rejected:**

- **A `ddev share` tunnel to DDEV.** The tunnel's own latency would be inside
  every number.
- **Fixing the `no-cache` headers first.** This item measures; the fix, if
  there is one, is a Week 11–14 change, and this result is its "before".

---

## Who does what

Step 3 is browser UI work on webpagetest.org, so it's **yours**. Claude does
steps 1, 2 and 4–6.

## Steps

| # | Action | Where | Owner | Status |
|---|---|---|---|---|
| 1 | Name the WebPageTest target, and why, in the baseline doc | `performance-baseline.md` → *Two targets* | Claude | Done |
| 2 | Wake Pantheon Dev and warm its page cache right before the test | `terminus env:wake`, `curl` | Claude or you | Done |
| 3 | Run the test: three runs, first and repeat view | webpagetest.org | You | Blocked: Cloudflare challenge (deviation 1) |
| 4 | Hand over the result URL | — | You | Superseded by step 7 |
| 5 | Read the first-view and repeat-view numbers and write them into the baseline doc | the result page | Claude | Done, from headers and step 7 |
| 6 | Tick item 5's second checkbox in Notion | Notion Phase 3, item 5 | Claude | Done |
| 7 | Confirm the repeat view in a real browser: first view, then a repeat navigation | Chrome DevTools → Network, on Pantheon Dev | You | Done |

### Step 2 — wake and warm Pantheon Dev

The sandbox sleeps when idle, and the first request after waking is slow. Run
this right before step 3:

```bash
terminus env:wake the-cookbook.dev
A=https://dev-the-cookbook.pantheonsite.io/articles/four-years-they-let-me-make-carbonara
curl -s -o /dev/null "$A"
curl -s -o /dev/null -D - "$A" | grep -iE '^(x-drupal-cache|cache-control):'
```

Expect `x-drupal-cache: HIT` on the second request.

### Step 3 — run the test (you)

1. Go to **webpagetest.org** and sign in. A free account is needed to run
   tests.
2. Paste the URL:
   `https://dev-the-cookbook.pantheonsite.io/articles/four-years-they-let-me-make-carbonara`
3. In the test settings:
   - **Browser:** Chrome, with **mobile emulation** if offered (a Moto G
     class device).
   - **Connection:** a **4G** preset. Note its exact name and values (down,
     up, RTT) as WebPageTest shows them.
   - **Number of runs:** **3**.
   - **Repeat view:** choose **First View and Repeat View**. It may be under
     the advanced settings.
   - **Location:** any. Note which one.
4. Start the test and wait for it to finish (a few minutes).

WebPageTest's labels change between versions. If a setting above is named
differently, pick the closest and write down what you chose.

### Step 4 — hand over

Paste the **result page URL** (it looks like
`https://www.webpagetest.org/result/<test-id>/`). WebPageTest results are
public by default, so Claude can read the numbers from it. If the page can't
be read, paste a screenshot of the summary table showing First View and
Repeat View.

### Step 5 — the doc (Claude)

Add **"WebPageTest — first and repeat view"** to the baseline doc, with:

- **The setup line:** URL, Pantheon commit, test location, browser and device,
  connection preset with values, 3 runs, and the result link.
- **A table, first view against repeat view:** requests, bytes in, number of
  `304` responses, LCP, start render, fully loaded.
- **What the repeat view saved,** in bytes and in requests.
- **"What it says"**, tied to the `no-cache` finding above.

### Step 6 — Notion

- **Tick item 5's second checkbox,** with the bytes and requests saved as
  evidence.
- **Add this plan as the item's `**Plan:**` line,** marked "not yet pushed"
  until it is.
- **Add the `no-cache` finding** as an open question for Week 11–14.

### Step 7 — confirm the repeat view in a real browser (you)

This replaces steps 3–4, because WebPageTest's agents are blocked (deviation
1). A real Chrome passes Cloudflare's challenge.

1. Open a **Guest window**, then DevTools (⌥⌘I) → **Network**.
   **Disable cache must be OFF:** the browser cache is what is being tested.
   Throttling isn't needed, since this counts requests and bytes, not time.
2. **First view:** load
   `https://dev-the-cookbook.pantheonsite.io/articles/four-years-they-let-me-make-carbonara`,
   with a single slash after `.io`. Note the bottom bar: *N requests,
   X kB transferred*.
3. **Repeat view:** click **Articles** in the menu, then click the same
   article's card. Don't press ⌘R or Enter on the same URL: Chrome treats that
   as a reload and re-checks everything, which isn't a repeat visit.
4. Note the bottom bar again, and the **Status** column for each file: `304`,
   or `200` with "(memory cache)" or "(disk cache)" in the Size column.

**Expected, from Pantheon's headers:**

| | Requests | Transferred |
|---|---|---|
| First view | 8 | about 150 KB |
| Repeat view, within 15 minutes | 7, all `304` (HTML reused) | about 5 KB |

If Chrome shows "(disk cache)" for the static files instead of `304`, the
headers have changed since 2026-10-12. Re-check them with `curl -D -` and log it
below.

---

## Verification

| Check | How | Expect | If not |
|---|---|---|---|
| Pantheon awake and warm | step 2 | `x-drupal-cache: HIT` | Run step 2 again |
| The right page was tested | the result page | The article URL, status 200 | Re-run with the exact URL |
| Repeat view actually ran | the result's summary | A *Repeat View* row exists | Re-run with *First View and Repeat View* |
| `304`s visible | the repeat view's waterfall | CSS, JS, image and fonts show `304` | If they show `200` in full, the cache headers changed: re-check them with `curl -D -` |
| Median used | the result's summary | Numbers from the median run | Use the median row, not a single run |

---

## Effect on the codebase

**Read-only.** WebPageTest's agents load public Pantheon Dev pages. Nothing in
the repo, config or database changes, apart from the baseline doc and this
plan.

---

## Deviation log

| # | Step | What we expected | What actually happened | What we did |
|---|---|---|---|---|
| 1 | 3 | A WebPageTest result with first and repeat view | **403 Navigation error.** The agent got Cloudflare's "Performing security verification" page (7 KB, 3 requests). Pantheon Dev is now fronted by Cloudflare (`server: cloudflare`). Checked with `curl` on 2026-10-12: a plain `curl` gets **200**; the same request with a Chrome 152 user-agent, or with WebPageTest's `PTST` user-agent, gets **403 with `cf-mitigated: challenge`** ("Just a moment…"). A real Chrome passes the JavaScript challenge; a data-centre test agent does not. The run was also configured as Desktop / WiFi / 1 run with no repeat view, and the URL was typed as `…io//articles/…` | **WebPageTest can't test Pantheon Dev.** The repeat-view question is answered instead from Pantheon's own cache headers, below, and confirmed in a real browser (step 7). The same challenge will block Lighthouse CI and PageSpeed Insights against Pantheon Dev, which matters for Week 14–16 |
| 2 | 5 | Numbers from WebPageTest | A header-based model, made with `curl --compressed` and `If-None-Match`. **First view: 8 requests, 149.7 KB** (HTML 7.5 KB, CSS 10.3 + 2.1 KB, JS 4.0 KB, hero 35.7 KB, fonts 38.6 + 49.3 KB, favicon 2.3 KB). **Repeat view within 15 minutes: 7 requests, all `304`, 5.3 KB.** Saved: 144.3 KB (96%), and **1 request** (the HTML, `max-age=900`). After 15 minutes, 0 requests are saved | Waiting on step 7 to confirm in a real browser before the doc and Notion record it as measured |
| 3 | 7 | 7 requests (all `304`) and about 5 kB; the HTML reused for 15 minutes | Chrome repeat view: **9 requests, 8.0 kB**. 7 × `304`, but **the HTML was re-fetched in full** (`200`, about 7 kB), and a third font, `eb-garamond-var.woff2`, appeared from memory cache. The font is referenced from inside the CSS, so the HTML-only model missed it (45.0 kB on first view, `no-cache`). The HTML carries `vary: Accept-Encoding,Cookie`, Cloudflare sets a fresh `__cf_bm` cookie on every response, and the response arrives part aged by Cloudflare's edge cache (`age: 482`) | First view corrected to 9 requests and about 194.7 kB. Recorded: 0 requests saved and about 186.7 kB (96%) saved. Which header caused the HTML re-fetch was not isolated: both are named in the doc |

---

## Out of scope

- **Why Pantheon Dev sends `no-cache` on static files,** and whether Test or
  Live differ. That is a Week 11–14 question: check Pantheon's documentation
  and the response headers on another environment.
- **Changing any cache header.**
- **A WebPageTest run for Thyme.**
