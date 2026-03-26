<?php

namespace Flute\Modules\GiveCore\Admin\Package\Screens;

use Flute\Admin\Platform\Actions\Button;
use Flute\Admin\Platform\Layouts\LayoutFactory;
use Flute\Admin\Platform\Screen;
use Flute\Admin\Platform\Support\Color;
use Flute\Modules\GiveCore\Services\CustomDriverService;

class CustomDriversListScreen extends Screen
{
    public ?string $name = 'givecore.custom_drivers.title';

    public ?string $description = 'givecore.custom_drivers.description';

    public ?string $permission = 'admin.givecore';

    public $drivers = [];

    public function mount(): void
    {
        $all = app(CustomDriverService::class)->getAll();
        $this->drivers = is_array($all) ? $all : iterator_to_array($all);

        breadcrumb()->add(__('def.admin_panel'), url('/admin'))->add(__('givecore.admin.menu.title'))->add(__(
            'givecore.custom_drivers.title',
        ));
    }

    public function commandBar(): array
    {
        return [
            Button::make(__('givecore.custom_drivers.add'))
                ->type(Color::PRIMARY)
                ->icon('ph.bold.plus-bold')
                ->redirect(url('/admin/givecore/custom-drivers/add')),
        ];
    }

    public function layout(): array
    {
        if (empty($this->drivers)) {
            return [
                LayoutFactory::view('givecore::admin.custom-drivers-empty'),
            ];
        }

        return [
            LayoutFactory::view('givecore::admin.custom-drivers-list', [
                'drivers' => $this->drivers,
            ]),
        ];
    }
}
