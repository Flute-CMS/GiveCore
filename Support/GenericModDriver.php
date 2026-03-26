<?php

namespace Flute\Modules\GiveCore\Support;

use Flute\Admin\Packages\Server\Contracts\ModDriverInterface;

/**
 * Generic ModDriver created dynamically for custom SQL drivers.
 * Registered via ModDriverFactory::registerInstance().
 */
class GenericModDriver implements ModDriverInterface
{
    protected string $key;

    protected string $name;

    public function __construct(string $key, string $name)
    {
        $this->key = $key;
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSettingsView(): string
    {
        return 'givecore::settings.generic';
    }

    public function getValidationRules(): array
    {
        return [];
    }
}
