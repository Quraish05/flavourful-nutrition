# Performance baseline — Flavourful

> Phase 3, Week 8–10 · Last updated: 2026-10-07

**Lab data only.** Every number in this document comes from Lighthouse's
mobile preset run against the local DDEV site (`foodrecipes-drupal.ddev.site`)
from Chrome on an Apple M5 MacBook with 16 GB of RAM. The preset emulates a
Moto G Power screen (412 × 823, DPR 1.75), slows the CPU 4×, and models a slow
4G network at 150 ms RTT, 1.6 Mbps down and 750 Kbps up. The throttling is
*simulated*: Lighthouse loads the page unthrottled and estimates the throttled
timings, so these are modelled numbers, not a slowed-down recording. There is
no field data: the site has no real visitors, so CrUX has nothing for it and
no RUM is collected. The numbers can show whether a change made this site
faster or slower on this setup. They cannot show whether it passes Core Web
Vitals, which is judged on field data at p75.

> **Status: definitions and profile written, no measurements yet.**

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
