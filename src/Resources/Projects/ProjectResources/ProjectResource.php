<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects\ProjectResources;

use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class ProjectResource
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public ObjectReference $project,
        public ?ObjectReference $employee,
        public ?ObjectReference $employeeContact,
        public ?ObjectReference $item,
        public ?string $description,
        public ?LocalDate $startDate,
        public ?ProjectResourcePricing $pricing,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $project = ArrayReader::reference($data, 'project');
        $pricing = ArrayReader::object($data['pricing'] ?? null);

        if ($project === null) {
            throw new MappingException('A project resource response must include its project reference.');
        }

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            project: $project,
            employee: ArrayReader::reference($data, 'employee'),
            employeeContact: ArrayReader::reference($data, 'employeeContact'),
            item: ArrayReader::reference($data, 'item'),
            description: ArrayReader::string($data, 'description'),
            startDate: ArrayReader::date($data, 'startDate'),
            pricing: $pricing === null ? null : ProjectResourcePricing::fromArray($pricing),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
