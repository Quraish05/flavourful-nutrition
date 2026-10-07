<?php

namespace Drupal\flavourful_nutrition\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns pages for the Flavourful Nutrition module.
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
