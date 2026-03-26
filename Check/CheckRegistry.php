<?php

namespace Flute\Modules\GiveCore\Check;

use Flute\Modules\GiveCore\Contracts\CheckableInterface;
use Flute\Modules\GiveCore\Contracts\CheckDriverInterface;
use Flute\Modules\GiveCore\Give\GiveFactory;
use InvalidArgumentException;

/**
 * Central registry for all condition-check drivers.
 * Combines standalone check drivers with GiveFactory's checkable delivery drivers.
 */
class CheckRegistry
{
    /**
     * @var array<string, class-string<CheckDriverInterface>>
     */
    protected array $drivers = [];

    /**
     * @var array<string, CheckDriverInterface>
     */
    protected array $instances = [];

    protected ?GiveFactory $giveFactory = null;

    public function setGiveFactory(GiveFactory $giveFactory): void
    {
        $this->giveFactory = $giveFactory;
    }

    /**
     * Register a standalone check driver.
     */
    public function register(string $key, string $driverClass): void
    {
        if (!is_subclass_of($driverClass, CheckDriverInterface::class)) {
            throw new InvalidArgumentException("Driver class {$driverClass} must implement CheckDriverInterface");
        }

        $this->drivers[$key] = $driverClass;
    }

    /**
     * Get a driver by alias. Checks own registry first, then GiveFactory's checkable drivers.
     */
    public function get(string $key): ?CheckDriverInterface
    {
        // Own registered drivers
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (isset($this->drivers[$key])) {
            $this->instances[$key] = new $this->drivers[$key]();

            return $this->instances[$key];
        }

        // Wrap GiveFactory checkable driver
        if ($this->giveFactory && $this->giveFactory->exists($key)) {
            $driver = $this->giveFactory->getDriver($key);
            if ($driver instanceof CheckableInterface) {
                $adapter = new DeliveryCheckAdapter($driver);
                $this->instances[$key] = $adapter;

                return $adapter;
            }
        }

        return null;
    }

    /**
     * Get all checkable drivers (own + GiveFactory's checkable delivery drivers).
     *
     * @return array<string, CheckDriverInterface>
     */
    public function all(): array
    {
        $result = [];

        // GiveFactory checkable drivers first (delivery drivers that also check)
        if ($this->giveFactory) {
            foreach ($this->giveFactory->getCheckableDrivers() as $alias => $driver) {
                if (!isset($this->drivers[$alias])) {
                    $result[$alias] = $this->get($alias);
                }
            }
        }

        // Own registered check-only drivers
        foreach ($this->drivers as $key => $class) {
            $result[$key] = $this->get($key);
        }

        return $result;
    }

    /**
     * Get only available drivers.
     *
     * @return array<string, CheckDriverInterface>
     */
    public function available(): array
    {
        return array_filter($this->all(), static fn(CheckDriverInterface $driver) => $driver->isAvailable());
    }

    /**
     * Check if a driver exists.
     */
    public function has(string $key): bool
    {
        if (isset($this->drivers[$key])) {
            return true;
        }

        if ($this->giveFactory && $this->giveFactory->exists($key)) {
            $driver = $this->giveFactory->getDriver($key);

            return $driver instanceof CheckableInterface;
        }

        return false;
    }
}
