# Build Outcomes — Day 7 (Advanced Views — REST export slice)

> Branch: `feat/nutrition-api-and-vetting-docs` · Last updated: 2026-07-13
>
> This records only the slice of the [Day 7 lab](../objectives/day7-advanced-views.md) exercised so far: the **REST export** display ([§5](../objectives/day7-advanced-views.md)) and enabling **EVA** ([§2](../objectives/day7-advanced-views.md)). Aggregation (§1), the chef-page EVA display, the attachment (§3), and exposed filters (§4) are not built here yet. Read [Day 7 §5](../objectives/day7-advanced-views.md) first — this does not repeat the REST-export setup. Companion to the [Day 6 outcomes](day6-hooks-preprocess.md), whose rename cascade directly caused deviation 3 below.

---

## Objective → outcome map

| Objective | What shipped | Status |
|---|---|---|
| [§5](../objectives/day7-advanced-views.md) — recipes as JSON via REST export | `recipes` view, `rest_export_1` display at `/api/recipes` returns one object per recipe (`title`, `field_recipe_cuisine_type`, `field_total_time`). | Done — after a data backfill and field-formatter overrides (see deviations) |
| [§2](../objectives/day7-advanced-views.md) — EVA module | `eva` enabled via `drush en eva`. | Partial — module on; the chef-page attachment display is not built or verified here |

---

## Deviation log

Same format as [`lessons-learned.md`](lessons-learned.md): what happened → why → what we did.

| # | Divergence / problem | Why it happened | What we did / open item |
|---|---|---|---|
| 1 | **`field_total_time` was empty for 18 of 21 recipes** in the JSON. | The Day 6 presave computes the total only *on save*; recipes created before the field existed had a `NULL` total and had never been re-saved. | Backfilled every recipe with a one-off re-save (see [Day 6 update](day6-hooks-preprocess.md)). API now returns `"30 min"`, `"60 min"`, etc. Going forward the presave keeps it current. |
| 2 | **`title` and `field_recipe_cuisine_type` came back as HTML anchors**, hex-escaped as `<a href=…`. | The `default` display formatters (title *link to content*; cuisine `entity_reference_label` with `link: true`) are inherited by `rest_export_1`. Drupal's JSON encoder then hex-escapes `< > & " '` (`JSON_HEX_*`) as an XSS guard, hence `<`. | Overrode the fields on **`rest_export_1` only**: title `link_to_entity: false`, cuisine `link: false`. Output is now plain `"Patatas Bravas"` / `"Spanish"`. The `<`-style escaping is expected Drupal behaviour — a JSON parser decodes it — and was left alone. |
| 3 | **`drush en eva` first failed with a fatal.** | The Day 6 `RecipeHooks` namespace bug aborted the post-install container rebuild; `eva` was written to config but the command errored. | Resolved once the namespace was fixed (see [Day 6 update](day6-hooks-preprocess.md)); re-running now reports `eva` already installed. |

---

## Deltas-only walkthrough

Only what differs from [Day 7 §5](../objectives/day7-advanced-views.md); the click-path and REST setup live there.

**1. Per-display field override, not a `default` edit.** The `recipes` view has four displays (`default`, `page_1`, `attachment_1`, `rest_export_1`). Unlinking the title/cuisine on `default` would strip the links from the two page displays too. The `fields` section is instead overridden on `rest_export_1` alone (`display_options.defaults.fields = false` plus its own `fields` copy), so the human-facing pages keep their links and only the API payload changes.

**2. Change lives in active config only.** The override was applied to the database, **not** exported to `config/sync` — a full `drush config:export` here drags in unrelated rename and Site-Studio drift (the same trap noted across the Day 6 update). Persist it with a targeted export of `views.view.recipes` when ready. Ties to [Day 7 §7](../objectives/day7-advanced-views.md) — Views live in config.

**3. `field_total_time` keeps its `' min'` suffix** in the JSON (`"30 min"`), from the integer formatter's prefix/suffix setting. Fine for display; switch that field to a raw number if the API should return `30`.

---

*Extend Day 7 here as the aggregation, EVA chef display, attachment, and exposed-filter slices land.*
