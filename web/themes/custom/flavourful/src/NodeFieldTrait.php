<?php

namespace Drupal\flavourful;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;

/**
 * Field readers shared by the per-bundle preprocess hook classes.
 *
 * These exist because reading a field in Twig is not safe when it may be empty.
 * `node.field_summary.value` does not resolve to NULL on an empty field: Twig
 * finds no `value` property (FieldItemList::__isset() is FALSE when the list is
 * empty), falls through to FieldItemList::getValue(), and gets back an empty
 * array — which is truthy, survives any `is not null` filter, and reaches a
 * component as []. A typed SDC prop then rejects it outright.
 *
 * Read in PHP and you get a real NULL, which a template can filter out.
 */
trait NodeFieldTrait {

  /** Reads the first item's scalar value, or NULL if absent or empty. */
  protected function fieldValue(NodeInterface $node, string $field): mixed {
    if (!$node->hasField($field)) {
      return NULL;
    }
    $items = $node->get($field);
    return $items->isEmpty() ? NULL : $items->first()->value;
  }

  /** Reads the first referenced entity from a reference field. */
  protected function referencedEntity(NodeInterface $node, string $field): mixed {
    if (!$node->hasField($field)) {
      return NULL;
    }
    $items = $node->get($field);
    assert($items instanceof FieldItemListInterface);
    return $items->isEmpty() ? NULL : $items->first()->entity;
  }

  /**
   * A link to an entity, or NULL when the user cannot view it.
   *
   * An entity the current user has no access to must not become a link they
   * cannot follow, so the caller gets NULL and renders plain text instead.
   *
   * The access result and the generated URL both carry cache metadata, which
   * a plain bool and a plain string would drop, so both go into $cache.
   */
  protected function accessibleUrl(?EntityInterface $entity, CacheableMetadata $cache): ?string {
    if (!$entity) {
      return NULL;
    }
    $access = $entity->access('view', NULL, TRUE);
    $cache->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      return NULL;
    }
    $url = $entity->toUrl()->toString(TRUE);
    $cache->addCacheableDependency($url);
    return $url->getGeneratedUrl();
  }

  /**
   * Term references as tag-pill rows, skipping any the user cannot view.
   *
   * Each chip links to its term page, so the tag row doubles as a way into the
   * related listings — which is what makes it worth the space.
   */
  protected function termChips(NodeInterface $node, string $field, CacheableMetadata $cache): array {
    if (!$node->hasField($field)) {
      return [];
    }

    $chips = [];
    foreach ($node->get($field) as $item) {
      $term = $item->entity;
      if (!$term) {
        continue;
      }
      // Only the label and URL reach the component, so without this the
      // element would not carry the term's tag, and renaming the term would
      // leave the old label in every cached chip.
      $cache->addCacheableDependency($term);
      $url = $this->accessibleUrl($term, $cache);
      if ($url === NULL) {
        continue;
      }
      $chips[] = [
        'label' => $term->label(),
        'url' => $url,
      ];
    }
    return $chips;
  }

  /**
   * Drops rows whose value is empty, so components never render a blank cell.
   */
  protected function dropEmptyRows(array $rows): array {
    return array_values(array_filter(
      $rows,
      static fn(array $row): bool => ($row['value'] ?? NULL) !== NULL && $row['value'] !== '',
    ));
  }

}
