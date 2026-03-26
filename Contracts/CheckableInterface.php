<?php

namespace Flute\Modules\GiveCore\Contracts;

use Flute\Core\Database\Entities\User;

/**
 * Contract for drivers that can check conditions (e.g. "does user have VIP?").
 * Delivery drivers may optionally implement this alongside DriverInterface.
 */
interface CheckableInterface
{
    /**
     * Check if a user meets the condition.
     *
     * @param array $params Driver-specific parameters (server_id, group, etc.)
     */
    public function check(User $user, array $params = []): bool;

    /**
     * Bulk check for multiple users.
     *
     * @param User[] $users
     *
     * @return array<int, bool> [userId => conditionMet]
     */
    public function checkBulk(array $users, array $params = []): array;

    /**
     * Field definitions for condition-check configuration.
     *
     * @return array<string, array{type: string, label: string, required?: bool, ...}>
     */
    public function checkFields(): array;
}
