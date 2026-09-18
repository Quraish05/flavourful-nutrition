# Plan — the Articles content model (Phase 2 prerequisite)

> Branch: `feat/articles-content-model` · Created: 2026-09-17 · Last updated: 2026-09-18
>
> **Status: content model built and exported.** Steps 1–5 are done: the `topic` vocabulary, the `article` type and all eleven tier-1 fields, with `field_chef`, `field_summary` and `field_hero` reused as planned. Outstanding: the presave hook for `field_reading_time` (step 6), the Card view mode and the teaser/card/rss displays (step 4), the pathauto pattern (step 5) and content (step 7).
>
> **Deviations from this plan, as built:** `field_topic` shipped as `field_topics`; the table below is updated to match. `body` and `field_recipes` were first created as `field_body` and `field_recipes_this_story_is_abou` — the machine names were corrected in config before any content existed. Tier 2 (Series) is not built.
>
> Execution plan for the content model that Week 4–6 assumes already exists. Neither an objective nor an outcome — this is the transient middle state. It feeds an eventual outcome note under [`docs/actual-outcomes/`](../actual-outcomes/).

---

## Why this plan exists

The Notion page *Phase 2 — Drupal Front-End Depth* opens Week 4–6 with "actually finish advanced Views, using Articles as the surface." Articles does not exist. `config/sync/` holds two content types — `recipe` and `chef` — and neither the `article` type, the `series` type nor a `topic` vocabulary was ever built. The Phase 1 Build page specified them; the accessibility work overtook it and they were never made.

So Week 4–6 is not blocked on Views knowledge. It is blocked on roughly two hours of site-building, and this plan is that two hours.

**The framing that makes the model worth building.** Articles here are not generic blog posts. They are **chef-authored pieces about cooking** — the journey into a cuisine, the story behind a recipe they learned, the technique that took a decade, the source they keep going back to. That framing is not decoration. It is what produces the relationships the Views exercises need: an article points *at a chef* and *at recipes*, which means the recipe page can ask "who has written about me?" — a relationship walked backwards, and the single hardest thing in the Week 4–6 list.

A generic blog would give a content type with a title and a body and nothing to relate to. This model earns its Views work.

---

## The model

```
Article ──field_chef──────────► Chef        (existing type, existing field storage)
        ──field_recipes───────► Recipe      (existing type, new storage, unlimited)
        ──field_topics─────────► Topic       (new vocabulary)
        ──field_story_type────► [list]      (journey / recipe-story / technique / source / interview)
        ──field_series────────► Series      (new type)

Recipe  ◄─── reverse: "articles that mention this recipe"
Chef    ◄─── reverse: "everything this chef has written"
```

The two reverse arrows are the point. Everything else exists to support them.

---

## Decisions

**Reuse `field_chef` for authorship rather than creating `field_author`.** The Notion page says "recipes have no relationship-based author yet" — that is wrong. [`field.storage.node.field_chef`](../../config/sync/field.storage.node.field_chef.yml) already exists: entity reference, target `node`, cardinality 1, bundle-restricted to `chef`. Attaching that same storage to `article` costs one form submission and gives a single relationship that answers "everything by this chef" across both bundles from one Views relationship.

The risk is that adding a second bundle to a shared storage widens every existing query that uses it. It does not here: `chef_recipes`, `chef_recipes_eva`, `related_recipes` and `recipes` **all carry an explicit `type = recipe` filter**, verified in config. Nothing breaks. Worth recording that observation — bundle filters on a shared field storage are exactly the thing people omit and then get bitten by, and this repo happens to have got it right.

The escape hatch, if a later article ever needs both an author-chef and a subject-chef: add a second, differently-named storage then. Do not pre-build it.

**Reuse `field_summary` and `field_hero` too.** Both are single-cardinality storages (`string_long`, `image`) already attached to `recipe`. Article wants exactly the same two things — a standfirst and a lead image. Reusing them makes `byline` and the card components genuinely two-consumer, which is the API-design lesson the phase is after, rather than a lesson simulated with parallel fields.

**Rejected — do not re-propose:** `field_deck` as a new storage. It is `field_summary` with a magazine name. Creating it would mean two storages holding the same shape of content for the same reason, and then a card component that has to branch on bundle to find the text. That branch is the bug this phase is supposed to teach you to design out.

**Rejected — do not re-propose:** repurposing the stock `tags` vocabulary as Topic. `taxonomy.vocabulary.tags.yml` exists and is referenced by nothing — it is dead config from the standard profile. Topic needs its own vocabulary because Week 4–6 item 1 asks for an argument validator scoped to a specific vocabulary, and validating against "the vocabulary that also catches anything anyone ever tagged" is not a real constraint. **Delete `tags` separately** as a config-hygiene win; do not absorb it.

**`field_reading_time` is computed, not entered.** It is an integer field on the node, populated in `hook_ENTITY_TYPE_presave()` from the body word count. The repo already has this exact pattern — `field_total_time` on recipes is computed on presave, built in Day 6 and backfilled later ([day6-hooks-preprocess.md](../actual-outcomes/day6-hooks-preprocess.md)). Reusing the pattern is cheaper than inventing one and gives the `article-card` component a real prop instead of a placeholder.

**Series is tier 2.** It is genuinely good Views material — ordered sequence, "Part 2 of 5", a contextual filter on the current node's series, an aggregation for the total. It is also a whole second content type. Build tier 1 first, get Week 4–6 items 1–3 moving, then come back. If time is short, **cut Series and keep everything else**; nothing in tier 1 depends on it.

---

## Tier 1 — the fields

| Field | Type | Card. | Storage | Notes |
|---|---|---|---|---|
| `title` | base | 1 | — | The headline |
| `field_summary` | `string_long` | 1 | **reuse** | Relabel to "Standfirst" on this bundle |
| `body` | `text_with_summary` | 1 | new | Format **Basic HTML**. The long-form prose |
| `field_hero` | `image` | 1 | **reuse** | Alt field required ✔ |
| `field_chef` | `entity_reference` → `chef` | 1 | **reuse** | Relabel to "Author". Required ✔ |
| `field_recipes` | `entity_reference` → `recipe` | **unlimited** | new | "Recipes this story is about" |
| `field_topics` | `entity_reference` → `topic` | **unlimited** | new | Autocomplete (tags style) |
| `field_story_type` | `list_string` | 1 | new | Required ✔. Copy the `field_difficulty` pattern |
| `field_story_year` | `integer` | 1 | new | "When this happened". Optional |
| `field_takeaways` | `text_long` | **unlimited** | new | Convention: first line is the heading |
| `field_reading_time` | `integer` | 1 | new | **Computed on presave.** Hidden on the form |

**`field_story_type` allowed values** — these drive the exposed filter, the facet and the aggregation, so they need to be few and genuinely distinct:

```
journey|A chef's path into a cuisine or craft
recipe_story|The story behind a specific recipe
technique|Something learned the hard way
source|A book, teacher or place worth citing
interview|In conversation with another chef
```

**Topic vocabulary terms** — deliberately orthogonal to `cuisine`, so a listing can filter on both without one subsuming the other: Technique, Sourcing, Seasonality, Travel, Family, Failure, Equipment, Sustainability.

## Tier 2 — Series (optional, build second)

| Field | Type | Card. | Storage |
|---|---|---|---|
| `title` | base | 1 | — |
| `field_summary` | `string_long` | 1 | **reuse** |
| `field_hero` | `image` | 1 | **reuse** |

Plus two fields back on `article`: `field_series` (ER → `series`, card 1) and `field_series_position` (integer, min 1).

Series has no fields of its own that aren't reused. That is the tell that it is a light type doing structural work, and it is worth saying so in the write-up.

---

## What each field is actually for

This is the table to check the model against — every field should earn a Views exercise, and anything that earns none is decoration.

| Field | Week 4–6 exercise it unlocks |
|---|---|
| `field_topics` | **Item 1** — contextual filter at `/articles/topic/%`, with an `entity:taxonomy_term` validator scoped to the vocabulary |
| `field_chef` | **Item 2** — relationship Article → Chef; the EVA on the chef page; and the shared-storage question of whether one relationship serves both bundles |
| `field_recipes` | **Item 2, the hard half** — the reverse walk. "Articles mentioning this recipe", an EVA on the recipe page, filtered by the *referenced* nid |
| `field_story_type` | **Item 3** — the exposed filter worth exposing. Five values, so a dropdown is right; a facet later |
| `field_summary`, `field_hero` | **Item 4** — fields vs view modes, built both ways. Shared storage means the Card view mode is comparable across bundles |
| `field_reading_time` | **Item 5** — a real prop on `article-card`, and a presave hook to write up |
| `field_story_year` | **Item 7** — aggregation with something to aggregate: MIN/MAX per chef, counts per decade. Saves item 7 from being a rebuild of `recipes_per_cuisine` |
| `body` | **Item 9** — the RSS display finally has something to summarise; and the `prose` component |
| `field_takeaways` | The disclosure component — `details`/`summary` first, ARIA second |
| `field_series`, `field_series_position` | Tier 2 — ordered contextual filter, "Part N of M", `aria-current` |

---

## Build steps

Do it in the UI. The site building *is* the practice, and it is what a staffing interview probes.

### Step 1 — Topic vocabulary (~10 min)

`/admin/structure/taxonomy/add` → name **Topic**, machine name `topic`. Add the eight terms above.

### Step 2 — Article content type (~10 min)

`/admin/structure/types/add` → **Article**, machine name `article`. Uncheck *Display author and date information* — the byline comes from `field_chef`, not from Drupal's node author, and leaving core's on guarantees two bylines fighting each other later.

### Step 3 — Article fields (~40 min)

Add them **in the order of the tier 1 table**. Form order becomes the default widget order, and widget order is an accessibility concern as well as an editorial one.

For the three reused storages, use **Re-use an existing field** on the add-field screen rather than creating new ones — that button is the whole point of the decision above.

Set on the way through:
- `field_hero` — *Alt field required* stays checked
- `field_chef` — required, relabel **Author**, handler restricted to bundle `chef`
- `field_summary` — relabel **Standfirst**
- `field_topics` — unlimited, autocomplete (tags style)
- `field_recipes` — unlimited, handler restricted to bundle `recipe`
- `field_story_type` — required, allowed values as above
- `field_reading_time` — hidden on the form display

### Step 4 — View modes (~15 min)

`/admin/structure/display-modes/view/add/node` → **Card** (`card`).

Then `/admin/structure/types/manage/article/display` → *Custom display settings* → enable **Teaser**, **Card** and **RSS**.

Worth knowing before you start: **no `core.entity_view_display.node.*.teaser.yml` exists in this repo at all.** Only `recipe.default` and `chef.default`. Every teaser on this site today is the default display in disguise, which is why `node--recipe--teaser.html.twig` is doing work the display config should be doing. Configure Article's properly and the contrast is a paragraph in the write-up.

Configure the four displays:

| Display | Shows |
|---|---|
| Default | Everything, labels above |
| Teaser | Hero, standfirst, author — labels hidden |
| Card | Hero, standfirst, topic, reading time — labels hidden |
| RSS | Body summary only |

### Step 5 — Pathauto (~5 min)

`/admin/config/search/path/patterns/add` → Content → Article → `/articles/[node:title]`. A `recipe_node_title` pattern already exists; copy its shape rather than inventing one.

### Step 6 — The reading-time presave hook (~20 min)

In the existing custom module, mirror the `field_total_time` presave. Word count of `body` ÷ 200, rounded up, minimum 1. Guard for an empty body — the Day 9 notes record that empty fields are exactly what trips validation here.

### Step 7 — Content (~30 min)

Create **8 articles by hand** across the existing chefs, deliberately shaped so the Views exercises have something to find:

- at least two by the **same chef** (so the chef EVA has more than one row)
- at least three pointing at the **same recipe** (so the reverse lookup has a real result set)
- at least one with **no recipes** and one with **no topic** (so "no results" and optional-relationship behaviour are observable)
- one long piece with 8+ body headings, several inline links and three related recipes — this is the one the accessibility and `prose` work needs
- spread `field_story_type` across all five values, and `field_story_year` across at least two decades

Then `/admin/config/development/generate/content` for ~20 more, so the pager has something to page.

### Step 8 — Export and predict

Before running `drush cex`, **write down the config filenames you expect**. Then export and diff your list against reality. Being able to predict config export is a genuine fluency test, and it is cheap to run.

The list to check yourself against:

```
node.type.article
taxonomy.vocabulary.topic
core.entity_view_mode.node.card
core.entity_view_display.node.article.{default,teaser,card,rss}
core.entity_form_display.node.article.default
field.storage.node.{field_recipes,field_topics,field_story_type,field_story_year,field_takeaways,field_reading_time,body}
field.field.node.article.{field_summary,field_hero,field_chef,body,…}
pathauto.pattern.*
```

The ones people miss: the **form** display, the `body` field storage (it is a field like any other, not a base field), and the fact that a **reused** storage produces a new `field.field.node.article.*` but **no** new `field.storage.*`. That last one is the storage-reuse decision showing up in the export, which is the neatest possible confirmation that it worked.

---

## Definition of done

- [ ] `article` exists with all eleven tier-1 fields; three of them reuse existing storages
- [ ] `topic` vocabulary with eight terms; `tags` flagged for separate deletion
- [ ] Card view mode created; four display modes configured on Article
- [ ] Reading time populates on save without being entered
- [ ] ~28 articles exist, shaped so that every Week 4–6 exercise has a non-trivial result set
- [ ] Config exported, and the predicted filename list reconciled against the actual diff
- [ ] Week 4–6 item 1 can start without touching the content model again

## Out of scope

- **All Views work.** This plan builds the model only. Eight views are Week 4–6's job, and mixing them in is how the content model ends up shaped by the first view rather than by the domain.
- **All SDC work.** Week 5–7.
- **The accessibility pass.** Phase 1's method is deliberate: build it the way anyone would build it in a hurry, *then* audit. Pre-fixing here destroys the one comparison the audit is for.
- **Series** — tier 2, build after item 3 is moving.
