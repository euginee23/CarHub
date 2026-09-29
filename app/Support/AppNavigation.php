<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * The areas of the signed-in app and the pages in each. The top navigation's
 * account menu and every page's section tabs are both built from this list.
 */
class AppNavigation
{
    /**
     * The areas the user can reach, in display order. Administrators run the
     * platform rather than use it, so they only get administration and their account.
     *
     * @return array<string, array{label: string, links: array<int, array{label: string, route: string, active: string}>}>
     */
    public static function sections(User $user): array
    {
        if ($user->is_admin) {
            return [
                'admin' => self::adminSection(),
                'account' => self::accountSection(),
            ];
        }

        return [
            'renting' => [
                'label' => __('Renting'),
                'links' => [
                    ['label' => __('Overview'), 'route' => 'renter.dashboard', 'active' => 'renter.dashboard'],
                    ['label' => __('My trips'), 'route' => 'trips.index', 'active' => 'trips.*'],
                    ['label' => __('ID verification'), 'route' => 'identity.edit', 'active' => 'identity.edit'],
                ],
            ],
            'hosting' => [
                'label' => __('Hosting'),
                'links' => Gate::forUser($user)->allows('list-vehicles')
                    ? [
                        ['label' => __('Overview'), 'route' => 'owner.dashboard', 'active' => 'owner.dashboard'],
                        ['label' => __('My vehicles'), 'route' => 'owner.vehicles.index', 'active' => 'owner.vehicles.*'],
                        ['label' => __('Booking requests'), 'route' => 'owner.bookings.index', 'active' => 'owner.bookings.*'],
                    ]
                    : [
                        ['label' => __('Become an owner'), 'route' => 'owner.apply', 'active' => 'owner.apply'],
                    ],
            ],
            'account' => self::accountSection(),
        ];
    }

    /**
     * The administration area.
     *
     * @return array{label: string, links: array<int, array{label: string, route: string, active: string}>}
     */
    protected static function adminSection(): array
    {
        return [
            'label' => __('Administration'),
            'links' => [
                ['label' => __('Overview'), 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
                ['label' => __('Owner applications'), 'route' => 'admin.owner-applications', 'active' => 'admin.owner-applications'],
                ['label' => __('ID reviews'), 'route' => 'admin.id-reviews', 'active' => 'admin.id-reviews'],
            ],
        ];
    }

    /**
     * The account settings area, shared by everyone.
     *
     * @return array{label: string, links: array<int, array{label: string, route: string, active: string}>}
     */
    protected static function accountSection(): array
    {
        return [
            'label' => __('Account'),
            'links' => [
                ['label' => __('Profile'), 'route' => 'profile.edit', 'active' => 'profile.edit'],
                ['label' => __('Security'), 'route' => 'security.edit', 'active' => 'security.edit'],
            ],
        ];
    }

    /**
     * The user's areas as top-level header links: each goes to the area's first
     * page and stays highlighted on every page inside it. Account settings live
     * in the account menu instead.
     *
     * @return array<int, array{label: string, route: string, active: array<int, string>}>
     */
    public static function areas(User $user): array
    {
        $areas = [];

        foreach (self::sections($user) as $key => $section) {
            if ($key === 'account') {
                continue;
            }

            $areas[] = [
                'label' => $section['links'][0]['route'] === 'owner.apply' ? $section['links'][0]['label'] : $section['label'],
                'route' => $section['links'][0]['route'],
                'active' => array_column($section['links'], 'active'),
            ];
        }

        return $areas;
    }

    /**
     * The key of the area the current page belongs to, if any.
     *
     * @param  array<string, array{label: string, links: array<int, array{label: string, route: string, active: string}>}>  $sections
     */
    public static function currentSection(array $sections): ?string
    {
        foreach ($sections as $key => $section) {
            foreach ($section['links'] as $link) {
                if (request()->routeIs($link['active'])) {
                    return $key;
                }
            }
        }

        return null;
    }
}
