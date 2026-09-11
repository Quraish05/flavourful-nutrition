# Plan — The range slider, and the facet work that replaces it

> Branch: `fix/a11y-facets-and-dead-deps` (off `master@f3461f7`) · Created: 2026-09-09 · Last updated: 2026-09-11
>
> **Status: 8 of 11 steps done. The flagship finding does not exist; the work that replaced it is complete and verified.** Phase 1 shipped as `48bbdad`. Phase 2 is done — F-B and F-C in config, F-E in theme JS. Outstanding: step 8 (F-D, a decision for the developer), step 10 (export) and step 11 (Notion).
>
> Execution plan for the *"flagship finding: the range slider"* slot in the Notion doc *"Phase 1 — Accessibility to Audit Level"* (Week 3–5, *Audit Flavourful for real*). Neither an objective nor an outcome — this is the transient middle state. It feeds an eventual outcome note under [`docs/actual-outcomes/`](../actual-outcomes/).

---

## Why this plan exists

The doc nominated the jQuery UI range slider as the phase's flagship finding — a real deprecation, a real accessibility failure, and the best article available — but attached a condition to it:

> ⚠️ **Verify usage first.** … so the slider may not be wired to anything. Check before planning the work. Either outcome is useful: **in use** means the full replacement task below and the strongest article available; **unused** means two deprecated dependencies to remove, a quick supply-chain win to hand to Phase 3B, and this slot redirects to the facet accessibility work instead.

**Verified: unused.** Four independent checks, all negative:

| Check | Result |
|---|---|
| Facets configured | **2**, `diet_type` and `recipe_cuisine_type`, both `widget.type: checkbox` |
| Slider widgets available but unselected | `facets_range_widget` provides `slider` and `range_slider`; neither is used |
| Slider assets on `/recipe-search` | **0 hits** for `jquery.ui`, `jquery_ui`, `slider`, `touch-punch`, `pips` |
| The vendor library itself | `/libraries/jquery-ui-slider-pips` **is not downloaded** — only `jquery-ui-touch-punch` is present |

That last row is the decisive one. `facets_range_widget.libraries.yml` loads the slider from `/libraries/jquery-ui-slider-pips/dist/…`, and that directory does not exist. **The widget could not work even if somebody selected it**, so there is no rendered control to audit, no keyboard behaviour to document, and no `aria-valuenow` to critique. There is nothing here that a screen reader has ever encountered.

**Decided:** take the doc's own contingency. Remove the dead dependency chain, and spend the slot on the facet accessibility work — which, unlike the slider, is on a page real users hit.

**Rejected — do not re-propose:** building the accessible dual-thumb range SDC anyway, "so it exists when we need it". It would be a component with no consumer, no configured facet to attach to, and no test surface, written against a pattern the site does not use. If a numeric facet is ever added — cooking time is the obvious candidate — build it then, against a real requirement. The article is still writable, but its subject is *"the flagship finding that wasn't"*, which is a better piece anyway.

**Known trap:** `jquery_ui` must **stay**. See F-A.

---

## What the doc got wrong

Four claims in the slider and facet sections do not survive contact with the running site. All four were written from `facets.facet.*.yml`; three of them are contradicted by that same file today, and the fourth by the rendered DOM.

| # | Doc claims | Reality |
|---|---|---|
| 1 | `show_reset_link: false` on both facets — "**there is no way to clear filters.** A keyboard or screen-reader user who over-filters has no recovery path" | **`show_reset_link: true`** on both, with `reset_text: "Clear all filters"`. The control exists and renders. There is a real defect here, but it is the opposite one — see F-B |
| 2 | `empty_behavior: behavior: none` — "**no messaging when a filter combination returns nothing.** The result count silently becomes zero with no announcement" | The **view** supplies a no-results text: *"No recipes match these filters. Try a different combination"*, rendered in `.view-empty`. A facet's `empty_behavior` governs what shows when **that facet** has no values to offer, which is a different condition from an empty result set. The two were conflated |
| 3 | "Both use `checkbox` widgets — verify real `<input type="checkbox">` with associated `<label>`, **not styled links dressed as checkboxes**" | Server-side markup **is** links wearing `js-form-type-checkbox` classes — so the suspicion was well-founded — but `facets/js/checkbox-widget.js` replaces them with genuine `<input type="checkbox">` plus a bound `<label for>`. Verified in the post-JS DOM: 4 inputs, 4 labels, correctly paired. **Credit this** |
| 4 | 4.1.3 Status Messages — "First establish whether applying a facet reloads the page or replaces it with AJAX. If it is a full reload, 4.1.3 does not apply at all and citing it would be wrong — that judgement is the exercise" | **Full reload.** `views.view.recipe_search` sets no `use_ajax` on any display, and clicking a checkbox navigates (`location.href` changes, focus resets to `<body>`). **4.1.3 does not apply, and no live region is owed.** The exercise resolves in the site's favour |

**Verified correct — log these as passes:** `show_numbers: true` puts the count inside the `<label>`, so it is part of the accessible name and is announced ("Vegan (3)"), not merely painted; `query_operator: or` behaves as configured; the leftover `<a>` that the JS supersedes is `display: none` and therefore not a duplicate tab stop — 4 visible controls for 4 filters, not 8.

---

## The findings that are actually here

| # | Finding | Criterion | Severity |
|---|---|---|---|
| **F-A** | `facets_range_widget` is enabled but unused, and is the sole consumer of two deprecated jQuery UI modules | — (supply chain) | Low risk, high tidiness |
| **F-B** | The facet reset control is a **checkbox whose checked state is inverted relative to its label** | **4.1.2** (A) | The real find |
| **F-C** | `soft_limit: 0` — every taxonomy value renders, so tab order grows with the vocabulary | 2.4.3 (A), weakly | Latent |
| **F-D** | No result count anywhere on `/recipe-search`; the only count on screen is the site-wide "21 recipes" | 1.3.1 (A), arguable | Worth raising |
| **F-E** | The soft-limit toggle has no `aria-expanded` — found by step 7, i.e. created by the step 6 fix | **4.1.2** (A) | Found, fixed |

### F-E — the fix that created a finding

Setting a soft limit makes `facets/soft-limit` build a toggle that was not on the page before:

```html
<a href="#" class="facets-soft-limit-link">Show more</a>
```

No `aria-expanded`, no `aria-controls`, and an `<a href="#">` performing an in-page action rather than navigating. The accessible name does flip between *Show more* and *Show less*, but a name is not a state. Activating it also moves focus off the toggle onto the first revealed checkbox — defensible alone, but with no exposed state a screen-reader user goes from "Show more, link" to "Vietnamese, checkbox, unchecked" with nothing explaining that five options appeared.

`soft-limit.js:61` creates the element in JavaScript and it appears in no Twig template, so **no template override can reach it.** The fix is a theme behaviour that mirrors the module's own `open` class into `aria-expanded`.

**Credit:** the five hidden `<li>` are `display: none`, so they are genuinely unfocusable — each one was tested. No phantom tab stops.

### F-A — the dependency chain, and the module that must not be removed

```
facets_range_widget  (enabled, unused)
  ├── jquery_ui_slider        ← only consumer is facets_range_widget
  └── jquery_ui_touch_punch   ← only consumer is facets_range_widget
        └── jquery_ui         ← ALSO required by cohesion, cohesion_elements,
                                cohesion_website_settings  ⚠️ KEEP
```

Both `drupal/jquery_ui_slider` and `drupal/jquery_ui_touch_punch` are **root** requirements in `composer.json` (`composer why` shows `drupal/recommended-project` requires them directly), not transitive ones — somebody added them by hand to satisfy `facets_range_widget`'s install-time dependency.

**`jquery_ui` itself is required by three Cohesion modules.** Removing it would break Site Studio. A naive "remove the deprecated jQuery UI modules" would take the site down, which is exactly the kind of change that looks like a tidy-up and lands as an outage.

### F-B — the reset checkbox announces the opposite of the truth

Measured on `/recipe-search`, `diet_type` block, post-JS DOM:

| Page state | "Clear all filters" checkbox | "Vegan" checkbox |
|---|---|---|
| No filters applied | **checked** | unchecked |
| `?f[0]=diet_type:59` (Vegan) | **unchecked** | checked |

So the control is checked precisely when there is **nothing to clear**, and unchecked once clearing would actually do something. A screen-reader user landing on the unfiltered page hears:

> "Clear all filters (20), checkbox, **checked**"

Which states that a filter named *Clear all filters* is currently applied. It is not a state at all — clearing filters is an **action** — and the state it does expose is backwards relative to its own label. `aria-checked` on a control whose label names an action is 4.1.2 Name, Role, Value.

The underlying cause is that Facets renders the reset item as just another facet item, and its "active" flag means *no selection in this facet*. The checkbox widget then faithfully maps active → checked. So the label is the module's, the inversion is the module's, and neither is ours to fix in a template — but the **config** can remove it from the state where it misinforms.

Note the reset counts are per-facet: with Vegan applied, the `diet_type` reset reads (20) and unchecked while the `recipe_cuisine_type` reset is still checked, because no cuisine filter is set. Two resets, two different states, on one page.

### F-D — nothing states how many results matched

No "N results" string exists in `<main>` on `/recipe-search` in any state. The only count a user sees is the category bar's **"21 recipes"**, which is the site-wide published total from `RecipeStats` — so with Vegan applied the page shows 3 rows under a heading strip that says 21. The result total is recoverable only from the reset control's own count, which is an odd place to hide it.

---

## Phase 1 — remove the dead chain (F-A)

| # | Step | Where | Status |
|---|---|---|---|
| 1 | Uninstall the module | `ddev drush pm:uninstall facets_range_widget` | **Done** |
| 2 | Uninstall the two now-orphaned modules | `ddev drush pm:uninstall jquery_ui_slider jquery_ui_touch_punch` | **Done** |
| 3 | Drop the composer requirements | `ddev exec composer remove drupal/jquery_ui_slider drupal/jquery_ui_touch_punch` | **Done** — also deleted the orphaned `docroot/libraries/jquery-ui-touch-punch` |
| 4 | Confirm `jquery_ui` survived and Site Studio still loads | `ddev drush pm:list --status=enabled \| grep jquery_ui` must still list `jquery_ui`; then load a Site Studio page | **Done** — `jquery_ui` still enabled, all 11 Cohesion modules enabled, `/admin/cohesion` 200. Asset lists on `/`, `/recipes`, `/recipe-search` byte-identical before and after |

Notes:

- **Order matters.** `composer remove` before `pm:uninstall` leaves Drupal with enabled modules whose code is gone — a fatal on the next cache rebuild. Uninstall first, always.
- **Step 4 is not optional.** Three Cohesion modules depend on `jquery_ui`, and the whole point of this phase is that the obvious version of it breaks the site.
- This touches `core.extension`, which was **already drifted** before this branch (see the Task 6 plan). Uninstalling makes that file's export unavoidable rather than optional — decide deliberately whether it belongs in this branch or in the separate config-drift commit.
- Nothing rendered changes. Verify by diffing the asset list on `/recipe-search` before and after: it should be identical, because none of these libraries were ever loaded.

## Phase 2 — the facet fixes (F-B, F-C)

| # | Step | Where | Criterion | Status |
|---|---|---|---|---|
| 5 | Set **Hide reset when no selection** on both facets | `/admin/config/search/facets` → each facet → *Edit* → widget settings → `hide_reset_when_no_selection` | **4.1.2** | **Done** — unfiltered now shows 3 checkboxes and no reset; filtered shows the reset, unchecked. A user never meets a checked "Clear all filters" |
| 6 | Set a **soft limit** (5 is right for 11 cuisines) on `recipe_cuisine_type` | same screen → *Soft limit* | 2.4.3 | **Done** — 5 of 10 shown; the 5 hidden `<li>` are `display: none` and verified unfocusable |
| 7 | Re-check the show-more control the soft limit introduces | It is a `<a class="facets-soft-limit-link">`; confirm it has a real accessible name and a sane `aria-expanded`, and log a finding if not | 4.1.2 | **Done — it had none. Logged as F-E and fixed** (deviation 5) |

Notes:

- **Step 5 is the F-B fix, and it is a mitigation rather than a cure.** It removes the control in the one state where it is checked-and-meaningless, which eliminates what a screen-reader user actually encounters on an unfiltered page. It does **not** make the control a button in the filtered state — that needs either a widget subclass or an upstream patch, and both should be judged against how much the remaining wrongness costs. Write the reasoning down; "I mitigated in config and here is the residual defect" is a stronger audit line than a silent template override.
- **Step 6 makes step 7 exist.** Adding a soft limit introduces a new control that has its own accessible-name question. Do not enable it and declare 2.4.3 closed without auditing the thing you just added — that is how one fix becomes a different finding, the same lesson Task 6 produced.

## Phase 3 — decide on F-D, verify, export, document

| # | Step | Status |
|---|---|---|
| 8 | Decide F-D deliberately: either add a result count to the view header (and then it must be accurate under filtering), or scope the category bar off `/recipe-search` so "21 recipes" stops contradicting the rows. **Do not do both** | **Open — developer's call.** Not a blocker; shipping the branch without it leaves F-D logged, not hidden |
| 9 | Re-run the facet probe: post-JS input/label pairing, reset state in both filter states, tab-stop count per block, empty-combination message | **Done** — all re-verified after the fixes, plus `aria-expanded` cycling `false → true → false` |
| 10 | Selective config export — the two `facets.facet.*.yml`. `core.extension` already went with phase 1 | **In progress** — `config:status` now lists twelve drifted items; only the two facets are ours |
| 11 | Notion — rewrite the slider section around "verified unused", correct the four claims above **in place**, and move the surviving facet findings into the audit doc | Not started |

---

## Verification

```bash
# The four checks that killed the flagship finding
ddev drush php:eval 'foreach (\Drupal::configFactory()->listAll("facets.facet.") as $n) {
  printf("%s widget=%s\n", $n, \Drupal::config($n)->get("widget.type"));
}'
curl -sk https://foodrecipes-drupal.ddev.site:33001/recipe-search \
  | grep -ci "jquery.ui\|slider\|touch-punch\|pips"          # expect 0
ls docroot/libraries                                          # no jquery-ui-slider-pips
ddev exec composer why drupal/jquery_ui_slider

# The trap: every module that needs jquery_ui. Expect the three Cohesion
# ones — cohesion, cohesion_elements, cohesion_website_settings.
find docroot/modules -name '*.info.yml' \
  -exec grep -lE '^\s+-\s+jquery_ui\s*$' {} +
```

F-B is only observable after JS, so it needs a browser rather than curl. Load `/recipe-search` and run:

```js
[...document.querySelectorAll('#block-flavourful-diettype input[type=checkbox]')]
  .map(i => [document.querySelector(`label[for="${i.id}"]`).textContent.trim(), i.checked])
// unfiltered  → [["Clear all filters (20)", true],  ["Vegan (3)", false]]
// ?f[0]=diet_type:59 → [["Clear all filters (20)", false], ["Vegan (3)", true]]
```

---

## Deviations log

| # | What changed from the plan as written | Why |
|---|---|---|
| 1 | The flagship finding was retired before any work started | The slider is configured nowhere, loads nothing, and its vendor library is not even installed. There is no artefact to audit. The doc's own "verify usage first" gate did its job |
| 2 | The strongest remaining finding (F-B) was **not** in the doc's list, while three findings that were in the list are not true | All four came from reading `facets.facet.*.yml`; F-B is only visible in the post-JS DOM, where the checkbox widget's inverted active-state mapping shows up. Same lesson as Task 6: load the page |
| 3 | "Remove the deprecated jQuery UI modules" narrowed to removing exactly two of the three | `jquery_ui` is required by three Cohesion modules. The tidy-up as originally phrased would have broken Site Studio |
| 4 | 4.1.3 was assessed and found **inapplicable**, and that is recorded as the outcome rather than dropped | A full page reload conveys the new state by delivering a new page. Citing 4.1.3 here would be a false finding, and the doc explicitly wanted the judgement recorded either way |
| 5 | A fifth finding (F-E) appeared **because of** the step 6 fix, and was fixed in the same branch | Enabling a soft limit creates a show-more toggle that did not exist before, and facets builds it as `<a href="#">` with no `aria-expanded`. Step 7 existed precisely to catch this, and it did. Fixed by mirroring the module's `open` class into `aria-expanded` with a `MutationObserver` rather than by binding a second click handler, so correctness does not depend on which handler jQuery runs first. Left as a link: `role="button"` would promise Space-key activation an `<a>` does not honour |
| 6 | F-E's first verification run reported the fix broken when it was not | The probe clicked and read `aria-expanded` in the same tick, and `MutationObserver` callbacks are asynchronous. With a wait it cycles `false → true → false` correctly. Second harness false-positive in this workstream, after the `rawKeyDown` skip-link one — both caught by re-testing rather than by trusting the first red result |
