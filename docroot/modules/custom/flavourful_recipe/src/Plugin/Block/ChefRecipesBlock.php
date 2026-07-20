<?php

namespace Drupal\flavourful_recipe\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "More recipes from this chef" block for recipe pages.
 *
 * Replaces the inline Chef recipes EVA (which attached to the author user
 * entity and rendered in the middle of the recipe body) with a compact,
 * sidebar-friendly list limited to a handful of the chef's latest recipes.
 */
#[Block(
  id: 'flavourful_recipe_chef_recipes',
  admin_label: new TranslatableMarkup("Chef's other recipes"),
  category: new TranslatableMarkup('Flavourful'),
)]
final class ChefRecipesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Maximum number of recipes to list.
   */
  private const LIMIT = 5;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('entity_type.manager'),
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

    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'recipe')
      ->condition('status', NodeInterface::PUBLISHED)
      ->condition('uid', $node->getOwnerId())
      // Exclude the recipe currently being viewed.
      ->condition('nid', $node->id(), '<>')
      ->sort('created', 'DESC')
      ->range(0, self::LIMIT)
      ->execute();

    if (!$nids) {
      return [];
    }

    $items = [];
    foreach ($storage->loadMultiple($nids) as $recipe) {
      $items[] = $recipe->toLink()->toRenderable();
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('More from this chef'),
      '#items' => $items,
      // Rebuild when this node changes (author may change) or when any recipe
      // is added/removed/updated.
      '#cache' => [
        'tags' => array_merge($node->getCacheTags(), ['node_list:recipe']),
        'contexts' => ['route'],
      ],
    ];
  }

}
