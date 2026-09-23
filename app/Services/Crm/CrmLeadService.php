<?php

namespace App\Services\Crm;

use App\Models\Crm\Activity;
use App\Models\Crm\Attachment;
use App\Models\Crm\Lead;
use App\Models\Crm\Note;
use App\Models\Crm\Task;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CrmLeadService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly CrmAuditService $audit,
        private readonly CrmDuplicateGuardService $duplicates,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): Lead
    {
        $this->duplicates->assertUniqueLead($data);

        $lead = Lead::create([
            'number' => $this->numbering->next('crm_lead', 'LD-'),
            'title' => $data['title'],
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'status' => $data['status'] ?? 'new',
            'rating' => $data['rating'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? $actor->id,
            'description' => $data['description'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? null,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'party_id' => $data['party_id'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log($lead, 'created', null, $lead->only(['title', 'status']), $lead->party_id);

        return $lead;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Lead $lead, array $data, User $actor): Lead
    {
        $this->duplicates->assertUniqueLead($data, $lead->id);

        $old = $lead->only(['title', 'status', 'assigned_user_id']);
        $lead->update(collect($data)->only([
            'title', 'first_name', 'last_name', 'company_name', 'phone', 'mobile', 'email',
            'source_id', 'status', 'rating', 'assigned_user_id', 'description',
            'estimated_value', 'expected_close_date', 'lost_reason', 'party_id',
        ])->merge(['updated_by' => $actor->id])->all());

        $this->audit->log($lead, 'updated', $old, $lead->only(['title', 'status', 'assigned_user_id']), $lead->party_id);

        return $lead->fresh(['source', 'assignedUser']);
    }

    public function delete(Lead $lead, User $actor): void
    {
        if ($lead->status === 'converted') {
            throw new \InvalidArgumentException('سرنخ تبدیل‌شده قابل حذف نیست.');
        }

        DB::transaction(function () use ($lead, $actor) {
            $this->audit->log(
                $lead,
                'deleted',
                $lead->only(['number', 'title', 'status']),
                null,
                $lead->party_id,
            );

            Task::query()
                ->where('taskable_type', Lead::class)
                ->where('taskable_id', $lead->id)
                ->delete();

            Activity::query()
                ->where('activitable_type', Lead::class)
                ->where('activitable_id', $lead->id)
                ->delete();

            Note::query()
                ->where('notable_type', Lead::class)
                ->where('notable_id', $lead->id)
                ->delete();

            $lead->attachments()->each(function (Attachment $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
                $attachment->delete();
            });

            $lead->update(['updated_by' => $actor->id]);
            $lead->delete();
        });
    }
}
