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

class VipDriver extends AbstractDriver
{
    public function deliver(User $user, Server $server, array $additional = [], ?int $timeId = null): bool
    {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

        if (!$steam->value) {
            throw new UserSocialException("Steam");
        }

        [$dbConnection, $sid] = $this->validateAdditionalParams($additional, $server);

        $accountId = steam()->steamid($steam->value)->GetAccountID();
        $group = $additional['group'];
        $time = !$timeId ? ($additional['time'] ?? 0) : $timeId;

        $db = dbal()->database($dbConnection->dbname);
        $dbusers = $db->table("users")->select()
            ->where('account_id', $accountId)
            ->andWhere('sid', $sid)
            ->fetchAll();

        if (!empty($dbusers)) {
            $dbuser = $dbusers[0];

            if ($dbuser['group'] === $group)
                $this->confirm(__("givecore.add_time", [
                    ':server' => $server->name
                ]));
            else
                $this->confirm(__("givecore.replace_group", [
                    ':group' => $dbuser['group'],
                    ':newGroup' => $group
                ]));

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
            // throw new GiveDriverException($e->getMessage());
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
        return 'vip';
    }

    private function validateAdditionalParams(array $additional, Server $server): array
    {
        if (empty($additional['group'])) {
            throw new BadConfigurationException('group');
        }

        $dbConnection = $server->getDbConnection('VIP');
        if (!$dbConnection) {
            throw new BadConfigurationException('db connection VIP is not exists');
        }

        $dbParams = Json::decode($dbConnection->additional);
        if (empty($dbParams->sid)) {
            throw new BadConfigurationException("SID {$server->name} for db connection is empty");
        }

        return [$dbConnection, $dbParams->sid];
    }

    private function updateOrInsertUser($db, $accountId, $sid, $group, $time, $user, $currentGroup = null)
    {
        if ($currentGroup['group'] !== $group) {
            $expiresTime = ($time === 0) ? 0 : time() + $time;
        } else {
            $expiresTime = ($time === 0) ? 0 : ($currentGroup ? $currentGroup['expires'] + $time : time() + $time);
        }
        
        if ($currentGroup) {
            $db->table('users')
                ->update([
                    'expires' => $expiresTime,
                    'group' => $group
                ])
                ->where('account_id', $accountId)
                ->andWhere('sid', $sid)
                ->run();
        } else {
            $db->insert('users')
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
}
