# Plan — Fields versus view modes (Week 4–6, item 4)

> Branch: not yet cut · Created: 2026-09-22 · Last updated: 2026-09-22
>
> **Status: 4a–4c and 4e done; 4d outstanding.** Blocks item 5 and all of Week 5–7, both of which assume a decision that has never been written down.
>
> Execution plan for item 4 of the Notion page *Phase 2 — Drupal Front-End Depth*, Week 4–6. Neither an objective nor an outcome — this is the transient middle state. It feeds `docs/actual-outcomes/day7b-advanced-views.md`.

---

## Why this plan exists

The item reads *"build `/articles` twice, compare, delete the loser."* Taken literally that produces the wrong deliverable, for three reasons found by reading the repo rather than the plan.

**The repo already made this decision, and it is neither of the two options.** `views.view.recipes` uses the grid style with a row plugin of `entity:node`, `view_mode: teaser` — so it picked rendered entity. But the props reaching the `recipe-card` component do not come from that view mode:

- **Scalars come from preprocess.** `RecipeHooks::addRecipeProps()` in [`src/Hook/RecipeHooks.php`](../../docroot/themes/custom/flavourful/src/Hook/RecipeHooks.php) supplies `recipe_eyebrow`, `recipe_summary`, `recipe_meta`, `recipe_difficulty`. The template is explicit about why: a typed SDC prop declared `type: string` **rejects null outright**, and an empty field read in Twig is exactly null. Hence the `|filter(v => v is not null)` on the props map.
- **The `media` slot takes the rendered field**, `content.field_hero`, deliberately — "so its image style, alt text and cacheability are preserved."
- **The `tags` slot takes `tag-pill` components** — components inside a slot, which props cannot express.

So the working rule is a three-way split, and it currently exists nowhere except the comment block of one template. Writing it down is the actual deliverable of item 4.

**Two factual corrections that change how the comparison is run:**

| The item says | Reality |
|---|---|
| Fields give "a cheaper query" | **No.** [`Sql::execute()`](../../docroot/core/modules/views/src/Plugin/views/query/Sql.php) calls `loadEntities($view->result)` for every result row **regardless of row plugin**. Same query, same entity loads. What differs is *render* cost — rendered entity builds every enabled field's render array plus the node template; Fields builds only the fields listed |
| Build `/articles` twice "inside the same view" | Two page displays cannot share a path. Use a throwaway second path, or the Preview pane |

**And a prerequisite the item does not mention:** there is no `card` view mode, and `article.default` is the only article display. That is Step 1's outstanding chore and item 4 cannot start without it.

---

## The rule this plan produces

| What | Where it comes from | Why |
|---|---|---|
| The row | Rendered entity + a view mode | The node object reaches the template, and display config stays meaningful to editors |
| Scalar props | **Preprocess** | A typed prop rejects null; an empty field read in Twig *is* null |
| Slot content that carries a formatter | The **rendered field** (`content.field_x`) | Keeps image style, alt text and cache metadata |

One sentence, for the write-up and for saying out loud:

> **Rendered entity for the row, preprocess for typed props, rendered fields for slots that carry a formatter.**

---

## Decisions

**Adopt the recipe pattern for Articles rather than inventing a second one.** Two card listings built two different ways, in one theme, is the inconsistency a reviewer notices first. The value here is in being able to *explain* the pattern, not in discovering an alternative.

**One deliberate departure: `heading_level` required with no default.** `recipe-card` hardcodes its heading level, so the same card is structurally wrong in at least one of the three contexts it already renders in. `article-card` makes the rendering context decide, consciously. This is the one place Articles should not copy Recipes.

**Give the Card view mode a real display.** `recipe.teaser` never got one — there is no `core.entity_view_display.node.recipe.teaser.yml` anywhere in this repo, so the teaser view mode silently falls back to the default display and `node--recipe--teaser.html.twig` then overrides the markup wholesale. Configuring `article.card` properly is what makes the comparison in step 4c honest rather than a comparison against an unconfigured fallback.

**Rejected — do not re-propose:** keeping both displays "for flexibility". Two displays of the same content on two paths is the duplicate-content problem item 1 just removed from `/articles/topic/all`. Decide, then delete.

**Rejected — do not re-propose:** feeding SDC props by reading `node.field_*` in the Twig template. It reads cleaner and it breaks on any entity with an empty field, because SDC validates prop types and null is not a string. `RecipeHooks` exists precisely because this was tried.

---

## Steps

### 4a — The Card view mode and a real display

1. `/admin/structure/display-modes/view/add/node` → **Card**, machine name `card`.
2. `/admin/structure/types/manage/article/display` → *Custom display settings* → ✔ **Card** → Save.
3. Open the **Card** tab and configure it as a card actually needs: hero, standfirst, topics, reading time. Labels hidden.

Expected config: `core.entity_view_mode.node.card.yml` and `core.entity_view_display.node.article.card.yml`.

### 4b — The second display, temporarily

`/admin/structure/views/view/articles` → **Add → Page**.

- Path `articles/card-test` — temporary, removed in 4d
- **Format → Show:** *Content* → **Card**
- Override for this display only, so `page_1` stays on Fields

`page_1` is already a Fields display, so half the comparison exists live and does not need building.

### 4c — The comparison, answered

Run on 5 articles, both displays live, `field_summary` as the control field —
the only field configured on both.

| Question | Fields (`page_1`) | Rendered entity (`page_2`, Card) |
|---|---|---|
| Honours the entity display config | **No** | **Yes** |
| Editor can reorder without a developer | No — Views UI | Yes — *Manage display* |
| Render cost, 5 rows, uncached | **~29 ms** | **~52 ms** |
| Markup owned | `views-field` wrappers only | Full `<article class="node node--view-mode-card">` + field templates |
| A hidden field can leak back in | **Yes** | n/a — it is the thing doing the hiding |

**The leak, reproduced.** With Standfirst dragged to *Disabled* on
`article.card`:

    /articles              standfirst=1
    /articles/card-test    standfirst=0

One listing obeyed the display config; the other carried on rendering the
field. The Views field handler reads the field directly and never consults
the entity view display. Classify this as **structural, not authorable** —
no editor can fix it from any screen they have, and no amount of care in
*Manage display* prevents it. The only defences are rendered-entity rows, or
auditing every Fields display by hand each time a field is hidden.

**On the render figures.** Measure in the same pass and discard the first —
a cold run inflates whichever path is timed first by roughly 2x. Both
displays issue the same query and load the same entities, so the ~1.8x gap
is render cost alone. It is also an *understated* gap: two of the Card
display's five fields (`field_hero`, `field_reading_time`) are empty on
every seeded article and so cost nothing to render, while `/articles`
carries the exposed-form block that `/articles/card-test` does not.

**An empty field renders nothing.** The Card rows emit wrappers for
`field_topics`, `field_chef` and `field_summary` only. Rendered entity drops
empty fields silently; a Fields display emits the wrapper unless *Hide if
empty* is ticked per field.

### 4d — Adopt on the display that already works, then delete the spare

Record 4c's answers first. Deleting a display destroys the evidence.

Do **not** delete `page_1` and move `page_2` onto its path. The exposed-form
block plugin is `views_exposed_filter_block:articles-page_1` and it is bound
to that display ID, not to the view — deleting `page_1` unplaces the block,
and the AJAX setting, pager, no-results text and path all go with it.

Change the row plugin on the display that already owns all of that instead:

1. `/admin/structure/views/view/articles` → **Page** (`page_1`) →
   **Format → Show: Fields** → change to **Content**, *"This page (override)"*.
2. View mode **Card**. Apply, Save.
3. Delete the `page_2` display — it has nothing left to prove.
4. Verify `/articles` still filters, still pages, still carries the
   *Filter articles* legend, and that `/articles/card-test` now 404s.

The three Fields handlers on `page_1` stay in config once the row plugin is
`entity:node` — Views keeps them and stops using them. Remove them so the
next reader is not misled about where the markup comes from.

### 4e — Write it down

The three-way table and the one-sentence rule go into `docs/actual-outcomes/day7b-advanced-views.md`, alongside:

- the query-cost correction, with the `loadEntities()` reference — it is the kind of specific that ends an argument
- the hidden-field leak, classified as **structural** rather than authorable
- why `article-card` requires `heading_level` while `recipe-card` hardcodes it

---

## Definition of done

- [x] `card` view mode exists, with `article.card` configured rather than falling back to default
- [x] Both displays built and compared against all five questions in 4c
- [x] The hidden-field leak reproduced and recorded
- [ ] Fields display deleted, `articles/card-test` gone, `/articles` serving the Card display
- [ ] Exposed form block, AJAX and no-results verified still working after the display swap
- [x] The three-way rule written down as a rule, not a preference — [`day7b-advanced-views.md`](../actual-outcomes/day7b-advanced-views.md)

## Out of scope

- **Building `article-card` itself.** That is Week 5–7. Item 4 decides where it will be rendered from and leaves the view rendering the Card view mode so the component has somewhere to land.
- **Fixing `recipe.teaser`'s missing display config.** Real, and a separate change — noted here so it is not rediscovered as new.
- **The `node--article--card.html.twig` template and its preprocess hook.** Week 5–7, following the `RecipeHooks` precedent.
