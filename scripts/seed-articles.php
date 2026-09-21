<?php

/**
 * @file
 * Seeds Article nodes for the Phase 2 Views work.
 *
 * Content is not config, so it does not survive a rebuild and cannot be
 * exported. This script is the reproducible form. Run it with:
 *
 *   ddev drush php:script scripts/seed-articles.php
 *
 * It is idempotent: an article whose title already exists is skipped, so
 * re-running adds only what is missing.
 *
 * The five articles are shaped deliberately rather than randomly, because
 * each Week 4-6 exercise needs a particular result set to be verifiable:
 *
 *   - Two articles share one author (Marco Rossi), so the chef EVA returns
 *     more than one row and an "is the relationship working?" check is real.
 *   - Three articles point at one recipe (Spaghetti Carbonara), so the
 *     reverse lookup on the recipe page has something to find.
 *   - One article has no recipes and one has no topics, so "no results"
 *     behaviour and optional-relationship row counts are observable.
 *   - All five field_story_type values appear, so the exposed filter has
 *     every option populated.
 *   - field_story_year spans 1998-2023, so aggregation across a
 *     relationship has a spread worth grouping.
 *
 * field_reading_time is deliberately left empty: the presave hook that
 * computes it does not exist yet, and seeding the value by hand would hide
 * that.
 */

use Drupal\node\Entity\Node;

// Chefs, by node ID.
const MARCO = 36;
const SOFIA = 37;
const KENJI = 38;
const PRIYA = 39;
const CAMILLE = 40;

// Recipes, by node ID.
const CARBONARA = 46;
const MARGHERITA = 47;
const TINGA = 48;
const RAMEN = 51;
const BUTTER_CHICKEN = 52;

// Topic terms, by term ID.
const TECHNIQUE = 73;
const SOURCING = 74;
const SEASONALITY = 75;
const TRAVEL = 76;
const FAMILY = 77;
const FAILURE = 78;
const SUSTAINABILITY = 80;

$articles = [];

// 1. The long one. Eight body headings, inline links, two topics, two
// recipes. This is the article the prose component and the accessibility
// pass will both need.
$articles[] = [
  'title' => 'Four Years Before They Let Me Make the Carbonara',
  'chef' => MARCO,
  'story_type' => 'journey',
  'topics' => [FAMILY, TECHNIQUE],
  'recipes' => [CARBONARA, MARGHERITA],
  'year' => 1998,
  'summary' => 'I started in my uncle\'s kitchen in Testaccio at sixteen. For four years I was not allowed near the pasta station, and I have come to think that was the whole education.',
  'body' => <<<'HTML'
<p>People ask how long it takes to learn a dish. The honest answer for the <a href="/recipes/spaghetti-carbonara">carbonara</a> is four years, and almost none of that time was spent making carbonara.</p>
<h2>The kitchen in Testaccio</h2>
<p>My uncle ran twenty-two covers a night out of a room narrower than most people's hallways. There was one pasta station and he stood at it himself, every service, for thirty-one years.</p>
<h2>What I was allowed to do instead</h2>
<p>Prep. Then more prep. I broke down guanciale for two years before I was allowed to render it, on the grounds that you cannot judge the fat until you have seen a hundred pieces of it raw.</p>
<h2>The first thing I got wrong</h2>
<p>Heat. Always heat. Every failure in that kitchen was a heat failure wearing a different coat, and it took me an embarrassingly long time to see the pattern.</p>
<h2>Eggs are not sauce</h2>
<p>The emulsion is the dish. Everything else is shopping. Once I understood that the egg is a suspension being held together by starch and temperature rather than a liquid being cooked, the dish stopped being a recipe and became a technique.</p>
<h2>Why the pasta water matters more than the pasta</h2>
<p>Starch concentration is the only variable you control at the moment it counts, and most home cooks throw it down the sink without looking at it.</p>
<h2>The night he let me do it</h2>
<p>No ceremony. He stepped aside during a service in February and said nothing at all. I have never been more frightened of a bowl.</p>
<h2>What the <a href="/recipes/margherita-pizza">pizza</a> taught me that the pasta could not</h2>
<p>Patience with fermentation is a different patience. Pasta punishes you in seconds; dough punishes you in days, and you cannot rush the apology.</p>
<h2>What I would tell someone starting now</h2>
<p>Stay longer than is comfortable at the station you think you have outgrown. The thing you are learning there is not the station.</p>
HTML,
  'takeaways' => [
    "<p><strong>Heat is the variable.</strong> Almost every failure in a pasta emulsion is a temperature failure, not an ingredient one.</p>",
    "<p><strong>Keep the pasta water.</strong> Starch concentration is the only thing you can still adjust at the moment it matters.</p>",
    "<p><strong>Stay at the boring station.</strong> What you learn there is judgement, and judgement does not announce itself.</p>",
  ],
];

// 2. Same author as #1, so the chef EVA returns two rows. Second pointer
// at the carbonara.
$articles[] = [
  'title' => 'The Pan Was Too Hot. It Was Always Too Hot.',
  'chef' => MARCO,
  'story_type' => 'technique',
  'topics' => [TECHNIQUE, FAILURE],
  'recipes' => [CARBONARA],
  'year' => 2011,
  'summary' => 'Thirteen years after I learned the dish I was still scrambling it about once a month, and the reason turned out to be something nobody had ever said out loud.',
  'body' => <<<'HTML'
<p>There is a particular humiliation in breaking a sauce you have made ten thousand times.</p>
<h2>The failure I could not reproduce</h2>
<p>It never happened on a quiet night. That should have told me something years earlier than it did.</p>
<h2>Residual heat is not heat</h2>
<p>The pan comes off the flame and keeps climbing. On a slow service it has time to fall; on a fast one it does not, and you are pouring egg into a pan that is hotter than the one you think you are holding.</p>
<h2>The fix is a plate</h2>
<p>Rest the pan on a cold surface for a count of five. That is the entire technique, and it is not in any recipe I have ever read.</p>
<h2>Why nobody teaches this</h2>
<p>Because in a kitchen you learn it through your hands within a month, and by the time you could explain it you have forgotten that you ever did not know it.</p>
HTML,
  'takeaways' => [
    "<p><strong>Carry-over heat is real.</strong> A pan off the flame is still climbing for several seconds.</p>",
    "<p><strong>Count to five.</strong> Resting the pan on a cold surface solves a fault that no ingredient change will.</p>",
  ],
];

// 3. Third pointer at the carbonara, from a different cuisine entirely.
// This is the one that makes the reverse lookup interesting rather than
// merely populated.
$articles[] = [
  'title' => 'What Carbonara Taught Me About Ramen Broth',
  'chef' => KENJI,
  'story_type' => 'recipe_story',
  'topics' => [TECHNIQUE, TRAVEL],
  'recipes' => [CARBONARA, RAMEN],
  'year' => 2016,
  'summary' => 'I went to Rome to eat and came back having finally understood why my tonkotsu kept splitting. The two dishes are the same problem in different languages.',
  'body' => <<<'HTML'
<p>I had been making <a href="/recipes/miso-ramen">ramen</a> professionally for nine years before a plate of pasta explained it to me.</p>
<h2>The trip I nearly did not take</h2>
<p>A week in Testaccio, no notebook, no plan. I ate the same dish eleven times in seven days.</p>
<h2>Two emulsions, one problem</h2>
<p>Tonkotsu is fat suspended in gelatin-rich stock. Carbonara is fat suspended in starch-rich water and egg. The stabiliser differs; the physics does not.</p>
<h2>Where my broth was breaking</h2>
<p>I had been treating agitation as the enemy. It is not — insufficient agitation at the wrong temperature is the enemy, and the two failures look identical in the bowl.</p>
<h2>What does not transfer</h2>
<p>Time. Ramen forgives a long hold; pasta does not forgive thirty seconds. Borrowing the technique without borrowing the tempo is how you ruin both.</p>
HTML,
  'takeaways' => [
    "<p><strong>Emulsions travel between cuisines.</strong> The stabiliser changes; the physics does not.</p>",
    "<p><strong>Tempo does not travel.</strong> A technique borrowed without its timing is a different technique.</p>",
  ],
];

// 4. No recipes at all. Exercises the empty-reference case and the
// optional-relationship row count.
$articles[] = [
  'title' => 'The Producer Who Would Not Sell Me Anything',
  'chef' => CAMILLE,
  'story_type' => 'source',
  'topics' => [SOURCING, SUSTAINABILITY],
  'recipes' => [],
  'year' => 2005,
  'summary' => 'She turned me down three years running. The fourth year she asked what I was doing with the leaves, and I realised the interview had been running the whole time.',
  'body' => <<<'HTML'
<p>The best supplier I ever had spent three years refusing to be my supplier.</p>
<h2>The first refusal</h2>
<p>No explanation. A restaurant name she did not recognise, and that was enough.</p>
<h2>What she was actually asking</h2>
<p>Whether I would take the whole plant or only the part that photographs well. It took me two more refusals to hear the question inside the question.</p>
<h2>Buying the difficult half</h2>
<p>The tops, the outer leaves, the ones that bolt early. Committing to those changed the menu more than any dish I have ever written.</p>
<h2>Why this is not a sustainability story</h2>
<p>Or rather — it is, but not the kind that goes on a menu. It was a commercial arrangement that happened to reduce waste, and calling it anything grander would be dishonest.</p>
HTML,
  'takeaways' => [
    "<p><strong>Buy the difficult half.</strong> A grower's hardest problem is what to do with everything that is not the photogenic part.</p>",
    "<p><strong>Refusal can be a question.</strong> Three years of no was a supplier working out whether I was serious.</p>",
  ],
];

// 5. No topics at all. Exercises the empty-taxonomy case, which is what
// the contextual filter's "no results" behaviour has to handle.
$articles[] = [
  'title' => 'In Conversation: Cooking for People Who Are Frightened of Spice',
  'chef' => PRIYA,
  'story_type' => 'interview',
  'topics' => [],
  'recipes' => [BUTTER_CHICKEN],
  'year' => 2023,
  'summary' => 'A conversation about the dish everyone orders when they are not ready for the menu, and why treating that as a failure is a mistake I made for a decade.',
  'body' => <<<'HTML'
<p>Every Indian restaurant has the dish that people order instead of reading the menu. Ours is <a href="/recipes/butter-chicken">butter chicken</a>.</p>
<h2>The resentment phase</h2>
<p>For about ten years I treated it as a tax. That was arrogance, and it showed in the cooking.</p>
<h2>What changed my mind</h2>
<p>A regular who ordered it forty times and on the forty-first asked what else was made the same way. The dish had been doing work I was not giving it credit for.</p>
<h2>The gateway argument, and its limits</h2>
<p>It is a real effect, but it only works if the gateway dish is cooked with the same seriousness as everything else. A cynical version teaches people that the cuisine is cynical.</p>
HTML,
  'takeaways' => [
    "<p><strong>Cook the gateway dish seriously.</strong> A cynical version teaches people the whole cuisine is cynical.</p>",
  ],
];

// ---------------------------------------------------------------------------

$storage = \Drupal::entityTypeManager()->getStorage('node');
$created = 0;
$skipped = 0;

foreach ($articles as $data) {
  $existing = $storage->loadByProperties([
    'type' => 'article',
    'title' => $data['title'],
  ]);
  if ($existing) {
    echo "skip   : {$data['title']}\n";
    $skipped++;
    continue;
  }

  $values = [
    'type' => 'article',
    'title' => $data['title'],
    'uid' => 1,
    'status' => 1,
    'field_summary' => $data['summary'],
    'body' => [
      'value' => $data['body'],
      'format' => 'basic_html',
    ],
    'field_chef' => ['target_id' => $data['chef']],
    'field_story_type' => $data['story_type'],
    'field_story_year' => $data['year'],
    'field_topics' => array_map(fn($tid) => ['target_id' => $tid], $data['topics']),
    'field_recipes' => array_map(fn($nid) => ['target_id' => $nid], $data['recipes']),
    'field_takeaways' => array_map(
      fn($html) => ['value' => $html, 'format' => 'basic_html'],
      $data['takeaways']
    ),
  ];

  $node = Node::create($values);
  $node->save();
  echo "created: {$node->id()}\t{$data['title']}\n";
  $created++;
}

echo "\n{$created} created, {$skipped} skipped.\n";
