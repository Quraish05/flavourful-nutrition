<?php

namespace Drupal\flavourful;

use Drupal\node\NodeInterface;

/**
 * The article half of the theme's hook_preprocess_node().
 *
 * A trait rather than its own hook class, and not by preference: a theme may
 * implement preprocess_node exactly once — ThemeManager::invoke() throws
 * otherwise — and a hook class is registered as a service only while it carries
 * a #[Hook] attribute. A second class therefore cannot be injected into the
 * first, and themes get no .services.yml to declare one by hand
 * (DrupalKernel only scans module filenames). So the one hook class holds both
 * bundles, and this trait keeps them readable apart.
 *
 * Turns article fields into the flat prop arrays article-card takes, so
 * node--article--card.html.twig stays a mapping rather than a query and the
 * field names live in one place.
 *
 * Only the `card` view mode is handled. The full article page has no component
 * yet — article-header and prose are still to come — so this does nothing there
 * rather than half-preparing props nothing consumes.
 */
trait ArticlePreprocessTrait {

  /** Preprocesses an article node. Called from NodeHooks::preprocessNode(). */
  private function preprocessArticle(array &$variables, NodeInterface $node): void {
    $view_mode = $variables['view_mode'] ?? '';

    if ($view_mode === 'card') {
      $this->setCardContext($variables, 'view.articles.page_1');
    }
    elseif ($view_mode !== 'full' && $view_mode !== 'default') {
      return;
    }

    $this->addArticleProps($variables, $node);

    // field_hero is configured `label: above` on every article display, so
    // without this the word "Hero" prints over the image. A label is never
    // right for a field handed to a component's media slot — the slot is the
    // label. Latent today because no article has a hero image, which is
    // precisely why it would have been missed.
    // Every component on the article displays is configured `label: above`,
    // and a label travels inside the *rendered* field — so passing
    // content.body to prose carries the word "Body" with it. The fields handed
    // to a component or an aside have their label suppressed here; the ones
    // left to render on their own keep theirs, because there they are the only
    // thing naming the value.
    foreach (['field_hero', 'body', 'field_takeaways'] as $field) {
      if (isset($variables['content'][$field])) {
        $variables['content'][$field]['#label_display'] = 'hidden';
      }
    }
  }

  /**
   * Maps the five fields node.article.card renders onto article-card's props.
   */
  private function addArticleProps(array &$variables, NodeInterface $node): void {
    // The story type is a list_string, so the stored value is a machine name:
    // "recipe_story", not "Recipe story". Take the label from the field's
    // allowed values rather than prettifying the key here.
    $variables['article_eyebrow'] = $this->storyTypeLabel($node);
    $variables['article_summary'] = $this->fieldValue($node, 'field_summary') ?: NULL;

    $chef = $this->referencedEntity($node, 'field_chef');
    $chef_url = $this->accessibleUrl($chef);

    // Every entry carries a label, which meta-list renders visually-hidden.
    // Without it "6 min" arrives as a bare fragment with no indication of what
    // it measures. dropEmptyRows() then removes any row with no value, so an
    // article with no reading time simply shows a shorter row — which is every
    // article today, because nothing writes field_reading_time yet.
    $reading_time = (int) $this->fieldValue($node, 'field_reading_time');
    $variables['article_meta'] = $this->dropEmptyRows([
      ['label' => $this->t('Author'), 'value' => $chef?->label(), 'url' => $chef_url],
      ['label' => $this->t('Published'), 'value' => $this->publishedDate($node)],
      [
        'label' => $this->t('Reading time'),
        'value' => $reading_time > 0
          ? (string) $this->t('@count min read', ['@count' => $reading_time])
          : NULL,
      ],
    ]);

    $variables['article_tags'] = $this->termChips($node, 'field_topics');

    // -- The full page -------------------------------------------------------
    // article-header needs the author and date split out rather than folded
    // into the meta row, because it renders the date inside a `time` element
    // with a machine-readable datetime that meta-list has no way to express.
    $variables['article_author_name'] = $chef?->label();
    $variables['article_author_url'] = $chef_url;
    // Cast, but keep NULL as NULL. An integer field reads back as the string
    // "1998", and article-header declares story_year as type: integer — SDC
    // validates that strictly and rejects the string outright. A blanket (int)
    // would turn an empty field into the year 0 instead of omitting it.
    $story_year = $this->fieldValue($node, 'field_story_year');
    $variables['article_story_year'] = $story_year !== NULL ? (int) $story_year : NULL;
    $variables['article_published'] = $this->publishedDate($node);
    $variables['article_published_iso'] = $this->publishedDateIso($node);
    $variables['article_reading_time'] = $reading_time > 0
      ? (string) $this->t('@count min read', ['@count' => $reading_time])
      : NULL;
  }

  /** The human label of field_story_type, or NULL when it is empty. */
  private function storyTypeLabel(NodeInterface $node): ?string {
    $key = $this->fieldValue($node, 'field_story_type');
    if ($key === NULL || $key === '') {
      return NULL;
    }

    $allowed = $node->get('field_story_type')
      ->getFieldDefinition()
      ->getFieldStorageDefinition()
      ->getSetting('allowed_values');

    return isset($allowed[$key]) ? (string) $allowed[$key] : NULL;
  }

  /** The publication date as an ISO 8601 string, for a `datetime` attribute. */
  private function publishedDateIso(NodeInterface $node): ?string {
    $created = $node->getCreatedTime();
    // 'Y-m-d' rather than a full timestamp: the time of day is not something
    // the page shows, and a datetime that claims more precision than the
    // rendered text is a small lie a parser will believe.
    return $created ? $this->dateFormatter->format($created, 'custom', 'Y-m-d') : NULL;
  }

  /**
   * The publication date, as a date and nothing more.
   *
   * Not the site's 'medium' format, which appends the time: "Fri, 18 Sep 2026
   * - 20:03" beside a datetime of "2026-09-18" is text claiming more precision
   * than the machine-readable attribute carries, and the time of day is not
   * something an article page has any reason to publish.
   */
  private function publishedDate(NodeInterface $node): ?string {
    $created = $node->getCreatedTime();
    return $created ? $this->dateFormatter->format($created, 'custom', 'j F Y') : NULL;
  }

}
