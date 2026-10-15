# The three cache layers, and a context leak that was already in the repo

> Phase 2, Week 6–8, and Phase 3, Week 9–12 (Part 3) · Last updated: 2026-10-14
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

---

## Part 3 — auditing the Articles side, and two leaks watched happening

> Phase 3, Week 9–12 item 1 · 2026-10-14 · Steps and raw output:
> [`plan-perf-cache-audit-articles.md`](../plans/plan-perf-cache-audit-articles.md)

Part 1's limitation no longer applies. The measurement profile (`ddev measure
on`, see [`performance-baseline.md`](performance-baseline.md)) runs with the
render cache on, so a leak can now be **watched**, not just computed: clear the
cache, load page X, then load page Y, and see whether Y shows X's output.

### The inventory

For each piece of the Articles side: what it reads, against what cache
metadata it declares. A read with no matching tag or context is the bug.

| Source | Verdict |
|---|---|
| `PageHooks::preprocessPage()` (date, category strip, footer, site identity) | Complete. Every read has a tag, context or max-age |
| `NavigationHooks::preprocessMenu()` | Complete |
| `RecipeStats::count()` | `node_list:recipe` tag present. Access-checked with no user context, which is harmless while every role sees every recipe |
| `articles` view (`page_1`) | Complete. `tag` caching is coarse: it carries `node_list`, so saving any node clears it |
| Article node display | Complete for the core formatters. But `field_recipes` renders recipe `teaser`s, which brings the leak below onto the article page |
| **`NodeHooks::setCardContext()`** (`web/themes/custom/flavourful/src/Hook/NodeHooks.php:120`) | **Leak A.** Reads the route to choose the card heading (`h2` on the bundle's own listing, `h3` elsewhere), and declares no route context |
| **`ListingHooks::preprocessViewsViewUnformatted()`** (`src/Hook/ListingHooks.php:124`, `:128`) | **Leak B.** Makes the homepage's first card `stacked` with an `h2`, through render-array keys that aren't part of the card's cache key |

### Both leaks, watched

Measured under the measurement profile (`FLAVOURFUL_PROFILE=measure`
checked), `drush cr` before each order. The value in **bold** is wrong for its
page.

| Leak | Card | First page loaded | Its card | Then | Its card | Correct there |
|---|---|---|---|---|---|---|
| A | moussaka | `/recipes` | `compact h2` | `/` | **`compact h2`** | `compact h3` |
| A | moussaka | `/` | `compact h3` | `/recipes` | **`compact h3`** | `compact h2` |
| A | margherita pizza | `/recipes?page=1` | `compact h2` | article 68 | **`compact h2`** | `compact h3` |
| B | margherita pizza | `/` | `stacked h2` | `/recipes?page=1` | **`stacked h2`** | `compact h2` |
| B | margherita pizza | `/recipes?page=1` | `compact h2` | `/` | **`compact h2`** | `stacked h2` |

**Why:** a card is cached once per recipe and view mode. The key, read from
`cache_render`, is `entity_view:node:<id>:teaser` plus language, theme,
timezone and permissions, with no route and no row position. So the first page
to build a card fixes its heading level and its look for every page after it.
In the dev profile, where the render bin is `cache.backend.null`, every page
builds its own card and both leaks disappear. That is why they went unnoticed.

**What it costs:** three of the six frozen URLs (`/`, `/recipes`, the article
node) can serve a card with the wrong heading level, which breaks the outline
screen-reader users navigate by (WCAG 1.3.1). Leak B also decides whether the
homepage lead renders as the large `stacked` card or a plain one, depending on
which page warmed the cache.

**Article cards** (`card` view mode, `ArticlePreprocessTrait.php:33`) share
`setCardContext()`, but they render on `/articles` only today, so leak A is
latent for them. It appears as soon as article cards render on a second route.

### Two ways to fix it

The two shapes considered:

- **Add the context:** `route.name` on the card for leak A. Simple, but it
  splits each card's cache per route.
- **Pass the value down as part of the key:** the listing tells the card its
  heading level and variant through something the cache keys on (a view mode
  per placement, or `#cache` keys), and the card stops reading the route.
  This is cleaner, and it fixes leak B too, which no context can describe.

### The fix, and the re-test

Both leaks fixed on 2026-10-14, using the simpler shape for each:

| Leak | Change | Why it works |
|---|---|---|
| A | `NodeHooks::setCardContext()` adds the `route.name` cache context when it derives the heading level from the route | Core bubbles `#cache` set in a preprocess function onto the element being rendered (`core/lib/Drupal/Core/Theme/ThemeManager.php:352-367`), so each card's cache entry varies by route. When a listing passes the level itself, no context is added |
| B | `ListingHooks::preprocessViewsViewUnformatted()` appends `lead` to the lead row's `#cache` keys, only when the row already has keys | The row is still an unrendered render array at that point (`core/modules/views/src/Hook/ViewsThemeHooks.php:1073`), so the lead gets its own cache entry |

The leak-A fix also covers article cards, which share `setCardContext()`, so
the latent case above is closed too.

**Re-test, measurement profile (`FLAVOURFUL_PROFILE=measure` checked), the
same orders as before:** every card showed its own page's value.

| Card | Order | Results |
|---|---|---|
| moussaka | `/recipes`, then `/` | `compact h2`, then `compact h3` |
| moussaka | `/`, then `/recipes` | `compact h3`, then `compact h2` |
| margherita pizza | `/`, then `/recipes?page=1` | `stacked h2`, then `compact h2` |
| margherita pizza | `/recipes?page=1`, then `/`, then article 68 | `compact h2`, then `stacked h2`, then `compact h3` |

**The cost:** each recipe card is now cached once per route it appears on,
plus once more for the homepage lead. With three card routes that is a few
entries per recipe, not a measurable change in the cache's size.

### The components' props, and a third leak (a missing tag)

> Phase 3, Week 9–12 item 1 · 2026-10-15 · Steps and raw output:
> [`plan-perf-cache-audit-props.md`](../plans/plan-perf-cache-audit-props.md)

**The components themselves can't leak.** All 26 component templates under
`web/themes/custom/flavourful/components/` call no function but `include()`.
They read no route, user, date or entity, and show only their props.

**The code that builds their props can.** `NodeFieldTrait` turns referenced
entities into plain strings, and nothing adds the entity's cache tag:

| Prop | Built from | Metadata dropped |
|---|---|---|
| `recipe_eyebrow` (the cuisine on a card) | the term's `label()`, `NodeHooks.php:145` | `taxonomy_term:<id>` |
| `chef_name`, `article_author_name` | the chef node's `label()`, `NodeHooks.php:142`, `ArticlePreprocessTrait.php:79` | `node:<id>` of the chef |
| `article_tags` (topic pills) | `termChips()`: label and `toUrl()->toString()`, `NodeFieldTrait.php:70-71` | each term's tag, and the URL's metadata |

**Watched happening.** Under the measurement profile (`measure` checked), the
pages were warmed, then cuisine term 35 "Greek" and topic term 77 "Family" were
renamed and saved, and the pages were reloaded without clearing anything:

| Page | Component | Showed the new name? |
|---|---|---|
| `/recipes` | moussaka's card | **No, still "Greek"** |
| `/` | moussaka's card | **No, still "Greek"** |
| article 68 | the header's topic pills | **No, still "Family"** |

**Leak confirmed: a missing tag.** Saving a term clears everything tagged with
it, and these cards and headers aren't tagged with it. They keep the old name
until the recipe or article itself is saved. On `/` and `/recipes` the stale
copy must be the card's own render-cache entry: the category strip makes both
pages depend on every cuisine term (`PageHooks.php:250`), so the pages
themselves were rebuilt. Which layer served the stale article header wasn't
recorded. The chef props share the cause, but weren't tested, because renaming
a chef node could rewrite its URL alias.

Both terms were renamed back, and `config:status` was unchanged (term names
are content).

**The fix (2026-10-15).** The prop builders now register what they read.
`NodeFieldTrait::accessibleUrl()` and `termChips()` take a `CacheableMetadata`
and record the access result (`access('view', NULL, TRUE)`), the URL's metadata
(`toUrl()->toString(TRUE)`) and each term. `addRecipeProps()` and
`addArticleProps()` register the chef and cuisine, and apply it all to the
element at the end. Each registration is guarded: passing `NULL` to
`addCacheableDependency()` would set the element's max-age to 0, which would
turn a missing chef into an uncacheable card. In the dev profile, the rendered
HTML of `/recipes` and article 68 is byte-identical before and after (asset
cache-busting tokens aside): the fix adds cache metadata only. The rename
re-test under the measurement profile is step 8 of the plan.

**Re-test, 2026-10-15 (`measure` checked), the same rename:**

| Page | Component | Showed the new name? |
|---|---|---|
| `/recipes` | moussaka's card | **Yes** |
| `/` | moussaka's card | **Yes** |
| article 68 | the header's topic pills | **Yes** |

Saving a term now clears every card and header that shows it, and the next
request rebuilds them with the new name.
