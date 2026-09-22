<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Projects\Tasks;

use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\CustomFields;
use ControlAir\Intacct\ValueObjects\Decimal;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;

final readonly class Task
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?string $description,
        public ObjectReference $project,
        public ?ObjectReference $parent,
        public ?ObjectReference $customer,
        public ?ObjectReference $item,
        public ?LocalDate $plannedStartDate,
        public ?LocalDate $plannedEndDate,
        public ?LocalDate $actualStartDate,
        public ?LocalDate $actualEndDate,
        public ?Decimal $percentComplete,
        public ?Decimal $observedPercentComplete,
        public ?bool $isMilestone,
        public ?bool $isUtilized,
        public ?bool $isBillable,
        public ?string $wbsCode,
        public ?int $priority,
        public ?TaskStatus $status,
        public ?ObjectReference $timeType,
        public ?ObjectReference $class,
        public ?ObjectReference $standardTask,
        public ?string $href,
        public CustomFields $customFields = new CustomFields,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $project = ArrayReader::reference($data, 'project');
        $planned = ArrayReader::object($data['planned'] ?? null);
        $actual = ArrayReader::object($data['actual'] ?? null);
        $taskStatus = ArrayReader::string($data, 'taskStatus');

        if ($project === null) {
            throw new MappingException('A task response must include its project reference.');
        }

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::requiredString($data, 'name'),
            description: ArrayReader::string($data, 'description'),
            project: $project,
            parent: ArrayReader::reference($data, 'parent'),
            customer: ArrayReader::reference($data, 'customer'),
            item: ArrayReader::reference($data, 'item'),
            plannedStartDate: $planned === null ? null : ArrayReader::date($planned, 'startDate'),
            plannedEndDate: $planned === null ? null : ArrayReader::date($planned, 'endDate'),
            actualStartDate: $actual === null ? null : ArrayReader::date($actual, 'startDate'),
            actualEndDate: $actual === null ? null : ArrayReader::date($actual, 'endDate'),
            percentComplete: ArrayReader::decimal($data, 'percentComplete'),
            observedPercentComplete: ArrayReader::decimal($data, 'observedPercentComplete'),
            isMilestone: ArrayReader::bool($data, 'isMilestone'),
            isUtilized: ArrayReader::bool($data, 'isUtilized'),
            isBillable: ArrayReader::bool($data, 'isBillable'),
            wbsCode: ArrayReader::string($data, 'wbsCode'),
            priority: ArrayReader::int($data, 'priority'),
            status: $taskStatus === null ? null : new TaskStatus($taskStatus),
            timeType: ArrayReader::reference($data, 'timeType'),
            class: ArrayReader::reference($data, 'class'),
            standardTask: ArrayReader::reference($data, 'standardTask'),
            href: ArrayReader::string($data, 'href'),
            customFields: CustomFields::fromArray($data),
        );
    }
}
