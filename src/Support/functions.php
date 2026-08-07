<?php
declare(strict_types=1);

/*
 * Two template shorthands, and nothing else.
 *
 * Guarded with function_exists because themes and modules are third-party code
 * that may already define a helper by the same name; Pluck loading first must
 * not be the reason someone's theme fatals.
 */

if (!function_exists('e')) {
	/** Escape for HTML text and double-quoted attribute values. */
	function e(mixed $value): string
	{
		return \Pluck\Security\Escaper::html(is_scalar($value) || $value === null ? (string) $value : '');
	}
}

if (!function_exists('attr')) {
	/** Render an attribute only when it has a value: attr('value', $x) */
	function attr(string $name, mixed $value): string
	{
		$value = is_scalar($value) ? (string) $value : '';

		return $value === '' ? '' : ' ' . $name . '="' . e($value) . '"';
	}
}
