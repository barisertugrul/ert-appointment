<?php declare(strict_types=1);
namespace ERTAppointment\Domain\Department;

final class Department
{
    public function __construct(
        public readonly ?int   $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $description,
        public readonly string $status,
        public readonly int    $sortOrder,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:          (int) $row['id'],
            name:        $row['name'],
            slug:        $row['slug'],
            description: $row['description'] ?? '',
            status:      $row['status'],
            sortOrder:   (int) $row['sort_order'],
        );
    }

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'status'      => $this->status,
            'sort_order'  => $this->sortOrder,
        ];
    }
}
