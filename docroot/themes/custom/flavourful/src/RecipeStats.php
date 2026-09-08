<?php

namespace Drupal\flavourful;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * The one place that knows how to count recipes and find their listing.
 *
 * Two hook classes need both facts — PageHooks for the category bar's tally,
 * ListingHooks for the homepage issue ornament — and a query plus its cache tag
 * duplicated across two files is a query that will eventually be tagged
 * correctly in only one of them.
 *
 * A plain collaborator rather than a service: it is constructed directly by the
 * hook classes that need it, so the theme needs no services.yml and there is
 * nothing to keep in sync with the container.
 */
final class RecipeStats {

  /**
   * Cached per request — both callers ask on the same page render.
   */
  private ?int $count = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Counts published recipes the current user may see.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   Collects the cache tag that invalidates the count. Passed in rather than
   *   returned so a caller cannot use the number without taking the tag.
   */
  public function count(CacheableMetadata $cache): int {
    // Any recipe being published, unpublished or deleted changes the count.
    $cache->addCacheTags(['node_list:recipe']);

    return $this->count ??= (int) $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'recipe')
      ->condition('status', 1)
      ->count()
      ->execute();
  }

  /**
   * The recipe listing URL, or NULL when the view is disabled.
   *
   * Url::fromRoute() is lazy, so it is access() and toString() that resolve the
   * route and throw. Caught here, because a disabled view should cost the site
   * a category strip rather than every page it has.
   */
  public function listingUrl(): ?string {
    try {
      $url = Url::fromRoute('view.recipes.page_1');
      return $url->access() ? $url->toString() : NULL;
    }
    catch (RouteNotFoundException) {
      return NULL;
    }
  }

}
