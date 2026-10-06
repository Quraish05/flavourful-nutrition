# Flavourful theme — "Cellar"

Custom Drupal 11 theme for the Flavourful site, built to the **Cellar** design:
a dark editorial look — near-black ground, one brass accent, hairline rules, no
rounded corners and no soft shadows. Depth comes from three ground tones rather
than from elevation, and structure comes from wide-tracked uppercase labels and
rules rather than from boxes.

The theme is **dark only**. There is no light mode and no `prefers-color-scheme`
swap: the design is a single committed look, so tokens are defined once.

## How the pieces fit

```
Component (components/*)   props in, markup out. Queries nothing, knows no field names.
        ▲
Template (templates/*)     maps variables onto component props. A mapping, not a query.
        ▲
Preprocess (src/Hook/*)    every lookup, with its cacheability declared.
```

Nothing in `components/` reads a field, a config object or a route, and nothing
in `templates/` reads `node.field_*`. That second rule is not only tidiness —
see [the footgun](#the-nodefield_-footgun) below.

## Component library

22 Single Directory Components in `components/`. Each is a directory holding
`*.component.yml` (the props/slots contract), `*.twig` and usually `*.css`;
SDC attaches a component's CSS automatically wherever it renders, so none of it
loads on a page that does not use it.

| Atoms | Molecules | Organisms |
| --- | --- | --- |
| `micro-label` — the tracked uppercase label | `utility-bar` — the top strip (start/centre/end slots) | `recipe-tools` — servings scaler, ingredients, method, Cook Mode |
| `button` — a link styled as a button | `site-masthead` — wordmark + flanking nav (`centered` / `inline`) | |
| `action-button` — a real `<button>` | `category-bar` — the cuisine strip with its tally | |
| `tag-pill` — a ruled chip | `section-header` — title, elastic rule, "view all" | |
| `rating-stars` — accessible star rating | `hero-feature` — the lead (`stacked` / `split`) | |
| `issue-mark` — the numbered brass ornament | `recipe-card` — `compact` / `stacked` / `list` | |
| `meta-list` — separator-joined meta row | `chef-byline` — avatar, name, rating | |
| | `spec-table` — Total / Prep / Cook / Level | |
| | `ingredient-list` — dotted-leader rows | |
| | `method-steps` — roman-numeral steps | |
| | `pairing-note` — the "To drink" aside | |
| | `journal-list` — the article rail | |
| | `thumb-strip` — the "Cook next" strip | |
| | `site-footer` — links, tagline, action | |

### Conventions these all follow

- **`heading_level` is a required prop, never a constant.** The same card is an
  `h2` under a page `h1` and an `h3` inside a section. `0` renders no heading at
  all, for a context where an `h1` already names the same thing.
- **Colour is never the only carrier of meaning.** Every difficulty badge, active
  nav item and current pager page pairs its colour with a text label, an
  underline or `aria-current` (WCAG 1.4.1).
- **Separators are pseudo-elements, not characters.** A literal `—` between meta
  items is read aloud by some screen readers and not others; a pseudo-element is
  reliably silent and reliably visible.
- **Absent data renders nothing, never a zero or a placeholder.** `meta-list`
  skips empty values; `chef-byline` just gets shorter without a rating. Where
  the absence is the *caller's* question rather than the component's, the prop
  is required instead and the caller omits the component — `rating-stars` works
  that way, which is why a rating of 0 draws five empty stars rather than
  vanishing.
- **Slots are captured through an inline block:**
  `{% set x %}{% block x %}{% endblock %}{% endset %}`. Twig's `block('x')`
  *throws* when a caller has not defined the block, and a caller filling only
  some slots is the normal case.

### Slots or props?

**Content belongs in a slot. Configuration belongs in a prop.** The library
already follows this — 16 slots across six components — it had just never been
written down.

The test is one question: **can the host already render this?** If what you want
to pass is markup something else produced — a rendered field, a block, another
component — it is a slot. If it is a value the component reasons about — a
level, a variant, a label, a URL — it is a prop.

`recipe-card` is the worked example, and `node--recipe--teaser.html.twig` shows
why it matters:

- **`media` takes `content.field_hero`**, the rendered field. Image style,
  responsive `srcset`, alt text and cacheability all survive untouched. As a
  `type: string` prop the component would have to rebuild the `<img>` itself,
  and alt text would become a second prop that nobody keeps in step with the
  image. The caller fills the slot with a placeholder glyph when the field is
  empty — a decision about *this* context, which is exactly what a slot is for.
- **`tags` takes `tag-pill` atoms.** A prop cannot express "a list of
  components"; the schema would have to flatten them to strings and the card
  would end up re-implementing the pill.

**The exception: a slot cannot repeat.** Twig blocks do not iterate, so a
*list* stays a prop even when each item carries markup — `method-steps.steps`
and `recipe-tools.steps` are arrays of strings that may contain inline markup,
and `spec-table.items`, `meta-list.items` and `ingredient-list`'s rows are
arrays of objects. Repetition beats the content/configuration split.

A component whose content is **entirely** slots has nothing to require:
`utility-bar` is three slots plus two optional presentational props, and that is
a finished shape rather than a gap. Its `label` in particular must stay optional
— naming a landmark that holds no navigation is worse than leaving it unnamed.

### When *not* to share a component

`article-header` has a byline. So does `chef-byline`. They were deliberately
not merged — the first time this library has had to answer that question
rather than simply add a component.

What they share is one `<a rel="author">`. Everything else differs, because
they answer different questions: `chef-byline` is *who this person is* —
avatar, name, standing — beside a recipe headline; `article-header`'s byline
is *where this text came from* — author, date, reading time — under an article
headline. A merged component would need all five surfaces optional, and would
then be a wrapper around a link.

Generalising would also have widened an API that has not earned the consumer
it already has. `chef-byline` declares four props and a slot; its one caller
supplies two. `avatar` is passed empty on purpose and `rating`/`rating_count`
have no field behind them — the chef bundle has exactly one field,
`field_chef_name`. Three of five surfaces are speculative.

**Share markup when two callers want the same thing, not when they want
things that look alike.** Count the surfaces both would use. If the shared
component needs most of its API optional to serve both, what they have in
common is smaller than a component.

### Two SDC gotchas worth knowing

**A `null` prop is not an absent prop.** SDC validates props against the
component's JSON schema, and a prop declared `type: string` rejects an
explicitly-passed `null`. Hence the `|filter(v => v is not null)` on the prop
maps in the node templates.

**Extra context is ignored, but same-named context wins.** The validator
intersects the context down to declared props, so surplus variables are
harmless — but a page variable sharing a prop's name (`attributes`, `label`,
`title`) silently *becomes* that prop. Every `{% embed %}` in this theme
therefore uses `only` and passes what its blocks need explicitly.

### The `node.field_*` footgun

`node.field_difficulty.value` does **not** evaluate to `null` on an empty field.
Twig finds no `value` property (`FieldItemList::__isset()` is `FALSE` when the
list is empty), falls through to `FieldItemList::getValue()`, and gets back an
empty **array** — which is truthy, survives any `is not null` filter, and reaches
the component as `[]`. That failed `recipe-card`'s `difficulty` enum on the one
recipe with no difficulty set. Read fields in PHP, where they return a real
`NULL`.

## Preprocess layer

`#[Hook]` attribute classes in `src/Hook/`. These are registered as **autowired
services** by `HookCollectorPass`, so constructor arguments resolve from the
interface aliases in `core.services.yml` — no `create()` needed.

- **`PageHooks`** — the shell: wordmark, utility-bar date, cuisine strip, footer
  links. Also drops two blocks that would otherwise break the outline: the
  site-branding block (the masthead renders the wordmark itself) and, on recipe
  pages, the page-title block (the hero renders the `h1`).
- **`NodeHooks`** — the theme's single `preprocess_node`. A theme gets exactly
  one implementation of each hook, and a hook class is a service only while it
  carries `#[Hook]`, so a second class cannot be injected into the first and
  themes get no `.services.yml`. Hence one class, with the per-bundle work in
  traits: recipe handling inline, articles in `ArticlePreprocessTrait`, field reading
  shared via `NodeFieldTrait`. The only file that knows a
  recipe field name.
- **`ListingHooks`** — which listing row is the lead story. The instruction
  travels as `#card_variant` on the row's render array, the same channel
  `#view_mode` uses, so page CSS never has to override a component's variant.
- **`RecipeStats`** (in `src/`, a plain collaborator, not a service) — the one
  place that counts recipes and finds their listing, so the query and its cache
  tag cannot drift between the two hook classes that need them.

Cacheability is collected into a `CacheableMetadata` and applied once at the end
of each hook. The utility-bar date sets `max-age` to the seconds remaining until
midnight rather than `0`, so one decorative line does not disable the page cache.

### Template or preprocess?

Not a choice between the two. The theme answers it three ways, split by the
*kind* of decision rather than by component:

- **Read in preprocess.** Field values, formatted dates, access-checked URLs,
  anything that can be absent. `node.field_x.value` does not return `null` on
  an empty field — see the footgun above — and `access('view')` and
  `DateFormatter` need services. `NodeHooks` and its traits are the only files
  that know a field name.
- **Compose in the template.** Which component, which slots take which rendered
  field, which variant suits *this* context. `node--recipe--teaser.html.twig`
  and `node--article--card.html.twig` are maps from scalars onto a component,
  with no queries in them.
- **Send per-context instructions through the render array.** Which row is the
  lead story is a fact about the listing, not about the card, so
  `ListingHooks` writes `#card_variant` and `#card_heading_level` onto row 0 —
  the same channel `#view_mode` travels on. `NodeHooks` reads them back out of
  `$variables['elements']`.

That third one is the one worth copying. The alternative is a page stylesheet
forcing a component into a different shape, and the whole point of a variant
prop is that the instruction is data rather than a specificity fight.

**The catch, found building `article-card`:** under an `entity:node` row plugin
there is no Views row template at all. `views-view-unformatted` renders the
node directly, so a `views-view-row--*.html.twig` never fires and the only
per-row template you get is `node--article--card.html.twig`. If a row template
seems to be ignored, this is why.

## Front-end build (SCSS)

SCSS source lives in `scss/` and compiles to `css/`. Node is provided by DDEV.

```bash
# Install build tooling (once):
ddev exec 'cd docroot/themes/custom/flavourful && npm install'

# One-off compile (compressed, for committing):
ddev exec 'cd docroot/themes/custom/flavourful && npm run build'

# Expanded build with source maps:
ddev exec 'cd docroot/themes/custom/flavourful && npm run dev'

# Watch and recompile on change while theming:
ddev exec 'cd docroot/themes/custom/flavourful && npm run watch'
```

`node_modules/` and `*.css.map` are git-ignored; the compiled `css/*.css` are
committed so the theme works without a build step.

### SCSS structure

```
scss/
├── abstracts/   # Design tokens (_variables) + mixins. No CSS output.
├── base/        # @font-face, :root custom properties, reset, base typography.
├── layout/      # Page shell, sidebars, breadcrumb, pager, tabs, messages.
├── components/  # Page-level *arrangement* only — listing grid, homepage bands.
├── global.scss  → css/global.css   (base + layout, loaded site-wide)
└── recipes.scss → css/recipes.css  (arrangement, loaded on recipe pages)
```

`scss/components/` holds **no components** — those are all SDCs, so their CSS
travels with their markup. What is left here is the grid or band a set of
components sits in, which belongs to a page rather than to any one component.

Import a partial's tools with `@use '../abstracts' as *;`. Tokens are defined
once in `abstracts/_variables.scss` and re-exposed as `--fr-*` custom properties
in `base/_root.scss` — that second file is the bridge, because SDC CSS files
live outside `scss/` and cannot `@use` the Sass layer. **Adding a token means
adding it in both places.**

### Contrast

Every colour the theme sets text in clears WCAG AA (4.5:1) against the *lightest*
of the three grounds, `$color-ink-raised`. The measured ratios are recorded next
to each token. Two colours are deliberately below AA and are named accordingly:
`$color-bone-faint` (3.2:1) and `$color-brass-dim` (3.9:1) — decorative glyphs,
hairline rules and disabled controls only, all of which WCAG 1.4.3 exempts.
Nothing a reader has to read may use them.

## Typography

Three self-hosted variable fonts in `fonts/`, latin subset, ~180 KB total:

| Role | Family | Used for |
| --- | --- | --- |
| display | Cormorant Garamond | the wordmark, every headline, card titles |
| body | EB Garamond (+ a real italic) | running prose, ingredient rows, method steps |
| label | Inter | uppercase micro-labels, nav, buttons, meta |

They are **variable** fonts, so `@font-face` declares a weight *range* and one
file covers 300–700. Do not split them back into per-weight faces. The display
and label faces are preloaded from `PageHooks::pageAttachmentsAlter()` because
they render above the fold on every page; the body serif is not, deliberately.

**The scale starts at 18px, not 16.** Both garamonds here have a notably small
x-height — EB Garamond at 16px reads about the size of a grotesque at 14px — so
`$font-size-base` is `1.125rem` and everything is set from the size the body
face actually needs. Line-height is 1.7, uppercase label tracking is `0.18em`,
the wordmark's is `0.34em`, and running prose carries a hair of `0.01em`.

Body sizes are tokens (`--fr-size-body`, `--fr-size-body-sm`,
`--fr-size-body-lg`), not literals in each component, so the scale can be
retuned in one place. If you find a bare `rem` font-size in a component, it is
either a display size or something that should have become a token.

## Asset libraries

Defined in `flavourful.libraries.yml`:

- **`flavourful/global-styling`** — `css/global.css`, attached site-wide from
  `flavourful.info.yml`.
- **`flavourful/recipes`** — `css/recipes.css`, attached on demand with
  `{{ attach_library('flavourful/recipes') }}` from the recipe and listing
  templates.
- **`flavourful/recipe-tools`** — JS only. The `recipe-tools` component declares
  it as a dependency in its `.component.yml`, so placing the component brings the
  behaviour with it and no caller has to remember an `attach_library()`.
- **`flavourful/base`** — structural CSS inherited from the starterkit, for
  markup this theme does not own (forms, field wrappers, dialogs). Six files
  were **removed** from it in the redesign — breadcrumb, menu, tabs, pager, links
  and more-link. They load in the `component` group, which comes *after* the
  `base` group `global.css` sits in, so they won on equal specificity; and
  `ul.menu a.is-active { color: #000 }` is *higher* specificity than the theme's
  own rule, which rendered the active nav item black on a near-black ground.
- **`flavourful/noop`** — an intentionally empty library. See below.

### Where component CSS lives

Three homes, and the rule for each is about *whose decision it is*:

- **A component's own CSS sits beside its Twig** — `components/x/x.css` —
  declared in no library and attached by SDC on render. Nothing to remember,
  and it cannot load on a page that does not render the component.
- **Page-level arrangement** — grids, bands, column splits, the stack of
  sections on a detail page — goes in a library attached by hand from the view
  or node template that lays them out.
- **Tokens, typography and the page shell** are `global-styling`, site-wide.

A page library may reach into a component, but only so far. **Which shape the
component takes is the component's decision, selected by a prop; how that shape
is proportioned in one named page slot may be the page's.** `.home__lead` sets
the lead card's type scale, column ratio and media crop, and that is fine —
those are facts about the lead slot. What it never does is *choose* `stacked`;
that arrives as `#card_variant` from `ListingHooks`. The test: delete the page
library and every component must still render as a correct, complete thing,
just untuned for its slot.

**One entrypoint per page library, not the barrel.** `articles.scss` pulls a
single partial, `@use 'components/articles-listing'`. `recipes.scss` pulls
`@use 'components'`, the whole barrel, so `css/recipes.css` carries the listing
*and* the home bands *and* the recipe-detail stack — and three templates
attach it: `node--recipe.html.twig`, `views-view--recipes--page-1.html.twig`
and `views-view--frontpage.html.twig`. Measured, roughly 70% of that file is
listing CSS, 14% home, 14% recipe detail, so each page loads about three
times what it uses. It is 4.3 KB, so this is an architecture point, not a
performance one — but the recipe detail page has no business carrying the home
page's grid, and the barrel is what makes that invisible.

### Olivero

Olivero is the base theme for its **templates**, not its looks. Every one of its
libraries is switched off in `libraries-override`, because inheriting them
brought ~55 stylesheets that fought this design: the tiled droplet SVG on
`body` (`olivero/css/base/base.css`), white form controls, blue link underlines,
a white page background, and Olivero's own `font-size`/`line-height` at high
specificity — which is what made the first pass of this redesign read as
cramped.

They are disabled in two groups, because they cannot all be disabled the same
way:

1. Libraries nothing else references — plain `false`.
2. Libraries named by Olivero's own `libraries-extend`, which this theme
   inherits and cannot un-declare. Setting these to `false` makes Drupal throw
   `InvalidLibrariesExtendSpecificationException` on **every page** — the extend
   still points at them, so they have to keep existing. They are redirected to
   `flavourful/noop` instead: resolvable, and carrying nothing.

Some Olivero **templates** also had to be displaced, because markup is as much
the problem as CSS. `menu--primary-menu` / `menu--secondary-menu` emitted
Olivero's mobile-navigation structure and attached its nav CSS;
`block--system-powered-by-block` embedded the Drupal logo SVG; `region--header`
injected a stray `header-nav-overlay` div; and `region--content` /
`region--breadcrumb` / `region--highlighted` added `grid-full` and
`layout--pass--content-medium` classes from a grid system this theme does not
use — which is also why the `.region-highlighted` rules in
`scss/layout/_page.scss` never matched, since Olivero emitted `region--`
double-dashed.

## Known gaps

The design shows data this content model does not have. Every affected component
takes it as an optional prop and renders nothing without it, so adding a field
lights the component up with no template change:

| Design element | Needs | Status |
| --- | --- | --- |
| Star ratings, "128 cooked it" | a rating field + count | props exist, unused |
| Issue numbering | an issue field | the published-recipe count stands in |
| Cost (`££`) | a cost field | column omitted |
| Ingredient rows, method steps | `field_ingredient_rows`, `field_method_steps` | `recipe-tools` is silent until both exist |
| The Journal rail | an Article content type | `journal-list` unused |
| Photo credits | a credit field | `hero-feature`'s `caption` slot left empty |

## Known blemishes (config, not theme)

Three things look wrong on screen but are not the theme's to fix:

| What you see | Where it comes from |
| --- | --- |
| Filter labels reading `Diet type (field_type_of_diet)` | The exposed filter labels in `views.view.recipes`. Edit them at `/admin/structure/views/view/recipes`. |
| The wordmark reading `Drush Site-Install` | `system.site:name`. Set a real site name and slogan at `/admin/config/system/site-information` — the slogan becomes the wordmark's "Est." line and the utility bar's issue line. |
| An A–Z row of bare letters joined by literal `\|` | Core's glossary attachment renders `<span>`s with no list semantics. Tracked separately in `docs/plans/plan-glossary-a11y-recipes-az.md`. |

## Twig debugging

Twig debug, `auto_reload` and disabled Twig cache are enabled in
`docroot/sites/development.services.yml`, activated by
`docroot/sites/default/settings.local.php`. View any page's source to see the
`THEME HOOK` / `FILE NAME SUGGESTIONS` comments that name the template to
override, and `data-component-id` attributes that name the component.

## Legacy files, kept on purpose

`templates/partials/`, `templates/macros/` and
`templates/views/views-view-unformatted--recipes.html.twig` are the pre-SDC
partial/macro card that `docs/actual-outcomes/day9-sdc.md` contrasts SDC
against. They are unreachable and superseded — do not build on them.
