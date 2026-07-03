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
   * Returns nutrition data for an ingredient.
   *
   * Currently returns stub data. This will be replaced with a real API call
   * using $this->httpClient and $this->loggerFactory for error handling.
   */
  public function getNutritionForIngredient(string $ingredient): array {
    // @todo Replace the stub below with a real API request.
    return [
      'ingredient' => $ingredient,
      'calories' => 42,
      'protein' => 3,
    ];
  }

}
