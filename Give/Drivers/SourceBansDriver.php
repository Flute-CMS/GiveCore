<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use xPaw\SteamID\SteamID;

/**
 * Params:
 * 
 * group - group name
 * immunity - admin immunity level
 * password - optional admin password (will be generated if not provided)
 */
class SourceBansDriver extends AbstractDriver
{
    protected string $prefix = '';

    /**
     * Deliver admin privileges to a user
     */
    public function deliver(User $user, Server $server, array $additional = [], ?int $timeId = null, bool $ignoreErrors = false) : bool
    {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

        if (! $steam->value) {
            throw new UserSocialException("Steam");
        }

        [$dbConnection, $sid] = $this->validateAdditionalParams($additional, $server);

        $this->prefix = $this->getPrefix($dbConnection->dbname, 'sb_');

        $db = dbal()->database($dbConnection->dbname);
        $authId = $this->convertSteamId($steam->value);
        $groupName = $additional['group'] ?? '';
        $immunity = (int) ($additional['immunity'] ?? 0);
        $password = $additional['password'] ?? $this->generatePassword();

        $existingAdmin = $db->select()
            ->from($this->prefix.'admins')
            ->where('authid', '=', $authId)
            ->fetchAll();

        if (! empty($existingAdmin)) {
            $admin = $existingAdmin[0];
            if (! $ignoreErrors) {
                $this->confirm(__("givecore.update_admin", [
                    ':name' => $admin['user'],
                    ':group' => $groupName
                ]));
            }

            $db->update($this->prefix.'admins', [
                'srv_group' => $groupName,
                'immunity' => $immunity
            ])
                ->where('aid', '=', $admin['aid'])
                ->run();

            $adminId = $admin['aid'];
        } else {
            $db->insert($this->prefix.'admins')
                ->values([
                    'user' => $user->name,
                    'authid' => $authId,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'gid' => 0,
                    'email' => $user->email ?? '',
                    'srv_group' => $groupName,
                    'immunity' => $immunity,
                    'lastvisit' => time()
                ])
                ->run();

            $results = $db->select('aid')
                ->from($this->prefix.'admins')
                ->where('authid', '=', $authId)
                ->orderBy('aid', 'DESC')
                ->fetchAll();

            if (empty($results)) {
                throw new \Exception("Failed to get admin ID after creation");
            }

            $adminId = $results[0]['aid'];
        }

        $sourceBansServerId = 0;

        if (! empty($server->ip) && ! empty($server->port)) {
            $servers = $db->select('sid')
                ->from($this->prefix.'servers')
                ->where('ip', '=', $server->ip)
                ->andWhere('port', '=', $server->port)
                ->fetchAll();

            if (! empty($servers)) {
                $sourceBansServerId = $servers[0]['sid'];
            }
        }

        $this->assignServerToAdmin($db, $adminId, $sourceBansServerId);

        return true;
    }

    /**
     * Assign a server to a SourceBans admin
     */
    protected function assignServerToAdmin($db, $adminId, $serverId) : void
    {
        // Check if assignment already exists
        $existingAssignment = $db->select()
            ->from($this->prefix.'admins_servers_groups')
            ->where('admin_id', '=', $adminId)
            ->andWhere('server_id', '=', $serverId)
            ->fetchAll();

        if (empty($existingAssignment)) {
            // Create new assignment
            $db->insert($this->prefix.'admins_servers_groups')
                ->values([
                    'admin_id' => $adminId,
                    'server_id' => $serverId,
                    'group_id' => -1,
                    'srv_group_id' => -1
                ])
                ->run();
        }
    }

    /**
     * Generate a random password
     */
    protected function generatePassword(int $length = 12) : string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[\mt_rand(0, strlen($chars) - 1)];
        }

        return $password;
    }

    /**
     * Convert Steam ID to SourceBans format
     */
    protected function convertSteamId(string $steamId) : string
    {
        try {
            $steamID = new SteamID($steamId);
            return $steamID->RenderSteam2();
        } catch (\Exception $e) {
            logs()->error('Failed to convert Steam ID: '.$e->getMessage());
            return $steamId;
        }
    }

    /**
     * Return the driver alias
     */
    public function alias() : string
    {
        return 'sourcebans';
    }

    /**
     * Validate the additional parameters
     */
    protected function validateAdditionalParams(array $additional, Server $server) : array
    {
        if (empty($additional['group'])) {
            throw new BadConfigurationException('group in configuration is required');
        }

        $dbConnection = $server->getDbConnection('SourceBans');

        if (! $dbConnection) {
            throw new BadConfigurationException("db connection SourceBans is not exists");
        }

        return [$dbConnection, 0];
    }
}