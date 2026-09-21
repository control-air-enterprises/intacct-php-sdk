<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\CompanyConfiguration\Users;

use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class User
{
    /**
     * @param  list<ObjectReference>  $locations
     * @param  list<ObjectReference>  $departments
     * @param  list<ObjectReference>  $roles
     */
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $userName,
        public ?string $accountEmail,
        public ?string $adminPrivileges,
        public ?string $userType,
        public ?bool $webServicesEnabled,
        public ?bool $webServicesRestricted,
        public ?RecordStatus $status,
        public ?ObjectReference $contact,
        public ?ObjectReference $entity,
        public array $locations,
        public array $departments,
        public array $roles,
        public ?string $href,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = ArrayReader::string($data, 'status');
        $webServices = ArrayReader::object($data['webServices'] ?? null);

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            userName: ArrayReader::requiredString($data, 'userName'),
            accountEmail: ArrayReader::string($data, 'accountEmail'),
            adminPrivileges: ArrayReader::string($data, 'adminPrivileges'),
            userType: ArrayReader::string($data, 'userType'),
            webServicesEnabled: $webServices === null ? null : ArrayReader::bool($webServices, 'isEnabled'),
            webServicesRestricted: $webServices === null ? null : ArrayReader::bool($webServices, 'isRestricted'),
            status: $status === null ? null : RecordStatus::tryFrom($status),
            contact: ArrayReader::reference($data, 'contact'),
            entity: ArrayReader::reference($data, 'entity'),
            locations: ArrayReader::references($data, 'locations'),
            departments: ArrayReader::references($data, 'departments'),
            roles: ArrayReader::references($data, 'roles'),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
