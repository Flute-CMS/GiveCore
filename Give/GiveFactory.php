<?php

namespace Flute\Modules\GiveCore\Give;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Contracts\DriverInterface;
use Flute\Modules\GiveCore\Give\Drivers\AdminDriver;
use Flute\Modules\GiveCore\Give\Drivers\iksAdminDriver;
use Flute\Modules\GiveCore\Give\Drivers\RconDriver;
use Flute\Modules\GiveCore\Give\Drivers\VipDriver;

class GiveFactory
{
    protected array $drivers = [
        'vip' => VipDriver::class,
        // 'admin' => AdminDriver::class,
        'iksAdmin' => iksAdminDriver::class,
        'rcon' => RconDriver::class
    ];

    public function getAll(): array
    {
        return $this->drivers;
    }

    public function make(string $name, User $user, Server $server, array $additional = [], ?int $timeId = null): bool
    {
        if( !$this->exists($name) )
            throw new \Exception("Driver '$name' is not exists");

        return (new $this->drivers[$name])->deliver($user, $server, $additional, $timeId);
    }

    public function add(string $class) : self
    {
        /** @var DriverInterface */
        $instance = new $class;

        $this->drivers[$instance->alias()] = $class;

        return $this;
    }

    public function remove(string $class) : self
    {
        unset($this->drivers[$class]);

        return $this;
    }

    public function exists(string $name): bool
    {
        return array_key_exists($name, $this->drivers);
    }
}