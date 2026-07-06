# Food Recipes theme

Custom Drupal 11 theme (generated from `starterkit_theme`) for the Food Recipes
site. Styling is authored in SCSS and compiled to CSS; markup is customised with
Twig template overrides.

## Front-end build (SCSS)

SCSS source lives in `scss/` and compiles to `css/`. Node is provided by DDEV.

```bash
# Install build tooling (once):
ddev exec 'cd web/themes/custom/foodrecipes && npm install'

# One-off compile (compressed, for committing):
ddev exec 'cd web/themes/custom/foodrecipes && npm run build'

# Expanded build with source maps:
ddev exec 'cd web/themes/custom/foodrecipes && npm run dev'

# Watch and recompile on change while theming:
ddev exec 'cd web/themes/custom/foodrecipes && npm run watch'
```

`node_modules/` and `*.css.map` are git-ignored; the compiled `css/*.css` are
committed so the theme works without a build step.

### SCSS structure

```
scss/
├── abstracts/   # Design tokens (_variables) + mixins. No CSS output.
├── base/        # :root custom properties, reset, base typography.
├── layout/      # Page/container layout.
├── components/  # Badge, recipe card, recipes listing (+ exposed filters).
├── global.scss  → css/global.css   (base + layout, loaded site-wide)
└── recipes.scss → css/recipes.css  (components, loaded on the /recipes View)
```

Import a partial's tools with `@use '../abstracts' as *;`. Design tokens are
defined once in `abstracts/_variables.scss` and re-exposed as `--fr-*` CSS custom
properties in `base/_root.scss`.

## Asset libraries

Defined in `foodrecipes.libraries.yml`:

- **`foodrecipes/global-styling`** — `css/global.css`. Attached site-wide via the
  `libraries:` key in `foodrecipes.info.yml`.
- **`foodrecipes/recipes`** — `css/recipes.css`. Attached on demand from
  `templates/views/views-view--recipes.html.twig` with
  `{{ attach_library('foodrecipes/recipes') }}` so it only loads where recipe
  cards render.

## Twig overrides for the /recipes View

- `templates/views/views-view--recipes.html.twig` — listing wrapper: intro
  header + attaches the `recipes` library.
- `templates/views/views-view-grid--recipes.html.twig` — flattens the grid rows
  into a single responsive `.recipes-grid` (columns handled by CSS grid).
- `templates/content/node--recipe--teaser.html.twig` — the recipe card
  (hero/placeholder, difficulty badge, cuisine, title, summary, meta row).

## Twig debugging

Twig debug, `auto_reload` and disabled Twig cache are enabled in
`web/sites/development.services.yml`, activated by
`web/sites/default/settings.local.php` (which also disables render/page caches).
View any page's source to see the `THEME HOOK` / `FILE NAME SUGGESTIONS` comments
that tell you which template file to override.

Generated from starterkit_theme — see the
[Starterkit documentation](https://www.drupal.org/docs/core-modules-and-themes/core-themes/starterkit-theme).
