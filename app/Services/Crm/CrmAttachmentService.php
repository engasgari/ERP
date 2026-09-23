<?php

namespace App\Services\Crm;

use App\Models\Crm\Attachment;
use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Models\User;
use App\Repositories\Crm\CrmLeadRepository;
use App\Repositories\Crm\CrmOpportunityRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmAttachmentService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/pjpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/octet-stream',
    ];

    /** @var array<string, string> */
    private const EXTENSION_MIMES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'csv' => 'text/csv',
        'txt' => 'text/plain',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(
        private readonly CrmLeadRepository $leads,
        private readonly CrmOpportunityRepository $opportunities,
    ) {}

    public function storeForLead(Lead $lead, UploadedFile $file, User $actor): Attachment
    {
        $this->assertCanManageLead($lead, $actor);

        return $this->persistUpload(
            attachableType: Lead::class,
            attachableId: $lead->id,
            partyId: $lead->party_id,
            storageSegment: "crm/leads/{$lead->id}",
            file: $file,
            actor: $actor,
        );
    }

    public function storeForOpportunity(Opportunity $opportunity, UploadedFile $file, User $actor): Attachment
    {
        $this->assertCanManageOpportunity($opportunity, $actor);

        return $this->persistUpload(
            attachableType: Opportunity::class,
            attachableId: $opportunity->id,
            partyId: $opportunity->party_id,
            storageSegment: "crm/opportunities/{$opportunity->id}",
            file: $file,
            actor: $actor,
        );
    }

    public function delete(Attachment $attachment, User $actor): void
    {
        $this->assertCanViewAttachment($attachment, $actor);
        $this->assertCanManageAttachment($attachment, $actor);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    public function downloadResponse(Attachment $attachment, User $actor): StreamedResponse
    {
        $this->assertCanViewAttachment($attachment, $actor);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response(
            $attachment->path,
            $attachment->original_name ?: basename($attachment->path),
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream'],
        );
    }

    private function persistUpload(
        string $attachableType,
        int $attachableId,
        ?int $partyId,
        string $storageSegment,
        UploadedFile $file,
        User $actor,
    ): Attachment {
        $size = (int) $file->getSize();

        if ($size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('حداکثر حجم فایل ۲ مگابایت است.');
        }

        $mime = $this->resolveMimeType($file);

        if ($mime === null) {
            throw new \InvalidArgumentException('فرمت فایل مجاز نیست. PDF، تصویر، Excel یا Word آپلود کنید.');
        }

        $safeBase = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $storedName = $safeBase.'-'.Str::uuid()->toString().($extension ? '.'.$extension : '');
        $path = $file->storeAs($storageSegment, $storedName, 'local');

        if ($path === false) {
            throw new \RuntimeException('ذخیره فایل روی سرور انجام نشد.');
        }

        return Attachment::query()->create([
            'attachable_type' => $attachableType,
            'attachable_id' => $attachableId,
            'party_id' => $partyId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size' => $size,
            'created_by' => $actor->id,
        ]);
    }

    private function assertCanViewAttachment(Attachment $attachment, User $actor): void
    {
        if ($attachment->attachable_type === Lead::class) {
            abort_unless($this->leads->findForShow((int) $attachment->attachable_id, $actor), 403);

            return;
        }

        if ($attachment->attachable_type === Opportunity::class) {
            abort_unless($this->opportunities->findAccessible((int) $attachment->attachable_id, $actor), 403);

            return;
        }

        abort(404);
    }

    private function assertCanManageAttachment(Attachment $attachment, User $actor): void
    {
        if ($attachment->attachable_type === Lead::class) {
            if (! $actor->hasPermission('crm.leads.update') && ! $actor->hasPermission('crm.leads.create')) {
                throw new \InvalidArgumentException('مجوز حذف پیوست را ندارید.');
            }

            return;
        }

        if ($attachment->attachable_type === Opportunity::class) {
            if (! $actor->hasPermission('crm.opportunities.update') && ! $actor->hasPermission('crm.opportunities.create')) {
                throw new \InvalidArgumentException('مجوز حذف پیوست را ندارید.');
            }

            return;
        }

        abort(404);
    }

    private function assertCanManageLead(Lead $lead, User $actor): void
    {
        if (! $this->leads->findForShow($lead->id, $actor)) {
            throw new \InvalidArgumentException('به این سرنخ دسترسی ندارید.');
        }

        if (! $actor->hasPermission('crm.leads.update') && ! $actor->hasPermission('crm.leads.create')) {
            throw new \InvalidArgumentException('مجوز آپلود فایل را ندارید.');
        }
    }

    private function assertCanManageOpportunity(Opportunity $opportunity, User $actor): void
    {
        if (! $this->opportunities->findAccessible($opportunity->id, $actor)) {
            throw new \InvalidArgumentException('به این فرصت دسترسی ندارید.');
        }

        if (! $actor->hasPermission('crm.opportunities.update') && ! $actor->hasPermission('crm.opportunities.create')) {
            throw new \InvalidArgumentException('مجوز آپلود فایل را ندارید.');
        }
    }

    private function resolveMimeType(UploadedFile $file): ?string
    {
        $detected = strtolower((string) ($file->getMimeType() ?: ''));
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($detected !== '' && in_array($detected, self::ALLOWED_MIMES, true) && $detected !== 'application/octet-stream') {
            return $detected;
        }

        if ($extension !== '' && isset(self::EXTENSION_MIMES[$extension])) {
            return self::EXTENSION_MIMES[$extension];
        }

        return null;
    }
}
