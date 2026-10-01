<?php

namespace Drupal\flavourful_recipe\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Keeps base fields out of the RSS view mode.
 *
 * This has to be a module hook rather than a theme one: the alter is invoked
 * through \Drupal::moduleHandler()->alter() in
 * EntityViewDisplay::collectRenderDisplay(), which themes never reach.
 */
class FeedDisplayHooks {

  /**
   * Base fields that render in every view mode and cannot be switched off.
   */
  private const UNCONFIGURABLE = ['title', 'uid', 'created'];

  /**
   * Implements hook_entity_view_display_alter().
   */
  #[Hook('entity_view_display_alter')]
  public function entityViewDisplayAlter(EntityViewDisplayInterface $display, array $context): void {
    if (($context['view_mode'] ?? '') !== 'rss') {
      return;
    }

    // Node's title, uid and created call setDisplayOptions('view') without
    // setDisplayConfigurable('view', TRUE). EntityDisplayBase::init()
    // re-applies
    // a non-configurable field's display options unconditionally — see its
    // first if() clause — so these three render in every view mode, never
    // appear in Manage display, and are put back even if the exported config
    // lists them as hidden.
    //
    // On a page that is invisible, because the node template places them. In a
    // feed the view mode's output becomes <description>, so they arrive as
    // markup duplicating the <title>, <dc:creator> and <pubDate> that the RSS
    // row plugin already emits as real elements.
    //
    // The alter runs after init(), which is the only reason removeComponent()
    // sticks here.
    foreach (self::UNCONFIGURABLE as $name) {
      $display->removeComponent($name);
    }
  }

}
