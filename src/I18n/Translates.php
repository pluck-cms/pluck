<?php
declare(strict_types=1);

namespace Pluck\I18n;

/**
 * Wording, for a class that has a translator and might not.
 *
 * Four classes carried this method: two modules, the form guard and the contact
 * module. Three lines each, and they had already drifted — two took a plural
 * count and two did not, so a module that wanted "3 reacties" had to know which
 * kind of class it was in.
 *
 * The falling back to the key rather than to an empty string is the part worth
 * keeping deliberate. A screen showing `blog.reaction.name` is a bug report; a
 * screen showing nothing is a mystery, and somebody has to guess which label is
 * missing before they can even say what is wrong.
 */
trait Translates
{
	/*
	 * The property is not declared here on purpose.
	 *
	 * Every class that uses this already has it as a promoted constructor
	 * parameter, and a trait declaring the same name — with or without readonly —
	 * is a fatal conflict rather than a merge. The trait supplies the method; the
	 * class supplies the translator.
	 */

	/**
	 * @param array<string,string|int> $replacements
	 */
	private function t(string $key, array $replacements = [], ?int $count = null): string
	{
		return $this->translator?->get($key, $replacements, $count) ?? $key;
	}
}
