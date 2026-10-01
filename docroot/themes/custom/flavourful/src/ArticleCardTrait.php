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
trait ArticleCardTrait {

  /** Preprocesses an article node. Called from NodeHooks::preprocessNode(). */
  private function preprocessArticle(array &$variables, NodeInterface $node): void {
    if (($variables['view_mode'] ?? '') !== 'card') {
      return;
    }

    $this->setCardContext($variables, 'view.articles.page_1');
    $this->addArticleProps($variables, $node);

    // field_hero is configured `label: above` on node.article.card, exactly as
    // it is on the recipe displays, so without this the word "Hero" prints over
    // every image. A label is never right for a field handed to a component's
    // media slot — the slot is the label. Latent today because no article has a
    // hero image, which is precisely why it would have been missed.
    if (isset($variables['content']['field_hero'])) {
      $variables['content']['field_hero']['#label_display'] = 'hidden';
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

  /** The publication date, in the site's medium format. */
  private function publishedDate(NodeInterface $node): ?string {
    $created = $node->getCreatedTime();
    return $created ? $this->dateFormatter->format($created, 'medium') : NULL;
  }

}
