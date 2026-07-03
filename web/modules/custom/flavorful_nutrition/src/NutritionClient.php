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
