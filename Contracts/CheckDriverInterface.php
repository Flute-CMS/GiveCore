<?php

namespace Flute\Modules\GiveCore\Contracts;

/**
 * Contract for standalone condition-check drivers (bans, stats, admin status, etc.).
 * Unlike DriverInterface, these drivers can only CHECK — they cannot deliver privileges.
 */
interface CheckDriverInterface extends CheckableInterface
{
    /**
     * Unique alias (e.g. 'sourcebans_ban', 'levelranks_stats').
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
     * Category: 'ban', 'admin', 'stats', etc.
     */
    public function category(): string;

    /**
     * Whether the driver is usable.
     */
    public function isAvailable(): bool;

    /**
     * Reason why unavailable, or null.
     */
    public function unavailableReason(): ?string;
}
