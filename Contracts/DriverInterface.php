<?php

namespace Flute\Modules\GiveCore\Contracts;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;

interface DriverInterface
{
    /**
     * Delivers a product to the some user.
     * Must return a bool or Exception if was error.
     * 
     * @param User $user The user to deliver the product to.
     * @param Server $server The server to deliver the product to.
     * @param array $additional Additional parameters for the delivery.
     * @param int|null $timeId The time ID for the delivery.
     * @param bool $ignoreErrors Whether to ignore errors.
     * 
     * @return bool
     * 
     * @throws \Exception
     */
    public function deliver( User $user, Server $server, array $additional = [], ?int $timeId = null, bool $ignoreErrors = false ) : bool;

    /**
     * Get the alias name for the system
     * 
     * @return string
     */
    public function alias() : string;
}