<?php declare(strict_types=1);
namespace ERTAppointment\Domain\Provider;

final class Provider
{
    public function __construct(
        public readonly ?int   $id,
        public readonly ?int   $departmentId,
        public readonly string $type,          // individual | unit
        public readonly string $name,
        public readonly string $email,
        public readonly string $phone,
        public readonly string $description,
        public readonly string $avatarUrl,
        public readonly string $status,
        public readonly int    $sortOrder,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:           (int) $row['id'],
            departmentId: isset($row['department_id']) ? (int) $row['department_id'] : null,
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

    public function toArray(): array
    {
        return [
            'department_id' => $this->departmentId,
            'type'          => $this->type,
            'name'          => $this->name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'description'   => $this->description,
            'avatar_url'    => $this->avatarUrl,
            'status'        => $this->status,
            'sort_order'    => $this->sortOrder,
        ];
    }
}
