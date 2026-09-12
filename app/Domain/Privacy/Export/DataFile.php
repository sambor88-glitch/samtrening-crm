<?php

namespace App\Domain\Privacy\Export;

/**
 * A finished export: what to call it and what is inside.
 */
readonly class DataFile
{
    public function __construct(
        public string $name,
        public string $contents,
    ) {}
}
