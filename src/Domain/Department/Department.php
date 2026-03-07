<?php declare(strict_types=1);
namespace ERTAppointment\Domain\Department;

final class Department {

	public function __construct(
		public readonly ?int $id,
		public readonly string $name,
		public readonly string $slug,
		public readonly string $description,
		public readonly string $status,
		public readonly int $sortOrder,
	) {}

	public static function fromRow( array $row ): self {
		return new self(
			id:          (int) $row['id'],
			name:        $row['name'],
			slug:        $row['slug'],
			description: $row['description'] ?? '',
			status:      $row['status'],
			sortOrder:   (int) $row['sort_order'],
		);
	}

	public static function create(
		string $name,
		string $slug,
		string $description = '',
		string $status = 'active',
		int $sortOrder = 0,
	): self {
		return new self(
			id: null,
			name: $name,
			slug: $slug,
			description: $description,
			status: $status,
			sortOrder: $sortOrder,
		);
	}

	/**
	 * @param array<string, mixed> $changes
	 */
	public function with( array $changes ): self {
		return new self(
			id: $this->id,
			name: isset( $changes['name'] ) ? (string) $changes['name'] : $this->name,
			slug: isset( $changes['slug'] ) ? (string) $changes['slug'] : $this->slug,
			description: isset( $changes['description'] ) ? (string) $changes['description'] : $this->description,
			status: isset( $changes['status'] ) ? (string) $changes['status'] : $this->status,
			sortOrder: isset( $changes['sort_order'] ) ? (int) $changes['sort_order'] : $this->sortOrder,
		);
	}

	public function toArray(): array {
		return array(
			'name'        => $this->name,
			'slug'        => $this->slug,
			'description' => $this->description,
			'status'      => $this->status,
			'sort_order'  => $this->sortOrder,
		);
	}
}
