# The three cache layers, and a context leak that was already in the repo

> Phase 2, Week 6–8 · Last updated: 2026-10-06
>
> Two boxes in one document, because they turned out to be one subject: the
> second explains why the first was invisible. Every number below was measured
> on this site. **One thing could not be measured — see the limitation at the
> end — and the leak is proved by computing cache addresses instead of by
> watching a stale page.**

---

## Part 1 — the leak

The box asked to put `#cache` on every custom render array, then drop a context
deliberately and watch one user's content served to another. No need to drop
one. **A context was already missing**, and the null cache bins had been hiding
it.

Two custom blocks. Both read the node out of the route:

```php
$node = $this->routeMatch->getParameter('node');
```

Only one said so:

```php
// ChefRecipesBlock.php:92                     ✓
'#cache' => ['tags' => …, 'contexts' => ['route']]

// NutritionFactsBlock.php:73                  ✗  before the fix
'#cache' => ['tags' => $node->getCacheTags()]
```

Neither overrides `getCacheContexts()`, so the plugin contributed nothing
either.

### Why that is a leak and not a style preference

`BlockViewBuilder` render-caches each block under its own keys:

```php
// core/modules/block/src/BlockViewBuilder.php:77
$build[$entity_id]['#cache']['keys'] = ['entity_view', 'block', $entity->id()];
```

A cache address is *keys plus resolved contexts*. With no contexts there is
nothing to resolve, so every recipe page addresses the same entry:

```
BEFORE
  node/46  Spaghetti Carbonara   entity_view:block:flavourful_recipenutritionfacts
  node/47  Margherita Pizza      entity_view:block:flavourful_recipenutritionfacts

AFTER
  node/46  …:[route]=entity.node.canonicalff18878bcc25db0c…
  node/47  …:[route]=entity.node.canonical16a5658f1a8a25a9…
```

The two recipes really do produce different figures — 633 kcal / 7.1 g against
492 kcal / 4.7 g — so the collision would have surfaced as **wrong numbers on a
real page**, not as a harmless duplicate.

### The measurement that failed, and why it is the important part

The obvious check is to compare `X-Drupal-Cache-Contexts` on two recipe pages
with and without the fix. It was done, by stashing the fix and rebuilding:

```
AFTER   margherita-pizza: route url      spaghetti-carbonara: route url
BEFORE  margherita-pizza: route url      spaghetti-carbonara: route url
```

**Identical.** The page already varies by route from other sources —
`PageHooks` adds `route.name`, the views add `url.query_args` — so the block's
contribution folds into contexts the page carried anyway.

That is the reason this survived. **A block's cache entry is keyed
independently of the page's**, under its own `keys` array, and nothing at page
level reflects it. Checking response headers — the instinct the second half of
this document trains — would have reported everything as fine.

### The fix, and the two decisions inside it

```php
'#cache' => [
  'contexts' => ['route'],
  'tags' => $tags,          // node tags + every referenced ingredient term
],
```

- **`route`, not `url`.** `RouteCacheContext::getContext()` returns the route
  name *plus a hash of the raw route parameters*, so it already varies per
  node. `url` would also work and would additionally vary by query string,
  which this block does not read — a strictly worse key.
- **Term tags were missing too.** The API is keyed on each ingredient term's
  *label*, so renaming a term changes the output. A node's own cache tag does
  not fire when a term it merely references is edited.
- **No `max-age`, deliberately**, against the box's wording. `NutritionClient`
  already caches each lookup for 24 hours in `cache.default`
  (`$this->cache->set($cid, $result, time() + 86400)`). The staleness of the
  external data is owned one layer down; capping `max-age` here would re-solve
  a solved problem and make the block uncacheable for a reason that has already
  been handled.
- **No `keys`, deliberately.** `BlockViewBuilder` supplies them. Setting them by
  hand fights the block render cache rather than joining it.

### A detour worth recording: does `contexts` in `build()` even work?

It looked like it might not. The block's wrapper is keyed *before* `build()`
runs, from `$entity->getCacheContexts()` and `$plugin->getCacheContexts()` —
and `BlockPluginTrait::getCacheContexts()` returns `[]`. Asking the plugin
directly confirms it:

```
contexts: (none)
max-age : -1
```

It does work, through a mechanism that is easy to miss. `RenderCache::set()`
passes **two** cacheability objects to the backend:

```php
// core/lib/Drupal/Core/Render/RenderCache.php:117-122
$cache_bin->set(
  $elements['#cache']['keys'],
  $data,
  CacheableMetadata::createFromRenderArray($data),                  // post-bubbling
  CacheableMetadata::createFromRenderArray($pre_bubbling_elements)  // pre-bubbling
);
```

`cache.render` is a `VariationCache`, which "stores redirect chain lookups".
The pre-bubbling contexts find a **redirect**; the redirect points at the entry
keyed by the full post-bubbling set. So a context declared in `build()` does key
the entry — it just costs one extra lookup hop. Overriding
`getCacheContexts()` on the plugin avoids the hop; declaring it in `build()`
keeps the cacheability next to the code that creates the variation. Either is
correct. This repo now does the second in both blocks.

---

## Part 2 — the three layers

### They are not three items in a list

| Layer | What it is | Where it runs |
|---|---|---|
| Internal Page Cache | `http_middleware`, **priority 200** | **outside the kernel** — answers without booting it |
| Dynamic Page Cache | event subscriber, `onRequest` 27 / `onResponse` 7 | inside the kernel |
| BigPipe | response subscriber, after the placeholder strategy | after the response is built; streams later |

"In what order" is a question about the stack, not about a sequence of equals.
Page Cache wraps the kernel; the other two are inside it.

### What the headers actually say here

Measured with `curl -s -o /dev/null -D -` — a real GET with the body discarded.
**Not `curl -I`**, which sends HEAD, and the page cache never stores a HEAD
response, so it can never show a hit however often it is run.

| | `X-Drupal-Cache` | `X-Drupal-Dynamic-Cache` |
|---|---|---|
| anonymous | `MISS` | `MISS` |
| authenticated | **`UNCACHEABLE (request policy)`** | `MISS` |

That `UNCACHEABLE (request policy)` is the most informative line available on
this site. It is the session gate firing:

```php
// core/modules/page_cache/src/StackMiddleware/PageCache.php:145-149
// 1. There is a session cookie on the request.
// 2. The Vary: Cookie header is on the response.
```

Internal Page Cache is declining **by policy, not for want of a backend** — a
null bin cannot produce that string. So the anonymous-only rule is demonstrable
even with caching switched off. Dynamic Page Cache answering `MISS` rather than
`UNCACHEABLE` in the same request proves the complementary point: it does serve
authenticated users, which is the entire reason it exists.

### BigPipe, which needs no cache at all

| Page | bytes | placeholders |
|---|---|---|
| `/recipes` | 109,040 | 12 |
| `/node` | 87,560 | 12 |
| `/user/2` | 65,587 | 12 |
| `/recipes/margherita-pizza` | 82,136 | **14** |
| `/articles` | 89,535 | 12 |
| **`/recipes` anonymous** | 86,598 | **0** |

The anonymous control is the finding: **BigPipe engages only for sessions.**
Its `<drupal-big-pipe-placeholder>` elements are in the markup regardless of
any cache, which makes this the one part of the subject the null bins do not
touch. The recipe node carries two extra placeholders — the two custom blocks
in its sidebar.

---

## The limitation, stated plainly

**No `HIT` is observable on this site.** `settings.local.php` points the
`render`, `page` and `dynamic_page_cache` bins at `cache.backend.null` so that
template edits appear without a rebuild. Both headers therefore report `MISS`
for ever.

What that costs:

- Part 2 loses one row of a table. Everything else above — the ordering, the
  session gate, BigPipe — is independent of it.
- Part 1 loses the box's literal instruction. **"Watch one user's content
  served to another" cannot be carried out here**, which is precisely why the
  leak is proved by computing cache addresses instead. That is a better proof
  anyway: it shows *why* the collision happens rather than that it happened
  once.

Restoring the bins is a one-shot if the `HIT` line is ever wanted —
`settings.local.php` is gitignored, so it leaves no repo diff. It costs two
cache rebuilds and makes every subsequent template edit need a `drush cr`,
which is why it is not the default.

## The three sentences

- **A missing cache context is invisible from the outside.** The page's headers
  looked correct throughout, because the block's entry is keyed separately from
  the page's.
- **Internal Page Cache is anonymous-only by request policy**, and says so in a
  header even when it has no backend to use.
- **BigPipe is a delivery strategy, not a cache**, which is why it is the only
  one of the three still fully observable with the caches switched off.
