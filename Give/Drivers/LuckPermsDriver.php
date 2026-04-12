<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Core\Rcon\RconService;
use Flute\Modules\GiveCore\Contracts\CheckableInterface;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Flute\Modules\GiveCore\Support\CheckableTrait;

class LuckPermsDriver extends AbstractDriver implements CheckableInterface
{
    use CheckableTrait;

    protected const MOD_KEY = 'LuckPerms';

    public function alias(): string
    {
        return 'luckperms';
    }

    public function name(): string
    {
        return __('givecore.drivers.luckperms.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.luckperms.description');
    }

    public function icon(): string
    {
        return 'ph.bold.sword-bold';
    }

    public function category(): string
    {
        return 'Minecraft';
    }

    public function sourceUrl(): ?string
    {
        return 'https://luckperms.net';
    }

    public function supportedGames(): array
    {
        return ['Minecraft'];
    }

    public function requiredSocial(array $config = []): ?string
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

        $prefix = $this->getPrefix($dbConnection->dbname, 'luckperms_');
        $group = $params['group'] ?? '';
        $permission = 'group.' . strtolower(trim($group));

        $db = dbal()->database($dbConnection->dbname);
        $query = $db
            ->select()
            ->from($prefix . 'user_permissions')
            ->where('uuid', $uuid)
            ->andWhere('value', 1);

        if (!empty($group)) {
            $query->andWhere('permission', $permission);
        } else {
            $query->andWhere('permission', 'LIKE', 'group.%');
        }

        $results = $query->fetchAll();

        if (empty($results)) {
            return false;
        }

        foreach ($results as $row) {
            $expiry = (int) $row['expiry'];
            if ($expiry === 0 || $expiry > time()) {
                return true;
            }
        }

        return false;
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

        $prefix = $this->getPrefix($dbConnection->dbname, 'luckperms_');
        $group = $additional['group'] ?? null;
        if (empty($group)) {
            throw new BadConfigurationException('group', $server->name);
        }

        $time = (int) ($timeId ?: ($additional['time'] ?? 0));
        $expiry = $time > 0 ? time() + $time : 0;
        $permission = 'group.' . strtolower(trim($group));

        $db = dbal()->database($dbConnection->dbname);

        // Ensure player exists in players table
        $existing = $db
            ->select()
            ->from($prefix . 'players')
            ->where('uuid', $uuid)
            ->fetchAll();

        if (empty($existing)) {
            $username = $user->name ?? 'Player';
            $db
                ->insert($prefix . 'players')
                ->values([
                    'uuid' => $uuid,
                    'username' => $username,
                    'primary_group' => 'default',
                ])
                ->run();
        }

        // Remove existing group permission if present
        $db
            ->delete($prefix . 'user_permissions')
            ->where('uuid', $uuid)
            ->andWhere('permission', $permission)
            ->run();

        // Insert new group permission
        $db
            ->insert($prefix . 'user_permissions')
            ->values([
                'uuid' => $uuid,
                'permission' => $permission,
                'value' => 1,
                'server' => 'global',
                'world' => 'global',
                'expiry' => $expiry,
                'contexts' => '{}',
            ])
            ->run();

        // Update primary group
        $db
            ->update($prefix . 'players')
            ->set('primary_group', strtolower(trim($group)))
            ->where('uuid', $uuid)
            ->run();

        // Send sync command via RCON if possible
        $this->sendRcon($server, 'lp sync');

        return true;
    }

    protected function getUserMinecraftUuid(User $user): ?string
    {
        $mc = $user->getSocialNetwork('Minecraft') ?? $user->getSocialNetwork('minecraft');

        return $mc?->value ?: null;
    }

    protected function sendRcon(Server $server, string $command): void
    {
        try {
            $rconService = app(RconService::class);

            if ($rconService->isAvailable($server)) {
                $rconService->execute($server, $command);
            }
        } catch (\Throwable $e) {
            // RCON sync is optional — LuckPerms has its own messaging
        }
    }
}
