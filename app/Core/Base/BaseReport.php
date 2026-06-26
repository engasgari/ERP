<?php

namespace App\Core\Base;

abstract class BaseReport
{
    abstract public function handle(array $filters = []): array;
}