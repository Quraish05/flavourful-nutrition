<?php

namespace Drupal\flavourful\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Supplies the mega-menu panels for the main menu.
 *
 * Nothing here is authored. A top-level menu link is content — see
 * scripts/seed-menu.php — and everything inside its panel is derived from
 * whatever that link points at. If the link resolves to a view page, that
 * view's exposed filters become the panel's groups: each filter already knows
 * its public label, its GET parameter and where its options come from, so the
 * whole map lives in one place, maintained by whoever built the listing.
 *
 * The panel is written onto the menu item rather than into a lookup keyed by
 * title, so renaming a link cannot silently detach its panel.
 *
 * Panel links point at filtered listings rather than taxonomy term pages,
 * because the filtered listing keeps this theme's markup and leaves the
 * exposed form in place so a visitor can refine. /taxonomy/term/27 does
 * neither.
 */
class NavigationHooks {

  /**
   * Rows to list when a view exposes no filters to derive groups from.
   */
  private const ROW_LIMIT = 12;

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

    foreach ($variables['items'] as $key => $item) {
      $groups = $this->viewGroups($item['url'], $cache);
      if (!$groups) {
        continue;
      }

      // Browse first, so "All recipes" is the first thing in the panel.
      // Without it the trigger is a button with no way to reach /recipes.
      $variables['items'][$key]['panel'] = $this->browseGroup($item, $cache) + $groups;
    }

    $cache->applyTo($variables);
  }

  /**
   * The item's own destination, plus any menu children it already has.
   *
   * @return array[]
   *   One group, keyed by its title.
   */
  private function browseGroup(array $item, CacheableMetadata $cache): array {
    $links = [
      [
        'label' => 'All ' . mb_strtolower((string) $item['title']),
        'url' => $this->generate($item['url'], $cache),
      ],
    ];

    foreach ($item['below'] ?? [] as $child) {
      $links[] = [
        'label' => (string) $child['title'],
        'url' => $this->generate($child['url'], $cache),
      ];
    }

    return ['Browse' => $links];
  }

  /**
   * Groups derived from the view a menu link points at.
   *
   * @return array[]
   *   Groups of links, keyed by group title. Empty if the link is not a view
   *   page, or if the current user cannot reach it.
   */
  private function viewGroups(Url $url, CacheableMetadata $cache): array {
    if (!$url->isRouted() || !preg_match('/^view\.(\w+)\.(\w+)$/', $url->getRouteName(), $matches)) {
      return [];
    }

    $view = Views::getView($matches[1]);
    if (!$view || !$view->setDisplay($matches[2]) || !$view->access($matches[2])) {
      return [];
    }

    // Renaming an exposed filter renames a panel group, so the menu depends on
    // the view config itself and not only on the entities the panel lists.
    $cache->addCacheableDependency($view->storage);

    // Tags only, deliberately not the display's contexts. Those include
    // url.query_args — which the panel does not vary by — and importing it
    // would cache a separate copy of the menu for every query string on the
    // site. The tags are the half that matters: they carry the
    // field.storage config a list field's allowed values come from.
    $cache->addCacheTags($view->display_handler->getCacheMetadata()->getCacheTags());

    $groups = [];
    foreach ($view->display_handler->getHandlers('filter') as $filter) {
      // getHandlers() is documented as returning ViewsHandlerInterface[],
      // and isExposed() is declared on HandlerBase rather than on that
      // interface. The guard narrows to the type filterGroup() requires.
      if (!$filter instanceof FilterPluginBase || !$filter->isExposed()) {
        continue;
      }

      [$label, $links] = $this->filterGroup($filter, $url, $cache);
      if ($label !== '' && $links) {
        $groups[$label] = $links;
      }
    }

    // A view with nothing exposed — /chefs — has no filters to offer, so its
    // panel lists its own rows instead. One rule for every view beats a
    // hardcoded exception for one of them.
    return $groups ?: $this->rowGroups($view, $cache);
  }

  /**
   * One panel group built from a single exposed filter.
   *
   * @return array
   *   The group title, then its links. Either may be empty.
   */
  private function filterGroup(FilterPluginBase $filter, Url $base, CacheableMetadata $cache): array {
    $options = $filter->options;

    // A grouped filter has already been given human labels by whoever built
    // it, and its GET value is the group key rather than the underlying field
    // value — ?field_difficulty_value=1, not =easy.
    if (!empty($options['is_grouped'])) {
      $identifier = (string) ($options['group_info']['identifier'] ?? '');

      $links = [];
      foreach ($options['group_info']['group_items'] ?? [] as $key => $group) {
        $links[] = $this->queryLink((string) $group['title'], $base, $identifier, (string) $key, $cache);
      }

      return [(string) ($options['group_info']['label'] ?? ''), $links];
    }

    $label = (string) ($options['expose']['label'] ?? '');
    $identifier = (string) ($options['expose']['identifier'] ?? '');

    // Only these two plugins contribute a group. Matching on InOperator
    // alone would catch more than intended — an exposed "Published status"
    // would render a Yes/No panel nobody asked for. Supporting a third filter
    // type is one more arm of this match.
    $links = match ($filter->getPluginId()) {
      // TaxonomyIndexTid::getValueOptions() returns whatever valueForm()
      // last put there, which during a menu render is nothing. The vocabulary
      // is in the filter's own options, so load the tree from that instead.
      'taxonomy_index_tid' => $this->termLinks((string) ($options['vid'] ?? ''), $base, $identifier, $cache),
      // ListField sets its options from field storage in init(), so these are
      // the allowed values with their human labels already resolved.
      'list_field' => $this->optionLinks($filter, $base, $identifier, $cache),
      default => [],
    };

    return [$label, $links];
  }

  /**
   * Published terms in a vocabulary, as links to the filtered listing.
   *
   * @return array[]
   *   Each entry has a 'label' and a 'url'.
   */
  private function termLinks(string $vocabulary, Url $base, string $parameter, CacheableMetadata $cache): array {
    if ($vocabulary === '') {
      return [];
    }

    // Adding or deleting a term has to invalidate the menu. Renames are
    // covered by the per-term dependency below; a list tag does not fire on
    // those.
    $cache->addCacheTags(['taxonomy_term_list:' . $vocabulary]);

    // getStorage() is declared to return the generic EntityStorageInterface;
    // loadTree() is on TermStorageInterface.
    /** @var \Drupal\taxonomy\TermStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');

    // loadTree() returns terms in the vocabulary's own order — weight, then
    // name — which is the order an editor set and expects to see. The last
    // argument loads full entities, so these are terms rather than the partial
    // stdClass rows the signature also allows.
    /** @var \Drupal\taxonomy\TermInterface[] $terms */
    $terms = $storage->loadTree($vocabulary, 0, NULL, TRUE);

    $links = [];
    foreach ($terms as $term) {
      // access('view') is the whole check — it already returns FALSE on an
      // unpublished term for anyone without 'administer taxonomy'. An
      // isPublished() guard in front of it looks like belt and braces but
      // short-circuits before access() runs, so it drops the term for the
      // admins access() would have let through, and makes the
      // user.permissions context below describe variation that cannot happen.
      if (!$term->access('view')) {
        continue;
      }

      $cache->addCacheableDependency($term);
      $links[] = $this->queryLink($term->label(), $base, $parameter, (string) $term->id(), $cache);
    }

    return $links;
  }

  /**
   * A list field's allowed values, as links to the filtered listing.
   *
   * @return array[]
   *   Each entry has a 'label' and a 'url'.
   */
  private function optionLinks(FilterPluginBase $filter, Url $base, string $parameter, CacheableMetadata $cache): array {
    // Core's list_field is an InOperator, but the plugin ID is a contract a
    // contrib module can claim, and getValueOptions() is not on every filter.
    if (!$filter instanceof InOperator) {
      return [];
    }

    $links = [];
    foreach ($filter->getValueOptions() ?? [] as $key => $text) {
      $links[] = $this->queryLink((string) $text, $base, $parameter, (string) $key, $cache);
    }

    return $links;
  }

  /**
   * A view's own rows, for a view that exposes no filters.
   *
   * @return array[]
   *   One group, keyed by the view's title. Empty if there is nothing to list.
   */
  private function rowGroups(ViewExecutable $view, CacheableMetadata $cache): array {
    // A contextual filter needs an argument this context cannot supply, and a
    // view executed without one either fails or returns everything.
    if ($view->display_handler->getHandlers('argument')) {
      return [];
    }

    // Deliberately broad: adding a row has to invalidate the menu, and with
    // no results there is no entity to read a narrower bundle tag from. The
    // view's own page stays the place to see all of them.
    $entity_type = $view->getBaseEntityType();
    if ($entity_type) {
      $cache->addCacheTags($entity_type->getListCacheTags());
    }

    // A panel is navigation, not a listing, so it is bounded.
    $view->setItemsPerPage(self::ROW_LIMIT);
    $view->execute();

    $links = [];
    foreach ($view->result as $row) {
      // ResultRow declares $_entity as EntityInterface but initialises it to
      // NULL, and resetEntityData() puts it back — so the property is always
      // present and the instanceof below is what actually guards this.
      $entity = $row->_entity;
      if (!$entity instanceof EntityInterface || !$entity->access('view')) {
        continue;
      }

      $cache->addCacheableDependency($entity);
      $links[] = [
        'label' => $entity->label(),
        // toUrl() rather than '/node/' . id, so a path alias is used if one
        // exists. None do yet; that is Step 1's outstanding pathauto chore.
        'url' => $this->generate($entity->toUrl(), $cache),
      ];
    }

    return $links ? [$view->getTitle() => $links] : [];
  }

  /**
   * One link to a listing with a single exposed filter pre-applied.
   *
   * @return array
   *   A 'label' and a 'url'.
   */
  private function queryLink(string $label, Url $base, string $parameter, string $value, CacheableMetadata $cache): array {
    if ($parameter === '') {
      return ['label' => $label, 'url' => $this->generate($base, $cache)];
    }

    $url = Url::fromRoute($base->getRouteName(), $base->getRouteParameters(), [
      'query' => [$parameter => $value],
    ]);

    return ['label' => $label, 'url' => $this->generate($url, $cache)];
  }

  /**
   * Renders a Url to a string without dropping its cacheability on the floor.
   */
  private function generate(Url $url, CacheableMetadata $cache): string {
    // toString(TRUE) rather than toString(): the plain form discards the URL's
    // cacheability instead of letting it bubble.
    $generated = $url->toString(TRUE);
    $cache->addCacheableDependency($generated);

    return $generated->getGeneratedUrl();
  }

}
