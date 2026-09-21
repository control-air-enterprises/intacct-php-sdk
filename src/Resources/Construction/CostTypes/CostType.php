<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Resources\Construction\CostTypes;

use ControlAir\Intacct\Exceptions\MappingException;
use ControlAir\Intacct\Support\ArrayReader;
use ControlAir\Intacct\ValueObjects\LocalDate;
use ControlAir\Intacct\ValueObjects\ObjectId;
use ControlAir\Intacct\ValueObjects\ObjectKey;
use ControlAir\Intacct\ValueObjects\ObjectReference;
use ControlAir\Intacct\ValueObjects\RecordStatus;

final readonly class CostType
{
    public function __construct(
        public ObjectKey $key,
        public ObjectId $id,
        public string $name,
        public ?string $description,
        public ObjectReference $project,
        public ObjectReference $task,
        public ?RecordStatus $status,
        public ?string $costUnitDescription,
        public ?ObjectReference $accumulationType,
        public ?ObjectReference $glAccount,
        public ?ObjectReference $parent,
        public ?ObjectReference $item,
        public ?ObjectReference $root,
        public ?ObjectReference $standardCostType,
        public ?LocalDate $plannedStartDate,
        public ?LocalDate $plannedEndDate,
        public ?LocalDate $actualStartDate,
        public ?LocalDate $actualEndDate,
        public ?string $href,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $project = ArrayReader::reference($data, 'project');
        $task = ArrayReader::reference($data, 'task');

        if ($project === null || $task === null) {
            throw new MappingException('A cost type response must include project and task references.');
        }

        $planned = ArrayReader::object($data['planned'] ?? null);
        $actual = ArrayReader::object($data['actual'] ?? null);
        $status = ArrayReader::string($data, 'status');

        return new self(
            key: ArrayReader::key($data),
            id: ArrayReader::id($data),
            name: ArrayReader::requiredString($data, 'name'),
            description: ArrayReader::string($data, 'description'),
            project: $project,
            task: $task,
            status: $status === null ? null : RecordStatus::tryFrom($status),
            costUnitDescription: ArrayReader::string($data, 'costUnitDescription'),
            accumulationType: ArrayReader::reference($data, 'accumulationType'),
            glAccount: ArrayReader::reference($data, 'glAccount'),
            parent: ArrayReader::reference($data, 'parent'),
            item: ArrayReader::reference($data, 'item'),
            root: ArrayReader::reference($data, 'root'),
            standardCostType: ArrayReader::reference($data, 'standardCostType'),
            plannedStartDate: $planned === null ? null : ArrayReader::date($planned, 'startDate'),
            plannedEndDate: $planned === null ? null : ArrayReader::date($planned, 'endDate'),
            actualStartDate: $actual === null ? null : ArrayReader::date($actual, 'startDate'),
            actualEndDate: $actual === null ? null : ArrayReader::date($actual, 'endDate'),
            href: ArrayReader::string($data, 'href'),
        );
    }
}
