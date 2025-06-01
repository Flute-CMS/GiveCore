<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;

class AdminSystemDriver extends AbstractDriver
{
    protected string $prefix = 'as_';

    /**
     * Deliver admin privileges to a user
     */
    public function deliver(User $user, Server $server, array $additional = [], ?int $timeId = null, bool $ignoreErrors = false): bool
    {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');
        if (! $steam->value) {
            throw new UserSocialException("Steam");
        }

        [$dbConnection, $serverId] = $this->validateAdditionalParams($additional, $server);

        $this->prefix = $this->getPrefix($dbConnection->dbname, 'as_');

        $db = dbal()->database($dbConnection->dbname);

        $steamId  = $steam->value;
        $groupId  = (int) ($additional['group']    ?? 0);
        $immunity = (int) ($additional['immunity'] ?? 0);
        $flags    =              $additional['flags']    ?? '';
        $expires  = (int) ($additional['expires']  ?? 0);
        $comment  =              $additional['comment']  ?? '';

        $existingAdmin = $db->select()
            ->from($this->prefix . 'admins')
            ->where('steamid', '=', $steamId)
            ->fetchAll();

        $groupName = '';
        if ($groupId > 0) {
            $group = $db->select()
                ->from($this->prefix . 'groups')
                ->where('id', '=', $groupId)
                ->fetchAll();

            if (empty($group)) {
                throw new BadConfigurationException("group not found");
            }

            $groupName = $group[0]['name'] ?? '';
        }

        if (! empty($existingAdmin)) {
            $admin = $existingAdmin[0];
            if (! $ignoreErrors) {
                $this->confirm(__("givecore.update_admin", [
                    ':name'  => $admin['name'],
                    ':group' => $groupName
                ]));
            }
            $adminId = $admin['id'];
        } else {
            $adminId = $db->insert($this->prefix . 'admins')
                ->values([
                    'name'    => $user->name,
                    'steamid' => $steamId,
                    'comment' => $comment,
                ])
                ->run();
        }

        $this->updateServerAssignment(
            $db,
            $adminId,
            $serverId,
            $groupId,
            $immunity,
            $expires,
            $flags
        );

        return true;
    }

    /**
     * Update or insert AdminSystem server assignment
     */
    protected function updateServerAssignment($db, int $adminId, int $serverId, int $groupId, int $immunity, int $expires, string $flags): void
    {
        $existing = $db->select()
            ->from($this->prefix . 'admins_servers')
            ->where('admin_id', '=', $adminId)
            ->andWhere('server_id', '=', $serverId)
            ->fetchAll();

        $data = [
            'group_id' => $groupId,
            'immunity' => $immunity,
            'expires'  => $expires,
            'flags'    => $flags,
        ];

        if (! empty($existing)) {
            $db->update($this->prefix . 'admins_servers', $data)
                ->where('admin_id', '=', $adminId)
                ->andWhere('server_id', '=', $serverId)
                ->run();
        } else {
            $db->insert($this->prefix . 'admins_servers')
                ->values(array_merge(
                    ['admin_id'  => $adminId, 'server_id' => $serverId],
                    $data
                ))
                ->run();
        }
    }

    /**
     * Return the driver alias
     */
    public function alias(): string
    {
        return 'adminsystem';
    }

    /**
     * Validate the additional parameters and extract server_id
     */
    protected function validateAdditionalParams(array $additional, Server $server): array
    {
        if (empty($additional['group'])) {
            throw new BadConfigurationException('group is required');
        }

        $dbConnection = $server->getDbConnection('AdminSystem');
        if (! $dbConnection) {
            throw new BadConfigurationException("db connection AdminSystem does not exist");
        }

        $serverId = isset($additional['sid'])
            ? (int) $additional['sid']
            : (int) ($server->id ?? 0);

        return [$dbConnection, $serverId];
    }
}
