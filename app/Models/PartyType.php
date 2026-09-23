<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PartyType extends Model
{
    public const DEFAULT_TYPES = [
        ['name' => 'customer', 'title' => 'مشتری'],
        ['name' => 'vendor', 'title' => 'فروشنده'],
        ['name' => 'colleague', 'title' => 'همکار'],
        ['name' => 'marketer', 'title' => 'بازاریاب'],
        ['name' => 'contractor', 'title' => 'پیمانکار'],
        ['name' => 'shareholder', 'title' => 'سهامدار/شریک'],
    ];

    protected $fillable = ['name', 'title'];

    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class);
    }

    public static function ensureDefaults(): void
    {
        foreach (self::DEFAULT_TYPES as $type) {
            static::updateOrCreate(['name' => $type['name']], $type);
        }
    }

    public static function shareholder(): ?self
    {
        return static::where('name', 'shareholder')->first();
    }
}
