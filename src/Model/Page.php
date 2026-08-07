<?php
declare(strict_types=1);

namespace Pluck\Model;

/**
 * A page. Content is inert data in v5: it is never a PHP file that gets
 * included, which removes the whole "write a page, get code execution" class of
 * bug that Pluck 4 lived with.
 */
final class Page
{
	/**
	 * @param array<string,mixed> $moduleData Replaces Pluck 4's opaque
	 *        $module_additional_data string with structured, named data.
	 */
	public function __construct(
		public string $path,
		public string $title,
		public string $content = '',
		public bool $hidden = false,
		public int $order = 0,
		public string $description = '',
		public string $keywords = '',
		public ?string $module = null,
		public array $moduleData = [],
		public ?string $theme = null,
		public ?string $authorId = null,
		public ?string $createdAt = null,
		public ?string $updatedAt = null,
	) {
		$this->createdAt ??= gmdate('c');
		$this->updatedAt ??= $this->createdAt;
	}

	public function slug(): string
	{
		$position = strrpos($this->path, '/');

		return $position === false ? $this->path : substr($this->path, $position + 1);
	}

	public function parent(): ?string
	{
		$position = strrpos($this->path, '/');

		return $position === false ? null : substr($this->path, 0, $position);
	}

	public function depth(): int
	{
		return $this->path === '' ? 0 : substr_count($this->path, '/') + 1;
	}

	public function touch(): void
	{
		$this->updatedAt = gmdate('c');
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return [
			'schema' => 1,
			'path' => $this->path,
			'title' => $this->title,
			'hidden' => $this->hidden,
			'order' => $this->order,
			'description' => $this->description,
			'keywords' => $this->keywords,
			'module' => $this->module,
			'module_data' => $this->moduleData,
			'theme' => $this->theme,
			'author_id' => $this->authorId,
			'created_at' => $this->createdAt,
			'updated_at' => $this->updatedAt,
			'content' => $this->content,
		];
	}

	/** @param array<string,mixed> $row */
	public static function fromArray(array $row): self
	{
		$moduleData = $row['module_data'] ?? [];
		if (is_string($moduleData)) {
			$decoded = json_decode($moduleData, true);
			$moduleData = is_array($decoded) ? $decoded : [];
		}

		return new self(
			path: (string) ($row['path'] ?? ''),
			title: (string) ($row['title'] ?? ''),
			content: (string) ($row['content'] ?? ''),
			hidden: (bool) ($row['hidden'] ?? false),
			order: (int) ($row['order'] ?? 0),
			description: (string) ($row['description'] ?? ''),
			keywords: (string) ($row['keywords'] ?? ''),
			module: isset($row['module']) && $row['module'] !== '' ? (string) $row['module'] : null,
			moduleData: is_array($moduleData) ? $moduleData : [],
			theme: isset($row['theme']) && $row['theme'] !== '' ? (string) $row['theme'] : null,
			authorId: isset($row['author_id']) && $row['author_id'] !== '' ? (string) $row['author_id'] : null,
			createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
			updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
		);
	}
}
