<?php

namespace Drupal\flavourful\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\flavourful\RecipeStats;
use Drupal\Core\Pager\PagerManagerInterface;

/**
 * Shapes the recipe listings — which row leads, and at what size.
 *
 * The design's homepage is one large lead story above a grid of small ones. A
 * View has no notion of "the lead row", so this class supplies it: the first
 * row of the frontpage display is told to render its card in the `stacked`
 * variant, and every other row keeps the default `compact`.
 *
 * The instruction travels as a `#card_variant` key on the row's render array,
 * which is the same channel `#view_mode` and `#node` already use — so it
 * arrives in hook_preprocess_node() as $variables['elements']['#card_variant']
 * without any global state, and without page CSS having to reach inside the
 * recipe-card component to override its own variant.
 */
class ListingHooks {

  /**
   * Displays whose first row is the lead story, keyed "view_id:display_id".
   */
  private const LEAD_ROW_DISPLAYS = [
    'frontpage:page_1',
  ];

  /**
   * Recipe count and listing URL, shared with PageHooks.
   */
  private RecipeStats $stats;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    protected PagerManagerInterface $pagerManager,
  ) {
    $this->stats = new RecipeStats($entityTypeManager);
  }

  /**
   * Implements hook_preprocess_views_mini_pager() via the #[Hook] attribute.
   *
   * Adds the total page count, which core computes and then throws away:
   * ViewsThemeHooks::preprocessViewsMiniPager() calls getTotalPages() to decide
   * whether to render the previous and next links, but only ever exposes
   * `items.current` to the template. "Page 2" tells a screen-reader user
   * nothing about how far through the set they are; "Page 2 of 7" does.
   *
   * Left NULL rather than guessed when the pager cannot be resolved — the
   * template falls back to the bare number.
   */
  #[Hook('preprocess_views_mini_pager')]
  public function preprocessViewsMiniPager(array &$variables): void {
    $pager = $this->pagerManager->getPager($variables['element'] ?? 0);
    $variables['total_pages'] = $pager?->getTotalPages();
  }

  /**
   * Implements hook_preprocess_views_view() via the #[Hook] attribute.
   *
   * Supplies the homepage template's two figures. Views templates get no page
   * variables, so what PageHooks put on the page is not visible here and has to
   * be looked up again — through the same RecipeStats, so the count and its
   * cache tag cannot drift apart between the two.
   */
  #[Hook('preprocess_views_view')]
  public function preprocessViewsView(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view) {
      return;
    }

    // The A–Z letter row is the only .views-summary on the site, and its
    // styling lives in the recipes listing stylesheet — a library /glossary
    // never attaches. Without this the row renders unstyled and the active
    // letter has no visual indicator at all, while screen readers do get
    // aria-current. The CSS is arguably in the wrong file; moving it out of
    // _recipes-listing.scss is the tidier fix and a bigger change.
    if ($view->id() === 'glossary') {
      $variables['#attached']['library'][] = 'flavourful/recipes';
    }

    if ($view->id() !== 'frontpage') {
      return;
    }

    $cache = CacheableMetadata::createFromRenderArray($variables);

    // The design's issue number. There is no issue field, so the ornament
    // carries the count of published recipes — a real figure the site can
    // stand behind — rather than a decorative "41".
    $count = $this->stats->count($cache);
    $variables['recipe_count_number'] = $count > 0 ? (string) $count : NULL;
    $variables['recipes_url'] = $this->stats->listingUrl();

    $cache->applyTo($variables);
  }

  /**
   * Implements hook_preprocess_views_view_unformatted() via #[Hook].
   */
  #[Hook('preprocess_views_view_unformatted')]
  public function preprocessViewsViewUnformatted(array &$variables): void {
    $view = $variables['view'] ?? NULL;
    if (!$view) {
      return;
    }

    $key = $view->id() . ':' . $view->current_display;
    if (!in_array($key, self::LEAD_ROW_DISPLAYS, TRUE)) {
      return;
    }

    // Only the first row of the first page: on page 2 there is no lead story,
    // and promoting a row there would give the pager's second page a headline
    // the size of the homepage's.
    if (($view->getCurrentPage() ?? 0) > 0) {
      return;
    }

    // views-view-unformatted--frontpage.html.twig renders the section header
    // between the lead row and the grid, so it needs the listing URL that the
    // parent views-view template also has — preprocess variables do not flow
    // from one template to the other.
    $variables['recipes_url'] = $this->stats->listingUrl();

    if (isset($variables['rows'][0]['content'])) {
      $variables['rows'][0]['content']['#card_variant'] = 'stacked';
      // The lead card sits directly under the page h1, so its title is an h2 —
      // one level up from the grid cards behind it, which RecipeHooks sets to
      // h2 as well on the listing route but h3 here.
      $variables['rows'][0]['content']['#card_heading_level'] = 2;
      $variables['rows'][0]['attributes']->addClass('home__lead-row');
    }
  }

}
