<?php
declare(strict_types=1);

namespace Pluck\Module;

use Pluck\Storage\StorageDriver;

/**
 * A module that can say what there is to insert from it.
 *
 * The editor's Pluck menu lists what a page can hold. Without this it could only
 * offer `[module:blog]` — the bare marker — because the alternatives are things
 * only the module knows: that a blog can be shown as titles or as summaries,
 * that this site's albums are called "Open dag" and "Kerst 2019".
 *
 * The other way round is what this exists to avoid. An editor that knows blog
 * takes `show=summary` is an editor carrying a copy of the blog's parameters,
 * and the two would drift the first time either was touched — six faults this
 * year had exactly that shape.
 *
 * Optional. A module that does not implement it is offered as `[module:name]`,
 * which is what every module got before this existed.
 */
interface Insertable
{
	/**
	 * The things a page can hold from this module.
	 *
	 * Each is a label somebody reads and the marker it inserts. Order is the
	 * order they appear in; the most ordinary first, because that is the one
	 * being looked for.
	 *
	 * Given storage because the useful answers are usually about content: an
	 * album module lists this site's albums, not the idea of albums.
	 *
	 * ## Something the writer has to fill in
	 *
	 * Some markers cannot be complete — a video needs the address of a video, and
	 * only the person inserting it knows which. Put a placeholder in the marker
	 * and name it in `select`:
	 *
	 *     ['label' => 'Video', 'marker' => '[module:video id=VIDEO_ID]',
	 *      'select' => 'VIDEO_ID']
	 *
	 * The editor selects that text after inserting, so the next thing typed
	 * replaces it. Without it somebody has to find the placeholder, select it by
	 * hand, and know it was a placeholder at all — and a marker left with
	 * VIDEO_ID in it renders as an error on the live page.
	 *
	 * `select` is optional and matched literally, first occurrence.
	 *
	 * @return list<array{label:string,marker:string,select?:string}>
	 */
	public function embedOptions(StorageDriver $storage): array;
}
