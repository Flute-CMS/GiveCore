<?php

namespace Flute\Modules\GiveCore\Check;

use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Contracts\CheckableInterface;
use Flute\Modules\GiveCore\Contracts\CheckDriverInterface;
use Flute\Modules\GiveCore\Contracts\DriverInterface;
use InvalidArgumentException;

/**
 * Wraps a GiveCore delivery driver (DriverInterface + CheckableInterface)
 * into a CheckDriverInterface so it can be used uniformly in CheckRegistry.
 */
class DeliveryCheckAdapter implements CheckDriverInterface
{
    private DriverInterface $driver;

    private CheckableInterface $checker;

    public function __construct(DriverInterface $driver)
    {
        if (!$driver instanceof CheckableInterface) {
            throw new InvalidArgumentException('Driver ' . $driver->alias() . ' does not implement CheckableInterface');
        }

        $this->driver = $driver;
        $this->checker = $driver;
    }

    public function alias(): string
    {
        return $this->driver->alias();
    }

    public function name(): string
    {
        $name = $this->driver->name();
        $category = $this->driver->category();

        // Add suffix for clarity in check context (e.g. "SourceBans" → "SourceBans Admin")
        $suffix = match ($category) {
            'admin' => ' ' . __('givecore.categories.admin'),
            'vip' => ' ' . __('givecore.categories.vip'),
            default => '',
        };

        // Avoid duplication like "VIP Core VIP"
        if (!empty($suffix) && !str_contains($name, trim($suffix))) {
            $name .= $suffix;
        }

        return $name;
    }

    public function description(): string
    {
        return $this->driver->description();
    }

    public function icon(): string
    {
        return $this->driver->icon();
    }

    public function category(): string
    {
        return $this->driver->category();
    }

    public function check(User $user, array $params = []): bool
    {
        return $this->checker->check($user, $params);
    }

    public function checkBulk(array $users, array $params = []): array
    {
        return $this->checker->checkBulk($users, $params);
    }

    public function checkFields(): array
    {
        return $this->checker->checkFields();
    }

    public function isAvailable(): bool
    {
        return $this->driver->isAvailable();
    }

    public function unavailableReason(): ?string
    {
        return $this->driver->unavailableReason();
    }
}
