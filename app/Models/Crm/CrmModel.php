<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

abstract class CrmModel extends Model
{
    public const STATUSES_LEAD = [
        'new' => 'جدید',
        'contacted' => 'تماس گرفته',
        'qualified' => 'واجد شرایط',
        'unqualified' => 'غیر واجد',
        'converted' => 'تبدیل شده',
        'lost' => 'از دست رفته',
    ];

    public const STATUSES_TASK = [
        'pending' => 'در انتظار',
        'in_progress' => 'در حال انجام',
        'completed' => 'انجام شده',
        'cancelled' => 'لغو شده',
    ];

    public const PRIORITIES_TASK = [
        'low' => 'کم',
        'normal' => 'معمولی',
        'high' => 'بالا',
        'urgent' => 'فوری',
    ];

    public const ACTIVITY_TYPES = [
        'call' => 'تماس',
        'meeting' => 'جلسه',
        'email' => 'ایمیل',
        'sms' => 'پیامک',
        'visit' => 'بازدید',
        'follow_up' => 'پیگیری',
        'demo' => 'دمو',
        'other' => 'سایر',
    ];

    public const CUSTOMER_STATUSES = [
        'prospect' => 'بالقوه',
        'active' => 'فعال',
        'inactive' => 'غیرفعال',
        'vip' => 'VIP',
        'churned' => 'ترک کرده',
    ];

    public const CONTACT_STATUSES = [
        'active' => 'فعال',
        'inactive' => 'غیرفعال',
    ];

    public const STATUSES_OPPORTUNITY = [
        'open' => 'باز',
        'won' => 'برنده شده',
        'lost' => 'از دست رفته',
    ];
}
