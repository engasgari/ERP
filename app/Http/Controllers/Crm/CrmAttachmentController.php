<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Attachment;
use App\Models\Crm\Lead;
use App\Models\Crm\Opportunity;
use App\Repositories\Crm\CrmLeadRepository;
use App\Repositories\Crm\CrmOpportunityRepository;
use App\Services\Crm\CrmAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmAttachmentController extends Controller
{
    public function storeForLead(
        Request $request,
        Lead $lead,
        CrmAttachmentService $attachments,
        CrmLeadRepository $leads,
    ): JsonResponse {
        abort_unless($leads->findForShow($lead->id, auth()->user()), 404);

        return $this->storeUploadedFile($request, fn () => $attachments->storeForLead($lead, $this->validatedFile($request), auth()->user()));
    }

    public function storeForOpportunity(
        Request $request,
        Opportunity $opportunity,
        CrmAttachmentService $attachments,
        CrmOpportunityRepository $opportunities,
    ): JsonResponse {
        abort_unless($opportunities->findAccessible($opportunity->id, auth()->user()), 404);

        return $this->storeUploadedFile($request, fn () => $attachments->storeForOpportunity($opportunity, $this->validatedFile($request), auth()->user()));
    }

    private function storeUploadedFile(Request $request, callable $store): JsonResponse
    {
        try {
            $store();

            return response()->json([
                'ok' => true,
                'message' => 'فایل پیوست شد.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'آپلود فایل انجام نشد.',
            ], 500);
        }
    }

    private function validatedFile(Request $request): \Illuminate\Http\UploadedFile
    {
        return $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ], [
            'file.max' => 'حداکثر حجم فایل ۲ مگابایت است.',
            'file.required' => 'فایلی انتخاب نشده است.',
            'file.uploaded' => 'بارگذاری فایل ناموفق بود. حجم را کمتر از ۲ مگابایت کنید یا upload_max_filesize در PHP را افزایش دهید.',
        ], [
            'file' => 'فایل',
        ])['file'];
    }

    public function show(Attachment $attachment, CrmAttachmentService $attachments): StreamedResponse
    {
        return $attachments->downloadResponse($attachment, auth()->user());
    }
}