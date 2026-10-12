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
> (2026-10-12) and the repeat view on Pantheon Dev (2026-10-12). The Lighthouse
> tables (item 6) are still to come.**

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
