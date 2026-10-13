<?php

namespace App\Services\Ai\WebSearch;

use Illuminate\Support\Carbon;

final class FetchedSource
{
    public const OK = 'ok';

    public const INVALID_URL = 'invalid_url';

    public const BLOCKED_HOST = 'blocked_host';

    public const HTTP_ERROR = 'http_error';

    public const REDIRECT = 'redirect';

    public const UNSUPPORTED_CONTENT = 'unsupported_content';

    public const FAILED = 'failed';

    public function __construct(
        public readonly string $url,
        public readonly string $domain,
        public readonly string $fetchStatus,
        public readonly ?int $httpStatus,
        public readonly ?string $title,
        public readonly ?string $text,
        public readonly Carbon $checkedAt,
        public readonly string $sourceType = 'web',
    ) {}

    public function isVerified(): bool
    {
        return $this->fetchStatus === self::OK;
    }
}
