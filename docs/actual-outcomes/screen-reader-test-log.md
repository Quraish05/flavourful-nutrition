# Screen Reader Test Log — Flavourful

> Six scripted task runs on the live DDEV site · Runs: 8–15 September 2026 · Last updated: 2026-09-15
>
> Companion to [`accessibility-audit.md`](accessibility-audit.md). The audit says **what was wrong and what was fixed**; this log says **what a screen reader actually did** on each task, before and after. Task scripts come from the Notion study doc *"Screen Reader Task Scripts — Six Runs on Flavourful"*.
>
> **Status: six of six tasks run and passing.** Evidence provenance is recorded per row — see *How to read the evidence column*, which is the part of this document that makes it trustworthy.

---

## How to read the evidence column

Not every row is backed by the same kind of proof, and pretending otherwise would make the strong rows worth less. Each result carries a tag:

| Tag | Means |
|---|---|
| **DOM** | Read from the rendered page or the post-JavaScript DOM. Objective, reproducible, and says nothing about how it sounds |
| **A11y tree** | Read from the browser's computed accessibility tree — name, role, state as assistive technology receives it |
| **Keyboard** | Driven from the keyboard only, no mouse |
| **VO** | Run by the developer with **VoiceOver + Safari on macOS**. Verdict recorded; **the verbatim utterance was not captured** |
| **VO + caption** | Run with VoiceOver and the Caption Panel screenshotted — the utterance is quoted exactly |

**Nothing in this log is a quoted utterance unless it is tagged `VO + caption`.** Where a row says what a control "announces", that is the computed accessible name from the accessibility tree, which is what a screen reader reads out — but it is a computed value, not a recording.

**Open gap:** no row currently carries `VO + caption`. Turning on VoiceOver Utility → Visuals → Caption Panel and screenshotting eight or so moments would upgrade the whole document, and those screenshots are the client-facing artefact. Listed as remaining work at the bottom.

---

## Task 1 — Filter to a recipe and open it

**Route:** `/recipe-search` · **Goal:** apply a diet facet, find the result count, open a recipe · **Criterion probed:** 4.1.3, 4.1.2, 2.4.3

| What was checked | Before | After | Criterion | Evidence | Result |
|---|---|---|---|---|---|
| Facet controls are real checkboxes | Server markup is `<a>` wearing checkbox classes | Facets' JS replaces them with `<input type="checkbox">` + bound `<label for>`; 4 inputs, 4 labels, correctly paired; the superseded link is `display: none` and is not a second tab stop | 4.1.2 (A) | DOM (post-JS) | **Pass** — credit to contrib |
| The count is part of the name | — | `show_numbers` puts the count **inside** the `<label>`, so the name is "Vegan (3)", announced rather than merely repainted | 4.1.2 (A) | A11y tree | **Pass** |
| Result-count change is announced | Assumed to need a live region | Applying a facet is a **full page reload** — the new state arrives with a new page | 4.1.3 (AA) | DOM | **Not applicable** |
| Focus after applying a facet | Assumed dropped to `<body>` | Focus is at `<body>`, but via an ordinary page load, not a dropped focus | 2.4.3 (A) | Keyboard | **Not applicable** |
| The reset control | Announced **"Clear all filters (20), checkbox, checked"** on an unfiltered page — a control asserting that a filter named "Clear all filters" was applied | Absent until something is actually selected | 4.1.2 (A) | A11y tree | **Fail → Pass** ([#25](https://github.com/Quraish05/flavourful-nutrition/pull/25)) |
| The show-more toggle | `<a href="#">` with no `aria-expanded` — state invisible | `aria-expanded` mirrors the module's `open` class; cycles correctly | 4.1.2 (A) | A11y tree, VO | **Fail → Pass** ([#25](https://github.com/Quraish05/flavourful-nutrition/pull/25)) |
| Exposed filter form has a group name | Predicted missing | Renders `<fieldset><legend>Filter recipes</legend>` — from core, no theme override | 1.3.1 (A) | DOM | **Pass** — prediction was wrong |

**What this task taught.** The script's headline expectation was a 4.1.3 live-region failure. Establishing the *mechanism* first — full reload, not AJAX — turned it into a criterion correctly cleared. The real defect was somewhere nobody had looked: a reset control lying about its own state, visible only after JavaScript ran.

---

## Task 2 — State how difficult a recipe is, without looking

**Route:** any card on `/recipes` · **Criterion probed:** 1.3.1

| What was checked | Before | After | Criterion | Evidence | Result |
|---|---|---|---|---|---|
| Is difficulty reachable at all? | **No.** The badge sat inside `<a class="recipe-card__media" aria-hidden="true" tabindex="-1">`, so it was removed from the accessibility tree along with the duplicate link it was nested in | Badge renders in the card body, outside the hidden container | 1.3.1 (A) | A11y tree, VO | **Fail → Pass** |
| Is the value self-sufficient? | — | A visually-hidden `Difficulty:` prefix means the name is "Difficulty: Medium", not a bare "Medium" that relies on colour and position for meaning | 1.3.1 (A) | A11y tree | **Pass** |
| Is the duplicate media link still suppressed? | — | Yes — `aria-hidden` **and** `tabindex="-1"` together, which is the correct pairing. Hiding a still-focusable element is the common mistake here | 4.1.2 (A) | Keyboard | **Pass** |

**This is the 30-second demonstration.** A sighted user sees "Medium" on every card; a screen-reader user was told nothing at all. The `aria-hidden` that caused it was *deliberate and correct* — it suppressed a genuine duplicate link. Good intent, subtle consequence, cheap fix. No automated scanner flags this, because the markup is valid and the attribute is doing exactly what it says.

---

## Task 3 — Get to page 3 and open the fifth recipe

**Route:** `/recipes` · **Criterion probed:** 4.1.2, 1.3.1, 2.4.4

| What was checked | Before | After | Criterion | Evidence | Result |
|---|---|---|---|---|---|
| Pager link names | Already correct — "Page 3", and the pagination nav has a name | unchanged | 2.4.4 (A) | A11y tree | **Pass** |
| Current page state | Marked by a CSS class only; the current item is plain text, not a link | `aria-current="page"`, and it reads **"Page 2 of 3"** — core computes the total to decide whether to draw prev/next, then discards it, so a preprocess hook re-exposes it | 4.1.2 (A) | A11y tree, VO | **Fail → Pass** ([#26](https://github.com/Quraish05/flavourful-nutrition/pull/26)) |
| Card heading level | `<h3>` hardcoded, giving h1 → h3 on every listing page | `heading_level` is a required prop; `/recipes` cards are `<h2>` | 1.3.1 (A) | DOM | **Fail → Pass** |
| Pager heading level | `h4`, so the page reads h2 → h4 | **Still `h4`** on this view | 1.3.1 (A) | DOM | **Open** — see audit O-1 |

**Correction recorded.** The first remediation attempt edited `templates/navigation/pager.html.twig` and changed nothing, because **every view on this site uses the mini pager**. `views-mini-pager.html.twig` is the file that renders. Editing a template that never executes produces a convincing diff and no behaviour change — the same trap as auditing a disabled view.

---

## Task 4 — From a recipe, find the chef and their other recipes

**Route:** recipe node → `/chefs` → `/chefs/{id}/recipes` · **Criterion probed:** 2.4.4, 4.1.2, 2.4.6

| What was checked | Before | After | Criterion | Evidence | Result |
|---|---|---|---|---|---|
| Links on `/chefs` | **20 links, 10 of them completely empty** — announced as "link" with no text, occupying ten invisible tab stops. And the destination someone configured deliberately (the chef's recipe listing) was the *unnamed* one, while the name that was exposed pointed at an unaliased `/node/67` | **10 links, 0 empty**, each named and pointing at `/chefs/{id}/recipes` | 2.4.4 (A), 4.1.2 (A) | DOM (browser), VO | **Fail → Pass** ([#27](https://github.com/Quraish05/flavourful-nutrition/pull/27)) |
| Headings on `/chefs` | **None.** Ten chefs, nothing to navigate by — while `/recipes` gave every card an `<h2>` | 10 chef `<h2>`s | 2.4.6 (AA) | DOM | **Fail → Pass** |
| The chef page's `<h1>` | Rendered its own markup as escaped text: `&lt;span class="field…"&gt;Joe H&lt;/span&gt;` | `<h1>Joe H</h1>` | 1.3.1 (A) | DOM, VO | **Fail → Pass** ([#26](https://github.com/Quraish05/flavourful-nutrition/pull/26)) |
| Embedded view heading levels | Predicted wrong | A recipe reads h1 *Butter Chicken* → h2 *Related Recipes* → h3 *Estimated nutrition* / *More from this chef*. No skips | 1.3.1 (A) | DOM | **Pass** — prediction was wrong |

**The method note that matters most in this whole log.** The empty links on `/chefs` **do not exist in the page source.** One `<a>` is emitted per row; the source contains a nested pair, and the *HTML parser* splits that pair into siblings, creating the empty one. A `curl`-and-`grep` check therefore reports the page as healthy — and the first version of our check did exactly that, printing *"links: 12 | empty: 0"* on a page carrying ten nameless links.

**Rule, now written into the plan template:** anything created by the parser or by JavaScript cannot be verified from source. This is the single most reusable thing the six runs produced.

The rotor view of this is also the clearest client-facing clip available from the project: `VO+U` → Links on `/chefs` goes from twenty entries, ten announcing as a bare "link", to ten named ones. No narration needed.

---

## Task 5 — Find every recipe beginning with "S"

**Route:** `/glossary`, `/glossary/s` · **Criterion probed:** 2.4.4, 1.3.1, 4.1.2, 2.4.2

**The task could not be run at all at first.** Core's `glossary` view ships **disabled**, and nobody had enabled it — `/glossary` was a 404. Every observation originally written for this task was a prediction about a page that did not exist, and several were wrong.

| What was checked | Before | After | Criterion | Evidence | Result |
|---|---|---|---|---|---|
| Does the page exist? | **404** — `status: false`, no route | Enabled, filtered to the Recipe bundle | — | DOM | **Fail → Pass** ([#23](https://github.com/Quraish05/flavourful-nutrition/pull/23)) |
| Page title | **All eleven letter pages shared one title** — `<title>Glossary</title>`, `<h1>Glossary</h1>` | "Recipes beginning with S" in both | **2.4.2 (A)** | DOM, VO | **Fail → Pass** |
| Does it answer the question? | No content-type filter — `/glossary/s` returned two recipes **and the chef Sofía Herrera** | Eleven letters totalling **21** — exactly the published recipe count; no chefs | — | DOM | **Fail → Pass** |
| A–Z list semantics | A run of `<span>`s with a literal ` \| ` separator inside each. **No list semantics whatever** — it only *looked* like a list because each span fell on its own line | Real `<ul>/<li>` | 1.3.1 (A) | DOM | **Fail → Pass** |
| Letter link names | Bare **"A"**, **"B"**, **"S"** | "Recipes beginning with S" — visually-hidden prefix inside each link | 2.4.4 (A) | DOM, VO | **Fail → Pass** ([#28](https://github.com/Quraish05/flavourful-nutrition/pull/28)) |
| Active letter state | CSS class only — you could not tell by ear that you were on S | `aria-current="page"` | 4.1.2 (A) | A11y tree, VO | **Fail → Pass** ([#26](https://github.com/Quraish05/flavourful-nutrition/pull/26)) |
| A–Z group context | No landmark, no name, no heading | `<nav aria-label="Browse recipes by first letter">` | 1.3.1 (A) | DOM, VO | **Fail → Pass** ([#28](https://github.com/Quraish05/flavourful-nutrition/pull/28)) |
| Table name | No `<caption>` | "Recipes, sortable by title and last update" | 1.3.1 (A) | DOM | **Fail → Pass** |
| Header cells | Predicted fine | `scope="col"` on all three — set unconditionally by the theme | 1.3.1 (A) | DOM | **Pass** |
| Sort state | Predicted absent | **Core sets `aria-sort`** on the sorted column. The original check looked in `views.theme.inc`; the hooks had moved to `src/Hook/ViewsThemeHooks.php` | 4.1.2 (A) | DOM | **Pass** — prediction was wrong |
| Table replaced on sort without announcement | `use_ajax: true` — sorting and paging silently replaced the table | AJAX off | 4.1.3 (AA) | DOM | **Fail → Not applicable** |
| Visual current-letter indicator | None | **Still none** — the stylesheet that would provide it never loads on this route | — | DOM | **Open** — see audit O-5 |

**Two lessons, and the second is uncomfortable.** First: *load the page before writing the finding* — a task written against a 404 produces confident prose about markup that never rendered. Second: **searching the wrong file reads exactly like absence.** `aria-sort` was reported missing twice on that basis.

And the parity note: after all of this, a screen-reader user can now tell which letter is current and a **sighted user still cannot**. Remediation can invert a defect rather than remove it.

---

## Task 6 — Navigate the whole site by landmarks alone

**Routes:** all · **Criterion probed:** 1.3.1, 2.4.1

| What was checked | Before | After | Criterion | Evidence | Result |
|---|---|---|---|---|---|
| Skip link | Predicted broken | **Works.** Tab once from a fresh load, Enter, focus moves to `#main-content` | 2.4.1 (A) | Keyboard | **Pass** — see the correction below |
| Two `complementary` landmarks | Both unnamed — indistinguishable in the landmarks list | Named **per route**: "Search filters" on `/recipe-search`, "More about this recipe" on a recipe, "Secondary information" elsewhere | 1.3.1 (A) | A11y tree, VO | **Fail → Pass** ([#24](https://github.com/Quraish05/flavourful-nutrition/pull/24)) |
| `contentinfo` count | **Two** — the status-messages region also claimed `role="contentinfo"` | Exactly one | 1.3.1 (A) | DOM | **Fail → Pass** |
| Local actions block | Unnamed landmark | `aria-label="Actions"` | 1.3.1 (A) | A11y tree | **Fail → Pass** |
| `<header>` inside the view | Suspected duplicate `banner` | **Not a banner.** Per HTML-AAM, `<header>` maps to `banner` only when it is *not* inside `article`, `aside`, `main`, `nav` or `section`. This one is inside `main` | 1.3.1 (A) | A11y tree | **Pass** — suspicion was wrong |
| One `<h1>` per page | — | Exactly one on all nine routes tested | 1.3.1 (A) | DOM | **Pass** |
| New landmark added by this work | — | `/glossary` now carries "Browse recipes by first letter" inside `main`. Named so it cannot be confused with "Main navigation" or the category bar | 1.3.1 (A) | A11y tree | **Pass** |

**Correction, recorded rather than buried.** The skip link was initially reported as broken. It was not — the test harness dispatched a `rawKeyDown` event, which fires no default action. With a real `keyDown` the skip link passes. **2.4.1 was never broken**, and a test harness that cannot trigger the behaviour it is testing reports a false failure just as confidently as a true one.

---

## Zoom, reflow and motion preferences

Run by the developer alongside the six tasks.

| Check | Routes | Criterion | Evidence | Result |
|---|---|---|---|---|
| 400% zoom reaches a single column | `/recipes`, `/recipe-search`, a recipe page, the glossary table | 1.4.10 (AA) | VO/manual | **Pass** — *figures not recorded* |
| No two-dimensional scrolling at 320×256 | as above | 1.4.10 (AA) | manual | **Pass** — *figures not recorded* |
| Text spacing values applied, nothing clips | as above | 1.4.12 (AA) | manual | **Pass** — *figures not recorded* |
| `prefers-reduced-motion` respected | card hover, Site Studio components | — | DevTools emulation | **Pass** — handled in `global.css`; Site Studio's own CSS **not separately confirmed** |
| `prefers-contrast` | — | — | — | **Not run** |

**These rows record a verdict, not a measurement.** The task script asked for numbers to be written down and they were not. That is a real gap: "reflow passes" is an assertion, while "the layout reaches a single column at 400% on all four routes, measured at 1280×1024 base" is evidence. Worth re-running once with figures captured — it is a fifteen-minute job and it makes four rows citable instead of four rows asserted.

---

## Summary

| Task | Defects found | Fixed | Open | Criteria cleared |
|---|---|---|---|---|
| 1 — Facet filtering | 2 | 2 | 0 | 2 |
| 2 — Difficulty by ear | 1 | 1 | 0 | 0 |
| 3 — Pagination | 2 | 2 | 1 | 0 |
| 4 — Chef navigation | 3 | 3 | 0 | 0 |
| 5 — Glossary A–Z | 9 | 9 | 1 | 2 |
| 6 — Landmarks | 3 | 3 | 0 | 1 |
| **Total** | **20** | **20** | **2** | **5** |

Five predictions written into the task scripts **inverted under testing** — the exposed form's legend, the embedded heading levels, `aria-sort`, the sort indicator, and the skip link were all reported as defects and all turned out to be correct. Each is recorded as a pass above rather than quietly deleted, because a checklist that only ever confirms its own suspicions is not a test.

---

## Remaining work on this log

1. **Capture Caption Panel screenshots** for the eight strongest moments, so rows can be upgraded from `VO` to `VO + caption`. The `/chefs` links rotor is the best single clip — twenty entries down to ten, no narration needed.
2. **Re-run the zoom and reflow checks with figures recorded**, replacing four asserted rows with measured ones.
3. **Run `prefers-contrast`**, which was skipped.
4. **NVDA + Firefox on Windows**, pending a VM. The WebAIM survey shows NVDA and JAWS are what most real users run, so a VoiceOver-only log understates coverage — and that limit is stated in the audit rather than left for a reader to discover.
