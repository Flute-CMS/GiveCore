<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Nette\Utils\Json;
use xPaw\SourceQuery\SourceQuery;

/**
 * Params:
 *
 * group - group name
 *
 * time - time in seconds
 */
class VipDriver extends AbstractDriver
{
    protected string $prefix = '';

    public function deliver(User $user, Server $server, array $additional = [], ?int $timeId = null, bool $ignoreErrors = false): bool
    {
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');

        if (!$steam->value) {
            throw new UserSocialException("Steam");
        }

        [$dbConnection, $sid] = $this->validateAdditionalParams($additional, $server);

        $this->prefix = $this->getPrefix($dbConnection->dbname, 'vip_');

        $accountId = steam()->steamid($steam->value)->GetAccountID();
        $simulate = false;
        if (array_key_exists('__simulate', $additional)) {
            $simulate = (bool)$additional['__simulate'];
            unset($additional['__simulate']);
        }

        $group = $additional['group'];
        $time = !$timeId ? ($additional['time'] ?? 0) : $timeId;

        $db = dbal()->database($dbConnection->dbname);
        $dbusers = $db->select()
            ->from($this->prefix.'users')
            ->where('account_id', $accountId)
            ->andWhere('sid', $sid)
            ->fetchAll();

        if (!empty($dbusers)) {
            $dbuser = $dbusers[0];

            $currentGroup = trim(strtolower($dbuser['group']));
            $desiredGroup = trim(strtolower($group));

            if ($currentGroup === $desiredGroup) {
                if (!$ignoreErrors) {
                    $this->confirm(__("givecore.add_time", [
                        ':server' => $server->name,
                    ]));
                }
            } else {
                if (!$ignoreErrors) {
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
            throw new BadConfigurationException('group in configuration is required');
        }

        $dbConnection = $server->getDbConnection('VIP');
        if (!$dbConnection) {
            throw new BadConfigurationException('db connection VIP is not exists (server: ' . $server->name . ')');
        }

        $dbParams = Json::decode($dbConnection->additional);
        if (!isset($dbParams->sid) || $dbParams->sid === '' || $dbParams->sid === null) {
            throw new BadConfigurationException("SID {$server->name} for db connection is empty");
        }

        return [$dbConnection, (int) $dbParams->sid];
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
                ->where('account_id', $accountId)
                ->andWhere('sid', $sid)
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
}
