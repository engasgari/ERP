<?php

namespace App\Core\Base;

abstract class BaseAction
{
    abstract public function execute(...$arguments);
}