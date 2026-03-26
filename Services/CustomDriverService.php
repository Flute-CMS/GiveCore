<?php

namespace Flute\Modules\GiveCore\Services;

use Flute\Modules\GiveCore\database\Entities\CustomDriver;
use Flute\Modules\GiveCore\Give\Drivers\CustomSqlDriver;
use Flute\Modules\GiveCore\Give\GiveFactory;

/**
 * Manages user-defined custom SQL drivers stored in DB.
 * On boot, they are instantiated and registered in GiveFactory + ModDriverFactory.
 */
class CustomDriverService
{
    /**
     * @return CustomDriver[]
     */
    public function getAll(): array
    {
        try {
            $result = CustomDriver::query()->fetchAll();

            return is_array($result) ? $result : iterator_to_array($result);
        } catch (\Throwable $e) {
            // Table may not exist yet
            return [];
        }
    }

    public function get(string $alias): ?CustomDriver
    {
        try {
            return CustomDriver::query()->where('alias', $alias)->fetchOne();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function save(array $data): CustomDriver
    {
        $alias = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim($data['alias'] ?? '')));

        $entity = CustomDriver::query()->where('alias', $alias)->fetchOne();

        if (!$entity) {
            $entity = new CustomDriver();
            $entity->alias = $alias;
        }

        $entity->name = $data['name'] ?? $alias;
        $entity->description = $data['description'] ?? null;
        $entity->icon = $data['icon'] ?? 'ph.bold.database-bold';
        $entity->category = $data['category'] ?? 'other';
        $entity->mod_key = !empty($data['mod_key']) ? $data['mod_key'] : null;
        $entity->identifier = $data['identifier'] ?? 'steam';
        $entity->sql_deliver = $data['sql_deliver'] ?? '';
        $entity->sql_check = $data['sql_check'] ?? null;

        if (isset($data['fields'])) {
            $entity->setFields(is_array($data['fields']) ? $data['fields'] : []);
        }

        $entity->saveOrFail();

        return $entity;
    }

    public function delete(string $alias): void
    {
        $entity = $this->get($alias);
        if ($entity) {
            $entity->delete();
        }
    }

    /**
     * Register all custom drivers into GiveFactory and ModDriverFactory.
     */
    public function registerAll(): void
    {
        $all = $this->getAll();
        if (empty($all)) {
            return;
        }

        $giveFactory = app(GiveFactory::class);

        foreach ($all as $entity) {
            $config = $entity->toConfig();
            $driver = new CustomSqlDriver($config);

            $this->registerInGiveFactory($giveFactory, $driver);

            if (!empty($config['mod_key'])) {
                $this->registerModDriver($config['mod_key'], $config['name'] ?? $config['mod_key']);
            }
        }
    }

    protected function registerInGiveFactory(GiveFactory $factory, CustomSqlDriver $driver): void
    {
        $ref = new \ReflectionClass($factory);

        $driversRef = $ref->getProperty('drivers');
        $driversRef->setAccessible(true);
        $drivers = $driversRef->getValue($factory);
        $drivers[$driver->alias()] = CustomSqlDriver::class;
        $driversRef->setValue($factory, $drivers);

        $instancesRef = $ref->getProperty('instances');
        $instancesRef->setAccessible(true);
        $instances = $instancesRef->getValue($factory);
        $instances[$driver->alias()] = $driver;
        $instancesRef->setValue($factory, $instances);
    }

    protected function registerModDriver(string $modKey, string $name): void
    {
        try {
            $factoryClass = 'Flute\\Admin\\Packages\\Server\\Factories\\ModDriverFactory';

            if (!class_exists($factoryClass) || !app()->getContainer()->has($factoryClass)) {
                return;
            }

            $modFactory = app($factoryClass);

            if ($modFactory->hasDriver($modKey)) {
                return;
            }

            $modFactory->registerInstance(
                $modKey,
                new \Flute\Modules\GiveCore\Support\GenericModDriver($modKey, $name),
            );
        } catch (\Throwable $e) {
            logs()->warning('Failed to register ModDriver for ' . $modKey . ': ' . $e->getMessage());
        }
    }

    /**
     * Parse JSON field definitions into deliver fields format.
     */
    public static function parseFields(string $fieldsJson): array
    {
        if (empty($fieldsJson)) {
            return [];
        }

        $raw = json_decode($fieldsJson, true);
        if (!is_array($raw)) {
            return [];
        }

        $fields = [];
        foreach ($raw as $fieldDef) {
            $name = $fieldDef['name'] ?? '';
            if (empty($name)) {
                continue;
            }

            $fields[$name] = [
                'type' => $fieldDef['type'] ?? 'text',
                'label' => $fieldDef['label'] ?? $name,
                'required' => (bool) ( $fieldDef['required'] ?? false ),
                'placeholder' => $fieldDef['placeholder'] ?? '',
                'help' => $fieldDef['help'] ?? '',
            ];

            if (( $fieldDef['type'] ?? 'text' ) === 'select' && !empty($fieldDef['options'])) {
                $fields[$name]['options'] = $fieldDef['options'];
            }
        }

        return $fields;
    }
}
