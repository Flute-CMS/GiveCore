<?php

declare(strict_types = 1);

namespace Flute\Modules\GiveCore\Admin\Package;

use Flute\Admin\Support\AbstractAdminPackage;

class GiveCorePackage extends AbstractAdminPackage
{
    public function initialize(): void
    {
        parent::initialize();

        $this->loadRoutesFromFile('routes.php');
        $this->registerScss('Resources/assets/scss/custom-driver.scss');

        $jsPath = path('app/Modules/GiveCore/Admin/Package/Resources/assets/js/custom-driver-fields.js');

        if (file_exists($jsPath)) {
            $tag = template()->getTemplateAssets()->assetFunction($jsPath);
            template()->prependToSection('footer', $tag);
        }
    }

    public function getPermissions(): array
    {
        return ['admin', 'admin.givecore'];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'type' => 'header',
                'title' => __('givecore.admin.menu.title'),
            ],
            [
                'title' => __('givecore.admin.give_privilege'),
                'icon' => 'ph.bold.gift-bold',
                'url' => url('/admin/givecore'),
                'permission' => ['admin.givecore'],
                'permission_mode' => 'any',
            ],
            [
                'title' => __('givecore.custom_drivers.title'),
                'icon' => 'ph.bold.database-bold',
                'url' => url('/admin/givecore/custom-drivers'),
                'permission' => ['admin.givecore'],
                'permission_mode' => 'any',
            ],
        ];
    }

    public function getPriority(): int
    {
        return 90;
    }
}
