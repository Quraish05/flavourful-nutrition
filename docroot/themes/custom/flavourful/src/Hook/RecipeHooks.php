<?php

namespace Drupal\flavourful\Hook;

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
    $this->setCardHeadingLevel($variables);
  }

  /**
   * Sets the heading level the recipe-card should use for this context.
   *
   * The same teaser renders in several places, and the correct level depends on
   * what heading precedes it: on /recipes the cards sit directly under the page
   * h1, so h2. Embedded in a node page they sit under an h2 section heading
   * ("Related Recipes", "More from this chef"), so h3.
   */
  private function setCardHeadingLevel(array &$variables): void {
    if (($variables['view_mode'] ?? '') !== 'teaser') {
      return;
    }
    $route = \Drupal::routeMatch()->getRouteName();
    $variables['card_heading_level'] = $route === 'view.recipes.page_1' ? 2 : 3;
  }

}
