<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Nette\Utils\Json;
use xPaw\SourceQuery\SourceQuery;
use xPaw\SteamID\SteamID;

/**
 * Params:
 * 
 * group - group name
 * 
 * time - time in seconds
 */
class FabiusDriver extends AbstractDriver
{
    protected string $prefix = 'vip_';

    public function deliver(User $user, Server $server, array $additional = [], ?int $timeId = null, bool $ignoreErrors = false) : bool
    {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

        if (! $steam->value) {
            throw new UserSocialException("Steam");
        }

        [$dbConnection, $sid] = $this->validateAdditionalParams($additional, $server);

        $this->prefix = $this->getPrefix($dbConnection->dbname, 'vip_');

        $accountId = $this->normalizeToAccountId($steam->value);
        $group = $additional['group'];
        $time = ! $timeId ? ($additional['time'] ?? 0) : $timeId;

        $db = dbal()->database($dbConnection->dbname);
        $dbusers = $db->select()
            ->from($this->prefix.'users')
            ->where('account_id', '=', $accountId)
            ->andWhere('sid', '=', $sid)
            ->fetchAll();

        if (! empty($dbusers)) {
            $dbuser = $dbusers[0];

            if (! $ignoreErrors) {
                if ($dbuser['group'] === $group) {
                    $this->confirm(__("givecore.add_time", [
                        ':server' => $server->name
                    ]));
                } else {
                    $this->confirm(__("givecore.replace_group", [
                        ':group' => $dbuser['group'],
                        ':newGroup' => $group
                    ]));
                }
            }

            $this->updateOrInsertUser($db, $accountId, $sid, $group, $time, $user, $dbuser);
        } else {
            $this->updateOrInsertUser($db, $accountId, $sid, $group, $time, $user);
        }

        if ($server->rcon)
            $this->updateVips($server);

        return true;
    }

    private function updateVips(Server $server)
    {
        $query = new SourceQuery();

        try {
            $query->Connect($server->ip, $server->port, 3, ($server->mod == 10) ? SourceQuery::GOLDSOURCE : SourceQuery::SOURCE);
            $query->SetRconPassword($server->rcon);
            $this->sendCommand($query, "vip_reload");
        } catch (\Exception $e) {
            logs()->error($e);
        } finally {
            $query->Disconnect();
        }
    }

    protected function sendCommand(SourceQuery $query, string $command) : void
    {
        $query->Rcon($command);
    }

    public function alias() : string
    {
        return 'fabius';
    }

    private function validateAdditionalParams(array $additional, Server $server) : array
    {
        if (empty($additional['group'])) {
            throw new BadConfigurationException('group in configuration is required');
        }

        $dbConnection = $server->getDbConnection('FabiusVIP');
        if (! $dbConnection) {
            throw new BadConfigurationException('db connection FabiusVIP is not exists');
        }

        $db = dbal()->database($dbConnection->dbname);
        $findServer = $db->select()
            ->from($this->prefix.'servers')
            ->where('serverIp', '=', $server->ip)
            ->where('port', '=', $server->port)
            ->fetchAll();

        if (empty($findServer)) {
            throw new BadConfigurationException('server not found');
        }

        $findServer = $findServer[0];

        return [$dbConnection, $findServer['serverId']];
    }

    private function updateOrInsertUser($db, $accountId, $sid, $group, $time, $user, $currentGroup = null)
    {
        if ($currentGroup && $currentGroup['group'] !== $group) {
            $expiresTime = ($time === 0) ? 0 : time() + $time;
        } else {
            $expiresTime = ($time === 0) ? 0 : ($currentGroup ? $currentGroup['expires'] + $time : time() + $time);
        }

        if ($currentGroup) {
            $db->table($this->prefix.'users')
                ->update([
                    'expires' => $expiresTime,
                    'group' => $group
                ])
                ->where('account_id', '=', $accountId)
                ->andWhere('sid', '=', $sid)
                ->run();
        } else {
            $db->insert($this->prefix.'users')
                ->values([
                    'expires' => $expiresTime,
                    'group' => $group,
                    'account_id' => $accountId,
                    'lastvisit' => time(),
                    'sid' => $sid,
                    'name' => $user->name,
                ])
                ->run();
        }
    }

    /**
     * Normalize Steam ID to account_id format
     * 
     * @param string $steamId Steam ID in any format
     * @return string account_id
     */
    protected function normalizeToAccountId(string $steamId) : string
    {
        try {
            $steamID = new SteamID($steamId);
            return $steamID->GetAccountID();
        } catch (\Exception $e) {
            logs()->error('Failed to normalize Steam ID: '.$e->getMessage());
            return $steamId;
        }
    }
}