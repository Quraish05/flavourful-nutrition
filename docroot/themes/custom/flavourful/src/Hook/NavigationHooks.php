<?php

namespace Drupal\flavourful\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Supplies the mega-menu panels for the main menu.
 *
 * The top-level links are content — see scripts/seed-menu.php — but everything
 * inside a panel is derived here at render time. Hand-maintaining 23 links
 * that mirror taxonomy would go stale the first time somebody added a term,
 * and nothing would say so.
 *
 * Panel links point at filtered listings rather than taxonomy term pages,
 * because /recipes and /articles already expose those filters as GET
 * parameters. The filtered listing keeps this theme's markup and leaves the
 * exposed form in place so a visitor can refine; /taxonomy/term/27 does
 * neither.
 */
class NavigationHooks {
  /**
   * Panel groups, keyed by the top-level menu link title they belong to.
   *
   * Each entry is [group label, vocabulary, listing path, filter parameter].
   */
  private const TERM_PANELS = [
    'Recipes' => [
      ['By cuisine', 'cuisine', '/recipes', 'field_recipe_cuisine_type_target_id'],
      ['By diet', 'dietary', '/recipes', 'field_type_of_diet_target_id'],
    ],
    'Articles' => [
      ['By topic', 'topic', '/articles', 'field_topics_target_id'],
    ],
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_preprocess_menu() via the #[Hook] attribute.
   */
  #[Hook('preprocess_menu')]
  public function preprocessMenu(array &$variables): void {
    // This fires for every menu on the page — account, footer, tools.
    if (($variables['menu_name'] ?? '') !== 'main') {
      return;
    }

    $cache = CacheableMetadata::createFromRenderArray($variables);

    // The panels are filtered by term and node access, so the output varies by
    // user. Without these contexts one visitor's menu can be served to another.
    $cache->addCacheContexts(['user.permissions', 'user.node_grants:view']);

    // Adding or deleting a term or a chef invalidates these. Renames are
    // covered by the per-entity tags the helpers add. Without both, the menu
    // keeps its first-rendered panels until somebody rebuilds caches — which
    // is invisible in development, where that happens constantly.
    $cache->addCacheTags([
      'taxonomy_term_list:cuisine',
      'taxonomy_term_list:dietary',
      'taxonomy_term_list:topic',
      'node_list:chef',
    ]);

    $panels = [];
    foreach (self::TERM_PANELS as $parent => $groups) {
      foreach ($groups as [$label, $vocabulary, $path, $parameter]) {
        $links = $this->termLinks($vocabulary, $path, $parameter, $cache);
        if ($links) {
          $panels[$parent][$label] = $links;
        }
      }
    }

    $chefs = $this->chefLinks($cache);
    if ($chefs) {
      $panels['Chefs']['Our chefs'] = $chefs;
    }

    // Fold each item's own destination and children into the panel, so the
    // component takes exactly one shape instead of two. Without this the
    // trigger is a button with no way to reach /recipes itself.
    foreach ($variables['items'] as $item) {
      $title = (string) $item['title'];
      if (!isset($panels[$title])) {
        continue;
      }

      $browse = [$this->menuLink('All ' . mb_strtolower($title), $item['url'], $cache)];
      foreach ($item['below'] ?? [] as $child) {
        $browse[] = $this->menuLink((string) $child['title'], $child['url'], $cache);
      }

      // Prepended, so "All recipes" is the first thing in the panel.
      $panels[$title] = ['Browse' => $browse] + $panels[$title];
    }

    $variables['panels'] = $panels;
    $cache->applyTo($variables);
  }

  /**
   * One panel link built from a menu item's own Url object.
   *
   * @return array
   *   A 'label' and a 'url'.
   */
  private function menuLink(string $label, Url $url, CacheableMetadata $cache): array {
    $generated = $url->toString(TRUE);
    $cache->addCacheableDependency($generated);

    return ['label' => $label, 'url' => $generated->getGeneratedUrl()];
  }

  /**
   * Published terms in a vocabulary, as links to a filtered listing.
   *
   * @return array[]
   *   Each entry has a 'label' and a 'url'.
   */
  private function termLinks(string $vocabulary, string $path, string $parameter, CacheableMetadata $cache): array {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // loadTree() returns terms in the vocabulary's own order — weight first,
    // then name — which is the order an editor set and expects to see.
    $terms = $storage->loadTree($vocabulary, 0, NULL, TRUE);

    $links = [];
    foreach ($terms as $term) {
      if (!$term->isPublished() || !$term->access('view')) {
        continue;
      }

      // toString(TRUE) rather than toString(): the plain form discards the
      // URL's cacheability instead of letting it bubble.
      $generated = Url::fromUserInput($path, [
        'query' => [$parameter => $term->id()],
      ])->toString(TRUE);

      $cache->addCacheableDependency($generated);
      $cache->addCacheableDependency($term);

      $links[] = [
        'label' => $term->label(),
        'url' => $generated->getGeneratedUrl(),
      ];
    }

    return $links;
  }

  /**
   * Published chefs, as links to their own pages.
   *
   * @return array[]
   *   Each entry has a 'label' and a 'url'.
   */
  private function chefLinks(CacheableMetadata $cache): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'chef')
      ->condition('status', 1)
      ->sort('title')
      ->execute();

    $links = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      // toUrl() rather than '/node/' . id, so a path alias is used if one
      // exists. None do yet; that is Step 1's outstanding pathauto chore.
      $generated = $node->toUrl()->toString(TRUE);

      $cache->addCacheableDependency($generated);
      $cache->addCacheableDependency($node);

      $links[] = [
        'label' => $node->label(),
        'url' => $generated->getGeneratedUrl(),
      ];
    }

    return $links;
  }

}
