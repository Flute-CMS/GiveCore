<?php

namespace Flute\Modules\GiveCore\Admin\Package\Screens;

use Flute\Admin\Platform\Actions\Button;
use Flute\Admin\Platform\Fields\Input;
use Flute\Admin\Platform\Fields\Select;
use Flute\Admin\Platform\Layouts\LayoutFactory;
use Flute\Admin\Platform\Screen;
use Flute\Admin\Platform\Support\Color;
use Flute\Modules\GiveCore\database\Entities\CustomDriver;
use Flute\Modules\GiveCore\Services\CustomDriverService;

class CustomDriverEditScreen extends Screen
{
    public ?string $name = 'givecore.custom_drivers.add';

    public ?string $description = 'givecore.custom_drivers.description';

    public ?string $permission = 'admin.givecore';

    protected ?CustomDriver $driver = null;

    public function mount(): void
    {
        $alias = request()->attributes->get('alias') ?? request()->input('alias');

        if ($alias) {
            $this->driver = app(CustomDriverService::class)->get($alias);

            if (!$this->driver) {
                $this->flashMessage(__('givecore.custom_drivers.not_found'), 'error');
                $this->redirect('/admin/givecore/custom-drivers');

                return;
            }

            $this->name = 'givecore.custom_drivers.edit';
        }

        breadcrumb()
            ->add(__('def.admin_panel'), url('/admin'))
            ->add(__('givecore.admin.menu.title'))
            ->add(__('givecore.custom_drivers.title'), url('/admin/givecore/custom-drivers'))
            ->add($this->driver ? __('givecore.buttons.edit') : __('givecore.buttons.add'));
    }

    public function commandBar(): array
    {
        $buttons = [
            Button::make(__('givecore.buttons.save'))
                ->type(Color::PRIMARY)
                ->icon('ph.bold.floppy-disk-bold')
                ->method('save'),

            Button::make(__('givecore.buttons.cancel'))
                ->type(Color::OUTLINE_SECONDARY)
                ->redirect(url('/admin/givecore/custom-drivers')),
        ];

        if ($this->driver) {
            $buttons[] = Button::make(__('givecore.buttons.delete'))
                ->type(Color::OUTLINE_DANGER)
                ->icon('ph.bold.trash-bold')
                ->confirm(__('givecore.custom_drivers.confirm_delete'))
                ->method('delete');
        }

        return $buttons;
    }

    public function layout(): array
    {
        $d = $this->driver;

        // Prepare existing fields for the view
        $existingFields = [];
        if ($d) {
            foreach ($d->getFields() as $name => $field) {
                $existingFields[] = array_merge(['name' => $name], $field);
            }
        }

        return [
            LayoutFactory::split([
                LayoutFactory::block([
                    LayoutFactory::field(
                        Input::make('alias')
                            ->value($d?->alias ?? '')
                            ->required()
                            ->readonly($d !== null)
                            ->placeholder('my_plugin'),
                    )
                        ->label(__('givecore.custom_drivers.alias'))
                        ->small(__('givecore.custom_drivers.alias_help')),

                    LayoutFactory::field(
                        Input::make('name')
                            ->value($d?->name ?? '')
                            ->required()
                            ->placeholder('My Plugin'),
                    )
                        ->label(__('givecore.custom_drivers.name'))
                        ->small(__('givecore.custom_drivers.name_help')),

                    LayoutFactory::field(Input::make('description')->value($d?->description ?? ''))->label(__(
                        'givecore.custom_drivers.description',
                    )),

                    LayoutFactory::field(
                        Input::make('icon')
                            ->type('icon')
                            ->value($d?->icon ?? 'ph.bold.database-bold'),
                    )->label(__('givecore.custom_drivers.icon')),

                    LayoutFactory::field(
                        Select::make('category')
                            ->options([
                                'vip' => __('givecore.categories.vip'),
                                'admin' => __('givecore.categories.admin'),
                                'rcon' => __('givecore.categories.rcon'),
                                'Minecraft' => __('givecore.categories.Minecraft'),
                                'CS 1.6' => __('givecore.categories.CS 1.6'),
                                'other' => __('givecore.categories.other'),
                            ])
                            ->value($d?->category ?? 'other'),
                    )->label(__('givecore.custom_drivers.category')),
                ]),

                LayoutFactory::block([
                    LayoutFactory::field(
                        Input::make('mod_key')
                            ->value($d?->mod_key ?? '')
                            ->placeholder('MyPlugin'),
                    )
                        ->label(__('givecore.custom_drivers.mod_key'))
                        ->small(__('givecore.custom_drivers.mod_key_help')),

                    LayoutFactory::field(
                        Select::make('identifier')
                            ->options([
                                'steam' => __('givecore.custom_drivers.identifier_steam'),
                                'minecraft' => __('givecore.custom_drivers.identifier_minecraft'),
                                'both' => __('givecore.custom_drivers.identifier_both'),
                                'name' => __('givecore.custom_drivers.identifier_name'),
                            ])
                            ->value($d?->identifier ?? 'steam'),
                    )->label(__('givecore.custom_drivers.identifier')),
                ]),
            ])->ratio('50/50'),

            // SQL + Fields via blade view (avoids HTML encoding issues)
            LayoutFactory::view('givecore::admin.custom-driver-sql', [
                'driver' => $d,
                'existingFields' => $existingFields,
            ]),
        ];
    }

    public function save(): void
    {
        $data = request()->input();

        $alias = $this->driver?->alias ?? trim($data['alias'] ?? '');

        if (empty($alias)) {
            $this->flashMessage('Alias is required', 'error');

            return;
        }

        // Parse fields from individual inputs
        $fields = [];
        $fieldNames = $data['field_name'] ?? [];
        $fieldTypes = $data['field_type'] ?? [];
        $fieldLabels = $data['field_label'] ?? [];
        $fieldRequired = $data['field_required'] ?? [];

        foreach ($fieldNames as $i => $fname) {
            $fname = trim($fname);
            if (empty($fname)) {
                continue;
            }

            $fields[$fname] = [
                'type' => $fieldTypes[$i] ?? 'text',
                'label' => $fieldLabels[$i] ?? $fname,
                'required' => (bool) ( $fieldRequired[$i] ?? false ),
            ];
        }

        $config = [
            'alias' => $alias,
            'name' => $data['name'] ?? $alias,
            'description' => $data['description'] ?? '',
            'icon' => $data['icon'] ?? 'ph.bold.database-bold',
            'category' => $data['category'] ?? 'other',
            'mod_key' => $data['mod_key'] ?? '',
            'identifier' => $data['identifier'] ?? 'steam',
            'sql_deliver' => $data['sql_deliver'] ?? '',
            'sql_check' => $data['sql_check'] ?? '',
            'fields' => $fields,
        ];

        $entity = app(CustomDriverService::class)->save($config);

        $this->flashMessage(__('givecore.custom_drivers.saved'), 'success');

        // If creating new driver, redirect to its edit page
        if (!$this->driver) {
            $this->redirect('/admin/givecore/custom-drivers/' . $entity->alias . '/edit');
        }
    }

    public function delete(): void
    {
        if ($this->driver) {
            app(CustomDriverService::class)->delete($this->driver->alias);
            $this->flashMessage(__('givecore.custom_drivers.deleted'), 'success');
        }

        $this->redirect('/admin/givecore/custom-drivers');
    }
}
