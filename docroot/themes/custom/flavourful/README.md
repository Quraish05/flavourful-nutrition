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
- **Absent data renders nothing, never a zero or a placeholder.** `rating-stars`
  with no value emits no markup at all.
- **Slots are captured through an inline block:**
  `{% set x %}{% block x %}{% endblock %}{% endset %}`. Twig's `block('x')`
  *throws* when a caller has not defined the block, and a caller filling only
  some slots is the normal case.

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
- **`RecipeHooks`** — recipe fields → component props. The only file that knows a
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
