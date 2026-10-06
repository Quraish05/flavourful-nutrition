# Autoescape, `|raw`, `|clean_class`, `create_attribute()` — what each escapes and what it trusts

> Phase 2, Week 6–8 · Last updated: 2026-10-05
>
> Every claim here is cited to core on this site's version, **Drupal 11.4.1**.
> The Views rewrite boundary — item 6 of the Views work — is the same mechanism
> entered from config instead of from a template, so it is the last section
> rather than a separate document.

---

## The one mechanism everything turns on

Drupal does not let you choose. `TwigEnvironment` sets the strategy and
comments on it:

```php
// core/lib/Drupal/Core/Template/TwigEnvironment.php:80-81
// Ensure autoescaping is always on.
$options['autoescape'] = 'html';
```

So every `{{ … }}` is escaped as HTML — **unless the value says it is already
safe.** Saying so is a type, not a flag:

```php
// core/lib/Drupal/Core/Template/TwigExtension.php:445-447
if ($autoescape && ($arg instanceof MarkupInterface)) {
  return (string) $arg;
}
```

`MarkupInterface` is the entire trust boundary. Everything below is a question
of which side of it a value sits on.

| | Escapes | Trusts |
|---|---|---|
| **autoescape** | every string printed with `{{ }}` | anything implementing `MarkupInterface` |
| **`\|raw`** | nothing | everything — it is the manual override of the above |
| **`\|clean_class`** | nothing (see below) | its input entirely |
| **`create_attribute()`** | nothing itself | its input — it is a constructor |
| **`Attribute` object** | every *value*, via `Html::escape()` | every *name*, and itself |

## `Attribute` — trusted as a whole because it escapes in its parts

The object is on the trusted side:

```php
// core/lib/Drupal/Core/Template/Attribute.php:73
class Attribute implements \ArrayAccess, \IteratorAggregate, MarkupInterface
```

That is only safe because each value escapes itself on the way out:

```php
// AttributeString.php:28
return Html::escape((string) $this->value);
// AttributeArray.php:77
return Html::escape(implode(' ', $this->value));
```

So `{{ attributes }}` is not escaped — it does not need to be, because by the
time `__toString()` runs, every value already has been. The division is exact:
**attribute names are trusted, attribute values are escaped.** A name from an
untrusted source is the hole this design leaves open, and nothing in core
closes it — which is why `create_attribute()` is only ever called here with
literal keys.

`create_attribute()` itself does nothing but construct:

```php
// TwigExtension.php:644-648
public function createAttribute(Attribute|array $attributes = []) {
  if (\is_array($attributes)) { return new Attribute($attributes); }
  return $attributes;
}
```

Its value is not safety. It is that an `Attribute` can be passed around,
merged and `addClass()`-ed by a caller, where a hand-built string cannot.

## `|clean_class` is not an escaper

This is the most commonly misunderstood of the four. It maps to:

```php
// TwigExtension.php:149
new TwigFilter('clean_class', '\Drupal\Component\Utility\Html::getClass');
```

…which lowercases and then calls `cleanCssIdentifier()`, whose job is stated in
its own `@see`: <https://www.w3.org/TR/CSS21/syndata.html#characters>. It
replaces `' '`, `'_'` and `'/'` with `-`, drops `]`, and strips anything outside
the set CSS allows in an identifier.

It is a **normaliser**. That it also removes `<` and `"` is a side effect of the
CSS identifier grammar, not a security guarantee, and relying on it as one is a
mistake: `Html::getClass()` has no idea what context its output lands in. The
class attribute is safe because `AttributeArray::render()` escapes it, not
because `clean_class` cleaned it.

### Why this theme uses it 34 times and its components use it zero

| Location | Uses |
|---|---|
| `components/` — the 26 SDCs | **0** |
| `templates/` — core and starterkit overrides | **34** |

Every one of the 34 is in markup this theme does not own: `block.html.twig`,
`field*.html.twig`, `region*.html.twig`, `node.html.twig`, `media.html.twig`,
`taxonomy-term.html.twig`, `form-element.html.twig`, and the views wrappers.
All of them are building a class out of a **machine name** — a bundle, a field
name, a region, a view id — an arbitrary string from config that has to become
a valid CSS identifier.

The components need it zero times because **their class names are literals**.
`article-card__title` is typed by hand. The only variable part is the variant,
and that is an enum prop with a declared set of values:

```twig
{{ attributes.addClass('article-card', 'article-card--' ~ variant) }}
```

The rule that falls out: **`clean_class` is for class names that come from
config; a component's class names come from the component.** If a component
ever needs it, that is a sign a machine name has leaked into the design layer.

## Why `|raw` appears zero times

Not discipline — the trust boundary already covers every legitimate case.

Everything a template here prints that genuinely contains markup is already a
`MarkupInterface`:

- **A rendered field** — `{{ content.body }}`, `{{ content.field_hero }}` —
  comes back from the renderer as `Markup`.
- **A translated string** — `{{ 'Under 30 minutes'|t }}` — is
  `TranslatableMarkup`. 100 `|t` calls, none needing `|raw`.
- **An `Attribute`** — as above.
- **Filtered text** — the body field passes through its text format, and the
  format is the sanitiser. That is the whole point of `full_html` vs
  `basic_html` being a permission-gated choice.

`|raw` is only needed when a template holds a **plain PHP string containing
markup** — which means something built HTML by concatenation and handed it over
without declaring it safe. This theme never does, because the components take
scalar props and compose the markup in Twig. The markup is written where it is
printed.

So the zero is not a boast about carefulness. It is a consequence of the
preprocess/template split: **preprocess returns values, templates make markup.**
A codebase with `|raw` in it usually has a function somewhere returning HTML.

## The same boundary from the Views side

Views' *Rewrite results* lets a site builder type markup into the admin UI, and
it ends up on the page unescaped. That is the same trust boundary, entered
through config rather than a template:

```php
// FieldPluginBase::renderText(), views/src/Plugin/views/field/FieldPluginBase.php:1306-1311
// $alter['text'] is entered through the views admin UI and will be safe
// because the output of $this->renderAltered() is run through
// Xss::filterAdmin().
$value_is_safe = TRUE;
```

and the result is wrapped in a type that carries that promise:

```php
// views/src/Render/ViewsRenderPipelineMarkup.php:21
final class ViewsRenderPipelineMarkup implements MarkupInterface, \Countable
```

Two gates, and they are different in kind:

1. **`Xss::filterAdmin()`** — an allow-list filter, permissive (it keeps most
   tags) because the input is assumed to come from someone with
   *administer views*.
2. **`MarkupInterface`** — not a filter at all, a declaration. Once the value
   wears it, Twig will not touch it.

The consequence worth stating plainly: **a Views rewrite is only as safe as the
permission that guards the Views UI.** The escaping has already been decided by
the time the value reaches a template, and no amount of care in Twig can undo
it. That is the argument for the theme's position of not rewriting in Views —
the markup stays in templates, where it is reviewable in a diff rather than in
a config export.

## The four sentences

- **autoescape** escapes everything and trusts `MarkupInterface`.
- **`|raw`** escapes nothing and trusts everything; its absence here is a
  by-product of preprocess returning values rather than markup.
- **`|clean_class`** escapes nothing; it normalises a machine name into a legal
  CSS identifier, and the class attribute is made safe by `Attribute`, not by it.
- **`create_attribute()`** escapes nothing; the `Attribute` it returns escapes
  every value and trusts every name.
