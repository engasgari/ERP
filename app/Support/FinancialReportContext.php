<?php

namespace App\Support;

class FinancialReportContext
{
    public function __construct(
        public readonly string $reportKey
    ) {
    }
}
