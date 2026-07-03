<?php

namespace Drupal\flavorful_nutrition\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
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
class NutritionFactsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected NutritionClient $client,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flavorful_nutrition.client'),
    );
  }

  public function build(): array {
    $data = $this->client->getNutritionForIngredient('tomato');
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Nutrition (placeholder)'),
      '#items' => [
        $this->t('Calories: @c', ['@c' => $data['calories']]),
        $this->t('Protein: @p g', ['@p' => $data['protein']]),
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
