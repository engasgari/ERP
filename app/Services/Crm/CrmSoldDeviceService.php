<?php

namespace App\Services\Crm;

use App\Models\Crm\SoldDevice;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrmSoldDeviceService
{
    public function __construct(private readonly CrmAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): SoldDevice
    {
        $payload = $this->preparePayload($data, $actor);
        $this->assertUniqueSerial($payload['serial_number']);

        $device = SoldDevice::create($payload + [
            'assigned_user_id' => $data['assigned_user_id'] ?? $actor->id,
            'created_by' => $actor->id,
            'is_active' => true,
        ]);

        $this->audit->log($device, 'created', null, $device->only(['serial_number', 'party_id']), $device->party_id);

        return $device->fresh(['party', 'item', 'assignedUser']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SoldDevice $device, array $data, User $actor): SoldDevice
    {
        $payload = $this->preparePayload($data, $actor);
        $this->assertUniqueSerial($payload['serial_number'], $device->id);

        $device->update($payload + ['updated_by' => $actor->id]);

        $this->audit->log($device, 'updated', null, $device->only(['serial_number', 'party_id']), $device->party_id);

        return $device->fresh(['party', 'item', 'assignedUser']);
    }

    public function remove(SoldDevice $device, User $actor): void
    {
        $device->update([
            'is_active' => false,
            'updated_by' => $actor->id,
        ]);

        $this->audit->log($device, 'deactivated', null, $device->only(['serial_number']), $device->party_id);
        $device->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function preparePayload(array $data, User $actor): array
    {
        $soldAt = $this->resolveSoldAt($data['sold_at'] ?? null);
        $warrantyYears = max(0, min(20, (int) ($data['warranty_years'] ?? 1)));
        $item = $this->resolveItem($data['item_id'] ?? null);

        return [
            'party_id' => (int) $data['party_id'],
            'item_id' => $item->id,
            'serial_number' => Str::upper(trim((string) $data['serial_number'])),
            'device_name' => $item->name,
            'sold_at' => $soldAt->toDateString(),
            'warranty_years' => $warrantyYears,
            'warranty_ends_at' => $this->computeWarrantyEndsAt($soldAt, $warrantyYears)->toDateString(),
            'notes' => blank($data['notes'] ?? null) ? null : trim((string) $data['notes']),
            'updated_by' => $actor->id,
        ];
    }

    private function resolveSoldAt(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if (is_string($value) && $value !== '') {
            if (str_contains($value, '/')) {
                $gregorian = jalaliToGregorianDate($value);

                if ($gregorian) {
                    return Carbon::parse($gregorian)->startOfDay();
                }
            }

            return Carbon::parse($value)->startOfDay();
        }

        throw ValidationException::withMessages([
            'sold_at' => 'تاریخ فروش معتبر نیست.',
        ]);
    }

    private function resolveItem(mixed $itemId): Item
    {
        $item = Item::query()
            ->where('is_active', true)
            ->find((int) $itemId);

        if (! $item) {
            throw ValidationException::withMessages([
                'item_id' => 'کالای انتخاب‌شده معتبر نیست.',
            ]);
        }

        return $item;
    }

    public function computeWarrantyEndsAt(Carbon $soldAt, int $warrantyYears): Carbon
    {
        if ($warrantyYears <= 0) {
            return $soldAt->copy()->startOfDay();
        }

        return $soldAt->copy()->addYears($warrantyYears)->startOfDay();
    }

    private function assertUniqueSerial(string $serialNumber, ?int $ignoreId = null): void
    {
        $query = SoldDevice::withTrashed()->where('serial_number', $serialNumber);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'serial_number' => 'این شماره سریال قبلاً ثبت شده است.',
            ]);
        }
    }
}
