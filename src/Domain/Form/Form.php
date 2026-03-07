<?php declare(strict_types=1);
namespace ERTAppointment\Domain\Form;

final class Form {

	public function __construct(
		public readonly ?int $id,
		public readonly string $scope,
		public readonly ?int $scopeId,
		public readonly string $name,
		public readonly array $fields,
		public readonly bool $isActive,
	) {}

	public static function fromRow( array $row ): self {
		return new self(
			id:       (int) $row['id'],
			scope:    $row['scope'],
			scopeId:  isset( $row['scope_id'] ) ? (int) $row['scope_id'] : null,
			name:     $row['name'],
			fields:   json_decode( $row['fields'] ?? '[]', true ) ?? array(),
			isActive: (bool) $row['is_active'],
		);
	}

	public function toArray(): array {
		return array(
			'scope'     => $this->scope,
			'scope_id'  => $this->scopeId,
			'name'      => $this->name,
			'fields'    => wp_json_encode( $this->fields ),
			'is_active' => $this->isActive ? 1 : 0,
		);
	}
}
