<?php
/**
 * What this server gives Pluck, and who fixes what it does not.
 *
 * The third column is the point. "ext-intl: missing" tells a site owner nothing
 * they can act on; "ask your host for it, dates fall back to 2021-06-01 without
 * it" tells them whether to bother and what to write.
 *
 * @var array<string,list<array{label:string,value:string,state:string,advice:string,whose:string}>> $groups
 * @var string $phpinfoUrl
 * @var \Pluck\View\View $view
 */
?>
<div class="head">
	<h1><?= $view->t('diagnostics.title.diagnostics') ?></h1>
	<a class="btn-quiet" href="<?= e($phpinfoUrl) ?>" target="_blank" rel="noopener"><?= $view->t('diagnostics.action.phpinfo') ?></a>
</div>

<div class="card">
	<p class="muted"><?= $view->t('diagnostics.help.intro') ?></p>
</div>

<?php foreach ($groups as $group => $rows): ?>
<div class="card">
	<h2><?= $view->t($group) ?></h2>

	<table class="diagnostics">
		<tbody>
<?php foreach ($rows as $row): ?>
			<tr class="is-<?= e($row['state']) ?>">
				<th scope="row"><?= $view->t($row['label']) ?></th>
				<td class="diagnostics__value">
					<?= str_starts_with($row['value'], 'diagnostics.') ? $view->t($row['value']) : e($row['value']) ?>
				</td>
				<td class="diagnostics__advice">
<?php if ($row['advice'] !== ''): ?>
					<?= $view->t($row['advice']) ?>
					<span class="diagnostics__whose"><?= $view->t('diagnostics.whose.' . $row['whose']) ?></span>
<?php endif; ?>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endforeach; ?>
