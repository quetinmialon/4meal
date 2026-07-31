<?php

namespace App\Exceptions;

use RuntimeException;

final class ImportException extends RuntimeException
{
    /** @param list<array{path: string, code: string, message: string}> $errors */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        public readonly string $errorCode = 'import_invalid',
    ) {
        parent::__construct($message);
    }
}
