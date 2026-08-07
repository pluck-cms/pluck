<?php
declare(strict_types=1);

namespace Pluck\Admin;

/**
 * The address the settings screen asks for to find out whether rewriting works.
 *
 * A reserved path rather than a query parameter, because the query form works
 * whether or not rewriting does and would therefore always answer yes. Only a
 * request that arrived as `/pluck-rewrite-probe` and still reached index.php
 * proves anything.
 *
 * index.php answers it before it touches storage or a theme, so the probe stays
 * true on an install whose content or theme is broken — which is exactly when
 * someone is likely to be in the settings screen changing things.
 *
 * The marker is deliberately not a word anyone would write by accident, so a
 * server that answers every unknown path with a friendly 200 page cannot pass by
 * coincidence.
 */
final class Probe
{
	public const PATH = 'pluck-rewrite-probe';

	public const MARKER = 'pluck-rewrite-probe-ok';
}
