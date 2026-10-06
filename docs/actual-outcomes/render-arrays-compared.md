# One render array of each kind, and the markup each produced

> Phase 2, Week 6–8 · Last updated: 2026-10-06
>
> Three pairs, rendered on this site through
> `\Drupal::service('renderer')->renderInIsolation()` and `renderRoot()`. Every
> block of output below is captured, not reconstructed. The scratch file used
> to produce it was deleted afterwards; the findings are here.

---

## Pair 1 — `#type` versus `#theme`

```php
['#type' => 'html_tag', '#tag' => 'p', '#attributes' => ['class' => ['lab']], '#value' => 'Hello']
```
```html
<p class="lab">Hello</p>
```

```php
['#theme' => 'item_list', '#items' => ['One', 'Two']]
```
```html
<!-- THEME HOOK: 'item_list' -->
<!-- 💡 BEGIN CUSTOM TEMPLATE OUTPUT from 'themes/custom/flavourful/templates/dataset/item-list.html.twig' -->
<div class="item-list"><ul><li>One</li><li>Two</li></ul></div>
```

The second one found **this theme's own template**. The first did not look for
one, and that is the difference that matters.

`#type` names a **render element plugin**. `ElementInfoManager` merges that
plugin's defaults into the array before rendering. For `html_tag` those
defaults are:

```
#pre_render, #attributes, #value, #type, #defaults_loaded
```

No `#theme`. The markup is assembled in PHP by the plugin's `#pre_render`
callback and a theme cannot override it. That is not special to `html_tag`:

| `#type` | `#theme` | `#pre_render` |
|---|---|---|
| `html_tag` | — | yes |
| `link` | — | yes |
| `container` | — | yes |
| `inline_template` | — | yes |
| `table` | `table` | yes |

**Four of these five have no theme hook at all.** So "use `#type`" and "this is
themeable" are unrelated claims, and the common assumption that `#type` is
simply a friendlier `#theme` is wrong. `#type` is a *behaviour* — defaults plus
pre-render. `#theme` is a *template contract* — registry lookup, suggestions,
preprocess, overridable by any theme.

`#type => 'table'` shows the two composing: a plugin whose defaults happen to
include a `#theme`, so it gets both.

**The rule for this repo:** if a front-end developer should be able to change
the markup, it has to reach `#theme` (or an SDC). A `#type` that renders
through `#pre_render` is closed.

## Pair 2 — `#markup` versus `#plain_text`

Both were given the same hostile string:

```php
'<em>keep</em> <script>alert(1)</script> <div onclick="x">attr</div>'
```

| Build | Output |
|---|---|
| `#markup` | `<em>keep</em> alert(1) <div>attr</div>` |
| `#plain_text` | `&lt;em&gt;keep&lt;/em&gt; &lt;script&gt;alert(1)&lt;/script&gt; …` |
| both keys set | `plain` |
| `#markup` already `Markup::create(…)` | `<em>keep</em> <script>alert(1)</script> <div onclick="x">attr</div>` |
| `#markup` + `#allowed_tags => ['em']` | `<em>keep</em> alert(1) attr` |

Nine lines of core explain all five:

```php
// core/lib/Drupal/Core/Render/Renderer.php:878-889
protected function ensureMarkupIsSafe(array $elements) {
  if (isset($elements['#plain_text'])) {
    $elements['#markup'] = Markup::create(Html::escape($elements['#plain_text']));
  }
  elseif (!($elements['#markup'] instanceof MarkupInterface)) {
    $tags = $elements['#allowed_tags'] ?? Xss::getAdminTagList();
    $elements['#markup'] = Markup::create(Xss::filter($elements['#markup'], $tags));
  }
  return $elements;
}
```

Four things fall out, and three of them surprise people:

- **`#plain_text` wins outright.** It is tested first and *overwrites*
  `#markup`. Setting both does not merge them — the `#markup` is discarded. The
  third row above is the proof: `<b>markup</b>` vanished entirely.
- **`Xss::filter()` strips tags but keeps their text.** `<script>alert(1)</script>`
  came back as `alert(1)`. "XSS filtered" does not mean "content removed", and
  anyone reading that output as a safety guarantee about *content* is reading
  it wrong. It is a guarantee about *tags and attributes* — note `onclick`
  disappeared while `<div>` survived, because `<div>` is on the admin tag list.
- **A `MarkupInterface` in `#markup` is not filtered at all.** The `elseif`
  skips it. `Markup::create()` is a promise, and core takes it at face value —
  the `<script>` tag came through whole. This is the single most dangerous
  thing in this document.
- **Both branches end in `Markup::create()`.** So whatever arrives in Twig is
  already `MarkupInterface` and autoescape will not touch it again. That is the
  same boundary described in
  [autoescape-and-the-trust-boundary.md](autoescape-and-the-trust-boundary.md),
  reached from the render-array side: **the escaping decision is made in PHP,
  before the template ever sees the value.**

## Pair 3 — `#lazy_builder` versus rendering inline

The box asks why a lazy builder exists at all, and says the answer is about
caching rather than rendering. It is, and the numbers are blunt. Same parent,
same child content, the only difference being how the child is attached:

```php
$parent = [
  '#cache' => ['keys' => ['lab'], 'max-age' => 3600, 'tags' => ['page_thing']],
  'static' => ['#markup' => 'cacheable part. '],
  'child'  => /* inline, or lazy-built */,
];
```

| | `max-age` | contexts | tags |
|---|---|---|---|
| child inline | **0** | …`user.permissions`, **`user`** | `page_thing`, **`dynamic_thing`** |
| child lazy-built | **3600** | …`user.permissions` | `page_thing` |

**One uncacheable child collapsed the whole parent to `max-age: 0`** and
dragged its `user` context and `dynamic_thing` tag up with it. Cacheability
bubbles, and it bubbles *upwards* — the most dynamic fragment on a page sets
the ceiling for everything around it.

A lazy builder cuts the bubble. The child is replaced, during render, by a real
element in the markup:

```html
cacheable part. <drupal-render-placeholder
  callback="Drupal\Core\Render\Element\StatusMessages::renderMessages"
  arguments="0"
  token="_HAdUpwWmet0TOTe2PSiJuMntExoshbm1kh2wQzzzAA"></drupal-render-placeholder>
```

The parent is then cached *with the placeholder in it*, and the callback runs
on every request to fill it — or, with BigPipe, streams in after the rest of
the page. The final markup is identical either way. **Nothing about the output
is improved; what changes is what the parent is allowed to cache.**

Hence the name: it is a builder that runs late, and the reason to want that is
never rendering.

### Where this repo already met it

Not theory here. `PageHooks::dropDuplicatePageTitle()` can only operate on the
content region, and its comment says why:

> Blocks in the header, menu and footer regions arrive here as `#lazy_builder`
> placeholders that carry no `#plugin_id` yet, so this approach cannot identify
> them at all — which is why the site-branding block is suppressed in its
> template instead.

That is the same mechanism seen from the other end. A placeholdered block is
*not yet built* when preprocess runs, so there is nothing to inspect: no
plugin id, no content, just a token. Any code that expects to look inside a
block from preprocess works in exactly one region and silently matches nothing
in the others.

## The three sentences

- **`#type` is a plugin, `#theme` is a template.** Only one of them is
  overridable by a theme, and `#type` usually is not.
- **`#plain_text` escapes and wins; `#markup` XSS-filters unless it is already
  `MarkupInterface`, in which case it is trusted completely.**
- **`#lazy_builder` is a caching tool wearing a rendering interface.** It
  changes no markup; it stops one dynamic fragment from making its parent
  uncacheable.
