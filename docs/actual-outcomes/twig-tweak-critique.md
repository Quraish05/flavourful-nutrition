# Twig Tweak — used once, deliberately, and the account of what it hid

> Phase 2, Week 6–8 · Last updated: 2026-10-05
>
> The box asked for the critique, not the usage. This is the critique. It
> answers why a theme this size had never called Twig Tweak, records the one
> place it was then used on purpose, and ends with a recommendation that is
> not the one expected going in.

---

## Where it started

Twig Tweak is required in `composer.json` (`drupal/twig_tweak: ^3.4`), enabled
at 3.4.2 — and called **nowhere**. Not one `drupal_view()`, `drupal_block()`,
`drupal_entity()`, `drupal_field()` or any sibling, across the theme and both
custom modules.

That is the more interesting starting point than "try it". A theme with 26
SDCs, five hook classes and eight view templates has had every opportunity.

## Why it was never needed

Not restraint — architecture. The theme already has a place for each thing
Twig Tweak offers to do from a template.

| The temptation | Where this theme already puts it |
|---|---|
| `drupal_view()` to embed a listing in a node | An **EVA** display in config — `recipe_articles`, `articles_by_author`, `chef_recipes_eva` |
| `drupal_config()` for a site setting | `PageHooks::addSiteIdentity()`, with `addCacheableDependency($config)` |
| `drupal_menu()` for a menu | `PageHooks::addFooterLinks()`, flattened to the rows `site-footer` takes |
| `drupal_field()` to print one field | The field is already in `content.*`; the template hands it to a slot |
| `drupal_entity()` to render a referenced node | `entity_reference_entity_view` formatter on the display |
| `drupal_image()` for a styled image | An image style on the formatter, so `srcset` and alt survive |

The pattern underneath: **the components are props-in, markup-out — not one of
them queries anything.** Every lookup happens in a hook class, once, with its
cacheability declared. Twig Tweak's whole value proposition is letting a
template do the lookup instead. This theme decided it shouldn't, and the
decision is now written down as *Template or preprocess?* in the theme README.

So the honest answer to "why has it never been needed" is that **needing it is
the symptom**. Reaching for `drupal_view()` in a template means the render
array arrived without something it should have carried.

## The one deliberate use

The strongest temptation on this site: `recipe_articles` is attached to the
recipe page as an EVA. Replacing that with one line in
`node--recipe.html.twig` is less configuration and less indirection:

```twig
{{ drupal_view('recipe_articles', 'entity_view_1', node.id) }}
```

It works. The view renders, the argument binds, the page returns 200.

**It does not break caching**, which is what I expected to find. Cache tags and
contexts are byte-identical to the EVA render — only `Content-Length` moves,
because the view is now on the page twice. `drupal_view` returns
`['#type' => 'view', …]`, so the render system bubbles its metadata exactly as
it does for the EVA. The caching critique, the obvious one, is wrong.

What it actually costs:

- **It leaves config.** The EVA is in `config/sync`, exported, diffable, and
  visible in *Manage display* where an editor can reorder or remove it. A Twig
  call is invisible to all three.
- **It hardcodes the argument.** The EVA passes the node id because it is
  attached to the node. The template has to know to pass `node.id`, and a
  template reused in another context passes the wrong thing silently.
- **It is unfindable.** "What renders this view?" is answerable from config
  for an EVA. For a Twig call it is answerable only by grep.

## The finding that settles it

`drupal_view` is not a Twig Tweak function. It is core's `views_embed_view()`,
mapped straight through:

```php
// twig_tweak/src/TwigTweakExtension.php:67
new TwigFunction('drupal_view', 'views_embed_view'),
```

And on this site's core version — **11.4.1** — that function is deprecated:

```php
// core/modules/views/views.module:346
@trigger_error(__FUNCTION__ . "() is deprecated in drupal:11.4.0 and is
  removed from drupal:13.0.0. Use '#type' => 'view' render elements instead.
  See https://www.drupal.org/node/3572594", E_USER_DEPRECATED);
```

It is not alone. `drupal_view_result` maps to `views_get_view_result()`,
deprecated at `views.module:384`.

So the two Twig Tweak functions most worth reaching for are both already
deprecated here, and the replacement core names — `'#type' => 'view'` — is
precisely what `views_embed_view()` returns after its access check. The wrapper
adds one `access()` call and an argument pack.

This matters more than a style preference. A deprecation that fires from a
**template** is one that static analysis does not see: `phpstan` reads PHP, and
these calls live in Twig. The warning exists and nothing in CI will surface it.

## Recommendation

**Do not adopt it, and reconsider the dependency.** Not because template
lookups are inelegant — because the specific functions that justify the module
are on a removal path in the version this site already runs, and because the
architecture that made it unnecessary is the architecture worth keeping.

If it is kept, keep it for the filters rather than the functions —
`|image_style`, `|truncate`, `|children` are presentational and have no
render-array equivalent to bypass. `composer why drupal/twig_tweak` first:
something else may depend on it.

## What this cost to find

The experiment was added to `node--recipe.html.twig`, measured, and reverted —
`git diff` on that file is empty. The two things worth keeping are both in this
document, and neither came from using the module: the deprecation chain came
from reading its source, and the architectural answer came from the table
above.
