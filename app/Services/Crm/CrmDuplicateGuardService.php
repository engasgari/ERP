<?php

namespace App\Services\Crm;

use App\Models\Crm\Contact;
use App\Models\Crm\Lead;
use App\Models\Party;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrmDuplicateGuardService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function assertUniqueCustomer(array $data, ?int $ignorePartyId = null): void
    {
        $duplicate = $this->findDuplicateCustomer($data, $ignorePartyId);

        if ($duplicate) {
            throw ValidationException::withMessages([
                $this->customerDuplicateField($duplicate, $data) => [
                    $this->customerDuplicateMessage($duplicate, $data),
                ],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function findDuplicateCustomer(array $data, ?int $ignorePartyId = null): ?Party
    {
        $query = Party::query()->customers();

        if ($ignorePartyId) {
            $query->where('id', '!=', $ignorePartyId);
        }

        $candidates = $query->get();

        $mobile = $this->normalizeMobile($data['mobile'] ?? null);
        $email = $this->normalizeEmail($data['email'] ?? null);
        $nationalId = $this->normalizeDigits($data['national_id'] ?? null);
        $name = $this->normalizeName($data['name'] ?? null);

        foreach ($candidates as $party) {
            if ($nationalId && $this->normalizeDigits($party->national_id) === $nationalId) {
                return $party;
            }

            if ($mobile && $this->normalizeMobile($party->mobile) === $mobile) {
                return $party;
            }

            if ($mobile && $this->normalizeMobile($party->phone) === $mobile) {
                return $party;
            }

            if ($email && $this->normalizeEmail($party->email) === $email) {
                return $party;
            }

            if ($name && $this->normalizeName($party->name) === $name) {
                return $party;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertUniqueContact(array $data, ?int $ignoreContactId = null): void
    {
        $duplicate = $this->findDuplicateContact($data, $ignoreContactId);

        if (! $duplicate) {
            return;
        }

        $field = $this->contactDuplicateField($duplicate, $data);

        throw ValidationException::withMessages([
            $field => [$this->contactDuplicateMessage($duplicate, $data)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function findDuplicateContact(array $data, ?int $ignoreContactId = null): ?Contact
    {
        $partyId = (int) ($data['party_id'] ?? 0);

        if ($partyId <= 0) {
            return null;
        }

        $query = Contact::query()->where('party_id', $partyId);

        if ($ignoreContactId) {
            $query->where('id', '!=', $ignoreContactId);
        }

        $candidates = $query->get();

        $mobile = $this->normalizeMobile($data['mobile'] ?? null);
        $email = $this->normalizeEmail($data['email'] ?? null);
        $fullName = $this->normalizeContactName(
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
        );

        foreach ($candidates as $contact) {
            if ($mobile && $this->normalizeMobile($contact->mobile) === $mobile) {
                return $contact;
            }

            if ($mobile && $this->normalizeMobile($contact->phone) === $mobile) {
                return $contact;
            }

            if ($email && $this->normalizeEmail($contact->email) === $email) {
                return $contact;
            }

            if ($fullName && $this->normalizeContactName($contact->first_name, $contact->last_name) === $fullName) {
                return $contact;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertUniqueLead(array $data, ?int $ignoreLeadId = null): void
    {
        $duplicate = $this->findDuplicateLead($data, $ignoreLeadId);

        if (! $duplicate) {
            return;
        }

        $field = $this->leadDuplicateField($duplicate, $data);

        throw ValidationException::withMessages([
            $field => [$this->leadDuplicateMessage($duplicate)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function findDuplicateLead(array $data, ?int $ignoreLeadId = null): ?Lead
    {
        $query = Lead::query()->where('status', '!=', 'converted');

        if ($ignoreLeadId) {
            $query->where('id', '!=', $ignoreLeadId);
        }

        $candidates = $query->get();

        $mobile = $this->normalizeMobile($data['mobile'] ?? null);
        $email = $this->normalizeEmail($data['email'] ?? null);
        $companyName = $this->normalizeName($data['company_name'] ?? null);
        $personName = $this->normalizeContactName(
            $data['first_name'] ?? '',
            $data['last_name'] ?? '',
        );

        foreach ($candidates as $lead) {
            if ($mobile && $this->normalizeMobile($lead->mobile) === $mobile) {
                return $lead;
            }

            if ($mobile && $this->normalizeMobile($lead->phone) === $mobile) {
                return $lead;
            }

            if ($email && $this->normalizeEmail($lead->email) === $email) {
                return $lead;
            }

            if ($companyName && $this->normalizeName($lead->company_name) === $companyName) {
                return $lead;
            }

            if ($personName && $this->normalizeContactName($lead->first_name, $lead->last_name) === $personName) {
                return $lead;
            }
        }

        return null;
    }

    public function normalizeMobile(?string $mobile): ?string
    {
        if ($mobile === null || trim($mobile) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    public function normalizeEmail(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return Str::lower(trim($email));
    }

    public function normalizeName(?string $name): ?string
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);

        return $normalized === '' ? null : $normalized;
    }

    public function normalizeContactName(?string $firstName, ?string $lastName): ?string
    {
        $full = trim(trim((string) $firstName).' '.trim((string) $lastName));

        return $this->normalizeName($full);
    }

    private function normalizeDigits(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits === '' ? null : $digits;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function customerDuplicateField(Party $party, array $data): string
    {
        if ($this->normalizeDigits($data['national_id'] ?? null)
            && $this->normalizeDigits($party->national_id) === $this->normalizeDigits($data['national_id'])) {
            return 'national_id';
        }

        if ($this->normalizeMobile($data['mobile'] ?? null)
            && in_array($this->normalizeMobile($data['mobile']), [
                $this->normalizeMobile($party->mobile),
                $this->normalizeMobile($party->phone),
            ], true)) {
            return 'mobile';
        }

        if ($this->normalizeEmail($data['email'] ?? null)
            && $this->normalizeEmail($party->email) === $this->normalizeEmail($data['email'])) {
            return 'email';
        }

        return 'name';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function customerDuplicateMessage(Party $party, array $data): string
    {
        $suffix = $party->code ? " (کد: {$party->code})" : '';

        if ($this->normalizeDigits($data['national_id'] ?? null)
            && $this->normalizeDigits($party->national_id) === $this->normalizeDigits($data['national_id'])) {
            return "مشتری با این کد/شناسه ملی قبلاً ثبت شده است: {$party->name}{$suffix}";
        }

        if ($this->normalizeMobile($data['mobile'] ?? null)
            && in_array($this->normalizeMobile($data['mobile']), [
                $this->normalizeMobile($party->mobile),
                $this->normalizeMobile($party->phone),
            ], true)) {
            return "مشتری با این موبایل قبلاً ثبت شده است: {$party->name}{$suffix}";
        }

        if ($this->normalizeEmail($data['email'] ?? null)
            && $this->normalizeEmail($party->email) === $this->normalizeEmail($data['email'])) {
            return "مشتری با این ایمیل قبلاً ثبت شده است: {$party->name}{$suffix}";
        }

        return "مشتری با این نام قبلاً ثبت شده است: {$party->name}{$suffix}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function contactDuplicateMessage(Contact $contact, array $data): string
    {
        $status = $contact->is_active ? '' : ' (غیرفعال — می‌توانید فعال‌سازی کنید)';

        if ($this->normalizeMobile($data['mobile'] ?? null)
            && in_array($this->normalizeMobile($data['mobile']), [
                $this->normalizeMobile($contact->mobile),
                $this->normalizeMobile($contact->phone),
            ], true)) {
            return "مخاطب با این موبایل برای این مشتری قبلاً ثبت شده است: {$contact->full_name}{$status}";
        }

        if ($this->normalizeEmail($data['email'] ?? null)
            && $this->normalizeEmail($contact->email) === $this->normalizeEmail($data['email'])) {
            return "مخاطب با این ایمیل برای این مشتری قبلاً ثبت شده است: {$contact->full_name}{$status}";
        }

        return "مخاطب با این نام برای این مشتری قبلاً ثبت شده است: {$contact->full_name}{$status}";
    }

    private function leadDuplicateMessage(Lead $lead): string
    {
        return "سرنخ مشابه قبلاً ثبت شده است: {$lead->number} — {$lead->title}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function contactDuplicateField(Contact $contact, array $data): string
    {
        if ($this->normalizeMobile($data['mobile'] ?? null)
            && in_array($this->normalizeMobile($data['mobile']), [
                $this->normalizeMobile($contact->mobile),
                $this->normalizeMobile($contact->phone),
            ], true)) {
            return 'mobile';
        }

        if ($this->normalizeEmail($data['email'] ?? null)
            && $this->normalizeEmail($contact->email) === $this->normalizeEmail($data['email'])) {
            return 'email';
        }

        return 'first_name';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function leadDuplicateField(Lead $lead, array $data): string
    {
        if ($this->normalizeMobile($data['mobile'] ?? null)
            && in_array($this->normalizeMobile($data['mobile']), [
                $this->normalizeMobile($lead->mobile),
                $this->normalizeMobile($lead->phone),
            ], true)) {
            return 'mobile';
        }

        if ($this->normalizeEmail($data['email'] ?? null)
            && $this->normalizeEmail($lead->email) === $this->normalizeEmail($data['email'])) {
            return 'email';
        }

        if ($this->normalizeName($data['company_name'] ?? null)
            && $this->normalizeName($lead->company_name) === $this->normalizeName($data['company_name'])) {
            return 'company_name';
        }

        return 'first_name';
    }
}
