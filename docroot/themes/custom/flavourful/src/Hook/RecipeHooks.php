<?php

namespace Drupal\flavorful_nutrition\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * OOP hook implementations for recipes.
 */
class RecipeHooks
{

  /**
   * Implements hook_preprocess_node() via the #[Hook] attribute.
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void
  {
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
