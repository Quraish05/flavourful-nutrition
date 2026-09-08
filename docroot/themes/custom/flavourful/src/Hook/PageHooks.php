<?php

namespace Drupal\flavourful\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\flavourful\RecipeStats;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Supplies the page shell — masthead, utility bar, category bar, footer.
 *
 * The shell components under components/ are all props-in, markup-out: not one
 * of them queries anything. That is deliberate, and this class is the other
 * half of the arrangement — every lookup the shell needs happens here, once,
 * with its cacheability declared, rather than being scattered through Twig as
 * drupal_config()/drupal_entity_query() calls that no cache tag follows.
 *
 * Hook classes are registered as autowired services by
 * \Drupal\Core\Hook\HookCollectorPass, so these constructor arguments resolve
 * from the interface aliases in core.services.yml. No create() needed.
 */
class PageHooks {

  use StringTranslationTrait;

  /**
   * Recipe count and listing URL, shared with ListingHooks.
   */
  private RecipeStats $stats;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
    protected DateFormatterInterface $dateFormatter,
    protected MenuLinkTreeInterface $menuTree,
    protected RequestStack $requestStack,
    protected ThemeExtensionList $themeList,
  ) {
    $this->stats = new RecipeStats($entityTypeManager);
  }

  /**
   * The faces that render above the fold on every page.
   *
   * The body serif is deliberately absent: it sets running prose, which is
   * below the fold, and preloading a third file would only delay these two.
   */
  private const PRELOAD_FONTS = [
    'cormorant-garamond-var.woff2',
    'inter-var.woff2',
  ];

  /**
   * Implements hook_page_attachments_alter() via the #[Hook] attribute.
   *
   * Preloads the display and label faces. Without this the browser only
   * discovers them after parsing global.css, which shows a full flash of
   * fallback type on the most prominent text on the page.
   *
   * Done here rather than as a <link> in html.html.twig, where it started: that
   * template has no `base_path` variable, so the href came out relative and
   * resolved against the current path — correct on the front page, a 404 on
   * /recipes/<slug>, and silent either way, because a failed preload warns
   * nobody. base_path() plus the extension list gives an unambiguous path that
   * also survives a sub-directory install.
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    $theme_path = base_path() . $this->themeList->getPath('flavourful');

    foreach (self::PRELOAD_FONTS as $file) {
      $attachments['#attached']['html_head_link'][] = [
        [
          'rel' => 'preload',
          'href' => $theme_path . '/fonts/' . $file,
          'as' => 'font',
          'type' => 'font/woff2',
          // Required even same-origin: fonts are fetched in CORS mode, and
          // without it the preload is discarded and the file fetched twice.
          'crossorigin' => 'anonymous',
        ],
      ];
    }
  }

  /**
   * Implements hook_preprocess_page() via the #[Hook] attribute.
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    // Collect cacheability as we go and apply it once at the end, so nothing
    // added below can be forgotten. Starting from the existing render array
    // means we add to the page's metadata rather than replacing it.
    $cache = CacheableMetadata::createFromRenderArray($variables);

    $this->addSiteIdentity($variables, $cache);
    $this->addUtilityDate($variables, $cache);
    $this->addCategoryBar($variables, $cache);
    $this->addFooterLinks($variables, $cache);

    // A full-bleed main lets the recipe hero's image reach the viewport edge.
    // Decided here so the list of bleeding routes lives in one place;
    // page.html.twig only reads the flag.
    $node = $this->routeMatch->getParameter('node');
    $variables['is_bleed'] = $this->routeMatch->getRouteName() === 'entity.node.canonical'
      && $node instanceof NodeInterface
      && $node->bundle() === 'recipe';

    $this->dropDuplicatePageTitle($variables);

    $cache->applyTo($variables);
  }

  /**
   * Removes the page-title block on node pages that render their own heading.
   *
   * The recipe template puts the h1 inside the hero, beside the image, as the
   * design requires. The page-title block would add a second h1 above it —
   * the same text twice, and a document outline with two level-1 headings,
   * which is a WCAG 1.3.1 failure and not something CSS can fix by hiding one.
   *
   * Done here rather than through the block's visibility settings so the rule
   * travels with the theme: a template that renders its own h1 and a config
   * change in a different repo that has to accompany it is exactly the pairing
   * that goes wrong on the next deploy.
   *
   * The separate case of a page with *no* title — where the block rendered an
   * empty `<h1 class="page-title">` — is handled in
   * templates/content/page-title.html.twig, which can test the rendered markup
   * directly rather than guessing at it from here.
   */
  private function dropDuplicatePageTitle(array &$variables): void {
    if (!$variables['is_bleed']) {
      return;
    }

    // Only the content region, and only because its blocks are built inline.
    // Blocks in the header, menu and footer regions arrive here as
    // `#lazy_builder` placeholders that carry no `#plugin_id` yet, so this
    // approach cannot identify them at all — which is why the site-branding
    // block is suppressed in its template instead. Do not "fix" that by moving
    // it here; it will silently match nothing.
    foreach ($variables['page']['content'] ?? [] as $key => $element) {
      // Match on the plugin, not on a block ID: the machine name depends on
      // which theme placed the block and would silently stop matching if the
      // block were ever re-placed.
      if (($element['#base_plugin_id'] ?? NULL) === 'page_title_block') {
        unset($variables['page']['content'][$key]);
      }
    }
  }

  /**
   * Adds the wordmark text and its "established" caption.
   */
  private function addSiteIdentity(array &$variables, CacheableMetadata $cache): void {
    $config = $this->configFactory->get('system.site');
    $cache->addCacheableDependency($config);

    $variables['site_name'] = $config->get('name');
    // The design's wordmark carries an "Est. 2019" caption between two rules.
    // There is no field for it, so the site slogan stands in — it is the one
    // piece of free editorial text the site settings already offer. An empty
    // slogan simply renders the wordmark without the caption.
    $variables['site_established'] = $config->get('slogan');
    $variables['home_url'] = Url::fromRoute('<front>')->toString();
  }

  /**
   * Adds the long-form date shown at the start of the utility bar.
   *
   * A date on every page would normally force max-age 0. Rather than disabling
   * the page cache site-wide for one decorative line, the page is cached until
   * the start of the next day — correct for its whole lifetime, and still a
   * cache hit for most of it.
   */
  private function addUtilityDate(array &$variables, CacheableMetadata $cache): void {
    $now = new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));

    $variables['utility_date'] = $this->dateFormatter->format($now->getTimestamp(), 'custom', 'l, j F Y');
    // The machine-readable form for the <time> element's datetime attribute.
    $variables['utility_date_iso'] = $now->format('Y-m-d');

    $cache->setCacheMaxAge($now->modify('tomorrow midnight')->getTimestamp() - $now->getTimestamp());
  }

  /**
   * Adds the cuisine category strip and the total recipe count.
   *
   * "All" is prepended pointing at the unfiltered listing, and the active item
   * is read from the same exposed-filter query parameter the /recipes view
   * already uses — so the strip and the filter select agree with each other
   * instead of being two competing notions of "current".
   */
  private function addCategoryBar(array &$variables, CacheableMetadata $cache): void {
    // NULL when the recipes view is disabled — the strip then has nowhere to
    // point, so it is simply not rendered.
    $listing_url = $this->stats->listingUrl();
    if ($listing_url === NULL) {
      return;
    }

    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadTree('cuisine', 0, 1, TRUE);

    // The strip changes whenever a cuisine term is added, renamed or deleted.
    $cache->addCacheTags(['taxonomy_term_list:cuisine']);

    $filter = 'field_recipe_cuisine_type_target_id';
    $active = $this->requestStack->getCurrentRequest()?->query->get($filter);
    // Reading the query string makes the output vary by it, which the page
    // cache has to know about or every visitor would get the first-served
    // variant's active state.
    $cache->addCacheContexts(['url.query_args:' . $filter]);

    $items = [[
      'label' => $this->t('All'),
      'url' => $listing_url,
      // Views' "All" option submits the literal string "All" for an unset
      // select, so both that and an absent parameter mean unfiltered.
      'is_active' => $active === NULL || $active === '' || $active === 'All',
    ]];

    foreach ($terms as $term) {
      $cache->addCacheableDependency($term);
      $items[] = [
        'label' => $term->label(),
        'url' => Url::fromRoute('view.recipes.page_1', [], [
          'query' => [$filter => $term->id()],
        ])->toString(),
        'is_active' => (string) $active === (string) $term->id(),
      ];
    }

    $variables['category_items'] = $items;
    $variables['recipe_count'] = $this->recipeCount($cache);
    // Also handed to the footer's trailing action. Resolved here, behind the
    // same access check, so no template has to call url() on a route that may
    // not exist — Twig's url() throws rather than returning empty.
    $variables['recipes_url'] = $listing_url;
  }

  /**
   * Formats the recipe count for the category bar's trailing tally.
   */
  private function recipeCount(CacheableMetadata $cache): ?string {
    $count = $this->stats->count($cache);

    // formatPlural rather than string concatenation, so a translation can
    // supply the plural forms its language actually needs instead of being
    // stuck with English's two.
    return $count > 0
      ? (string) $this->formatPlural($count, '1 recipe', '@count recipes')
      : NULL;
  }

  /**
   * Flattens the footer menu into the rows the site-footer component takes.
   *
   * The component takes an array rather than rendered menu markup because the
   * footer strip is a single flat row by design — handing it a menu block would
   * bring along a tree, a block wrapper and a title it has nowhere to put.
   */
  private function addFooterLinks(array &$variables, CacheableMetadata $cache): void {
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters('footer');
    $parameters->setMaxDepth(1)->onlyEnabledLinks();

    $tree = $this->menuTree->transform($this->menuTree->load('footer', $parameters), [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    $cache->addCacheTags(['config:system.menu.footer']);

    $links = [];
    foreach ($tree as $element) {
      // checkAccess above marks inaccessible links rather than removing them.
      if (!$element->access?->isAllowed()) {
        continue;
      }
      $cache->addCacheableDependency($element->access);
      $links[] = [
        'label' => $element->link->getTitle(),
        'url' => $element->link->getUrlObject()->toString(),
      ];
    }

    $variables['footer_links'] = $links;
  }

}
