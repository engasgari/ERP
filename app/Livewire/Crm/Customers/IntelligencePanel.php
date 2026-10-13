<?php

namespace App\Livewire\Crm\Customers;

use App\Models\Crm\CustomerIntelligence;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Crm\CrmCustomerInsightService;
use App\Services\Crm\CustomerIntelligenceService;
use Livewire\Component;

class IntelligencePanel extends Component
{
    public int $partyId;

    public ?string $message = null;

    public ?string $messageType = null;

    public function mount(int $partyId): void
    {
        $this->partyId = $partyId;
    }

    public function requestSummary(CustomerIntelligenceService $service): void
    {
        try {
            $service->request($this->partyId, auth()->user());
            $this->flashMessage('درخواست تولید خلاصه هوشمند ثبت شد.', 'success');
        } catch (AiException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }

    public function review(int $intelligenceId, bool $accepted, CustomerIntelligenceService $service): void
    {
        $intelligence = CustomerIntelligence::query()
            ->where('party_id', $this->partyId)
            ->findOrFail($intelligenceId);

        try {
            $service->review($intelligence, auth()->user(), $accepted);
            $this->flashMessage($accepted ? 'خلاصه تأیید شد.' : 'خلاصه رد شد.', 'success');
        } catch (AiException $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }

    public function render(
        CrmCustomerRepository $customers,
        CrmCustomerInsightService $insights,
        CustomerIntelligenceService $service,
    ) {
        $user = auth()->user();
        $party = $customers->findForShow($this->partyId, $user);

        abort_if(! $party, 404);

        $intelligence = $service->latestFor($this->partyId, $user);

        return view('livewire.crm.customers.intelligence-panel', [
            'summary' => $insights->internalSummary($party),
            'intelligence' => $intelligence,
            'freshness' => $service->freshness($intelligence),
            'aiEnabled' => $service->isEnabled(),
            'canRequest' => $service->canRequest($user, $this->partyId),
            'isPending' => $intelligence?->status === CustomerIntelligence::STATUS_PENDING,
        ]);
    }

    private function flashMessage(string $message, string $type): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }
}
