<?php

namespace Flute\Modules\GiveCore\Support;

use Flute\Modules\GiveCore\Contracts\CheckDriverInterface;

/**
 * Base class for condition-check drivers (bans, stats, admin status).
 * Uses DriverHelpers for Steam/DB utilities and CheckableTrait for default checkBulk().
 */
abstract class AbstractCheckDriver implements CheckDriverInterface
{
    use CheckableTrait;
    use DriverHelpers;

    /**
     * DB connection key (override in subclass).
     */
    protected const MOD_KEY = null;

    public function isAvailable(): bool
    {
        $key = static::MOD_KEY;
        if ($key === null) {
            return true;
        }

        return !empty($this->getServersWithConnection($key));
    }

    public function unavailableReason(): ?string
    {
        if ($this->isAvailable()) {
            return null;
        }

        return __('givecore.drivers.' . $this->alias() . '.no_servers');
    }

    /**
     * Select options [serverId => serverName] for servers with this driver's connection.
     */
    protected function getAvailableServerOptions(): array
    {
        return $this->getServerOptions(static::MOD_KEY);
    }

    /**
     * Get a specific server by ID, or the first available one.
     */
    protected function resolveServer(int $serverId = 0): ?\Flute\Core\Database\Entities\Server
    {
        if ($serverId > 0) {
            return $this->getServerById($serverId);
        }

        $servers = $this->getServersWithConnection(static::MOD_KEY);

        return $servers[0] ?? null;
    }
}
