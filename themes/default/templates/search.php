<?php
/**
 * Search results.
 *
 * The query is printed back, so it goes through e() like everything else — a
 * search box is the most reliably attacker-reachable input on a public site, and
 * reflecting it unescaped is the oldest way to put someone else's script on your
 * page.
 *
 * Snippets arrive as plain text and are escaped here. The highlight is added
 * after escaping, which is the only order that works: marking up first and
 * escaping second would escape the mark, and escaping by hand around a mark
 * inserted first is how off-by-one holes get written.
 *
 * @var string $query
 * @var list<\Pluck\Site\SearchResult> $results
 * @var \Pluck\Site\Urls $urls
 * @var \Pluck\View\View $view
 */

$highlight = static function (string $text, string $needle) use ($view): string {
	$escaped = e($text);
	if ($needle === '') {
		return $escaped;
	}

	// Both sides escaped, so the positions line up and nothing straddles an
	// entity boundary.
	$escapedNeedle = e($needle);
	$at = mb_stripos($escaped, $escapedNeedle);
	if ($at === false) {
		return $escaped;
	}

	return mb_substr($escaped, 0, $at)
		. '<mark>' . mb_substr($escaped, $at, mb_strlen($escapedNeedle)) . '</mark>'
		. mb_substr($escaped, $at + mb_strlen($escapedNeedle));
};
?>
<div class="search">
	<h1><?= $view->t('search.title') ?></h1>

	<form class="search-form" method="get" action="<?= e($urls->to('search')) ?>" role="search">
<?php if (!$urls->isPretty()): ?>
		<input type="hidden" name="page" value="search">
<?php endif; ?>
		<label for="q"><?= $view->t('search.label.what') ?></label>
		<input type="search" id="q" name="q" value="<?= e($query) ?>" maxlength="100" required>
		<button type="submit"><?= $view->t('search.action.search') ?></button>
	</form>

<?php if ($query === ''): ?>
	<p class="muted"><?= $view->t('search.help.type_something') ?></p>
<?php elseif (!\Pluck\Site\Search::isUsable($query)): ?>
	<p class="muted"><?= $view->t('search.help.too_short') ?></p>
<?php elseif ($results === []): ?>
	<p class="search-none"><?= $view->t('search.nothing_found', ['query' => $query]) ?></p>
<?php else: ?>
	<p class="muted"><?= $view->t('search.found', ['count' => count($results)], count($results)) ?></p>

	<ol class="search-results">
<?php foreach ($results as $result): ?>
		<li class="search-result">
			<h2><a href="<?= e($urls->to($result->path)) ?>"><?= $highlight($result->title, $query) ?></a></h2>
			<p class="search-result__kind"><?= $view->t($result->kindKey) ?></p>
<?php if ($result->snippet !== ''): ?>
			<p class="search-result__snippet"><?= $highlight($result->snippet, $query) ?></p>
<?php endif; ?>
		</li>
<?php endforeach; ?>
	</ol>
<?php endif; ?>
</div>
