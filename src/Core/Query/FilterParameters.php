<?php

declare(strict_types=1);

namespace ControlAir\Intacct\Core\Query;

use ControlAir\Intacct\ValueObjects\LocalDate;

final readonly class FilterParameters
{
    public function __construct(
        public ?LocalDate $asOfDate = null,
        public bool $includeHierarchyFields = false,
        public bool $caseSensitiveComparison = true,
        public bool $includePrivate = false,
    ) {}

    /** @return array<string, bool|string> */
    public function toArray(): array
    {
        $values = [
            'includeHierarchyFields' => $this->includeHierarchyFields,
            'caseSensitiveComparison' => $this->caseSensitiveComparison,
            'includePrivate' => $this->includePrivate,
        ];

        if ($this->asOfDate !== null) {
            $values['asOfDate'] = $this->asOfDate->value;
        }

        return $values;
    }
}
