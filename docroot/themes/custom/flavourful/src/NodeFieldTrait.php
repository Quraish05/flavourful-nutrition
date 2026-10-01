<?php

namespace Drupal\flavourful;

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
   */
  protected function accessibleUrl(?EntityInterface $entity): ?string {
    return $entity && $entity->access('view') ? $entity->toUrl()->toString() : NULL;
  }

  /**
   * Term references as tag-pill rows, skipping any the user cannot view.
   *
   * Each chip links to its term page, so the tag row doubles as a way into the
   * related listings — which is what makes it worth the space.
   */
  protected function termChips(NodeInterface $node, string $field): array {
    if (!$node->hasField($field)) {
      return [];
    }

    $chips = [];
    foreach ($node->get($field) as $item) {
      $term = $item->entity;
      if (!$term || !$term->access('view')) {
        continue;
      }
      $chips[] = [
        'label' => $term->label(),
        'url' => $term->toUrl()->toString(),
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
