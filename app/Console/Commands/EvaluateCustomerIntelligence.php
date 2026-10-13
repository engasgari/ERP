<?php

namespace App\Console\Commands;

use App\Models\Crm\CustomerIntelligence;
use App\Services\Ai\Evaluation\CustomerIntelligenceEvaluator;
use Illuminate\Console\Command;

/**
 * Read-only: compares the latest stored summary of each listed customer with a human reference file.
 * Does not call any AI provider and does not modify data.
 */
class EvaluateCustomerIntelligence extends Command
{
    protected $signature = 'crm-ai:evaluate-customer-intelligence {file : JSON file with reference cases (keep outside the repository)}';

    protected $description = 'Score stored Customer Intelligence summaries against a human-prepared reference set';

    public function handle(CustomerIntelligenceEvaluator $evaluator): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error('Reference file not found.');

            return self::FAILURE;
        }

        $cases = json_decode((string) file_get_contents($path), true);

        if (! is_array($cases) || ! isset($cases['cases']) || ! is_array($cases['cases'])) {
            $this->error('Invalid reference file: expected {"cases": [...]}.');

            return self::FAILURE;
        }

        $rows = [];
        $scores = [];

        foreach ($cases['cases'] as $case) {
            $partyId = (int) ($case['party_id'] ?? 0);
            $intelligence = CustomerIntelligence::query()
                ->where('party_id', $partyId)
                ->whereIn('status', [CustomerIntelligence::STATUS_COMPLETED, CustomerIntelligence::STATUS_INSUFFICIENT])
                ->with('verifiedSources')
                ->latest('id')
                ->first();

            $score = $evaluator->score(
                $case['reference'] ?? [],
                $intelligence?->result,
                $intelligence?->verifiedSources->pluck('url')->all() ?? [],
            );

            $scores[] = $score;
            $rows[] = array_merge(['party_id' => $partyId], array_map(
                fn ($v) => $v === null ? '-' : ($v ? 'yes' : 'no'),
                $score,
            ));
        }

        if ($rows !== []) {
            $this->table(array_keys($rows[0]), $rows);
        }

        $summary = [];
        foreach ($evaluator->aggregate($scores) as $metric => $t) {
            $summary[] = [$metric, $t['passed'].'/'.$t['total']];
        }
        $this->table(['metric', 'passed/total'], $summary);

        return self::SUCCESS;
    }
}
