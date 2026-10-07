<?php

namespace Drupal\flavourful_nutrition\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flavourful_nutrition\NutritionClient;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Recipe nutrition facts" block.
 */
#[Block(
  id: 'flavourful_nutrition_facts',
  admin_label: new TranslatableMarkup('Recipe nutrition facts'),
  category: new TranslatableMarkup('Flavourful'),
)]
final class NutritionFactsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected NutritionClient $client,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flavourful_nutrition.client'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof NodeInterface || $node->bundle() !== 'recipe') {
      return [];
    }
    if (!$node->hasField('field_recipe_ingredients')) {
      return [];
    }
    $calories = 0;
    $protein = 0;
    $terms = $node->get('field_recipe_ingredients')->referencedEntities();

    foreach ($terms as $term) {
      $n = $this->client->getNutritionForIngredient($term->label());
      $calories += $n['calories'];
      $protein += $n['protein'];
    }
    // The API is keyed on each term's *label*, so a rename changes the result.
    // Nothing else invalidates that: the node's own tag does not fire when
    // a term it merely references is edited.
    $tags = $node->getCacheTags();
    foreach ($terms as $term) {
      $tags = Cache::mergeTags($tags, $term->getCacheTags());
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Estimated nutrition'),
      '#items' => [
        $this->t('Calories: @c kcal', ['@c' => $calories]),
        $this->t('Protein: @p g', ['@p' => round($protein, 1)]),
      ],
      // Rebuild when this node changes, or when any ingredient term it
      // references is renamed.
      //
      // `route` is what makes this block *per recipe*. Without it the block
      // has no declared variation at all, so every recipe page resolves to one
      // cache entry and the first recipe rendered supplies the figures for all
      // of them. RouteCacheContext hashes the raw route parameters, not just
      // the route name, so one entry per node is what it yields.
      //
      // No max-age: NutritionClient already caches each API lookup for 24h in
      // cache.default, so the freshness of the external data is its concern,
      // not this block's. Capping max-age here would re-solve a solved problem
      // and make the block uncacheable for a reason that no longer applies.
      '#cache' => [
        'contexts' => ['route'],
        'tags' => $tags,
      ],
    ];
  }

}
