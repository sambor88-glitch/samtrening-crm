<?php

namespace App\Domain\Billing\Export;

/**
 * A finished file: what to call it, what is in it and how many rows it carries.
 */
readonly class CsvFile
{
    public function __construct(
        public string $name,
        public string $contents,
        public int $rows,
    ) {}
}
