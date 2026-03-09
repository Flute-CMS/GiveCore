<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
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

    public function deliver(User $user, Server $server, array $additional = [], ?int $timeId = null, bool $ignoreErrors = false): bool
    {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

        if (!$steam->value) {
            throw new UserSocialException("Steam");
        }

        $simulate = false;
        if (array_key_exists('__simulate', $additional)) {
            $simulate = (bool)$additional['__simulate'];
            unset($additional['__simulate']);
        }

        [$dbConnection, $sid] = $this->validateAdditionalParams($additional, $server);

        $this->prefix = $this->getPrefix($dbConnection->dbname, 'vip_');

        $accountId = $this->normalizeToAccountId($steam->value);
        $group = $additional['group'];
        $time = !$timeId ? ($additional['time'] ?? 0) : $timeId;

        $db = dbal()->database($dbConnection->dbname);
        $dbusers = $db->select()
            ->from($this->prefix.'users')
            ->where('account_id', '=', $accountId)
            ->andWhere('sid', '=', $sid)
            ->fetchAll();

        if (!empty($dbusers)) {
            $dbuser = $dbusers[0];

            if (!$ignoreErrors) {
                if ($dbuser['group'] === $group) {
                    $this->confirm(__("givecore.add_time", [
                        ':server' => $server->name,
                    ]));
                } else {
                    $this->confirm(__("givecore.replace_group", [
                        ':group' => $dbuser['group'],
                        ':newGroup' => $group,
                    ]));
                }
            }

            if (!$simulate) {
                $this->updateOrInsertUser($db, $accountId, $sid, $group, $time, $user, $dbuser);
            }
        } else {
            if (!$simulate) {
                $this->updateOrInsertUser($db, $accountId, $sid, $group, $time, $user);
            }
        }

        if (!$simulate && $server->rcon) {
            $this->updateVips($server);
        }

        return !$simulate;
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

    protected function sendCommand(SourceQuery $query, string $command): void
    {
        $query->Rcon($command);
    }

    public function alias(): string
    {
        return 'fabius';
    }

    private function validateAdditionalParams(array $additional, Server $server): array
    {
        if (empty($additional['group'])) {
            throw new BadConfigurationException('group in configuration is required');
        }

        $dbConnection = $server->getDbConnection('FabiusVIP');
        if (!$dbConnection) {
            throw new BadConfigurationException('db connection FabiusVIP is not exists (server: ' . $server->name . ')');
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
        if ($time === 0) {
            $expiresTime = 0;
        } else {
            if ($currentGroup) {
                $currentGroupName = isset($currentGroup['group']) ? trim(strtolower($currentGroup['group'])) : null;
                $desiredGroupName = trim(strtolower($group));

                if ($currentGroupName === $desiredGroupName) {
                    $base = max((int) $currentGroup['expires'], time());
                    $expiresTime = $base + $time;
                } else {
                    $expiresTime = time() + $time;
                }
            } else {
                $expiresTime = time() + $time;
            }
        }

        if ($currentGroup) {
            $db->table($this->prefix.'users')
                ->update([
                    'expires' => $expiresTime,
                    'group' => $group,
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
    protected function normalizeToAccountId(string $steamId): string
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
