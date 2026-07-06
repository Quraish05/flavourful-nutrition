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
