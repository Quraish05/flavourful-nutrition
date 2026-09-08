<?php

namespace Drupal\flavourful\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\flavourful\RecipeStats;

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

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->stats = new RecipeStats($entityTypeManager);
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
    if (!$view || $view->id() !== 'frontpage') {
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
