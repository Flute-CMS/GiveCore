<?php

namespace Flute\Modules\GiveCore\Drivers;

use Flute\Admin\Packages\Server\Contracts\ModDriverInterface;

class FabiusVIPModDriver implements ModDriverInterface
{
    /**
     * Get the driver name.
     */
    public function getName() : string
    {
        return "FabiusVIP";
    }

    /**
     * Get the settings view for this driver.
     */
    public function getSettingsView() : string
    {
        return '';
    }

    /**
     * Get validation rules for this driver's settings.
     * 
     * Note: For FabiusVIP, we don't need the sid parameter as it uses the server ID directly
     */
    public function getValidationRules() : array
    {
        return [];
    }
} 