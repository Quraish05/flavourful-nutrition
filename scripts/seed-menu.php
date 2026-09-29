<?php

/**
 * @file
 * Seeds the main-menu structure for the mega menu.
 *
 * Menu links are content, not config: `menu_link_content` is a content entity,
 * so this structure does not export, does not survive a rebuild and cannot be
 * code-reviewed as a diff. This script is its reproducible form. Run it with:
 *
 *   ddev drush php:script scripts/seed-menu.php
 *
 * It is idempotent — a link whose title already exists in the menu is skipped,
 * so re-running adds only what is missing.
 *
 * Only the structural links live here. Everything inside a mega-menu panel is
 * derived at render time: cuisines, diets and topics come from their
 * vocabularies, chefs from published Chef nodes. Hand-maintaining 23 links that
 * mirror taxonomy would go stale the first time somebody adds a term, and
 * nothing would say so.
 *
 * Panel links point at filtered listings rather than taxonomy term pages:
 *
 *   /recipes?field_recipe_cuisine_type_target_id=27   not /taxonomy/term/27
 *
 * Both render, but the filtered listing keeps this theme's markup and leaves
 * the exposed form in place so a visitor can refine. That is the same
 * shareable-URL property the exposed filters were built for.
 *
 * The links below are deliberately the only hand-maintained ones. "Home" stays
 * module-provided (standard.front_page).
 *
 * The A–Z link is created here rather than by the glossary view's own menu
 * setting, which used to provide it. Nesting that config-provided link under a
 * content-provided parent would have written a `menu_link_content:<uuid>`
 * reference into `views.view.glossary.yml` — a config file depending on a UUID
 * that differs in every environment. Creating it as content removes the
 * dependency rather than stabilising it, and stops the glossary view owning
 * navigation concerns that are not its business.
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

const MENU = 'main';

// Top-level items, then their structural children. Weight 0 is Home.
$links = [
  ['title' => 'Recipes',  'uri' => 'internal:/recipes',  'weight' => 1, 'parent_of' => NULL],
  ['title' => 'Chefs',    'uri' => 'internal:/chefs',    'weight' => 2, 'parent_of' => NULL],
  ['title' => 'Articles', 'uri' => 'internal:/articles', 'weight' => 3, 'parent_of' => NULL],
  ['title' => 'Search recipes', 'uri' => 'internal:/recipe-search', 'weight' => 1, 'parent_of' => 'Recipes'],
  ['title' => 'A–Z', 'uri' => 'internal:/glossary', 'weight' => 2, 'parent_of' => 'Recipes'],
];

$storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$created = 0;
$skipped = 0;
$uuids = [];

// Index anything already in the menu so re-runs are safe and children can
// find their parent whether or not this run created it.
foreach ($storage->loadByProperties(['menu_name' => MENU]) as $existing) {
  $uuids[$existing->getTitle()] = 'menu_link_content:' . $existing->uuid();
}

foreach ($links as $data) {
  if (isset($uuids[$data['title']])) {
    echo "skip   : {$data['title']}\n";
    $skipped++;
    continue;
  }

  $values = [
    'title' => $data['title'],
    'link' => ['uri' => $data['uri']],
    'menu_name' => MENU,
    'weight' => $data['weight'],
    'expanded' => TRUE,
  ];

  if ($data['parent_of'] !== NULL) {
    if (!isset($uuids[$data['parent_of']])) {
      echo "SKIP   : {$data['title']} — parent '{$data['parent_of']}' not found\n";
      $skipped++;
      continue;
    }
    $values['parent'] = $uuids[$data['parent_of']];
  }

  $link = MenuLinkContent::create($values);
  $link->save();
  $uuids[$data['title']] = 'menu_link_content:' . $link->uuid();
  echo "created: {$data['title']}\t{$data['uri']}\n";
  $created++;
}

echo "\n{$created} created, {$skipped} skipped.\n";
