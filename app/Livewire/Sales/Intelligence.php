<?php

namespace App\Livewire\Sales;

use App\Services\AISalesAnalyticsService;
use App\Services\SalesDashboardService;
use Livewire\Component;

class Intelligence extends Component
{
    public string $question = '';

    public ?string $answer = null;

    public function ask(AISalesAnalyticsService $ai, SalesDashboardService $dashboard): void
    {
        $user = auth()->user();
        $payload = $dashboard->build($user);
        $result = $ai->answerQuestion($this->question, $payload);
        $this->answer = $result['answer'];
    }

    public function render(SalesDashboardService $dashboard)
    {
        $user = auth()->user();

        return view('livewire.sales.intelligence', [
            'dashboard' => $dashboard->build($user),
        ]);
    }
}
