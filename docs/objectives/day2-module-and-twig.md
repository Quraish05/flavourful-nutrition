# Flavorful — Day 2 Hands-On Lab (Custom module + Twig, with full file contents)

*Companion to the 5-day prep guide. This is the copy-and-build version of Day 2. You'll create a real custom module (`flavorful_nutrition`) and a custom theme (`flavorful_theme`). Everything compiles on Drupal 10/11. Type it out at least once — don't just paste — so the structure sticks for the vetting call.*

> Paths below are relative to your Drupal **docroot** (the `web/` folder in a Composer install). Create files with your editor; enable things with Drush or the admin UI.

---

## Part 1 — The `flavorful_nutrition` custom module

### 1.1 Directory layout you're about to build

```
web/modules/custom/flavorful_nutrition/
├── flavorful_nutrition.info.yml
├── flavorful_nutrition.routing.yml
├── flavorful_nutrition.services.yml
└── src/
    ├── NutritionClient.php
    ├── Controller/
    │   └── NutritionController.php
    └── Plugin/
        └── Block/
            └── NutritionFactsBlock.php
```

*What you're learning:* Drupal autoloads classes under `src/` using **PSR-4** — the folder path after `src/` = the PHP namespace after `Drupal\flavorful_nutrition\`. This mapping is the thing beginners get wrong; know it cold.

### 1.2 `flavorful_nutrition.info.yml`

```yaml
name: 'Flavorful Nutrition'
type: module
description: 'Fetches and displays nutrition data for recipes.'
package: Flavorful
core_version_requirement: ^10 || ^11
dependencies:
  - drupal:node
```

*Talk track:* `type: module`, `core_version_requirement` is what makes it install on D10 **and** D11, and `dependencies` declares other modules that must be enabled first.

### 1.3 `flavorful_nutrition.routing.yml` — a route + controller

```yaml
flavorful_nutrition.summary:
  path: '/nutrition/summary'
  defaults:
    _controller: '\Drupal\flavorful_nutrition\Controller\NutritionController::summary'
    _title: 'Nutrition summary'
  requirements:
    _permission: 'access content'
```

*Talk track:* a **route** maps a URL to a controller method; `requirements._permission` is the access check (never leave a route open — always gate it).

### 1.4 `src/Controller/NutritionController.php`

```php
<?php

namespace Drupal\flavorful_nutrition\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns pages for the Flavorful Nutrition module.
 */
class NutritionController extends ControllerBase {

  /**
   * A simple render array. Day 5 will pull live API data in here.
   */
  public function summary(): array {
    return [
      '#markup' => $this->t('Nutrition summary — wired to a live API on Day 5.'),
      '#cache' => ['max-age' => 0],
    ];
  }

}
```

*Talk track:* the controller returns a **render array**, not HTML. `#markup`, `#theme`, `#cache` are render-array keys; `$this->t()` is Drupal's translation wrapper (always wrap user-facing strings). Visit `/nutrition/summary` after enabling to see it.

### 1.5 `flavorful_nutrition.services.yml` — a service with dependency injection

```yaml
services:
  flavorful_nutrition.client:
    class: Drupal\flavorful_nutrition\NutritionClient
    arguments: ['@http_client', '@logger.factory']
```

*Talk track:* this registers a reusable **service**. The `@http_client` and `@logger.factory` are Drupal core services **injected** into your class — this is dependency injection, and it's why you avoid calling `\Drupal::service()` inside classes.

### 1.6 `src/NutritionClient.php` — the service class (Guzzle injected)

```php
<?php

namespace Drupal\flavorful_nutrition;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Fetches nutrition data for ingredients.
 */
class NutritionClient {

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Day 2: returns stub data.
   * Day 5: replace the body with a real API call using $this->httpClient.
   */
  public function getNutritionForIngredient(string $ingredient): array {
    // ----- Day 5 will look like this -----
    // try {
    //   $response = $this->httpClient->request('GET', 'https://api.example.com/nutrition', [
    //     'query' => ['q' => $ingredient],
    //     'headers' => ['X-Api-Key' => 'YOUR_KEY'],
    //     'timeout' => 5,
    //   ]);
    //   $data = json_decode((string) $response->getBody(), TRUE);
    //   return ['ingredient' => $ingredient, 'calories' => $data['calories'] ?? 0, 'protein' => $data['protein'] ?? 0];
    // }
    // catch (\Throwable $e) {
    //   $this->loggerFactory->get('flavorful_nutrition')->error($e->getMessage());
    //   return ['ingredient' => $ingredient, 'calories' => 0, 'protein' => 0];
    // }

    // ----- Day 2 stub -----
    return [
      'ingredient' => $ingredient,
      'calories' => 42,
      'protein' => 3,
    ];
  }

}
```

*Talk track:* `$this->httpClient` is Guzzle (Drupal's `http_client` service). Real integration work is exactly this — call an external API, decode JSON, handle failures, log. The commented Day-5 block is your integration story ready to go.

### 1.7 `src/Plugin/Block/NutritionFactsBlock.php` — a block plugin

```php
<?php

namespace Drupal\flavorful_nutrition\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flavorful_nutrition\NutritionClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Recipe nutrition facts" block.
 */
#[Block(
  id: 'flavorful_nutrition_facts',
  admin_label: new TranslatableMarkup('Recipe nutrition facts'),
  category: new TranslatableMarkup('Flavorful'),
)]
class NutritionFactsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected NutritionClient $client,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flavorful_nutrition.client'),
    );
  }

  public function build(): array {
    $data = $this->client->getNutritionForIngredient('tomato');
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Nutrition (placeholder)'),
      '#items' => [
        $this->t('Calories: @c', ['@c' => $data['calories']]),
        $this->t('Protein: @p g', ['@p' => $data['protein']]),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
```

*Talk track:* this is a **plugin** — discovered via the `#[Block(...)]` **PHP attribute** (Drupal 10.2+/11 style; older code uses `/** @Block(...) */` annotations, know both). `ContainerFactoryPluginInterface` + `create()` is how a plugin gets services injected. `build()` returns a render array. This one method touches plugins, DI, services, and render arrays — a great thing to be able to walk through verbally.

### 1.8 Enable and test the module

```bash
drush en flavorful_nutrition -y
drush cr
```

- Visit **`/nutrition/summary`** → your controller page renders.
- **Structure → Block layout** (`/admin/structure/block`) → **Place block** in a region → search **Recipe nutrition facts** → place it → reload any page → the placeholder nutrition list shows.

---

## Part 2 — The `flavorful_theme` custom theme + Twig

### 2.1 Directory layout

```
web/themes/custom/flavorful_theme/
├── flavorful_theme.info.yml
├── flavorful_theme.libraries.yml
├── flavorful_theme.theme
├── css/
│   └── style.css
└── templates/
    ├── node--recipe.html.twig
    └── field--field-ingredients.html.twig
```

We'll subtheme **Olivero** (core's default front-end theme) so you inherit its regions and markup and only override what you need.

### 2.2 `flavorful_theme.info.yml`

```yaml
name: 'Flavorful Theme'
type: theme
description: 'Custom subtheme of Olivero for the Flavorful recipe site.'
core_version_requirement: ^10 || ^11
base theme: olivero
libraries:
  - flavorful_theme/global
```

### 2.3 `flavorful_theme.libraries.yml` — attach CSS/JS

```yaml
global:
  version: 1.x
  css:
    theme:
      css/style.css: {}
```

*Talk track:* Drupal attaches CSS/JS through **libraries**, not `<link>` tags. Libraries can be attached globally (via `.info.yml`) or per-element in a render array with `#attached`.

### 2.4 `css/style.css` (minimal, just to prove attachment)

```css
.recipe { max-width: 760px; margin-inline: auto; }
.recipe__time { font-weight: 600; color: #b3541e; }
.recipe__meta { display: flex; gap: 1.5rem; margin: 1rem 0; }
```

### 2.5 `flavorful_theme.theme` — a preprocess function

```php
<?php

/**
 * @file
 * Theme hooks for Flavorful Theme.
 */

use Drupal\node\NodeInterface;

/**
 * Implements hook_preprocess_HOOK() for node templates.
 *
 * Adds a computed {{ total_time }} variable to Recipe nodes.
 */
function flavorful_theme_preprocess_node(array &$variables): void {
  $node = $variables['node'] ?? NULL;
  if ($node instanceof NodeInterface && $node->bundle() === 'recipe') {
    $prep = (int) $node->get('field_prep_time')->value;
    $cook = (int) $node->get('field_cook_time')->value;
    $variables['total_time'] = $prep + $cook;
  }
}
```

*Talk track:* a **preprocess function** prepares variables *before* Twig renders. The pattern is `THEME_preprocess_HOOK` (here `flavorful_theme_preprocess_node`). This is where you do PHP/logic so the Twig template stays clean. (A module can do the same with `MODULE_preprocess_node` — preprocess isn't theme-only.)

### 2.6 `templates/node--recipe.html.twig` — the template override

```twig
{#
/**
 * @file
 * Theme override for a Recipe node.
 *
 * Available: label, content, node, total_time (from preprocess).
 */
#}
<article{{ attributes.addClass('recipe') }}>
  <h1{{ title_attributes }}>{{ label }}</h1>

  {% if total_time %}
    <p class="recipe__time">
      Total time: {{ total_time }} min
      ({{ content.field_prep_time }} prep + {{ content.field_cook_time }} cook)
    </p>
  {% endif %}

  <div class="recipe__hero">{{ content.field_hero_image }}</div>

  <div class="recipe__meta">
    <span>Cuisine: {{ content.field_cuisine }}</span>
    <span>Difficulty: {{ content.field_difficulty }}</span>
  </div>

  <div class="recipe__ingredients">
    <h2>{{ 'Ingredients'|t }}</h2>
    {{ content.field_ingredients }}
  </div>

  <div class="recipe__steps">
    <h2>{{ 'Method'|t }}</h2>
    {{ content.field_steps }}
  </div>
</article>
```

*Talk track points to memorize:*
- The **filename is the API**: `node--recipe.html.twig` is a **template suggestion** — Drupal picks it automatically for Recipe nodes over the generic `node.html.twig`.
- `{{ content.field_x }}` renders the field through its formatter (respecting Manage display). Rendering the whole `field_x` is preferred over digging into `.value` because it keeps caching, translation, and formatters intact.
- `|t` translates; `|raw` prints unescaped HTML and is **dangerous** — only on trusted markup. Twig **autoescapes** by default (a security feature).

### 2.7 `templates/field--field-ingredients.html.twig` — a field-level override (optional polish)

```twig
{#
/**
 * @file
 * Renders the ingredients field as a simple styled list.
 */
#}
<ul class="ingredient-list">
  {% for item in items %}
    <li{{ item.attributes }}>{{ item.content }}</li>
  {% endfor %}
</ul>
```

### 2.8 Enable the theme + turn on Twig debug

```bash
drush theme:enable flavorful_theme -y
drush config:set system.theme default flavorful_theme -y
drush cr
```

Turn on **Twig debug** so template suggestions show up as HTML comments in the page source — this is *the* everyday theming tool:

1. Copy `sites/default/default.services.yml` to `sites/default/services.yml` if you don't have one.
2. Set:
   ```yaml
   parameters:
     twig.config:
       debug: true
       auto_reload: true
       cache: false
   ```
3. `drush cr`, then open a recipe and **View Source** — you'll see comments like
   `<!-- FILE NAME SUGGESTIONS: ... node--recipe.html.twig ... -->` and which template was used.

> Remember to turn debug **off** for production — it's a dev-only setting (great thing to mention alongside Config Split on Day 3: debug on for dev, off for prod).

### 2.9 Verify

- Open a Recipe node → your `node--recipe.html.twig` layout renders, the `.recipe__time` line shows the **total time** you computed in preprocess, and `style.css` styling is applied.
- If nothing changes: `drush cr` (render cache), confirm the theme is default, and check the debug comments to see which template Drupal actually loaded.

---

## End-of-day verification (say out loud)

1. Walk the **request lifecycle**: route → controller → render array → theme layer → Twig → response, cached throughout.
2. **Hook vs service vs plugin:** hook = procedural extension point (`hook_preprocess_node`); service = injectable reusable object (`NutritionClient`); plugin = discoverable swappable implementation (the Block).
3. Why you render `{{ content.field_x }}` instead of `{{ node.field_x.value }}` (formatters, caching, escaping).
4. How a **template suggestion** works and how Twig debug reveals it.
5. Where a change "won't show up" comes from → render cache → `drush cr`.

---

## Quick commands

```
drush en flavorful_nutrition -y        enable the module
drush theme:enable flavorful_theme -y  enable the theme
drush cr                               rebuild cache (after almost any change)
drush config:set system.theme default flavorful_theme -y   set default theme
```

*Next up: Day 4 — building the Site Studio Recipe Card. See the Day 4 lab file.*
