<?php

namespace App\Livewire\Crm\Concerns;

use App\Models\Crm\Attachment;
use App\Services\Crm\CrmAttachmentService;

trait ManagesCrmAttachments
{
    public function deleteCrmAttachment(int $attachmentId, CrmAttachmentService $attachments): void
    {
        $attachment = Attachment::query()->findOrFail($attachmentId);

        try {
            $attachments->delete($attachment, auth()->user());
            session()->flash('success', 'پیوست حذف شد.');
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Throwable) {
            session()->flash('error', 'حذف پیوست انجام نشد.');
        }
    }
}
