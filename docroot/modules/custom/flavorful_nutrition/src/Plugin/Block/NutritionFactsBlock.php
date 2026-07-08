<?php

namespace Drupal\flavorful_nutrition\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\flavorful_nutrition\NutritionClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Recipe nutrition facts" block.
 */
#[Block(
  id: 'flavorful_nutrition_facts',
  admin_label: new TranslatableMarkup('Recipe nutrition facts'),
  category: new TranslatableMarkup('Flavorful'),
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
      $container->get('flavorful_nutrition.client'),
       $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    if (!$node instanceof \Drupal\node\NodeInterface || $node->bundle() !== 'recipe') {
      return [];
    }
    if (!$node->hasField('field_recipe_ingredients')) {
      return [];
    }
    $calories = 0;
    $protein = 0;
    foreach ($node->get('field_recipe_ingredients')->referencedEntities() as $term) {
      $n = $this->client->getNutritionForIngredient($term->label());
      $calories += $n['calories'];
      $protein += $n['protein'];
    }
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Estimated nutrition'),
      '#items' => [
        $this->t('Calories: @c kcal', ['@c' => $calories]),
        $this->t('Protein: @p g', ['@p' => round($protein, 1)]),
      ],
      // Correct caching: rebuild when THIS node changes, not on every request.
      '#cache' => ['tags' => $node->getCacheTags()],
    ];
  }

}
