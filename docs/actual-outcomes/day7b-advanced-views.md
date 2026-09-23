# Build Outcomes — Week 4–6 (Views to real depth)

> Branch: `master` · Last updated: 2026-09-22
>
> A second pass over the same territory as the [Day 7 lab](../objectives/day7-advanced-views.md), driven by the Phase 2 roadmap in Notion rather than by a file in [`../objectives/`](../objectives/). Day 7 built the five Views features; this slice asks the questions Day 7 does not — what a contextual filter does when the argument is missing, what a relationship costs when walked backwards, whether an exposed filter survives being pasted into Slack, and where row markup should come from. Read [Day 7 §2](../objectives/day7-advanced-views.md) and [§4](../objectives/day7-advanced-views.md) first; the EVA and exposed-filter mechanics are not repeated here.
>
> Companion to [`day9-sdc.md`](day9-sdc.md), whose `recipe-card` component is the precedent the item 4 rule generalises.

---

## Objective → outcome map

| Roadmap item | What shipped | Status |
|---|---|---|
| 1 — Contextual filters and the validation decision | `articles_by_topic` at `articles/topic/%`, argument `taxonomy_index.tid`, validator `entity:taxonomy_term` scoped to the `topic` bundle. PR #34 | Done |
| 2 — Relationships, and the same relationship backwards | `articles_by_author` and `recipe_articles` (EVA displays, `entity_view_1`); `recipes_without_articles` at `reports/recipes-without-articles` using `reverse__node__field_recipes`. PR #35 | Done |
| 3 — Exposed filters as shareable state | `articles` view, `page_1` at `/articles`, two exposed filters (`field_story_type_value`, `field_topics_target_id`) with GET identifiers; exposed-form legend now names its own view. PR #36 | Done |
| 4 — Fields versus view modes | `card` view mode plus a configured `article.card` display; both row plugins built and compared; the five comparison questions answered and recorded below | Done — PR #37. `page_2` was inverted to Fields at `articles/fields-test` rather than deleted, so both row plugins stay observable |
| 5 — Views templates, and knowing when to stop writing them | `views-view--articles.html.twig` and `views-view-unformatted--articles.html.twig`; a `flavourful/articles` library compiled from its own partial; three docblock-only overrides deleted; the dead recipes row template disarmed | Partial — the `node--article--card.html.twig` component call waits on `article-card` |
| Content to exercise all of the above | [`scripts/seed-articles.php`](../../scripts/seed-articles.php) — five articles shaped so each exercise has a verifiable result set. PR #35 | Done |

---

## The rule this slice produced

Item 4 asks you to build a listing twice and delete the loser. The useful answer is that neither row plugin wins outright, and the split is three ways rather than two:

| What | Where it comes from | Why |
|---|---|---|
| The row | Rendered entity plus a view mode | The node object reaches the template, and display config stays meaningful to editors |
| Scalar props | **Preprocess** | A typed SDC prop rejects `null`, and an empty field read in Twig *is* `null` |
| Slot content carrying a formatter | The **rendered field** (`content.field_x`) | Keeps image style, alt text and cache metadata |

> **Rendered entity for the row, preprocess for typed props, rendered fields for slots that carry a formatter.**

This was already the working practice in [`node--recipe--teaser.html.twig`](../../docroot/themes/custom/flavourful/templates/content/node--recipe--teaser.html.twig) and `RecipeHooks::addRecipeProps()`, but it existed only as a comment in one template. Writing it down is the actual deliverable of item 4.

---

## Deviation log

Same format as [`lessons-learned.md`](lessons-learned.md): what happened → why → what we did.

| # | Divergence / problem | Why it happened | What we did / open item |
|---|---|---|---|
| 1 | **Fields do not give "a cheaper query."** | A widely repeated claim, and false. `Sql::execute()` calls `loadEntities($view->result)` for every result row **regardless of row plugin**. | Compared *render* time instead. Same query, same entity loads; the difference is render cost alone — measured at roughly 1.8x (29 ms Fields, 52 ms rendered entity, 5 rows, uncached). |
| 2 | **A field hidden in *Manage display* still rendered in the listing.** | The Views field handler reads the field directly and never consults the entity view display. | Reproduced deliberately (below) and classified as **structural, not authorable**. No editor can prevent it from any screen they have. |
| 3 | **Two page displays cannot share a path**, so "build it twice" could not be done in place. | Views routes by path; a duplicate path silently loses. | Built the second display on a throwaway path `articles/card-test`, removed once the comparison is recorded. |
| 4 | **`recipes_without_articles` returned zero rows.** | The `nid IS NULL` filter was left on `relationship: none`, which asks whether the recipe's *own* nid is null — never true. | Pointed the filter at `reverse__node__field_recipes`. An anti-join in Views is a filter *on the relationship*, and getting that wrong fails silently rather than erroring. |
| 5 | **`/articles/topic/all` rendered a dangling title, "Articles on".** | The argument's `exception.value` was set with `title_enable: false`, so the title token resolved to nothing. | Cleared the exception value. The three decisions — `default_action`, `validate.fail` and `exception` — are independent, and a view can satisfy two while contradicting the third. |
| 6 | **The exposed-form legend was hardcoded to "Filter recipes."** | `views-exposed-form.html.twig` is the site-wide fallback, so every view without an override inherited the wrong noun. | Stashed the view title in `flavourful_form_views_exposed_form_alter()` and lifted it into a variable in preprocess. The exposed form's render array carries `#theme` but not the view, so form state is the only place the view is reachable. |
| 7 | **`drush cim` failed on a module that has no code.** | `flavourful_api` was declared in `core.extension.yml` and `config/sync/`; its source only ever existed in a git stash. | Removed from active config, `system.schema` and `config/sync/`. `block.block.flavourful_recipe_tools.yml` is the same shape and is still held back — its plugin class is only in `stash@{1}`. |

---

## Deltas-only walkthrough

Only what differs from [Day 7](../objectives/day7-advanced-views.md).

**1. A contextual filter makes three decisions, not one.** *When the filter value is NOT available* (`default_action`), *what to do if validation fails* (`validate.fail`), and the **Exception value** — which lives in a collapsed *Exceptions* details element inside the `no_argument` fieldset, not under MORE. They are independent. `articles_by_topic` sets `default_action: 'not found'`, `validate.fail: 'not found'` and an empty exception value.

**2. Scope the validator to the vocabulary.** `validate_options.bundles` is `{topic: topic}`. Without it, a term ID from *any* vocabulary validates, so `/articles/topic/12` would accept a cuisine term and return an empty page that looks like a content problem rather than a routing one.

**3. The argument is a term ID, so the URL is `/articles/topic/73`.** A path segment of `technique` does not validate. Pathauto-style term slugs in a contextual filter need a different argument plugin.

**4. An anti-join is a filter on the relationship.** `recipes_without_articles` finds recipes nothing references by adding `reverse__node__field_recipes`, marking it **not required**, then filtering `nid IS NULL` **with that relationship selected**. Leaving the filter on `none` is the silent failure in deviation 4.

**5. Exposed filters are shareable only if their identifiers are.** Both filters on `articles` expose GET identifiers, so a filtered listing survives being copied out of the address bar. The back button then behaves, because each filter state is a distinct URL rather than a POST.

**6. The hidden-field leak, reproduced.** With Standfirst dragged to *Disabled* on `article.card`, and the same article's standfirst grepped from both paths:

```
/articles              standfirst=1     (Fields row plugin)
/articles/card-test    standfirst=0     (rendered entity, Card view mode)
```

One listing obeyed the display config and the other did not. The defences are rendered-entity rows, or a manual audit of every Fields display each time a field is hidden — there is no third option, and nothing warns you.

**7. Measure render time in one pass and discard the first run.** A cold run inflates whichever display is timed first by roughly 2x; the first measurement taken here showed the opposite of the truth. The 1.8x gap is also *understated*, because two of the Card display's five fields are empty on every seeded article and cost nothing to render, while `/articles` additionally renders an exposed-form block that `/articles/card-test` does not.

**8. An empty field renders nothing under rendered entity.** The Card rows emit wrappers for `field_topics`, `field_chef` and `field_summary` only. A Fields display emits the wrapper regardless unless *Hide if empty* is ticked per field.

**9. `article-card` will require `heading_level` with no default.** `recipe-card` hardcodes its heading level and therefore renders at the wrong structural depth in at least one of the three contexts it already appears in. The deliberate departure is to make the rendering context decide.

---

## Views templates, and the stopping rule

Item 5 asks which markup a Views template suggestion should own. The rule:

> **Template suggestions own wrapper markup — the container, the header, the
> empty region, anything Views itself structures. SDC owns anything with a
> props contract.**

Under an `entity:node` row plugin there is no row template to write at all.
Views hands the rendered node to the list template and themes nothing per row,
so the component call belongs in `node--article--card.html.twig`, following
[`node--recipe--teaser.html.twig`](../../docroot/themes/custom/flavourful/templates/content/node--recipe--teaser.html.twig).
The Views side owns exactly two files: the view wrapper and the list wrapper.

**A template override that changes no markup is a liability, not a neutral.**
Three of this theme's seventeen views templates differed from their core
originals only in the `@file` docblock — `views-view-unformatted`,
`views-view-list` and `views-view-grid`. An override like that freezes core's
markup at the version it was copied from, so accessibility and markup fixes
shipped in later core releases silently never arrive. The audit is a `diff`
against the core original, not a judgement call, and the proof is that
deleting all three changed nothing on `/recipes`, `/chefs`, `/articles`,
`/articles/fields-test` or a chef node page.

All three were reachable, which is what made the check worth running:
`views-view-grid` renders `/recipes`, `views-view-unformatted` renders every
`default`-style listing, and `views-view-list` renders the `html_list` style —
which has no path of its own and reaches the site only through the
`chef_recipes_eva` display embedded in a chef node.

**A suggestion-named file is armed even when nothing reaches it.**
`views-view-unformatted--recipes.html.twig` had carried a `DEAD TEMPLATE`
docblock for two builds, and it was registered in the theme registry the whole
time:

    views_view_unformatted__recipes => themes/custom/flavourful/templates/views/…

"Unreachable" described the view's current style setting, not the template.
Changing `views.view.recipes` from grid to unformatted would have rendered it —
silently and emptily, because it loops `recipes` where the template is handed
`rows`. It now lives at `templates/partials/legacy-recipes-row.html.twig`,
which preserves the Day 8 baseline the [Day 9 §7 comparison](day9-sdc.md)
contrasts against while matching no theme hook. The registry scans `templates/`
by filename across every subdirectory, so the rename is what deregisters it;
moving it without renaming would not have.

That is the general rule worth keeping: `partials/` and `macros/` are safe
places for a superseded artifact **because nothing can auto-select them**. A
template-suggestion filename is not.

**Listing CSS and component CSS are different things.** The listing library is
attached by hand from the view template — `attach_library('flavourful/articles')`
— while the component's CSS travels with the component and is declared nowhere.
The trap is the SCSS barrel: `scss/components/_index.scss` forwards every
page-level partial, so an entry point that does `@use 'components'` compiles
all of them. Pointing `articles.scss` at the barrel produced a `css/articles.css`
byte-identical to `css/recipes.css` — 4478 bytes of recipe grid, homepage bands
and recipe detail on the articles page, and nothing matching `.articles-list`.
Two libraries with identical bytes look scoped and are not. Each page-level
entry point should `@use` its own partial directly; `articles.css` is 252 bytes
and four rules once it does.

---

## Open items

- `node--article--card.html.twig` is not written. Until `article-card` exists, `/articles` rows render as default node markup inside the new list wrapper.
- `scss/components/_index.scss` is still a barrel that `recipes.scss` pulls wholesale, so `css/recipes.css` carries the homepage bands and the recipe detail stack as well as the listing. The same fix applied to `articles.scss` would apply here; not done, because it changes what loads on `/recipes`.
- `field_reading_time` has no presave hook, so it is empty on every article and renders nothing. Seeding a value by hand would hide that.
- `recipe.teaser` has no display config anywhere in this repo, so the teaser view mode falls back to the default display and the template overrides the markup wholesale. Real, and separate.
- `block.block.flavourful_recipe_tools.yml` is still held back; its plugin class exists only in `stash@{1}`.
- Building `article-card` itself is Week 5–7. Item 4 decides only where it will be rendered from.

---

*Extend here as the Week 5–7 component work lands.*
