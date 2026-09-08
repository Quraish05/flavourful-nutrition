<?php

namespace Drupal\flavourful\Hook;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * OOP hook implementations for recipes.
 *
 * Turns recipe fields into the flat prop arrays the components take. The
 * mapping lives here rather than in Twig for two reasons: the templates stay
 * readable, and the field names appear in exactly one place — so the day
 * field_cooking_time is renamed, one file changes rather than four templates.
 *
 * Hook classes are registered as autowired services, so the constructor
 * arguments resolve from the interface aliases in core.services.yml.
 */
class RecipeHooks {

  use StringTranslationTrait;

  public function __construct(
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_preprocess_node() via the #[Hook] attribute.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'recipe') {
      return;
    }

    $total = (int) $this->value($node, 'field_total_time');
    $variables['is_quick'] = $total > 0 && $total <= 30;
    if ($variables['is_quick']) {
      $variables['attributes']['class'][] = 'recipe--quick';
    }

    $this->setCardContext($variables);
    $this->addRecipeProps($variables, $node);

    // field_hero is configured with `label: above`, which printed the word
    // "hero" over every card and hero image. A label is never right for a field
    // that is handed to a component's media slot — the slot is the label — so
    // it is suppressed here rather than by asking every template to strip it.
    if (isset($variables['content']['field_hero'])) {
      $variables['content']['field_hero']['#label_display'] = 'hidden';
    }
  }

  /**
   * Sets the heading level and card variant the recipe-card should use here.
   *
   * The same teaser renders in several places, and the correct level depends on
   * what heading precedes it: on /recipes the cards sit directly under the page
   * h1, so h2. Embedded in a node page they sit under an h2 section heading
   * ("Related Recipes", "More from this chef"), so h3.
   */
  private function setCardContext(array &$variables): void {
    if (($variables['view_mode'] ?? '') !== 'teaser') {
      return;
    }

    // A listing row may override both, because only the View knows which of its
    // rows is the lead story. The keys arrive on the row's render array — see
    // \Drupal\flavourful\Hook\ListingHooks.
    $elements = $variables['elements'] ?? [];
    $variables['card_variant'] = $elements['#card_variant'] ?? 'compact';
    $variables['card_heading_level'] = $elements['#card_heading_level']
      ?? ($this->routeMatch->getRouteName() === 'view.recipes.page_1' ? 2 : 3);
  }

  /**
   * Builds every prop array the recipe templates hand to components.
   */
  private function addRecipeProps(array &$variables, NodeInterface $node): void {
    $prep = (int) $this->value($node, 'field_prep_time');
    $cook = (int) round((float) $this->value($node, 'field_cooking_time'));
    $total = (int) $this->value($node, 'field_total_time');
    $difficulty = $this->value($node, 'field_difficulty');

    $chef = $this->referencedEntity($node, 'field_chef');
    $variables['chef_name'] = $chef?->label();
    // A chef the current user cannot view must not become a broken link.
    $variables['chef_url'] = $chef && $chef->access('view')
      ? $chef->toUrl()->toString()
      : NULL;

    $variables['recipe_eyebrow'] = $this->referencedEntity($node, 'field_recipe_cuisine_type')?->label();

    // Both templates take these from here rather than reading node.field_*
    // directly in Twig, and that is not just tidiness. On an *empty* field,
    // `node.field_difficulty.value` does not resolve to NULL: Twig finds no
    // `value` property (FieldItemList::__isset() is FALSE when the list is
    // empty), falls through to FieldItemList::getValue(), and gets back an
    // empty array. An empty array then survives any `is not null` filter and
    // reaches the component as [] — which failed recipe-card's `difficulty`
    // enum on the one recipe that has no difficulty set. Reading the field in
    // PHP gives a real NULL.
    $variables['recipe_difficulty'] = $difficulty ?: NULL;
    $variables['recipe_summary'] = $this->value($node, 'field_summary') ?: '';

    // -- The card / hero meta row -------------------------------------------
    // Every entry carries a `label`, which meta-list renders visually-hidden.
    // Without it "45 min" arrives as a bare fragment with no indication of what
    // it measures.
    //
    // Difficulty is deliberately NOT here. It already appears on every surface
    // this row does — as the badge over a card's thumbnail, and as "Level" in
    // the recipe page's spec strip — so a third copy said the same word twice
    // per card. It was also what pushed the row to two lines, orphaning a
    // leading "·" at the start of the wrap.
    $variables['recipe_meta'] = $this->compact([
      ['label' => $this->t('Chef'), 'value' => $variables['chef_name'], 'url' => $variables['chef_url']],
      ['label' => $this->t('Total time'), 'value' => $this->duration($total)],
    ]);

    // -- The spec strip ------------------------------------------------------
    // Units go in `suffix`, not baked into `value`, so the numbers stay alone
    // in tabular figures and the columns line up. Rows with no value are
    // dropped by the component, so a recipe with no prep time shows two
    // columns rather than an empty one.
    $variables['recipe_spec'] = $this->compact([
      ['label' => $this->t('Total'), 'value' => $this->durationValue($total), 'suffix' => $this->durationUnit($total)],
      ['label' => $this->t('Prep'), 'value' => $this->durationValue($prep), 'suffix' => $this->durationUnit($prep)],
      ['label' => $this->t('Cook'), 'value' => $this->durationValue($cook), 'suffix' => $this->durationUnit($cook)],
      ['label' => $this->t('Level'), 'value' => $difficulty ? ucfirst($difficulty) : NULL],
    ]);

    // -- Tags ----------------------------------------------------------------
    // field_type_of_diet and field_recipe_ingredients are both single-value
    // term references (cardinality 1), so this is a list of at most two — but
    // it is built by iterating the field rather than reading ->entity, so
    // raising either field's cardinality needs no change here.
    $variables['recipe_tags'] = array_merge(
      $this->termChips($node, 'field_type_of_diet'),
      $this->termChips($node, 'field_recipe_ingredients'),
    );

    // -- Ingredients and method ---------------------------------------------
    // This content model has no per-row ingredient field and no method field,
    // so both come back empty and the recipe-tools organism (servings scaler,
    // Cook Mode) does not render at all. That is the intended degradation, not
    // an oversight: the fields are read by name here, so adding
    // field_ingredient_rows / field_method_steps to the recipe type is enough
    // to light the whole thing up without touching a template.
    $variables['recipe_ingredients'] = $this->ingredientRows($node);
    $variables['recipe_steps'] = $this->methodSteps($node);
  }

  /**
   * Parses an ingredient field into the rows the ingredient-list takes.
   *
   * Accepts "150 ml dry vermouth" style lines and splits the leading number
   * and unit off the name, because that split is what the servings scaler
   * needs — a row with no leading number keeps its whole text as a free-text
   * amount and is never scaled.
   */
  private function ingredientRows(NodeInterface $node): array {
    if (!$node->hasField('field_ingredient_rows')) {
      return [];
    }

    $rows = [];
    foreach ($node->get('field_ingredient_rows') as $item) {
      $line = trim(strip_tags((string) ($item->value ?? '')));
      if ($line === '') {
        continue;
      }
      // "150 ml dry vermouth" -> qty 150, unit "ml", name "dry vermouth".
      // "salt, to taste"      -> no match, so the whole line is the name.
      if (preg_match('/^(\d+(?:[.,]\d+)?)\s*([a-zA-Z]+)?\s+(.+)$/u', $line, $m)) {
        $rows[] = [
          'name' => $m[3],
          'qty' => (float) str_replace(',', '.', $m[1]),
          'unit' => $m[2] ?? '',
        ];
      }
      else {
        $rows[] = ['name' => $line];
      }
    }
    return $rows;
  }

  /**
   * Reads the method field into a flat list of step strings.
   */
  private function methodSteps(NodeInterface $node): array {
    if (!$node->hasField('field_method_steps')) {
      return [];
    }

    $steps = [];
    foreach ($node->get('field_method_steps') as $item) {
      $step = trim((string) ($item->value ?? ''));
      if ($step !== '') {
        $steps[] = $step;
      }
    }
    return $steps;
  }

  /**
   * Builds tag-pill rows from a term reference field.
   *
   * Each chip links to its term page, so the tag row doubles as a way into the
   * related listings — which is what makes it worth the space.
   */
  private function termChips(NodeInterface $node, string $field): array {
    if (!$node->hasField($field)) {
      return [];
    }

    $chips = [];
    foreach ($node->get($field) as $item) {
      $term = $item->entity;
      if (!$term || !$term->access('view')) {
        continue;
      }
      $chips[] = [
        'label' => $term->label(),
        'url' => $term->toUrl()->toString(),
      ];
    }
    return $chips;
  }

  /**
   * Formats a duration in minutes as a display string, e.g. "1 hr 20 min".
   */
  private function duration(int $minutes): ?string {
    if ($minutes <= 0) {
      return NULL;
    }
    if ($minutes < 60) {
      return (string) $this->t('@count min', ['@count' => $minutes]);
    }
    $hours = intdiv($minutes, 60);
    $rest = $minutes % 60;
    return $rest === 0
      ? (string) $this->t('@count hr', ['@count' => $hours])
      : (string) $this->t('@h hr @m min', ['@h' => $hours, '@m' => $rest]);
  }

  /**
   * The numeric half of a duration, for the spec strip's tabular figures.
   *
   * Split from durationUnit() so the spec table can set the number in lining
   * tabular figures and the unit in the small tracked label — the two need
   * different type, which a single formatted string cannot give them.
   */
  private function durationValue(int $minutes): ?string {
    if ($minutes <= 0) {
      return NULL;
    }
    if ($minutes < 60) {
      return (string) $minutes;
    }
    $hours = $minutes / 60;
    // 90 minutes reads as "1.5 hr"; 120 as "2 hr", not "2.0 hr".
    return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');
  }

  /**
   * The unit half of a duration — see durationValue().
   */
  private function durationUnit(int $minutes): ?string {
    if ($minutes <= 0) {
      return NULL;
    }
    return $minutes < 60 ? (string) $this->t('min') : (string) $this->t('hr');
  }

  /**
   * Drops rows whose value is empty, so components never render a blank cell.
   */
  private function compact(array $rows): array {
    return array_values(array_filter(
      $rows,
      static fn(array $row): bool => ($row['value'] ?? NULL) !== NULL && $row['value'] !== '',
    ));
  }

  /**
   * Reads a scalar field value, or NULL when the field is absent or empty.
   */
  private function value(NodeInterface $node, string $field): mixed {
    if (!$node->hasField($field)) {
      return NULL;
    }
    $items = $node->get($field);
    return $items->isEmpty() ? NULL : $items->first()->value;
  }

  /**
   * Reads the first referenced entity from a reference field.
   */
  private function referencedEntity(NodeInterface $node, string $field): mixed {
    if (!$node->hasField($field)) {
      return NULL;
    }
    $items = $node->get($field);
    assert($items instanceof FieldItemListInterface);
    return $items->isEmpty() ? NULL : $items->first()->entity;
  }

}
