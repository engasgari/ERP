<?php

namespace App\Core\Support;

final readonly class Result
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $message = null,
        public array $errors = []
    ) {}

    public static function success(
        mixed $data = null,
        ?string $message = null
    ): self {
        return new self(true, $data, $message);
    }

    public static function failure(
        string $message,
        array $errors = []
    ): self {
        return new self(false, null, $message, $errors);
    }
}