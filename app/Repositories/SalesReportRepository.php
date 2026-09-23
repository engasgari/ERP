<?php

namespace App\Repositories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReportRepository
{
    public function hasValidCogs(): bool
    {
        return Item::query()
            ->where('type', 'product')
            ->whereNotNull('purchase_price')
            ->where('purchase_price', '>', 0)
            ->exists();
    }

    public function baseInvoiceQuery(array $filters, bool $onlyCancelled = false): Builder
    {
        $query = Invoice::query()
            ->where('direction', 'sale')
            ->where('document_type', 'invoice');

        if ($onlyCancelled) {
            $query->where('status', 'cancelled');
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', '!=', 'cancelled');
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('invoice_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('invoice_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['fiscal_year_id'])) {
            $query->where('fiscal_year_id', $filters['fiscal_year_id']);
        }

        if (! empty($filters['party_id'])) {
            $query->where('party_id', $filters['party_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (! empty($filters['payment_status'])) {
            if ($filters['payment_status'] === 'settled') {
                $query->whereNotNull('settled_at');
            } elseif ($filters['payment_status'] === 'unsettled') {
                $query->whereNull('settled_at');
            }
        }

        if (! empty($filters['item_id']) || ! empty($filters['category'])) {
            $query->whereHas('lines', function (Builder $lineQuery) use ($filters): void {
                if (! empty($filters['item_id'])) {
                    $lineQuery->where('item_id', $filters['item_id']);
                }

                if (! empty($filters['category'])) {
                    $lineQuery->whereHas('item', fn (Builder $itemQuery) => $itemQuery->where('category', $filters['category']));
                }
            });
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('party', fn (Builder $partyQuery) => $partyQuery->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    public function invoiceTotals(array $filters, bool $onlyCancelled = false): array
    {
        $row = $this->baseInvoiceQuery($filters, $onlyCancelled)
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_amount')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_amount')
            ->selectRaw('COALESCE(SUM(subtotal - discount_amount), 0) as net_amount')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as tax_amount')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN settled_at IS NOT NULL THEN total_amount ELSE 0 END), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN settled_at IS NULL THEN total_amount ELSE 0 END), 0) as outstanding_amount')
            ->first();

        return [
            'invoice_count' => (int) ($row->invoice_count ?? 0),
            'gross_amount' => (float) ($row->gross_amount ?? 0),
            'discount_amount' => (float) ($row->discount_amount ?? 0),
            'net_amount' => (float) ($row->net_amount ?? 0),
            'tax_amount' => (float) ($row->tax_amount ?? 0),
            'total_amount' => (float) ($row->total_amount ?? 0),
            'paid_amount' => (float) ($row->paid_amount ?? 0),
            'outstanding_amount' => (float) ($row->outstanding_amount ?? 0),
        ];
    }

    public function paginateInvoices(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'invoice_date';
        $direction = $filters['direction'] ?? 'desc';

        $query = $this->baseInvoiceQuery($filters)
            ->with(['party:id,name', 'project:id,name', 'createdBy:id,name']);

        $query = match ($sort) {
            'number' => $query->orderBy('number', $direction),
            'total_amount' => $query->orderBy('total_amount', $direction),
            'party' => $query->leftJoin('parties', 'parties.id', '=', 'invoices.party_id')
                ->orderBy('parties.name', $direction)
                ->select('invoices.*'),
            default => $query->orderBy('invoice_date', $direction)->orderBy('id', $direction),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function aggregateByCustomer(array $filters): Collection
    {
        $sort = $filters['sort'] ?? 'total_amount';
        $direction = strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = $this->baseInvoiceQuery($filters)
            ->join('parties', 'parties.id', '=', 'invoices.party_id');

        if ($this->hasValidCogs()) {
            $query->leftJoinSub($this->invoiceCogsSubquery(), 'line_cogs', function ($join): void {
                $join->on('line_cogs.invoice_id', '=', 'invoices.id');
            });
        }

        $query->select('invoices.party_id', 'parties.name as party_name')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(invoices.subtotal), 0) as gross_amount')
            ->selectRaw('COALESCE(SUM(invoices.discount_amount), 0) as discount_amount')
            ->selectRaw('COALESCE(SUM(invoices.tax_amount), 0) as tax_amount')
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.settled_at IS NOT NULL THEN invoices.total_amount ELSE 0 END), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.settled_at IS NULL THEN invoices.total_amount ELSE 0 END), 0) as outstanding_amount')
            ->groupBy('invoices.party_id', 'parties.name');

        if ($this->hasValidCogs()) {
            $query->selectRaw('COALESCE(SUM(line_cogs.cogs_amount), 0) as cogs_amount');
        }

        $orderColumn = match ($sort) {
            'invoice_count' => 'invoice_count',
            'outstanding_amount' => 'outstanding_amount',
            'profit' => 'cogs_amount',
            default => 'total_amount',
        };

        if ($sort === 'profit' && ! $this->hasValidCogs()) {
            $orderColumn = 'total_amount';
        }

        return $query->orderBy($orderColumn, $direction)->get();
    }

    public function aggregateByProduct(array $filters): Collection
    {
        $sort = $filters['sort'] ?? 'sales_amount';
        $direction = strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = InvoiceLine::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->join('items', 'items.id', '=', 'invoice_lines.item_id')
            ->where('invoices.direction', 'sale')
            ->where('invoices.document_type', 'invoice')
            ->where('invoices.status', '!=', 'cancelled');

        $this->applyLineFilters($query, $filters);

        $query->select('items.id as item_id', 'items.name as item_name', 'items.category as item_category')
            ->selectRaw('COALESCE(SUM(invoice_lines.quantity), 0) as quantity_sold')
            ->selectRaw('COALESCE(SUM(invoice_lines.line_total + invoice_lines.discount_amount), 0) as gross_amount')
            ->selectRaw('COALESCE(SUM(invoice_lines.discount_amount), 0) as discount_amount')
            ->selectRaw('COALESCE(SUM(invoice_lines.line_total), 0) as net_amount');

        if ($this->hasValidCogs()) {
            $query->selectRaw('COALESCE(SUM(CASE WHEN items.type = ? THEN invoice_lines.quantity * COALESCE(items.purchase_price, 0) ELSE 0 END), 0) as cogs_amount', ['product']);
        }

        $query->groupBy('items.id', 'items.name', 'items.category');

        $orderColumn = match ($sort) {
            'quantity_sold' => 'quantity_sold',
            'profit' => 'cogs_amount',
            default => 'net_amount',
        };

        if ($sort === 'profit' && ! $this->hasValidCogs()) {
            $orderColumn = 'net_amount';
        }

        return $query->orderBy($orderColumn, $direction)->get();
    }

    public function aggregateBySalesperson(array $filters): Collection
    {
        $sort = $filters['sort'] ?? 'total_amount';
        $direction = strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = $this->baseInvoiceQuery($filters)
            ->join('users', 'users.id', '=', 'invoices.created_by');

        if ($this->hasValidCogs()) {
            $query->leftJoinSub($this->invoiceCogsSubquery(), 'line_cogs', function ($join): void {
                $join->on('line_cogs.invoice_id', '=', 'invoices.id');
            });
        }

        $query->select('invoices.created_by', 'users.name as salesperson_name')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COUNT(DISTINCT invoices.party_id) as customer_count')
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(invoices.subtotal - invoices.discount_amount), 0) as net_sales')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.settled_at IS NOT NULL THEN invoices.total_amount ELSE 0 END), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN invoices.settled_at IS NULL THEN invoices.total_amount ELSE 0 END), 0) as outstanding_amount')
            ->groupBy('invoices.created_by', 'users.name');

        if ($this->hasValidCogs()) {
            $query->selectRaw('COALESCE(SUM(line_cogs.cogs_amount), 0) as cogs_amount');
        }

        return $query->orderBy($sort === 'customer_count' ? 'customer_count' : 'total_amount', $direction)->get();
    }

    public function receivableInvoices(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->baseInvoiceQuery($filters)
            ->where('status', 'confirmed')
            ->whereNull('settled_at')
            ->with(['party:id,name']);

        if (! empty($filters['party_id'])) {
            // already applied in base
        }

        $sort = $filters['sort'] ?? 'invoice_date';
        $direction = $filters['direction'] ?? 'asc';

        $query = match ($sort) {
            'total_amount' => $query->orderBy('total_amount', $direction),
            'party' => $query->join('parties', 'parties.id', '=', 'invoices.party_id')
                ->orderBy('parties.name', $direction)
                ->select('invoices.*'),
            default => $query->orderBy('invoice_date', $direction),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function receivableSummary(array $filters): array
    {
        $today = Carbon::today()->startOfDay();

        // مطالبات باز همیشه بدون محدودیت بازه زمانی داشبورد محاسبه می‌شود.
        $openFilters = collect($filters)
            ->except(['date_from', 'date_to', 'date_preset', 'fiscal_year_id'])
            ->all();

        $rows = $this->baseInvoiceQuery($openFilters)
            ->where('status', 'confirmed')
            ->whereNull('settled_at')
            ->with(['party:id,name'])
            ->get(['id', 'party_id', 'invoice_date', 'total_amount']);

        $buckets = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'over_90' => 0.0,
        ];

        $parties = [];

        foreach ($rows as $invoice) {
            $days = 0;
            if ($invoice->invoice_date) {
                $days = (int) abs($today->diffInDays($invoice->invoice_date->copy()->startOfDay()));
            }

            $amount = abs((float) $invoice->total_amount);

            if ($days <= 0) {
                $buckets['current'] += $amount;
            } elseif ($days <= 30) {
                $buckets['days_1_30'] += $amount;
            } elseif ($days <= 60) {
                $buckets['days_31_60'] += $amount;
            } elseif ($days <= 90) {
                $buckets['days_61_90'] += $amount;
            } else {
                $buckets['over_90'] += $amount;
            }

            $partyId = (int) ($invoice->party_id ?? 0);
            if ($partyId <= 0) {
                continue;
            }

            if (! isset($parties[$partyId])) {
                $parties[$partyId] = [
                    'party_id' => $partyId,
                    'party_name' => (string) ($invoice->party?->name ?: '—'),
                    'outstanding_amount' => 0.0,
                    'invoice_count' => 0,
                    'days' => 0,
                ];
            }

            $parties[$partyId]['outstanding_amount'] += $amount;
            $parties[$partyId]['invoice_count']++;
            $parties[$partyId]['days'] = max((int) $parties[$partyId]['days'], $days);
        }

        $total = array_sum($buckets);
        $partyRows = collect($parties)
            ->sortByDesc('outstanding_amount')
            ->values()
            ->all();

        return [
            'total_receivables' => $total,
            'current' => $buckets['current'],
            'overdue' => $total - $buckets['current'],
            'over_90' => $buckets['over_90'],
            'invoice_count' => $rows->count(),
            'buckets' => $buckets,
            'parties' => $partyRows,
        ];
    }

    public function discountRows(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->baseInvoiceQuery($filters)
            ->where('discount_amount', '>', 0)
            ->with(['party:id,name', 'createdBy:id,name']);

        $sort = $filters['sort'] ?? 'discount_amount';
        $direction = $filters['direction'] ?? 'desc';

        if ($sort === 'discount_amount') {
            $query->orderBy('discount_amount', $direction);
        } else {
            $query->orderBy('invoice_date', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function discountTotals(array $filters): array
    {
        $row = $this->baseInvoiceQuery($filters)
            ->where('discount_amount', '>', 0)
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discount_amount')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as gross_amount')
            ->first();

        return [
            'invoice_count' => (int) ($row->invoice_count ?? 0),
            'discount_amount' => (float) ($row->discount_amount ?? 0),
            'gross_amount' => (float) ($row->gross_amount ?? 0),
        ];
    }

    public function cancelledSaleRows(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->baseInvoiceQuery($filters, onlyCancelled: true)
            ->with(['party:id,name', 'createdBy:id,name', 'lines.item:id,name']);

        return $query->orderByDesc('invoice_date')->paginate($perPage)->withQueryString();
    }

    public function cancelledSaleTotals(array $filters): array
    {
        $row = $this->baseInvoiceQuery($filters, onlyCancelled: true)
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        return [
            'invoice_count' => (int) ($row->invoice_count ?? 0),
            'total_amount' => (float) ($row->total_amount ?? 0),
        ];
    }

    public function profitabilityBreakdown(array $filters, string $groupBy): Collection
    {
        return match ($groupBy) {
            'invoice' => $this->profitabilityByInvoice($filters),
            'customer' => $this->profitabilityGrouped($filters, 'party'),
            'product' => $this->profitabilityByProduct($filters),
            'category' => $this->profitabilityGrouped($filters, 'category'),
            'salesperson' => $this->profitabilityGrouped($filters, 'salesperson'),
            'project' => $this->profitabilityGrouped($filters, 'project'),
            default => $this->profitabilityByInvoice($filters),
        };
    }

    public function monthlySalesTrend(array $filters, int $months = 12): Collection
    {
        $query = $this->baseInvoiceQuery($filters)
            ->selectRaw('DATE_FORMAT(invoice_date, "%Y-%m") as period')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_amount')
            ->selectRaw('COUNT(*) as invoice_count')
            ->groupBy('period')
            ->orderBy('period');

        if (empty($filters['date_from']) && empty($filters['date_to']) && empty($filters['fiscal_year_id'])) {
            $query->whereDate('invoice_date', '>=', Carbon::today()->subMonths($months - 1)->startOfMonth());
        }

        return $query->get();
    }

    public function aggregatePurchasesByJalaliMonthSplit(array $filters): Collection
    {
        $query = InvoiceLine::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->join('items', 'items.id', '=', 'invoice_lines.item_id')
            ->where('invoices.direction', 'purchase')
            ->where('invoices.document_type', 'invoice')
            ->where('invoices.status', '!=', 'cancelled');

        $this->applyLineFilters($query, $filters);

        return $query
            ->get(['invoices.invoice_date', 'invoice_lines.line_total', 'items.type'])
            ->groupBy(function ($row) {
                if (! $row->invoice_date) {
                    return 'unknown';
                }

                return Verta::instance($row->invoice_date)->format('Y-m');
            })
            ->reject(fn ($group, $period) => $period === 'unknown')
            ->map(function (Collection $group, string $period) {
                $product = 0.0;
                $service = 0.0;

                foreach ($group as $row) {
                    $amount = (float) $row->line_total;
                    if ($row->type === 'service') {
                        $service += $amount;
                    } else {
                        $product += $amount;
                    }
                }

                return (object) [
                    'period' => $period,
                    'product_amount' => $product,
                    'service_amount' => $service,
                    'total_amount' => $product + $service,
                ];
            })
            ->values();
    }

    public function aggregateSalesByJalaliMonth(array $filters): Collection
    {
        return $this->baseInvoiceQuery($filters)
            ->get(['invoice_date', 'total_amount'])
            ->groupBy(function (Invoice $invoice) {
                if (! $invoice->invoice_date) {
                    return 'unknown';
                }

                return Verta::instance($invoice->invoice_date)->format('Y-m');
            })
            ->reject(fn ($group, $period) => $period === 'unknown')
            ->map(function (Collection $group, string $period) {
                return (object) [
                    'period' => $period,
                    'total_amount' => (float) $group->sum('total_amount'),
                    'invoice_count' => $group->count(),
                ];
            })
            ->values();
    }

    public function salesByCategory(array $filters): Collection
    {
        $query = InvoiceLine::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->join('items', 'items.id', '=', 'invoice_lines.item_id')
            ->where('invoices.direction', 'sale')
            ->where('invoices.document_type', 'invoice')
            ->where('invoices.status', '!=', 'cancelled');

        $this->applyLineFilters($query, $filters);

        return $query
            ->selectRaw('COALESCE(NULLIF(items.category, ""), "بدون گروه") as category')
            ->selectRaw('COALESCE(SUM(invoice_lines.line_total), 0) as total_amount')
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get();
    }

    public function topCustomers(array $filters, int $limit = 10): Collection
    {
        return $this->baseInvoiceQuery($filters)
            ->join('parties', 'parties.id', '=', 'invoices.party_id')
            ->select('parties.name as party_name')
            ->selectRaw('COALESCE(SUM(invoices.total_amount), 0) as total_amount')
            ->groupBy('parties.id', 'parties.name')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get();
    }

    public function salespersonChart(array $filters): Collection
    {
        return $this->aggregateBySalesperson($filters)->take(10);
    }

    public function filterOptions(): array
    {
        return [
            'parties' => DB::table('parties')->orderBy('name')->select('id', 'name')->get(),
            'projects' => DB::table('projects')->orderBy('name')->select('id', 'name')->get(),
            'items' => DB::table('items')->where('is_active', true)->orderBy('name')->select('id', 'name', 'category')->get(),
            'categories' => DB::table('items')
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'salespeople' => User::query()->whereIn('id', Invoice::query()->whereNotNull('created_by')->distinct()->pluck('created_by'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'fiscal_years' => DB::table('fiscal_years')->orderByDesc('jalali_year')->select('id', 'title')->get(),
        ];
    }

    private function profitabilityByInvoice(array $filters): Collection
    {
        $query = $this->baseInvoiceQuery($filters)
            ->join('parties', 'parties.id', '=', 'invoices.party_id')
            ->leftJoinSub($this->invoiceCogsSubquery(), 'line_cogs', function ($join): void {
                $join->on('line_cogs.invoice_id', '=', 'invoices.id');
            })
            ->select(
                'invoices.id as invoice_id',
                'invoices.number',
                'invoices.invoice_date',
                'parties.name as party_name',
            )
            ->selectRaw('COALESCE(invoices.subtotal - invoices.discount_amount, 0) as net_sales')
            ->selectRaw('COALESCE(line_cogs.cogs_amount, 0) as cogs_amount')
            ->orderByDesc('invoices.invoice_date');

        return $query->get();
    }

    private function profitabilityGrouped(array $filters, string $group): Collection
    {
        $query = match ($group) {
            'party' => $this->baseInvoiceQuery($filters)
                ->join('parties', 'parties.id', '=', 'invoices.party_id')
                ->select('invoices.party_id as group_id', 'parties.name as group_name'),
            'salesperson' => $this->baseInvoiceQuery($filters)
                ->join('users', 'users.id', '=', 'invoices.created_by')
                ->select('invoices.created_by as group_id', 'users.name as group_name'),
            'project' => $this->baseInvoiceQuery($filters)
                ->leftJoin('projects', 'projects.id', '=', 'invoices.project_id')
                ->select('invoices.project_id as group_id', DB::raw('COALESCE(projects.name, "بدون پروژه") as group_name')),
            'category' => InvoiceLine::query()
                ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
                ->join('items', 'items.id', '=', 'invoice_lines.item_id')
                ->where('invoices.direction', 'sale')
                ->where('invoices.document_type', 'invoice')
                ->where('invoices.status', '!=', 'cancelled')
                ->selectRaw('COALESCE(NULLIF(items.category, ""), "بدون گروه") as group_id')
                ->selectRaw('COALESCE(NULLIF(items.category, ""), "بدون گروه") as group_name'),
            default => $this->baseInvoiceQuery($filters)
                ->join('parties', 'parties.id', '=', 'invoices.party_id')
                ->select('invoices.party_id as group_id', 'parties.name as group_name'),
        };

        if ($group === 'category') {
            $this->applyLineFilters($query, $filters);

            return $query
                ->leftJoinSub($this->globalPurchaseUnitCostSubquery(), 'purchase_costs', function ($join): void {
                    $join->on('purchase_costs.item_id', '=', 'items.id');
                })
                ->selectRaw('COALESCE(SUM(invoice_lines.line_total - invoice_lines.tax_amount), 0) as net_sales')
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN items.type = ? THEN invoice_lines.quantity * COALESCE(purchase_costs.unit_cost, items.purchase_price, 0) ELSE 0 END), 0) as cogs_amount',
                    ['product']
                )
                ->groupBy('group_id', 'group_name')
                ->orderByDesc('net_sales')
                ->get();
        }

        $query->leftJoinSub($this->invoiceCogsSubquery(), 'line_cogs', function ($join): void {
            $join->on('line_cogs.invoice_id', '=', 'invoices.id');
        })
            ->selectRaw('COALESCE(SUM(invoices.subtotal - invoices.discount_amount), 0) as net_sales')
            ->selectRaw('COALESCE(SUM(line_cogs.cogs_amount), 0) as cogs_amount')
            ->groupBy('group_id', 'group_name')
            ->orderByDesc('net_sales');

        return $query->get();
    }

    private function profitabilityByProduct(array $filters): Collection
    {
        $query = InvoiceLine::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->join('items', 'items.id', '=', 'invoice_lines.item_id')
            ->where('invoices.direction', 'sale')
            ->where('invoices.document_type', 'invoice')
            ->where('invoices.status', '!=', 'cancelled');

        $this->applyLineFilters($query, $filters);

        return $query
            ->leftJoinSub($this->globalPurchaseUnitCostSubquery(), 'purchase_costs', function ($join): void {
                $join->on('purchase_costs.item_id', '=', 'items.id');
            })
            ->select('items.id as group_id', 'items.name as group_name')
            ->selectRaw('COALESCE(SUM(invoice_lines.line_total - invoice_lines.tax_amount), 0) as net_sales')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN items.type = ? THEN invoice_lines.quantity * COALESCE(purchase_costs.unit_cost, items.purchase_price, 0) ELSE 0 END), 0) as cogs_amount',
                ['product']
            )
            ->groupBy('items.id', 'items.name')
            ->orderByDesc('net_sales')
            ->get();
    }

    /**
     * Per-invoice COGS using project purchase avg → global purchase avg → master purchase_price.
     * Built with JOINs (not correlated SELECT in GROUP BY) for MySQL ONLY_FULL_GROUP_BY.
     */
    private function invoiceCogsSubquery()
    {
        return DB::query()
            ->from('invoice_lines')
            ->join('items', 'items.id', '=', 'invoice_lines.item_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->leftJoinSub($this->projectPurchaseUnitCostSubquery(), 'project_costs', function ($join): void {
                $join->on('project_costs.item_id', '=', 'invoice_lines.item_id')
                    ->on('project_costs.project_id', '=', 'invoices.project_id');
            })
            ->leftJoinSub($this->globalPurchaseUnitCostSubquery(), 'global_costs', function ($join): void {
                $join->on('global_costs.item_id', '=', 'invoice_lines.item_id');
            })
            ->select('invoice_lines.invoice_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN items.type = ? THEN invoice_lines.quantity * COALESCE(project_costs.unit_cost, global_costs.unit_cost, items.purchase_price, 0) ELSE 0 END), 0) as cogs_amount',
                ['product']
            )
            ->groupBy('invoice_lines.invoice_id');
    }

    private function projectPurchaseUnitCostSubquery()
    {
        return DB::table('invoice_lines as pl')
            ->join('invoices as pi', 'pi.id', '=', 'pl.invoice_id')
            ->where('pi.direction', 'purchase')
            ->where('pi.document_type', 'invoice')
            ->where('pi.status', 'confirmed')
            ->whereNotNull('pi.project_id')
            ->groupBy('pi.project_id', 'pl.item_id')
            ->select('pi.project_id', 'pl.item_id')
            ->selectRaw('SUM(pl.quantity * pl.unit_price) / NULLIF(SUM(pl.quantity), 0) as unit_cost');
    }

    private function globalPurchaseUnitCostSubquery()
    {
        return DB::table('invoice_lines as pl')
            ->join('invoices as pi', 'pi.id', '=', 'pl.invoice_id')
            ->where('pi.direction', 'purchase')
            ->where('pi.document_type', 'invoice')
            ->where('pi.status', 'confirmed')
            ->groupBy('pl.item_id')
            ->select('pl.item_id')
            ->selectRaw('SUM(pl.quantity * pl.unit_price) / NULLIF(SUM(pl.quantity), 0) as unit_cost');
    }

    private function applyLineFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate('invoices.invoice_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('invoices.invoice_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['fiscal_year_id'])) {
            $query->where('invoices.fiscal_year_id', $filters['fiscal_year_id']);
        }

        if (! empty($filters['party_id'])) {
            $query->where('invoices.party_id', $filters['party_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('invoices.project_id', $filters['project_id']);
        }

        if (! empty($filters['created_by'])) {
            $query->where('invoices.created_by', $filters['created_by']);
        }

        if (! empty($filters['item_id'])) {
            $query->where('invoice_lines.item_id', $filters['item_id']);
        }

        if (! empty($filters['category'])) {
            $query->where('items.category', $filters['category']);
        }
    }
}
