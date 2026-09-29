<?php

namespace App\Support;

use Illuminate\Contracts\Session\Session;

/**
 * The vehicles a visitor has lined up for side-by-side comparison, kept in
 * their session so it works for guests as well as signed-in renters.
 */
class CompareList
{
    public const string SESSION_KEY = 'compare.vehicles';

    public function __construct(protected Session $session) {}

    /**
     * The IDs of the vehicles being compared, in the order they were added.
     *
     * @return array<int, int>
     */
    public function ids(): array
    {
        return array_values(array_map('intval', (array) $this->session->get(self::SESSION_KEY, [])));
    }

    /**
     * Determine whether the vehicle is on the list.
     */
    public function has(int $vehicleId): bool
    {
        return in_array($vehicleId, $this->ids(), true);
    }

    /**
     * Determine whether the list has room for another vehicle.
     */
    public function isFull(): bool
    {
        return count($this->ids()) >= $this->limit();
    }

    /**
     * Add the vehicle when it is missing, or remove it when it is already there.
     * Returns false when the vehicle could not be added because the list is full.
     */
    public function toggle(int $vehicleId): bool
    {
        if ($this->has($vehicleId)) {
            $this->remove($vehicleId);

            return true;
        }

        if ($this->isFull()) {
            return false;
        }

        $this->session->put(self::SESSION_KEY, [...$this->ids(), $vehicleId]);

        return true;
    }

    /**
     * Take the vehicle off the list.
     */
    public function remove(int $vehicleId): void
    {
        $this->session->put(self::SESSION_KEY, array_values(array_diff($this->ids(), [$vehicleId])));
    }

    /**
     * Empty the list.
     */
    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * The most vehicles that can be compared at once.
     */
    public function limit(): int
    {
        return (int) config('carhub.compare_limit');
    }
}
