<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Contracts\CheckableInterface;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Flute\Modules\GiveCore\Support\CheckableTrait;

class PexDriver extends AbstractDriver implements CheckableInterface
{
    use CheckableTrait;

    protected const MOD_KEY = 'PEX';

    public function alias(): string
    {
        return 'pex';
    }

    public function name(): string
    {
        return __('givecore.drivers.pex.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.pex.description');
    }

    public function icon(): string
    {
        return 'ph.bold.sword-bold';
    }

    public function category(): string
    {
        return 'Minecraft';
    }

    public function deliverFields(): array
    {
        return [
            'group' => [
                'type' => 'text',
                'label' => __('givecore.fields.group'),
                'required' => true,
                'placeholder' => 'vip',
            ],
        ];
    }

    public function checkFields(): array
    {
        return [
            'server_id' => [
                'type' => 'select',
                'label' => __('givecore.fields.server'),
                'required' => true,
                'options' => $this->getServerOptions(static::MOD_KEY),
            ],
            'group' => [
                'type' => 'text',
                'label' => __('givecore.fields.group'),
                'required' => false,
                'placeholder' => __('givecore.fields.group_any_placeholder'),
            ],
        ];
    }

    public function check(User $user, array $params = []): bool
    {
        $serverId = $params['server_id'] ?? null;
        if (!$serverId) {
            return false;
        }

        $server = $this->getServerById((int) $serverId);
        if (!$server) {
            return false;
        }

        $uuid = $this->getUserMinecraftUuid($user);
        if (!$uuid) {
            return false;
        }

        $dbConnection = $server->getDbConnection(static::MOD_KEY);
        if (!$dbConnection) {
            return false;
        }

        $prefix = $this->getPrefix($dbConnection->dbname, '');
        $group = $params['group'] ?? '';
        $db = dbal()->database($dbConnection->dbname);

        $query = $db
            ->select()
            ->from($prefix . 'permissions_inheritance')
            ->where('child', $uuid)
            ->andWhere('type', 1); // type 1 = user

        if (!empty($group)) {
            $query->andWhere('parent', strtolower(trim($group)));
        }

        return !empty($query->fetchAll());
    }

    public function deliver(
        User $user,
        Server $server,
        array $additional = [],
        ?int $timeId = null,
        bool $ignoreErrors = false,
    ): bool {
        $uuid = $this->getUserMinecraftUuid($user);
        if (!$uuid) {
            throw new UserSocialException('Minecraft');
        }

        $dbConnection = $server->getDbConnection(static::MOD_KEY);
        if (!$dbConnection) {
            throw new BadConfigurationException(static::MOD_KEY, $server->name);
        }

        $prefix = $this->getPrefix($dbConnection->dbname, '');
        $group = $additional['group'] ?? null;
        if (empty($group)) {
            throw new BadConfigurationException('group', $server->name);
        }

        $group = strtolower(trim($group));
        $db = dbal()->database($dbConnection->dbname);

        // Ensure entity exists for user
        $existing = $db
            ->select()
            ->from($prefix . 'permissions_entity')
            ->where('name', $uuid)
            ->andWhere('type', 1)
            ->fetchAll();

        if (empty($existing)) {
            $db
                ->insert($prefix . 'permissions_entity')
                ->values([
                    'name' => $uuid,
                    'type' => 1,
                    'default' => 0,
                ])
                ->run();
        }

        // Remove old group inheritance if exists
        $db
            ->delete($prefix . 'permissions_inheritance')
            ->where('child', $uuid)
            ->andWhere('parent', $group)
            ->andWhere('type', 1)
            ->run();

        // Add group inheritance
        $db
            ->insert($prefix . 'permissions_inheritance')
            ->values([
                'child' => $uuid,
                'parent' => $group,
                'type' => 1,
                'world' => null,
            ])
            ->run();

        return true;
    }

    protected function getUserMinecraftUuid(User $user): ?string
    {
        $mc = $user->getSocialNetwork('Minecraft') ?? $user->getSocialNetwork('minecraft');

        return $mc?->value ?: null;
    }
}
