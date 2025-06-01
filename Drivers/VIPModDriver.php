<?php

namespace Flute\Modules\GiveCore\Drivers;

use Flute\Admin\Packages\Server\Contracts\ModDriverInterface;

class VIPModDriver implements ModDriverInterface
{
    /**
     * Get the driver name.
     */
    public function getName() : string
    {
        return "VIP";
    }

    /**
     * Get the settings view for this driver.
     */
    public function getSettingsView() : string
    {
        return 'givecore::settings.vip';
    }

    public function prepareData(array $data) : array
    {
        $data['sid'] = $data['sid'] ?? 0;
        return $data;
    }

    /**
     * Get validation rules for this driver's settings.
     */
    public function getValidationRules() : array
    {
        return [
            'sid' => 'required|integer',
        ];
    }
}