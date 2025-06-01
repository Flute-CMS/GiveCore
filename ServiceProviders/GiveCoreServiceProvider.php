<?php

namespace Flute\Modules\GiveCore\ServiceProviders;

use Flute\Admin\Packages\Server\Factories\ModDriverFactory;
use Flute\Core\Support\ModuleServiceProvider;
use Flute\Modules\GiveCore\Drivers\FabiusVIPModDriver;
use Flute\Modules\GiveCore\Drivers\VIPModDriver;

class GiveCoreServiceProvider extends ModuleServiceProvider
{
    public array $extensions = [];

    public function boot(\DI\Container $container): void
    {
        $this->loadTranslations();
        
        $this->loadViews('Resources/views', 'givecore');
        
        if ($container->has(ModDriverFactory::class)) {
            $modDriverFactory = $container->get(ModDriverFactory::class);
            $modDriverFactory->register('VIP', VIPModDriver::class);
            $modDriverFactory->register('FabiusVIP', FabiusVIPModDriver::class);
        }
    }

    public function register(\DI\Container $container): void
    {
    }
}