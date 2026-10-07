<?php

namespace Drupal\flavourful_nutrition;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Fetches nutrition data for ingredients from Open Food Facts.
 */
class NutritionClient {

  public function __construct(
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected CacheBackendInterface $cache,
  ) {}

  /**
   * Returns rough per-100g nutrition for an ingredient (calories, protein).
   */
  public function getNutritionForIngredient(string $ingredient): array {
    $cid = 'flavourful_nutrition:' . md5(strtolower($ingredient));
    if ($hit = $this->cache->get($cid)) {
      // Served from cache.
      return $hit->data;
    }

    $result = ['ingredient' => $ingredient, 'calories' => 0, 'protein' => 0];

    try {
      $response = $this->httpClient->request('GET',
        'https://world.openfoodfacts.org/cgi/search.pl', [
          'query' => [
            'search_terms' => $ingredient,
            'search_simple' => 1,
            'action' => 'process',
            'json' => 1,
            'page_size' => 1,
            'fields' => 'product_name,nutriments',
          ],
          'headers' => ['User-Agent' => 'Flavourful/1.0 (learning project)'],
          'timeout' => 5,
        ]);
      $data = json_decode((string) $response->getBody(), TRUE);
      $nutriments = $data['products'][0]['nutriments'] ?? [];
      $result['calories'] = (int) round($nutriments['energy-kcal_100g'] ?? 0);
      $result['protein']  = (float) ($nutriments['proteins_100g'] ?? 0);

      // Cache for 24h so we don't call the API on every page load.
      $this->cache->set($cid, $result, time() + 86400);
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('flavourful_nutrition')->error('Nutrition API: @m', ['@m' => $e->getMessage()]);
    }
    return $result;
  }

}
