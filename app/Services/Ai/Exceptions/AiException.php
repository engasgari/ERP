<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;
use Throwable;

class AiException extends RuntimeException
{
    public const DISABLED = 'disabled';

    public const NOT_CONFIGURED = 'not_configured';

    public const UNAUTHORIZED = 'unauthorized';

    public const RATE_LIMITED = 'rate_limited';

    public const BUDGET_EXCEEDED = 'budget_exceeded';

    public const COST_UNKNOWN = 'cost_unknown';

    public const DUPLICATE = 'duplicate_request';

    public const INPUT_TOO_LARGE = 'input_too_large';

    public const INVALID_INPUT = 'invalid_input';

    public const TIMEOUT = 'timeout';

    public const PROVIDER_RATE_LIMITED = 'provider_rate_limited';

    public const PROVIDER_ERROR = 'provider_error';

    public const INVALID_OUTPUT = 'invalid_output';

    private const RETRYABLE = [self::TIMEOUT, self::PROVIDER_RATE_LIMITED, self::PROVIDER_ERROR];

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        ?Throwable $previous = null,
        /** @var list<string> */
        public readonly array $details = [],
    ) {
        parent::__construct($message !== '' ? $message : $errorCode, 0, $previous);
    }

    public static function make(string $code, string $message = '', array $details = []): self
    {
        return new self($code, $message, null, $details);
    }

    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE, true);
    }

    /** Whether the failure happened before any provider call (nothing to log as a provider error). */
    public function isPreflight(): bool
    {
        return in_array($this->errorCode, [
            self::DISABLED, self::NOT_CONFIGURED, self::UNAUTHORIZED, self::RATE_LIMITED,
            self::BUDGET_EXCEEDED, self::COST_UNKNOWN, self::DUPLICATE, self::INPUT_TOO_LARGE,
        ], true);
    }
}
