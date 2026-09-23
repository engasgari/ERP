<?php



namespace App\Support;



use App\Models\User;

use Illuminate\Support\Collection;

use Illuminate\Support\Facades\Route;



class CrmNavigationMenu

{

    /**

     * @return array<int, array<string, mixed>>

     */

    public static function forUser(?User $user): array

    {

        return collect(self::definition())

            ->map(fn (array $item) => self::resolveItem($item, $user))

            ->filter()

            ->values()

            ->all();

    }



    /**

     * @return array<int, array<string, mixed>>

     */

    public static function definitionForBreadcrumbs(): array

    {

        return self::definition();

    }



    /**

     * @return array<int, array<string, mixed>>

     */

    private static function definition(): array

    {

        return [

            [

                'label' => 'داشبورد',

                'route' => 'crm.dashboard',

                'active' => ['crm.dashboard'],

                'permission' => 'crm.dashboard.view',

            ],

            [

                'label' => 'مشتریان',

                'route' => 'crm.customers.index',

                'active' => ['crm.customers.*'],

                'permission' => 'crm.customers.view',

            ],

            [

                'label' => 'مخاطبین',

                'route' => 'crm.contacts.index',

                'active' => ['crm.contacts.*'],

                'permission' => 'crm.contacts.view',

            ],

            [

                'label' => 'سرنخ‌ها',

                'route' => 'crm.leads.index',

                'active' => ['crm.leads.*'],

                'permission' => 'crm.leads.view',

            ],

            [

                'label' => 'فرصت‌ها',

                'route' => 'crm.opportunities.index',

                'active' => ['crm.opportunities.*'],

                'permission' => 'crm.opportunities.view',

            ],

            [

                'label' => 'خط فروش',

                'route' => 'crm.pipeline.index',

                'active' => ['crm.pipeline.*'],

                'permission' => 'crm.opportunities.view',

            ],

            [

                'label' => 'گزارش‌ها',

                'route' => 'crm.tasks.index',

                'active' => ['crm.activities.*', 'crm.tasks.*', 'crm.calendar.*'],

                'children' => [

                    ['label' => 'وظایف', 'route' => 'crm.tasks.index', 'permission' => 'crm.tasks.view'],

                    ['label' => 'فعالیت‌ها', 'route' => 'crm.activities.index', 'permission' => 'crm.activities.view'],

                    ['label' => 'تقویم', 'route' => 'crm.calendar.index', 'permission' => 'crm.activities.view'],

                ],

            ],

            [

                'label' => 'گارانتی',

                'route' => 'crm.sold-devices.index',

                'active' => ['crm.sold-devices.*'],

                'permission' => 'crm.sold_devices.view',

            ],

            [

                'label' => 'تنظیمات',

                'route' => 'crm.settings.index',

                'active' => ['crm.settings.*'],

                'permission' => 'crm.settings.manage',

            ],

        ];

    }



    /**

     * @param  array<string, mixed>  $item

     * @return array<string, mixed>|null

     */

    private static function resolveItem(array $item, ?User $user): ?array

    {

        $children = collect($item['children'] ?? [])

            ->filter(function (array $child) use ($user) {

                if (($child['type'] ?? null) === 'divider') {

                    return true;

                }



                $route = $child['route'] ?? null;



                if (! $route || ! Route::has($route)) {

                    return false;

                }



                return self::canSee($user, $child['permission'] ?? null);

            })

            ->values();



        $children = self::pruneDividers($children);



        if (! empty($item['children'])) {

            if ($children->where(fn (array $child) => ($child['type'] ?? null) !== 'divider')->isEmpty()) {

                return null;

            }



            $route = $item['route'] ?? null;



            if (! $route || ! Route::has($route) || ! self::canSee($user, $item['permission'] ?? null)) {

                return null;

            }



            $item['children'] = $children->all();



            return $item;

        }



        if (! Route::has($item['route'] ?? '') || ! self::canSee($user, $item['permission'] ?? null)) {

            return null;

        }



        return $item;

    }



    /**

     * @param  Collection<int, array<string, mixed>>  $children

     * @return Collection<int, array<string, mixed>>

     */

    private static function pruneDividers(Collection $children): Collection

    {

        $result = collect();

        $pendingDivider = null;



        foreach ($children as $child) {

            if (($child['type'] ?? null) === 'divider') {

                $pendingDivider = $child;



                continue;

            }



            if ($pendingDivider) {

                $result->push($pendingDivider);

                $pendingDivider = null;

            }



            $result->push($child);

        }



        return $result;

    }



    private static function canSee(?User $user, ?string $permission): bool

    {

        if (! $permission) {

            return true;

        }



        if (! $user) {

            return false;

        }



        return $user->hasPermission($permission);

    }



    /**

     * @return array<int, array<string, mixed>>

     */

    public static function mobileBottomBarForUser(?User $user): array

    {

        $items = [

            [

                'label' => 'خانه',

                'route' => 'crm.dashboard',

                'active' => ['crm.dashboard'],

                'permission' => 'crm.dashboard.view',

                'icon' => 'home',

            ],

            [

                'label' => 'مشتری',

                'route' => 'crm.customers.index',

                'active' => ['crm.customers.*'],

                'permission' => 'crm.customers.view',

                'icon' => 'customers',

            ],

            [

                'label' => 'خط فروش',

                'route' => 'crm.pipeline.index',

                'active' => ['crm.pipeline.*'],

                'permission' => 'crm.opportunities.view',

                'icon' => 'pipeline',

            ],

            [

                'label' => 'فرصت',

                'route' => 'crm.opportunities.index',

                'active' => ['crm.opportunities.*'],

                'permission' => 'crm.opportunities.view',

                'icon' => 'opportunity',

            ],

        ];



        return collect($items)

            ->filter(fn (array $item): bool => Route::has($item['route']) && self::canSee($user, $item['permission'] ?? null))

            ->values()

            ->all();

    }

}


