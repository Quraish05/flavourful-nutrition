# Flavorful — Day 6 Lab: The Hook System & Preprocess Deep Dive

> Companion to the [advanced curriculum](advanced-plan-days6-9.md). Written teacher-style: the *why* comes before the *how*, because "how do you change Drupal's behaviour without hacking core?" is the archetypal interview question and hooks are the answer.
>
> Everything builds on the `flavorful_nutrition` module from [Day 2](day2-module-and-twig.md).

**Build target:** four working hook implementations in Flavorful — a form alter, an entity presave, a preprocess that adds a computed variable + CSS class, and a custom template suggestion — plus **one of them rewritten as a modern `#[Hook]` class** so you can compare the two styles out loud.

---

## 1. The mental model: what a hook actually is

**Why start here:** if you can explain the hook *mechanism* in one breath, the interviewer relaxes. Everything else is just names.

Drupal core and modules reach certain points and say *"does anyone want to weigh in?"* — that's **invoking** a hook. Your module answers by **implementing** it. You never edit core; you plug into its extension points.

- **Invoke** (Drupal does this): "I'm about to save an entity — `hook_entity_presave` implementations, run now."
- **Implement** (you do this): a function named `MODULE_entity_presave()` (procedural) *or* a method tagged `#[Hook('entity_presave')]` (the modern OOP way).

Two styles exist today (Drupal 11.4):

| Style | Where it lives | Notes |
|---|---|---|
| **Procedural** | functions in `flavorful_nutrition.module` | The classic form; still works, **planned for removal in Drupal 12** |
| **OOP `#[Hook]`** | a class under `src/Hook/` | Since Drupal **11.1**; autoloaded, supports dependency injection, unit-testable. Preferred for new code |

We'll write procedurally first (easier to read while learning), then convert one to the OOP form in §6.

> **Interview line:** "A hook is an extension point Drupal invokes and modules implement — it's how you change behaviour without patching core. Drupal 11 is moving hooks from procedural `.module` functions to `#[Hook]`-attributed class methods, which are autoloaded and DI-friendly; procedural hooks are slated for removal in Drupal 12."

Create the file that will hold the procedural hooks (if it doesn't exist): **`web/modules/custom/flavorful_nutrition/flavorful_nutrition.module`** starting with:

```php
<?php

/**
 * @file
 * Hook implementations for Flavorful Nutrition.
 */
```

After adding any hook, run `drush cr` so Drupal re-scans implementations.

---

## 2. `hook_form_alter` — tweak the recipe form

**Why:** altering another module's (or core's) form is the most common hook task — "add a field, change a label, reorder, add validation." Core builds the node form; you adjust it.

Add to `flavorful_nutrition.module`:

```php
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter() for the Recipe node form.
 *
 * FORM_ID-specific alters only fire for that one form — cleaner than a
 * generic hook_form_alter() with an if-check inside.
 */
function flavorful_nutrition_form_node_recipe_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
  // Add help text under the cook-time field.
  if (isset($form['field_cook_time'])) {
    $form['field_cook_time']['widget'][0]['value']['#description'] = t('Active cooking time in minutes (excludes prep).');
  }
  // Nudge the summary field to the top of the form.
  if (isset($form['field_summary'])) {
    $form['field_summary']['#weight'] = -10;
  }
}
```

`drush cr`, edit a recipe, and you'll see the new description and reordering.

> **Why `hook_form_FORM_ID_alter` over `hook_form_alter`:** the FORM_ID variant only runs for that exact form, so you avoid a generic hook firing on *every* form with an `if ($form_id === …)` inside. Cleaner and faster.

---

## 3. `hook_entity_presave` — compute and store `total_time`

**Why:** entity lifecycle hooks (`presave`, `insert`, `update`, `delete`) let you react to data changes. Here we **store** total time on save — a useful contrast with Day 2, where we *computed it at render* in preprocess. Storing it means Views can sort by it and it's queryable.

First add an integer field **`field_total_time`** to Recipe (Manage fields), and hide it on the form (Manage form display → Disabled) since it's auto-filled.

```php
use Drupal\Core\Entity\EntityInterface;

/**
 * Implements hook_entity_presave().
 *
 * Runs just before ANY entity is saved; we narrow to Recipe nodes and
 * populate field_total_time = prep + cook.
 */
function flavorful_nutrition_entity_presave(EntityInterface $entity): void {
  if ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'recipe') {
    $prep = (int) $entity->get('field_prep_time')->value;
    $cook = (int) $entity->get('field_cook_time')->value;
    $entity->set('field_total_time', $prep + $cook);
  }
}
```

Save a recipe → `field_total_time` is populated automatically.

> **Compute-and-store vs compute-at-render (a great thing to articulate):** the Day-2 preprocess computed total time *for display only*. Storing it via `presave` makes it a real field you can **sort/filter in Views** (you'll use this on Day 7's "Quickest first" sort). Rule of thumb: store it if you need to query/sort on it; compute at render if it's purely presentational.

---

## 4. Preprocess deep dive — add a computed variable + CSS class

**Why:** preprocess hooks are where logic meets theming. They run *before* Twig and prepare `$variables`. Interviewers love "how do you pass a computed value or a conditional class to a template?"

We'll flag quick recipes and expose that to Twig. (Preprocess can live in a module *or* a theme; Day 2 did the theme version — here's the module version.)

```php
/**
 * Implements hook_preprocess_HOOK() for node templates.
 */
function flavorful_nutrition_preprocess_node(array &$variables): void {
  $node = $variables['node'] ?? NULL;
  if (!$node || $node->bundle() !== 'recipe') {
    return;
  }

  $total = (int) $node->get('field_total_time')->value;

  // 1. A computed variable available in Twig as {{ is_quick }}.
  $variables['is_quick'] = $total > 0 && $total <= 30;

  // 2. Add a class the Drupal way (the attributes object handles escaping).
  if ($variables['is_quick']) {
    $variables['attributes']['class'][] = 'recipe--quick';
  }
}
```

In `node--recipe.html.twig` you can now do:

```twig
{% if is_quick %}<span class="badge">Quick — under 30 min</span>{% endif %}
```

> **Two things to know for the call:**
> - **`$variables` is the bridge** between PHP and Twig — anything you set here is available in the template.
> - **Add classes via `$variables['attributes']['class'][]`**, not by string-building markup — the `Attribute` object renders and escapes them safely. (You then print `{{ attributes }}` on the element in Twig.)

Other preprocess targets worth naming: `hook_preprocess_field`, `hook_preprocess_page`, `hook_preprocess_html` (add `<body>` classes / meta), `hook_preprocess_block`.

---

## 5. `hook_theme_suggestions_HOOK_alter` — target a template to specific content

**Why:** "How would you give Italian recipes a different template?" You *add a template suggestion*. Drupal already offers `node--recipe.html.twig`; we add `node--recipe--italian.html.twig` as an option.

```php
/**
 * Implements hook_theme_suggestions_HOOK_alter() for nodes.
 *
 * Adds a per-cuisine suggestion, e.g. node--recipe--italian.html.twig.
 */
function flavorful_nutrition_theme_suggestions_node_alter(array &$suggestions, array $variables): void {
  $node = $variables['elements']['#node'] ?? NULL;
  if (!$node || $node->bundle() !== 'recipe' || $node->get('field_cuisine')->isEmpty()) {
    return;
  }
  $term = $node->get('field_cuisine')->entity;
  if ($term) {
    // clean_class-style: lowercase, dashes.
    $machine = preg_replace('/[^a-z0-9]+/', '-', strtolower($term->label()));
    $suggestions[] = 'node__recipe__' . $machine;   // note: double underscores in the suggestion
  }
}
```

Now create `templates/node--recipe--italian.html.twig` in your theme and Italian recipes will use it (falling back to `node--recipe.html.twig`, then `node.html.twig`). Turn on Twig debug (Day 2) to see the suggestion listed in the page-source comments.

> **Naming gotcha:** in PHP suggestions use **double underscores** (`node__recipe__italian`); the corresponding **filename** uses **double dashes** (`node--recipe--italian.html.twig`). Drupal maps between them.

---

## 6. The modern way: rewrite a hook as a `#[Hook]` class

**Why:** this is the "are you current with Drupal 11?" flex. The OOP form autoloads (no `.module` scan), supports dependency injection, and is unit-testable.

Create **`web/modules/custom/flavorful_nutrition/src/Hook/RecipeHooks.php`**:

```php
<?php

namespace Drupal\flavorful_nutrition\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * OOP hook implementations for recipes.
 */
class RecipeHooks {

  /**
   * Implements hook_preprocess_node() via the #[Hook] attribute.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node || $node->bundle() !== 'recipe') {
      return;
    }
    $total = (int) $node->get('field_total_time')->value;
    $variables['is_quick'] = $total > 0 && $total <= 30;
    if ($variables['is_quick']) {
      $variables['attributes']['class'][] = 'recipe--quick';
    }
  }

}
```

Then **delete** the procedural `flavorful_nutrition_preprocess_node()` from §4 so you don't run it twice. `drush cr`.

> **What just happened (say this):** "Drupal 11.1+ auto-discovers any class under the module's `src/Hook/` with `#[Hook('…')]` methods and registers it as a service — no `.module` function, no manual service registration. Because it's a real class, I can inject services into the constructor and unit-test it. Since Drupal 11.2, even preprocess hooks work this way."
>
> **Dependency injection bonus:** if this hook needed a service (say the current user), you'd add a constructor with that service injected — impossible with a procedural hook, which is exactly why the OOP form exists.

---

## 7. `hook_update_N()` — how structural changes ship (awareness)

**Why:** ties Day 6 back to Day 3 deploys. When code needs to change existing data/config on deploy, you write an **update hook**:

```php
/**
 * Backfill field_total_time on existing recipes.
 */
function flavorful_nutrition_update_10001(): void {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  foreach ($storage->loadByProperties(['type' => 'recipe']) as $node) {
    $node->save();   // triggers our presave, populating total_time
  }
}
```

This runs during `drush updb` (which, remember, runs **before** `drush cim` on deploy). You don't call it directly — Drupal tracks which `_N` numbers have run.

---

## 8. End-of-day verification (say these out loud)

1. What a hook is (invoke vs implement) in one sentence.
2. `hook_form_alter` vs `hook_form_FORM_ID_alter` — why the specific one is cleaner.
3. Entity lifecycle hooks, and **compute-and-store vs compute-at-render** (why you'd pick each).
4. How preprocess passes data to Twig, and the *right* way to add a class (`attributes`).
5. How to make specific content use a specific template (theme suggestions), including the `__` vs `--` naming.
6. What changed about hooks in Drupal 11 (`#[Hook]`, autoloading, DI, procedural removal in D12).
7. What an update hook is and when it runs (`drush updb`, before `cim`).

## Interview Q&A

| Question | Answer shape |
|---|---|
| "Change a form without editing core?" | `hook_form_FORM_ID_alter` in a module; alter `$form`. |
| "React when content is saved?" | Entity lifecycle hooks — `hook_entity_presave`/`insert`/`update`. |
| "Pass a computed value / conditional class to a template?" | `hook_preprocess_HOOK`; set `$variables[...]`; add classes via `$variables['attributes']['class'][]`. |
| "Give some content a different template?" | `hook_theme_suggestions_HOOK_alter` → add a suggestion → create the matching `--` template. |
| "What's new with hooks in Drupal 11?" | `#[Hook]` attribute classes (11.1), preprocess OOP (11.2), themes (11.3); procedural removal planned for D12. |

---

### Sources

- [Object-oriented hooks / `#[Hook]` attribute (Drupal.org change record)](https://www.drupal.org/node/3442349)
- [Drupal 11.1 adds hooks as classes (Drupalize.Me)](https://drupalize.me/blog/drupal-111-adds-hooks-classes-history-how-and-tutorials-weve-updated)
