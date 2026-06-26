<?php

namespace App\Core\Base;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseService
{
    protected function transaction(callable $callback)
    {
        return DB::transaction($callback);
    }

    protected function log(string $message, array $context = []): void
    {
        Log::info(static::class . ' : ' . $message, $context);
    }
}