<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\GiveDriverException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Nette\Utils\Json;
use xPaw\SourceQuery\SourceQuery;

class iksAdminDriver extends AbstractDriver
{
    public function deliver(User $user, Server $server, array $additional = [], ?int $timeId = null): bool
    {
        $steamid64 = $this->getSteamId($user);

        [$dbConnection, $sid] = $this->validateAdditionalParams($additional, $server);

        $group_id = $additional['group_id'];
        $time = $timeId ?? ($additional['time'] ?? 0);

        $db = dbal()->database($dbConnection->dbname);
        $dbusers = $this->getDbUsers($db, $steamid64->value, $sid);

        if (!empty($dbusers)) {
            $dbuser = $dbusers[0];
            $this->confirmUserGroup($dbuser, $group_id, $server);
            $this->updateOrInsertUser($db, $steamid64->value, $sid, $group_id, $time, $user, $dbuser);
        } else {
            $this->updateOrInsertUser($db, $steamid64->value, $sid, $group_id, $time, $user);
        }

        if ($server->rcon) {
            $this->updateAdmins($server);
        }

        return true;
    }

    private function getSteamId(User $user)
    {
        $steamid64 = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

        if (!$steamid64 || !$steamid64->value) {
            throw new UserSocialException("Steam");
        }

        return $steamid64;
    }

    private function getDbUsers($db, $steamid64, $sid)
    {
        return $db->table("admins")->select()
            ->where('sid', $steamid64)
            ->andWhere('server_id', $sid)
            ->fetchAll();
    }

    private function confirmUserGroup($dbuser, $group_id, $server)
    {
        if ($dbuser['group_id'] === $group_id) {
            $this->confirm(__("givecore.add_time", [':server' => $server->name]));
        } else {
            $this->confirm(__("givecore.replace_group", [
                ':group_id' => $dbuser['group_id'],
                ':newGroup' => $group_id
            ]));
        }
    }

    private function updateOrInsertUser($db, $steamid64, $sid, $group_id, $time, $user, $currentGroup = null): void
    {
        $currentUnixTime = time();
        $expiresTime = $this->calculateExpiresTime($time, $currentUnixTime, $currentGroup, $group_id);

        if ($currentGroup) {
            $this->updateUser($db, $steamid64, $sid, $group_id, $expiresTime);
        } else {
            $this->insertUser($db, $steamid64, $sid, $group_id, $expiresTime, $user);
        }
    }

    private function calculateExpiresTime($time, $currentUnixTime, $currentGroup, $group_id): int
    {
        if ($currentGroup['group_id'] === $group_id) {
            return ($time === 0) ? 0 : ($currentGroup ? $currentGroup['end'] + $time : $currentUnixTime + $time);
        }
        return ($time === 0) ? 0 : $currentUnixTime + $time;
    }

    private function updateUser($db, $steamid64, $sid, $group_id, $expiresTime): void
    {
        $db->table('admins')
            ->update([
                'end' => $expiresTime,
                'group_id' => $group_id
            ])
            ->where('sid', $steamid64)
            ->andWhere('server_id', $sid)
            ->run();
    }

    private function insertUser($db, $steamid64, $sid, $group_id, $expiresTime, $user): void
    {
        $db->insert('admins')
            ->values([
                'end' => $expiresTime,
                'group_id' => $group_id,
                'sid' => $steamid64,
                'flags' => "",
                'immunity' => -1,
                'server_id' => $sid,
                'name' => $user->name,
            ])
            ->run();
    }

    private function updateAdmins(Server $server): void
    {
        $query = new SourceQuery();

        try {
            $query->Connect($server->ip, $server->port, 3, ($server->mod == 10) ? SourceQuery::GOLDSOURCE : SourceQuery::SOURCE);
            $query->SetRconPassword($server->rcon);
            $this->sendCommand($query, "css_reload_admins");
        } catch (\Exception $e) {
            throw new GiveDriverException($e->getMessage());
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
        return 'iksAdmin';
    }

    private function validateAdditionalParams(array $additional, Server $server): array
    {
        if (empty($additional['group_id'])) {
            throw new BadConfigurationException('group_id');
        }

        $dbConnection = $server->getDbConnection('IKSAdmin');
        if (!$dbConnection) {
            throw new BadConfigurationException('db connection IKSAdmin is not exists');
        }

        $dbParams = Json::decode($dbConnection->additional);
        if (empty($dbParams->sid)) {
            throw new BadConfigurationException("SID {$server->name} for db connection is empty");
        }

        return [$dbConnection, $dbParams->sid];
    }
}
