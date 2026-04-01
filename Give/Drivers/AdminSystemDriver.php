<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Contracts\CheckableInterface;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Flute\Modules\GiveCore\Support\CheckableTrait;

class AdminSystemDriver extends AbstractDriver implements CheckableInterface
{
    use CheckableTrait;

    protected const MOD_KEY = 'AdminSystem';

    protected string $prefix = 'as_';

    // ── Metadata ───────────────────────────────────────────────────

    public function alias(): string
    {
        return 'adminsystem';
    }

    public function name(): string
    {
        return __('givecore.drivers.adminsystem.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.adminsystem.description');
    }

    public function icon(): string
    {
        return 'ph.bold.shield-star-bold';
    }

    public function category(): string
    {
        return 'admin';
    }

    public function deliverFields(): array
    {
        return [
            'group' => [
                'type' => 'number',
                'label' => __('givecore.fields.admin_group'),
                'required' => false,
                'help' => __('givecore.fields.as_group_help'),
            ],
            'flags' => [
                'type' => 'text',
                'label' => __('givecore.fields.flags'),
                'required' => false,
                'placeholder' => __('givecore.fields.flags_placeholder'),
                'help' => __('givecore.fields.as_flags_help'),
            ],
            'immunity' => [
                'type' => 'number',
                'label' => __('givecore.fields.immunity'),
                'required' => false,
                'min' => 0,
                'max' => 100,
            ],
            'comment' => [
                'type' => 'text',
                'label' => __('givecore.fields.comment'),
                'required' => false,
            ],
        ];
    }

    public function checkFields(): array
    {
        return [
            'server_id' => [
                'type' => 'select',
                'label' => __('givecore.fields.server'),
                'required' => false,
                'options' => $this->getServerOptions(static::MOD_KEY),
            ],
            'min_immunity' => [
                'type' => 'number',
                'label' => __('givecore.fields.min_immunity'),
                'required' => false,
                'min' => 0,
                'max' => 100,
                'default' => 0,
            ],
            'flags' => [
                'type' => 'text',
                'label' => __('givecore.fields.required_flags'),
                'required' => false,
                'placeholder' => __('givecore.fields.flags_placeholder'),
            ],
        ];
    }

    // ── Condition check ────────────────────────────────────────────

    public function check(User $user, array $params = []): bool
    {
        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            return false;
        }

        $servers = !empty($params['server_id'])
            ? [$this->getServerById((int) $params['server_id'])]
            : $this->getServersWithConnection(static::MOD_KEY);

        foreach ($servers as $server) {
            if (!$server) {
                continue;
            }

            $dbConnection = $server->getDbConnection('AdminSystem');
            if (!$dbConnection) {
                continue;
            }

            $prefix = $this->getPrefix($dbConnection->dbname, 'as_');
            $db = dbal()->database($dbConnection->dbname);

            $admin = $db
                ->select()
                ->from($prefix . 'admins')
                ->where('steamid', '=', $steamId)
                ->fetchAll();

            if (empty($admin)) {
                continue;
            }

            $minImmunity = (int) ( $params['min_immunity'] ?? 0 );
            $requiredFlags = $params['flags'] ?? '';

            $adminId = $admin[0]['id'];

            $assignments = $db
                ->select()
                ->from($prefix . 'admins_servers')
                ->where('admin_id', '=', $adminId)
                ->fetchAll();

            foreach ($assignments as $assignment) {
                if ((int) $assignment['expires'] !== 0 && (int) $assignment['expires'] < time()) {
                    continue;
                }

                if ($minImmunity > 0 && (int) $assignment['immunity'] < $minImmunity) {
                    continue;
                }

                if (!empty($requiredFlags) && !$this->hasFlags($assignment['flags'] ?? '', $requiredFlags)) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    // ── Delivery ───────────────────────────────────────────────────

    public function deliver(
        User $user,
        Server $server,
        array $additional = [],
        ?int $timeId = null,
        bool $ignoreErrors = false,
    ): bool {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');
        if (!$steam?->value) {
            throw new UserSocialException('Steam');
        }

        $simulate = false;
        if (array_key_exists('__simulate', $additional)) {
            $simulate = (bool) $additional['__simulate'];
            unset($additional['__simulate']);
        }

        [$dbConnection, $serverId] = $this->validateAdditionalParams($additional, $server);

        $this->prefix = $this->getPrefix($dbConnection->dbname, 'as_');

        $db = dbal()->database($dbConnection->dbname);

        $steamId = $steam->value;
        $groupId = (int) ( $additional['group'] ?? 0 );
        $immunity = (int) ( $additional['immunity'] ?? 0 );
        $flags = $additional['flags'] ?? '';
        $comment = $additional['comment'] ?? '';

        $time = $timeId ?? (int) ( $additional['time'] ?? 0 );

        $existingAdmin = $db
            ->select()
            ->from($this->prefix . 'admins')
            ->where('steamid', '=', $steamId)
            ->fetchAll();

        $groupName = '';
        if ($groupId > 0) {
            $group = $db
                ->select()
                ->from($this->prefix . 'groups')
                ->where('id', '=', $groupId)
                ->fetchAll();

            if (empty($group)) {
                throw new BadConfigurationException('group not found');
            }

            $groupName = $group[0]['name'] ?? '';
        }

        $existingServerAssignment = null;
        if (!empty($existingAdmin)) {
            $admin = $existingAdmin[0];
            if (!$ignoreErrors) {
                $this->confirm(__('givecore.update_admin', [
                    ':name' => $admin['name'],
                    ':group' => $groupName,
                ]));
            }
            $adminId = $admin['id'];

            $existingServerAssignment = $db
                ->select()
                ->from($this->prefix . 'admins_servers')
                ->where('admin_id', '=', $adminId)
                ->andWhere('server_id', '=', $serverId)
                ->fetchAll();
            $existingServerAssignment = !empty($existingServerAssignment) ? $existingServerAssignment[0] : null;
        } else {
            if (!$simulate) {
                $adminId = $db
                    ->insert($this->prefix . 'admins')
                    ->values([
                        'name' => $user->name,
                        'steamid' => $steamId,
                        'comment' => $comment,
                    ])
                    ->run();
            } else {
                $adminId = 0;
            }
        }

        if ($time === 0) {
            $expires = 0;
        } else {
            if ($existingServerAssignment && (int) $existingServerAssignment['group_id'] === $groupId) {
                $base = max((int) $existingServerAssignment['expires'], time());
                $expires = $base + $time;
            } else {
                $expires = time() + $time;
            }
        }

        if (!$simulate) {
            $this->updateServerAssignment($db, $adminId, $serverId, $groupId, $immunity, $expires, $flags);
        }

        return !$simulate;
    }

    // ── Private helpers ────────────────────────────────────────────

    protected function hasFlags(string $userFlags, string $requiredFlags): bool
    {
        for ($i = 0; $i < strlen($requiredFlags); $i++) {
            if (strpos($userFlags, $requiredFlags[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    protected function updateServerAssignment(
        $db,
        int $adminId,
        int $serverId,
        int $groupId,
        int $immunity,
        int $expires,
        string $flags,
    ): void {
        $existing = $db
            ->select()
            ->from($this->prefix . 'admins_servers')
            ->where('admin_id', '=', $adminId)
            ->andWhere('server_id', '=', $serverId)
            ->fetchAll();

        $data = [
            'group_id' => $groupId,
            'immunity' => $immunity,
            'expires' => $expires,
            'flags' => $flags,
        ];

        if (!empty($existing)) {
            $db
                ->update($this->prefix . 'admins_servers', $data)
                ->where('admin_id', '=', $adminId)
                ->andWhere('server_id', '=', $serverId)
                ->run();
        } else {
            $db
                ->insert($this->prefix . 'admins_servers')
                ->values(array_merge(['admin_id' => $adminId, 'server_id' => $serverId], $data))
                ->run();
        }
    }

    protected function validateAdditionalParams(array $additional, Server $server): array
    {
        if (empty($additional['group']) && empty($additional['flags'])) {
            throw new BadConfigurationException('group or flags is required');
        }

        $dbConnection = $server->getDbConnection('AdminSystem');
        if (!$dbConnection) {
            throw new BadConfigurationException(
                'db connection AdminSystem does not exist (server: ' . $server->name . ')',
            );
        }

        $serverId = isset($additional['sid']) ? (int) $additional['sid'] : (int) ( $server->id ?? 0 );

        return [$dbConnection, $serverId];
    }
}
