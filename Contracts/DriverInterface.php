<?php

namespace Flute\Modules\GiveCore\Contracts;

use Exception;
use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;

/**
 * Contract for privilege delivery drivers.
 */
interface DriverInterface
{
    /**
     * Deliver a privilege to a user on a server.
     *
     * @throws Exception
     */
    public function deliver(
        User $user,
        Server $server,
        array $additional = [],
        ?int $timeId = null,
        bool $ignoreErrors = false,
    ): bool;

    /**
     * Unique alias (e.g. 'vip', 'sourcebans').
     */
    public function alias(): string;

    /**
     * Human-readable name.
     */
    public function name(): string;

    /**
     * Short description.
     */
    public function description(): string;

    /**
     * Phosphor icon path.
     */
    public function icon(): string;

    /**
     * Category: 'vip', 'admin', 'rcon', etc.
     */
    public function category(): string;

    /**
     * Field definitions for delivery configuration.
     *
     * @return array<string, array{type: string, label: string, required?: bool, options?: array, ...}>
     */
    public function deliverFields(): array;

    /**
     * DB connection key this driver needs (e.g. 'VIP', 'SourceBans'), or null.
     */
    public function dbConnectionKey(): ?string;

    /**
     * Whether the driver is usable (required servers/modules exist).
     */
    public function isAvailable(): bool;

    /**
     * Why the driver is unavailable, or null.
     */
    public function unavailableReason(): ?string;
}
