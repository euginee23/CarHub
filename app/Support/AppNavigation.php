<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

/**
 * The areas of the signed-in app and the pages in each. The top navigation's
 * account menu and every page's section tabs are both built from this list.
 */
class AppNavigation
{
    /**
     * The areas the user can reach, in display order. Each kind of account has
     * exactly one working area — renting, hosting, or administration — plus
     * their account settings.
     *
     * @return array<string, array{label: string, links: array<int, array{label: string, route: string, active: string}>}>
     */
    public static function sections(User $user): array
    {
        $area = match ($user->role) {
            UserRole::Admin => ['admin' => self::adminSection()],
            UserRole::Owner => ['hosting' => self::hostingSection($user)],
            UserRole::Renter => ['renting' => self::rentingSection()],
        };

        return [...$area, 'account' => self::accountSection()];
    }

    /**
     * The renting area, for renter accounts.
     *
     * @return array{label: string, links: array<int, array{label: string, route: string, active: string}>}
     */
    protected static function rentingSection(): array
    {
        return [
            'label' => __('Renting'),
            'links' => [
                ['label' => __('Overview'), 'route' => 'renter.dashboard', 'active' => 'renter.dashboard'],
                ['label' => __('My trips'), 'route' => 'trips.index', 'active' => 'trips.*'],
                ['label' => __('ID verification'), 'route' => 'identity.edit', 'active' => 'identity.edit'],
            ],
        ];
    }

    /**
     * The hosting area, for owner accounts. Until an owner is verified it only
     * holds their verification application.
     *
     * @return array{label: string, links: array<int, array{label: string, route: string, active: string}>}
     */
    protected static function hostingSection(User $user): array
    {
        if (! $user->isVerifiedOwner()) {
            return [
                'label' => __('Hosting'),
                'links' => [
                    ['label' => __('Owner verification'), 'route' => 'owner.apply', 'active' => 'owner.apply'],
                ],
            ];
        }

        return [
            'label' => __('Hosting'),
            'links' => [
                ['label' => __('Overview'), 'route' => 'owner.dashboard', 'active' => 'owner.dashboard'],
                ['label' => __('My vehicles'), 'route' => 'owner.vehicles.index', 'active' => 'owner.vehicles.*'],
                ['label' => __('Booking requests'), 'route' => 'owner.bookings.index', 'active' => 'owner.bookings.*'],
                ['label' => __('Verification'), 'route' => 'owner.apply', 'active' => 'owner.apply'],
            ],
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
                ['label' => __('Users'), 'route' => 'admin.users', 'active' => 'admin.users'],
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
                'label' => $section['label'],
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
