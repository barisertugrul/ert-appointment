<?php declare(strict_types=1);
namespace ERTAppointment\Domain\Provider;

final class Provider {

	public function __construct(
		public readonly ?int $id,
		public readonly ?int $departmentId,
		public readonly string $type,          // individual | unit
		public readonly string $name,
		public readonly string $email,
		public readonly string $phone,
		public readonly string $description,
		public readonly string $avatarUrl,
		public readonly string $status,
		public readonly int $sortOrder,
	) {}

	public static function fromRow( array $row ): self {
		return new self(
			id:           (int) $row['id'],
			departmentId: isset( $row['department_id'] ) ? (int) $row['department_id'] : null,
			type:         $row['type'],
			name:         $row['name'],
			email:        $row['email'] ?? '',
			phone:        $row['phone'] ?? '',
			description:  $row['description'] ?? '',
			avatarUrl:    $row['avatar_url'] ?? '',
			status:       $row['status'],
			sortOrder:    (int) $row['sort_order'],
		);
	}

	public static function create(
		?int $departmentId,
		string $type,
		string $name,
		string $email = '',
		string $phone = '',
		string $description = '',
		string $status = 'active',
		int $sortOrder = 0,
	): self {
		return new self(
			id: null,
			departmentId: $departmentId,
			type: $type,
			name: $name,
			email: $email,
			phone: $phone,
			description: $description,
			avatarUrl: '',
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
			departmentId: array_key_exists( 'department_id', $changes ) ? ( $changes['department_id'] !== null ? (int) $changes['department_id'] : null ) : $this->departmentId,
			type: isset( $changes['type'] ) ? (string) $changes['type'] : $this->type,
			name: isset( $changes['name'] ) ? (string) $changes['name'] : $this->name,
			email: isset( $changes['email'] ) ? (string) $changes['email'] : $this->email,
			phone: isset( $changes['phone'] ) ? (string) $changes['phone'] : $this->phone,
			description: isset( $changes['description'] ) ? (string) $changes['description'] : $this->description,
			avatarUrl: isset( $changes['avatar_url'] ) ? (string) $changes['avatar_url'] : $this->avatarUrl,
			status: isset( $changes['status'] ) ? (string) $changes['status'] : $this->status,
			sortOrder: isset( $changes['sort_order'] ) ? (int) $changes['sort_order'] : $this->sortOrder,
		);
	}

	public function toArray(): array {
		return array(
			'department_id' => $this->departmentId,
			'type'          => $this->type,
			'name'          => $this->name,
			'email'         => $this->email,
			'phone'         => $this->phone,
			'description'   => $this->description,
			'avatar_url'    => $this->avatarUrl,
			'status'        => $this->status,
			'sort_order'    => $this->sortOrder,
		);
	}
}
