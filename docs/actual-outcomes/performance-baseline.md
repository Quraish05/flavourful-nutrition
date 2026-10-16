# Performance baseline — Flavourful

> Phase 3, Week 8–10 · Last updated: 2026-10-12

**Lab data only.** Every Lighthouse number in this document comes from
Lighthouse's mobile preset run against the local DDEV site
(`foodrecipes-drupal.ddev.site`) under the measurement profile
(`ddev measure on`, below), from Chrome on an Apple M5 MacBook with 16 GB of
RAM. The preset emulates a
Moto G Power screen (412 × 823, DPR 1.75), slows the CPU 4×, and models a slow
4G network at 150 ms RTT, 1.6 Mbps down and 750 Kbps up. The throttling is
*simulated*: Lighthouse loads the page unthrottled and estimates the throttled
timings, so these are modelled numbers, not a slowed-down recording. There is
no field data: the site has no real visitors, so CrUX has nothing for it and
no RUM is collected. The numbers can show whether a change made this site
faster or slower on this setup. They cannot show whether it passes Core Web
Vitals, which is judged on field data at p75. DevTools trace numbers are a
separate lab series with their own settings, listed under *DevTools traces*.

> **Status: definitions, Lighthouse settings and measurement profile in place.
> DevTools traces recorded: the article node (2026-10-09) and a facet click on
> `/recipe-search` (2026-10-10), plus an XHGui profile of `/recipe-search`
> (2026-10-12) and the repeat view on Pantheon Dev (2026-10-12). Lighthouse
> baseline of the six frozen URLs recorded (2026-10-12, `899039d`), with the
> page-cache "before" (`max_age` 0). The jQuery UI removal has no "before":
> it changed no page.**

---

## The frozen URL set

These six URLs are **frozen** as of 2026-10-12. Every baseline number, and
every "after" number in Weeks 9–16, is taken on exactly these pages, so a
before and an after always describe the same page. Nothing here is being
fixed or changed. The point is the opposite: these slugs must stay as they
are, so that numbers taken months apart can still be compared.

| # | URL | Template it represents | Node | LCP element (verified 2026-10-07) |
|---|---|---|---|---|
| 1 | `/` | Homepage | — | `<img>` hero, `loading="lazy"` |
| 2 | `/articles` | Article listing (Views, AJAX filter) | — | `p.articles-header__lead` (text) |
| 3 | `/articles/four-years-they-let-me-make-carbonara` | Article node | 68 | `p.article-header__deck` (text) |
| 4 | `/recipes` | Recipe card grid | — | `p.recipes-header__lead` (text), from the Lighthouse baseline of 2026-10-12 |
| 5 | `/recipes/spaghetti-carbonara` | Recipe node (Site Studio display) | 46 | `p.hero__summary` (text) |
| 6 | `/recipe-search` | Search with facets (Solr) | — | `h1.page-title` (text) |

All six returned `200` with the expected page title on 2026-10-12. The
article is node 68, the same page as the DevTools article trace below, so its
Lighthouse row and its trace row describe the same page.

**Rules that keep the set frozen:**

- **Use these exact URLs:** a single slash after the host, no query strings,
  and `/recipe-search` unfiltered.
- **Measure all six every time,** or say which are missing and why. For
  example, `/recipe-search` can't be measured on Pantheon Dev, which has no
  Solr.
- **Don't rename these slugs.** Changing a node's title or its Pathauto
  pattern can change its alias. If an alias has to change, add a dated note
  under this table rather than editing the row, so older numbers stay traceable.
  The node IDs (68, 46) are the fallback: `/node/68` and `/node/46` always
  reach the same content.

---

## Targets, and the profile every number is taken under

### Two targets, two separate series

| Target | Role | What it has | What it lacks |
|---|---|---|---|
| DDEV (`foodrecipes-drupal.ddev.site`) | **The baseline.** Every before and after number | Solr, so `/recipe-search` can be measured | A CDN. TTFB includes Docker overhead on macOS |
| Pantheon Dev (`dev-the-cookbook.pantheonsite.io`) | A production-like second series | Cloudflare in front, `cache-control: max-age=900, public` on HTML, a public URL a real browser can load | Solr, so `/recipe-search` returns no results there. Automated agents (WebPageTest, likely Lighthouse CI) get Cloudflare's bot challenge |

**Never compare a number from one target with a number from the other.** Each
target is compared only with itself, before against after.

**WebPageTest target: Pantheon Dev, `/articles/four-years-they-let-me-make-carbonara`.**
WebPageTest's agents can't reach DDEV (it resolves to `127.0.0.1`), and a
`ddev share` tunnel adds its own latency. Pantheon Dev is public, runs the same
code as `master` (`ad21c90`, the same files as `af026f4`), and serves aggregated
assets with Twig debug off. The article is the same page as the DevTools
baseline trace. **In practice WebPageTest can't test it either:** Cloudflare
challenges its agents (403, 2026-10-12), so the repeat view was measured in a
real Chrome instead (see *Repeat view on Pantheon Dev*).

### The measurement profile

DDEV normally runs a development profile, and a baseline taken under it would
measure that profile rather than the site. `ddev measure on` switches to a
measurement profile, and `ddev measure off` switches back. The command lives in
`.ddev/commands/host/measure`.

| | Development profile (`ddev measure off`) | Measurement profile (`ddev measure on`) |
|---|---|---|
| Render, page and dynamic page caches | Off (`cache.backend.null`) | On, as `config/sync` sets them |
| CSS and JS aggregation | Off | On (`preprocess: true`) |
| Twig debug and Twig cache disable | On | Off |
| `X-Drupal-Cache-Contexts`, `-Max-Age`, `-Tags` headers | Present | Absent |

**How the switch works, because part of it leaves no repo diff:**

- **Flag file.** `on` writes `.ddev/config.measure.yaml` (gitignored), which
  sets `FLAVOURFUL_PROFILE=measure` in the web container.
- **`settings.local.php` (gitignored).** It wraps its performance overrides in
  `if (getenv('FLAVOURFUL_PROFILE') !== 'measure')`: the
  `development.services.yml` include, both `preprocess` lines and the three
  null cache bins.
- **Twig settings.** Twig debug is not set in any settings file. It lives in the
  database (`development_settings` key-value). `on` runs `drush theme:dev off`.
  `off` sets `twig_debug` and `twig_cache_disable` back to `TRUE` directly.
  It doesn't use `theme:dev on`, which would also write `preprocess: false`
  to active config.

Checked on `/articles` on 2026-10-09 (second anonymous request):

| | Development profile | Measurement profile |
|---|---|---|
| `X-Drupal-Cache` | `MISS` | `HIT` |
| `THEME DEBUG` blocks | 48 | 0 |
| Stylesheets | 37 | 2 (aggregated) |
| Scripts | 17 | 2 (aggregated) |
| `config:status` differences | 4 | 4 |

The four config differences are `search_api_solr.solr_field_type.*` items
(`''` versus `"\n"` for empty word lists). They are unrelated to the profile.
**Capture the cacheability headers before switching on,** because the
measurement profile removes them.

### Cacheability headers per URL — 2026-10-12

Taken under the development profile, on the second anonymous `curl` of each
frozen URL. `X-Drupal-Cache` and `X-Drupal-Dynamic-Cache` read `MISS` on all
six, because the development profile sends both caches to
`cache.backend.null`. They say nothing about the site, and the measurement
profile's numbers are the ones that count. All six send
`Cache-Control: max-age=900, public`.

| # | URL | `X-Drupal-Cache-Max-Age` | Contexts beyond the shared set |
|---|---|---|---|
| 1 | `/` | 27,289 s (until midnight) | `timezone`, `url.path`, `url.query_args`, `url.site` |
| 2 | `/articles` | 27,288 s (until midnight) | `languages:language_content`, `timezone`, `url` |
| 3 | `/articles/four-years-they-let-me-make-carbonara` | 27,288 s (until midnight) | `layout_builder_is_active:node`, `timezone`, `url.path`, `url.query_args:_wrapper_format`, `url.query_args:field_recipe_cuisine_type_target_id`, `url.site` |
| 4 | `/recipes` | 27,288 s (until midnight) | `languages:language_content`, `timezone`, `url` |
| 5 | `/recipes/spaghetti-carbonara` | 27,286 s (until midnight) | `languages:language_content`, `layout_builder_is_active:node`, `timezone`, `url` |
| 6 | `/recipe-search` | **0 (Uncacheable)** | `facets_filter:f`, `languages:language_content`, `url` (no `timezone`) |

**The shared set,** on all six: `cookies:big_pipe_nojs`,
`languages:language_interface`, `route`, `session.exists`, `theme`,
`user.node_grants:view`, `user.permissions`, `user.roles:authenticated`.

**What it says:**

- **Five URLs expire at the next midnight, not after a fixed time.** The
  utility-bar date (`PageHooks::addUtilityDate()`,
  `web/themes/custom/flavourful/src/Hook/PageHooks.php:201`) sets the max-age
  to the seconds left until midnight in the site's timezone (Asia/Kolkata). It
  was taken at 16:25 IST, so about 7.6 hours were left. The number depends on
  the time of day it is read, so compare "until midnight", not the seconds.
- **`/recipe-search` is max-age 0,** and its dynamic page cache reads
  `UNCACHEABLE (poor cacheability)`, which is what the facets add. For
  anonymous visitors, Internal Page Cache still stores it (see *Facet click*
  below), so the measurement profile will report a `HIT` there.
- **Every page varies by the `/recipes` cuisine filter**
  (`url.query_args:field_recipe_cuisine_type_target_id`, added by the category
  strip at `PageHooks.php:231`). It shows by name only on the article node,
  because the other pages already vary by the whole URL or by all query
  arguments. It is harmless for the frozen URLs, which carry no query string.

---

## The three metrics, and what makes a number mean something

### LCP — Largest Contentful Paint

How long after the navigation starts the biggest thing in the viewport
finishes painting. That thing is an image, a video poster or a block of text.
It answers *"when does the page look loaded?"* — not when the HTML arrived,
and not when the last script finished. The browser stops updating its
candidate once the user clicks, taps or presses a key.

### CLS — Cumulative Layout Shift

How much visible content jumps around without the user asking it to. Each
shift is scored by how much of the viewport moved times how far it moved.
Shifts less than a second apart are grouped into one burst, and the
**worst burst** is the score. It is a unitless number, not a time. A shift
within half a second of the user's own input does not count, because the
user caused it.

### INP — Interaction to Next Paint

How long the page takes to show a visible response after a click, tap or key
press. It is measured from the input to the next frame painted, and that
covers waiting for a busy main thread, running the handlers, and rendering
the result. It looks at every interaction in the visit and reports roughly
the worst one. Scrolling and hovering are not interactions. **No interaction
means no INP**, which is why a page-load test cannot produce one.

### Thresholds

| Metric | Good | Needs improvement | Poor |
|---|---|---|---|
| LCP | ≤ 2.5 s | 2.5 – 4 s | > 4 s |
| CLS | ≤ 0.1 | 0.1 – 0.25 | > 0.25 |
| INP | ≤ 200 ms | 200 – 500 ms | > 500 ms |

### The p75 rule

A page passes only if it is in the *Good* band at the **75th percentile** of
real visits: three visits in four have to be good, measured separately for
mobile and desktop. The threshold is about the slow end of the audience, not
the typical visit.

So **one fast run proves nothing about the 75th percentile.** A single
Lighthouse run is one visit, on one machine, on one simulated network. It
cannot say where three-quarters of users land. Everything in this document
is lab data, a median of repeated runs on the same machine. The numbers are
good for comparing *before* with *after* on this site, but they cannot tell
you whether real visitors pass.

Two lab limits follow from the definitions above:

- **INP cannot come from a page-load test.** Lighthouse's navigation mode
  reports Total Blocking Time instead, as a proxy. This document records
  them in separate columns, and TBT is never written up as INP.
- **Lab CLS only sees the load.** A shift caused by a facet click after the
  page has loaded does not appear in a navigation report.

---

## Lighthouse baseline — 2026-10-12

**SHA `899039d`, measurement profile** (`ddev measure on`), nothing modified
outside `docs/`. Lighthouse **13.5.0** CLI, headless Chrome 153, mobile preset:
`formFactor: mobile`, `throttlingMethod: simulate` (150 ms RTT, 4× CPU),
412 × 823 at DPR 1.75. Performance category only. **5 runs per URL; each
column is the median of its own five values, with min–max in brackets.** All
runs were made logged out, against a warm page cache. Raw output and the 30
gzipped reports are in
[`performance-baseline/lighthouse/2026-10-12/`](performance-baseline/lighthouse/2026-10-12/),
and the commands are in
[`plan-perf-lighthouse-baseline.md`](../plans/plan-perf-lighthouse-baseline.md).

### Server: TTFB and cache headers

TTFB is `curl`'s `time_starttransfer`, in ms, anonymous. **Cold** is the first
request after `ddev drush cr`; **warm** is the request straight after it, a
page-cache `HIT`. Five of each per URL. These are DDEV numbers, so they include
Docker overhead on macOS.

| # | URL | TTFB cold | TTFB warm | `X-Drupal-Cache` | `X-Drupal-Dynamic-Cache` |
|---|---|---|---|---|---|
| 1 | `/` | 568 (466–735) | 14 (13–28) | `HIT` | `HIT` |
| 2 | `/articles` | 534 (442–715) | 26 (13–49) | `HIT` | `HIT` |
| 3 | article node | 428 (387–630) | 14 (12–48) | `HIT` | `HIT` |
| 4 | `/recipes` | 756 (615–1,211) | 41 (13–221) | `HIT` | `HIT` |
| 5 | recipe node | **1,552** (1,228–1,850) | 51 (34–68) | `HIT` | `HIT` |
| 6 | `/recipe-search` | 587 (423–805) | 25 (19–100) | `HIT` | **`UNCACHEABLE (poor cacheability)`** |

`X-Drupal-Cache` is read on a repeat request. `X-Drupal-Dynamic-Cache` is read
after clearing only the page bin (`drush cache:clear bin page`), because a
page-cache `HIT` replays the dynamic-cache header it was stored with.

### Browser: Lighthouse

| # | URL | LCP (ms) | LCP element | CLS | TBT (ms) | Transfer (bytes) | Requests |
|---|---|---|---|---|---|---|---|
| 1 | `/` | 1,510 (1,505–1,660) | `img.image-style-recipe-hero-800`, `loading="lazy"` | 0 | 0 | 184,793 | 9 |
| 2 | `/articles` | 1,888 (1,881–1,902) | `p.articles-header__lead` | 0 | 0 (0–12) | 197,165 | 11 |
| 3 | article node | 1,506 (1,504–1,512) | `p.article-header__deck` | 0.012 | 0 | 185,618 | 9 |
| 4 | `/recipes` | 1,593 (1,582–1,638) | `p.recipes-header__lead` | 0 | 0 (0–1) | 150,239 | 9 |
| 5 | recipe node | 1,807 (1,804–1,812) | `p.hero__summary` | 0.009 | 0 | 198,118 | 9 |
| 6 | `/recipe-search` | 1,659 (1,657–1,699) | `h1.page-title` | 0 | 0 | 178,306 | 10 |

The LCP element was the same in all five runs of every URL. Transfer bytes
varied by under 0.1% and request counts not at all. The CLS on rows 3 and 5
came from one element each, every run: `div.prose` on the article node and
`div.hero__aside` on the recipe node.

### What it says

- **All six pages are in the *Good* band for LCP and CLS, in the lab.** LCP is
  1.5–1.9 s against 2.5 s, and CLS is 0.012 at most against 0.1. That
  says nothing about real visitors (see the first paragraph).
- **TBT is 0 almost everywhere.** No page runs enough JavaScript at load to
  block the main thread at 4× CPU. Week 11–14's INP work has to come from
  interactions, not from load.
- **The page cache is worth 20–40× on TTFB.** Warm is 14–51 ms, cold is
  428–1,552 ms. A warm server is all Lighthouse ever sees, so its LCP numbers
  sit on top of a 14–51 ms server.
- **The recipe node is the slowest page to build:** 1,552 ms cold, about 3×
  the article node, and the slowest warm. It is the only page rendered by Site
  Studio. That makes it the first candidate for an XHGui profile in Week 9–12.
- **`/articles` has the slowest LCP** (1,888 ms) and the most requests (11), on
  a warm server, so the cause is in the browser half. It is the only page that
  loads jQuery 4.0 (27.9 kB) and a 20.9 kB footer aggregate, for the view's
  AJAX exposed filter. Whether those scripts delay the LCP text is not yet
  shown; a trace of `/articles` is the way to check.
- **The only image LCP is still lazy-loaded** (`/`), as recorded on
  2026-10-07.

### The page-cache "before": `max_age` 0 — 2026-10-12

`fbf3b43` changed `system.performance` `cache.page.max_age` from 0 to 900.
To reproduce the before, it was set back to 0 in active config
(`ddev drush cset`), measured, and returned to 900 in the same session. Same
SHA, profile and Lighthouse settings as above. Raw output is in
[`lighthouse/2026-10-12/max-age-0/`](performance-baseline/lighthouse/2026-10-12/max-age-0/),
and the steps are in
[`plan-perf-page-cache-before.md`](../plans/plan-perf-page-cache-before.md).

**What the setting controls.** Core's Internal Page Cache ignores it:
*"Because of this, page cache ignores max age"*
(`web/core/modules/page_cache/src/StackMiddleware/PageCache.php:268-269`). It
only sets the `Cache-Control` header for browsers and CDNs
(`FinishResponseSubscriber.php:164`, `:254`). The measurement confirms it:

| | `max_age` 900 (baseline) | `max_age` 0 (before `fbf3b43`) |
|---|---|---|
| `Cache-Control` | `max-age=900, public` | `must-revalidate, no-cache, private` |
| `Expires` | — | `Sun, 19 Nov 1978 05:00:00 GMT` |
| `X-Drupal-Cache` on a repeat request | `HIT` on all six | **`HIT` on all six** |
| TTFB warm, median range across the six URLs | 14–51 ms | 16–34 ms |
| Article node: LCP | 1,506 ms (1,504–1,512) | 1,503 ms (1,360–1,667) |
| Article node: CLS, TBT, requests | 0.012, 0, 9 | 0.012, 0 (0–12), 9 |
| Article node: transfer | 185,618 bytes | 185,387 bytes |

TTFB cold per URL at `max_age` 0, for the record: `/` 586, `/articles` 537,
article node 581, `/recipes` 582, recipe node 2,170, `/recipe-search` 540
(medians in ms, raw in `max-age-0/ttfb.txt`).

**What it says:**

- **On DDEV, `max_age` 0 is not slower.** Drupal's page cache served a `HIT`
  either way, so warm TTFB and the article node's LCP sit inside each other's
  noise. Transfer is 231 bytes smaller at 0, which is the size of the
  headers, not the page.
- **What `max_age` 0 costs is reuse outside Drupal, and DDEV can't show it.**
  `no-cache, private` tells a CDN not to store the HTML, and tells the browser
  to revalidate every time. DDEV has no CDN, and Lighthouse clears the browser
  cache every run. The place it would show is Pantheon Dev, behind its CDN:
  every visit would reach PHP instead of the edge. That measurement changes
  the hosted site's config, so it has not been made.
- **Cold TTFB was noisier in this session.** The recipe node's cold median is
  2,170 ms against 1,552 ms in the baseline, and the article node's 581 against
  428. A cold request after `drush cr` does the same work whatever `max_age`
  is, so this is run-to-run variation on the machine, not the setting. Treat
  cold TTFB as accurate to a few hundred ms, and compare it only across many
  runs.
- **So the honest before/after for `fbf3b43` is a header, not a speed-up.** It
  belongs in the case study as "the HTML became cacheable by browsers and
  CDNs", measured on Pantheon if it is measured at all.

### No "before" for the jQuery UI removal (`48bbdad`)

Decided 2026-10-12: **not reproduced, and dropped from the deliverables.**
`48bbdad` uninstalled `facets_range_widget`, `jquery_ui_slider` and
`jquery_ui_touch_punch`, and removed three Composer packages
(`drupal/jquery_ui_slider`, `drupal/jquery_ui_touch_punch`,
`politsin/jquery-ui-touch-punch`). None of them was ever attached to a page:
both facets use the checkbox widget, and the slider's vendor library was never
downloaded. The commit checked that the stylesheet and script lists on `/`,
`/recipes` and `/recipe-search` were byte-identical before and after. **Its
front-end cost was 0 bytes and 0 requests**, so a reproduction (80 commits
back, plus re-enabling uninstalled modules) would only measure that again.
`jquery_ui` itself stays, because Site Studio requires it. The jQuery a visitor
does download is core's jQuery 4.0 on `/articles`, for the Views AJAX filter,
which `48bbdad` did not touch.


### What the `articles` view's own cache buys — 2026-10-15

Week 9–12 item 2. Measurement profile, SHA `74b8af3`. `/articles` TTFB × 5,
with only the page and dynamic page caches cleared before each request
(`drush cache:clear bin page,dynamic_page_cache`). Checked: that request
returns `x-drupal-cache: MISS` and `x-drupal-dynamic-cache: MISS`, so each
request rebuilds the page, and only the view's own cache differs between the
two runs. The view was switched in the Views UI (all displays) and back, and
`config:status` showed only the four known differences afterwards.

| `articles` view cache | Runs (ms) | Median |
|---|---|---|
| `tag` | 121, 169, 92, 48, 34 | 92 |
| `none` | 112, 99, 56, 59, 47 | 59 |

**No measurable difference.** The ranges overlap, and both runs fall steadily
from the first request to the fifth: that warm-up is larger than any gap
between them. The view lists five articles with a cheap query, and each card is
render-cached separately whatever the view's setting. So the view's own cache
only wraps five already-cached cards. It would start to matter with many rows,
an expensive query, or rows that aren't cached on their own.

---

## DevTools traces

These are DevTools Performance-panel recordings with **applied** throttling.
They are a separate series from the Lighthouse numbers, which use simulated
throttling, and the two are never compared.

### Article node — 2026-10-09

**Setup:** `/articles/four-years-they-let-me-make-carbonara` · commit `af026f4`
(the measurement-profile tooling was uncommitted, but no site code was
changed) · DDEV with `ddev measure on` · Chrome 153 · CPU 4× · network Slow 4G
(180,000 B/s down, 84,375 B/s up, 562.5 ms request latency) · logged out ·
server cache warm (`x-drupal-cache: HIT`, `cache-control: max-age=900,
public`) · browser cache disabled · five *Record and reload* runs in one
recording.

**Trace:** [`performance-baseline/traces/article-node-2026-10-09.json.gz`](performance-baseline/traces/article-node-2026-10-09.json.gz)
(gzipped; open it in the Performance panel with *Load profile*).

| Run | LCP element | LCP | TTFB | Resource load delay | Resource load duration | Element render delay | CLS |
|---|---|---|---|---|---|---|---|
| 1 | deck (text) | 1,509 ms | 611 ms | n/a (text LCP) | n/a (text LCP) | 897 ms | 0 |
| 2 | deck (text) | 1,460 ms | 577 ms | n/a | n/a | 882 ms | 0 |
| 3 | deck (text) | 1,493 ms | 586 ms | n/a | n/a | 907 ms | 0 |
| 4 | deck (text) | 1,508 ms | 594 ms | n/a | n/a | 914 ms | 0 |
| 5 | deck (text) | 1,493 ms | 587 ms | n/a | n/a | 905 ms | 0 |
| **Median** | **deck (text)** | **1,493 ms** | **587 ms** | **n/a** | **n/a** | **905 ms** | **0** |

The median row takes the median of each column separately. TTFB is when the
renderer received the document response, which includes DevTools' throttling
delay.

**What the breakdown says:**

- **About 60% of LCP is element render delay (905 of 1,493 ms).** The deck
  text arrives with the HTML at about 590 ms but cannot paint until three
  render-blocking files finish: two aggregated CSS files (done at about 1,190
  and 1,340 ms) and one aggregated JS file (done at about 1,245 ms). They are
  discovered only when the HTML is parsed, and each pays the 562.5 ms request
  latency.
- **Render delay here is network-bound, not CPU-bound.** The logged-in,
  unthrottled-CPU observation below measured about 890 ms of render delay. 4×
  CPU added only about 15 ms. No task on the main thread ran longer than 50 ms.
- **TTFB (about 590 ms) is almost all network latency.** The page-cache `HIT`
  means Drupal does next to no work, so the server side is not the lever on
  this page.
- **CLS is 0.**

The obvious levers are the three render-blocking requests: fewer of them,
smaller ones, or ones discovered earlier. Changing them is Week 11–14 work, and
this table is its "before".

### Cost of `recipe-interactions.js` on an article node — 2026-10-10

**Source:** the article-node trace above (`article-node-2026-10-09.json.gz`:
five loads, CPU 4×, Slow 4G, logged out, measurement profile), plus the footer
aggregate it loaded, `js_zjHkGoZ8OxI7-JQBDmsu3ZBCa8iPFMmgq9M1bpeEui4.js`. The
file was still in `sites/default/files/js/` and is the same file the trace
requested. No new recording was needed.

`recipe-interactions.js` is attached globally in `flavourful.info.yml`. On an
article page its only work is `once("recipe-toggle", ".recipe-card", …)`,
which finds no `.recipe-card` and returns.

| Cost | Number | How it was measured |
|---|---|---|
| Source size | 467 B (300 B gzipped alone) | `wc -c`, `gzip -9` on `js/recipe-interactions.js` |
| Size inside the aggregate | 297 B minified, 3.5% of the 8,437 B footer aggregate | Its segment (line 11 of the aggregate) |
| Added transfer | **78 B** gzipped: the aggregate is 2,952 B with it and 2,874 B without | `gzip -9` on the aggregate with and without the segment. The trace shows 2,998 B transferred, including headers |
| Execute time | **Below the profiler's resolution:** 0 of 67,041 CPU samples, at a 168 µs interval, over five loads | Profile samples mapped to the segment's line in the aggregate. `Drupal.attachBehaviors`, which runs every behaviour on the page, was never sampled either |
| Parse and compile | Not separable. For the **whole** 8,437 B aggregate, per load: background parse 0.2–0.3 ms, compile 0.07–0.57 ms, evaluate 0.8–1.4 ms. At 3.5% of the bytes, this script's share is roughly 0.05 ms | `v8.parseOnBackground`, `v8.compile` and `EvaluateScript` events for the aggregate |

**What it says:**

- **The script costs about 78 bytes on the wire and too little CPU to
  measure.** At Slow 4G's 1.44 Mbps, 78 B is about 0.4 ms.
- **Removing it from article pages would not remove the Drupal JS core.** It
  depends on `core/drupal`, `core/once` and `core/drupalSettings`, but so does
  `mega-menu.js` (`core/drupal`, `core/once`), and `core/drupal` itself depends
  on `core/drupalSettings`. All of them load on article pages regardless.
- **So moving it is code hygiene, not performance.** That is the Week 11–14
  decision: the measured gain is about 78 B and no measurable time.

### Facet click on /recipe-search — 2026-10-10

**Setup:** commit `af026f4` · DDEV with `ddev measure on` · Chrome 153 · CPU 4×
· network Slow 4G (180,000 B/s down, 84,375 B/s up, 562.5 ms request latency)
· logged out (every response `cache-control: max-age=900, public`, none
`UNCACHEABLE (request policy)`) · browser cache disabled. One ● *Record*
session containing three facet clicks among other navigation. Each facet URL
was being requested for the first time, so every one was an `x-drupal-cache:
MISS`.

**Trace:** [`performance-baseline/traces/facet-click-2026-10-10.json.gz`](performance-baseline/traces/facet-click-2026-10-10.json.gz).

**What a facet click is:** the checkbox's jQuery `change` handler
(`facets/js/checkbox-widget.js`) triggers `facets_filter`, and
`facets/js/base-widget.js` sets `window.location`. Every click is a full page
navigation; `views.view.recipe_search` has no AJAX.

| Click | `change` handler | Click → navigation | Longest task on the new page | What that task is | Server time (cold) | LCP (text) |
|---|---|---|---|---|---|---|
| Diet type → Vegetarian on | 9.2 ms | 36 ms | 37.6 ms | Rendering: style 15 ms, commit 11 ms, layout 7 ms. No script | 253 ms | 1,430 ms |
| Diet type → Vegetarian off | 6.8 ms | 41 ms | 37.0 ms | Rendering: commit 14 ms, layout 11 ms, style 7 ms. No script | 203 ms | 1,288 ms |
| Diet type → Vegan on | 2.1 ms | 11 ms | under 30 ms | — | 164 ms | 1,320 ms |

**Server time** is the response's `receiveHeadersStart − sendEnd`. On these
pages DevTools' 562.5 ms throttling latency hides it inside a TTFB of about
600 ms, so it is read directly.

**What it says:**

- **No long tasks.** Nothing on the main thread reached 50 ms, in any of the
  six page loads in the recording. The longest were about 37 ms, and they were
  rendering work (style, layout, commit), not script. The facets' own JavaScript
  costs 2–9 ms per click. The only script-heavy task was a 25.8 ms
  jQuery/Drupal `listener` in the aggregated JS, on `/`.
- **The cost of a facet click is the server, not the browser:** 164–253 ms of
  Drupal plus Solr for a new filter combination, against 17–37 ms for the
  page-cached article page.
- **Caveat:** 4× slowdown on an Apple M5 is still faster than a low-end phone.
  The ~37 ms rendering tasks are the first that would cross 50 ms on such a
  device.

**Facet pages are cached after all, for anonymous visitors (checked 2026-10-10
with `curl`).** `/recipe-search` reports `X-Drupal-Cache-Max-Age: 0` and
`X-Drupal-Dynamic-Cache: UNCACHEABLE (poor cacheability)`. Internal Page Cache
ignores that bubbled max-age: the first anonymous request for a filter
combination is a `MISS` (about 200 ms), and every repeat is a `HIT` (11–21 ms),
sent with `cache-control: max-age=900, public`. "Uncacheable" is true of the
dynamic page cache only, so of logged-in visitors. Whether a cached facet page
goes stale when a recipe changes is a Week 9–12 question.

---

## XHGui profile of /recipe-search — 2026-10-12

The server half of the facet click above: what Drupal spends a page-cache
`MISS` on, function by function.

**Setup:** commit `af026f4` · DDEV with `ddev measure on`, which flushed every
cache · warm-up: two anonymous requests (`/recipe-search` and
`?f[0]=recipe_cuisine_type:31`) to fill the shared render cache · XHGui on
(`xhprof_mode: xhgui`) · three anonymous `curl` requests to facet URLs not
requested since the flush, each confirmed `x-drupal-cache: MISS` · one repeat,
confirmed `HIT`, for contrast. The runs are stored in DDEV's `xhgui` database,
table `results`. Steps and commands:
[`plan-perf-xhgui-recipe-search.md`](../plans/plan-perf-xhgui-recipe-search.md).

**XHProf inflates times.** These numbers rank functions and give shares of a
request. They are never compared with the DevTools traces' server times.

| Run | `x-drupal-cache` | Wall time (XHProf) | Peak memory | Functions called | DB queries | Solr requests |
|---|---|---|---|---|---|---|
| `diet_type:62` Vegetarian | `MISS` | 245.0 ms | 21.2 MB | 4,442 | 135 | 1 |
| `diet_type:59` Vegan | `MISS` | 143.8 ms | 21.1 MB | 4,435 | 74 | 1 |
| `diet_type:60` Non Vegetarian (median) | `MISS` | 173.3 ms | 21.7 MB | 4,502 | 104 | 1 |
| `diet_type:62` repeat | `HIT` | **3.0 ms** | 2.3 MB | 268 | 3 | 0 |

### Where a `MISS` spends its time

Inclusive wall time, as a share of the request, with framework wrappers left
out. The ranges cover all three `MISS` runs:

| Share | Step | Owner |
|---|---|---|
| 36–47% | Rendering the result rows: `ViewExecutable::render` → `StylePluginBase::renderFields` → field theming and Twig | Views, the theme |
| 21–25% | The search query: `SearchApiQuery::execute` → `SearchApiSolrBackend::search` → one Solr HTTP request | Search API, `search_api_solr` |
| the rest | The page around the view: blocks, menus, `HtmlRenderer`, Cohesion route checks | Core render system, Site Studio |

### The three most expensive functions

Ranked by **exclusive** time (the function's own time, not its callees). They
are the top three in every `MISS` run:

| # | Function | Own time (3 `MISS` runs) | Calls | What it is |
|---|---|---|---|---|
| 1 | `Solarium\Core\Client\Adapter\Curl::getData` | 6.9–36.8 ms (4–15%) | 1 | Waiting for Solr's HTTP response: the facet query and its counts, in **one** request. The spread is Solr's own response time |
| 2 | `Drupal\Core\Database\Statement\StatementBase::clientExecute` | 15.8–30.0 ms (11–16%) | 74–135 | Executing SQL queries: entity loads for the result rows, path aliases and config. The count grows with the rows rendered |
| 3 | `Drupal\Core\Cache\ApcuBackend::getMultiple` | 3.9–10.9 ms (2–4%) | 107–118 | Reading cache items from APCu: the warm render cache and the discovery caches doing their job |

The `HIT` run, by contrast, spends 3.0 ms in total: 0.7 ms in 3 database
queries, and the rest in kernel start-up and class loading, with no Solr
request. That is Internal Page Cache answering before Drupal renders anything.

**What it says:**

- **A facet click's server time is mostly rendering, not searching.** The
  search query, Solr included, is about a quarter of the request. Rendering
  the result rows is about 40%.
- **There is one Solr request per page load.** The facet counts come back in
  the same request as the results, so `show_numbers: true` does not add a
  request. Whether it makes that request slower needs a toggle-and-compare
  test (Finding 5).
- **Database work scales with the rows.** There were 74 queries for 3 results
  (Vegan) and 104–135 for 5–12 results. Entity loading per row is the likely
  source, and naming the exact queries is Week 9–12 work.
- **The page cache removes almost all of it.** A repeat costs 3 ms against
  144–245 ms. The cost lands only on the first visitor to each filter
  combination, and on every logged-in visitor.

---

## Repeat view on Pantheon Dev — 2026-10-12

What a returning visitor's browser cache saves, on the article node. This is
item 5's question, answered without WebPageTest, which can't test this target
(below).

**Setup:** `https://dev-the-cookbook.pantheonsite.io/articles/four-years-they-let-me-make-carbonara`
· Pantheon `ad21c90` (the same files as `master`) · logged out.
**First view:** worked out from Pantheon's own response headers and compressed
sizes (`curl --compressed`, plus `If-None-Match` for the `304` sizes).
**Repeat view:** measured in Chrome DevTools → Network, *Disable cache* off,
Slow 4G. Steps: load the article, click *Articles*, click the same article.
Steps and commands:
[`plan-perf-webpagetest-repeat-view.md`](../plans/plan-perf-webpagetest-repeat-view.md).

**WebPageTest can't test Pantheon Dev.** Pantheon Dev is fronted by
Cloudflare (`server: cloudflare`), which challenges automated browsers. A
WebPageTest run on 2026-10-12 got a **403** and Cloudflare's "Performing
security verification" page. Checked with `curl`: a plain request gets
**200**, while the same request with a Chrome or WebPageTest (`PTST`)
user-agent gets **403 `cf-mitigated: challenge`**. A real Chrome passes the
challenge. Expect the same block for Lighthouse CI or PageSpeed Insights
pointed at this URL.

**What Pantheon Dev sends:**

| Response | `cache-control` | Other headers that matter |
|---|---|---|
| Article HTML | `max-age=900, public` | `vary: Accept-Encoding,Cookie`; Cloudflare edge cache (`cf-cache-status: HIT`, `age` already counting down); a new `__cf_bm` cookie on every response |
| 2 CSS aggregates, 1 JS aggregate, the hero image, 3 fonts, the favicon | **`no-cache`** | An `ETag`, and each answers `304 Not Modified` to `If-None-Match` |

| | Requests | Transferred | Detail |
|---|---|---|---|
| **First view** (from headers) | 9 | **≈ 194.7 kB** | HTML 7.5 kB, CSS 10.3 + 2.1 kB, JS 4.0 kB, hero 35.7 kB, fonts 38.6 + 49.3 + 45.0 kB, favicon 2.3 kB |
| **Repeat view** (measured) | 9 | **8.0 kB** | 7 × `304` (both CSS, the JS, the hero, two fonts, the favicon), the HTML re-fetched in full (`200`, about 7 kB), one font from memory cache. 251 kB of resources used |
| **Saved** | **0 requests** | **≈ 186.7 kB (96%)** | The memory-cache font skipped the network only because `/articles` had just loaded it in the same tab. On a real return visit it is a `304` too |

**What it says:**

- **The browser cache saves the bytes but none of the requests.** Every
  static file is sent `no-cache`, so a returning browser has to ask about each
  one. Aggregation gives the CSS and JS stable, hashed file names that could
  safely be cached for a year. Here, that benefit is lost.
- **Each of those `304`s costs a round trip.** On Slow 4G the repeat view
  still took 1.26 s to `load`. The two render-blocking CSS files have to be
  revalidated before anything paints, even on a return visit.
- **The HTML is never reused either.** It varies by `Cookie`, Cloudflare sets
  a fresh `__cf_bm` cookie on every response, and the copy arrives already part
  aged by Cloudflare's own cache. Which of these caused this particular
  re-fetch was not isolated.
- **Open question for Week 11–14:** why Pantheon Dev sends `no-cache` on
  static files (a Dev-environment rule, or a missing setting), and whether
  Test or Live differ.

---

## Observations — not baseline numbers

These came from recordings taken under conditions that differ from the
measurement profile, so they are **not** baseline values and are never compared
with an "after". They are kept because the diagnosis they show does not depend
on those differences.

### Article pages: render delay dominates LCP (2026-10-09)

**Conditions:** DevTools Performance panel, Chrome 153, DDEV with
`ddev measure on`, network Slow 4G (180,000 B/s down, 84,375 B/s up, 562.5 ms
request latency), **no CPU throttling**, and **logged in** (as a `chef` user),
so every response was `x-drupal-cache: UNCACHEABLE (request policy)` and was
served by the dynamic page cache. Two recordings, five page loads each:
`/articles/four-years-they-let-me-make-carbonara` (unthrottled) and
`/articles/what-carbonara-taught-me-about-ramen-broth` (Slow 4G).

| Load (Slow 4G recording) | LCP | TTFB | Element render delay | CLS |
|---|---|---|---|---|
| 1 | 1,529 ms | 629 ms | 900 ms | 0 |
| 2 | 1,489 ms | 593 ms | 896 ms | 0 |
| 3 (median) | 1,484 ms | 586 ms | 898 ms | 0 |
| 4 | 1,465 ms | 580 ms | 885 ms | 0 |
| 5 | 1,480 ms | 594 ms | 886 ms | 0 |

TTFB here is when the renderer received the document response. The response's
own `timing.receiveHeadersStart` leaves out DevTools' throttling delay on
reused connections, so it reads 54–85 ms on loads 2–5.

**What holds regardless of the conditions:**

- **The LCP element is text** (the article deck) on both articles, in all ten
  loads, with one LCP candidate each. Resource load delay and resource load
  duration do not apply.
- **Render delay is most of LCP** (about 890 of 1,480 ms). Two render-blocking
  aggregated CSS files and one render-blocking JS file (about 12 kB together)
  are requested only once the HTML arrives, at about 590 ms. Each pays the
  562.5 ms request latency, so they finish around 1,350 ms, and the deck can
  only paint after that.
- **CLS is 0** on article pages during load.

The baseline row for this page is under *DevTools traces → Article node*,
recorded logged out with 4× CPU under the measurement profile.
