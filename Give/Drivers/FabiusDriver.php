<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Exception;
use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Core\Rcon\RconService;
use Flute\Modules\GiveCore\Contracts\CheckableInterface;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Flute\Modules\GiveCore\Support\CheckableTrait;
use xPaw\SteamID\SteamID;

class FabiusDriver extends AbstractDriver implements CheckableInterface
{
    use CheckableTrait;

    protected const MOD_KEY = 'FabiusVIP';

    protected string $prefix = 'vip_';

    // ── Metadata ───────────────────────────────────────────────────

    public function alias(): string
    {
        return 'fabius';
    }

    public function name(): string
    {
        return __('givecore.drivers.fabius.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.fabius.description');
    }

    public function icon(): string
    {
        return 'ph.bold.crown-bold';
    }

    public function category(): string
    {
        return 'vip';
    }

    public function sourceUrl(): ?string
    {
        return 'https://github.com/partiusfabaa/cs2-LiteVIP';
    }

    public function supportedGames(): array
    {
        return ['CS2'];
    }

    public function requiredSocial(array $config = []): ?string
    {
        return 'Steam';
    }

    public function deliverFields(): array
    {
        return [
            'group' => [
                'type' => 'text',
                'label' => __('givecore.fields.group'),
                'required' => true,
                'placeholder' => __('givecore.fields.group_placeholder'),
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

    // ── Condition check ────────────────────────────────────────────

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

        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            return false;
        }

        $dbConnection = $server->getDbConnection('FabiusVIP');
        if (!$dbConnection) {
            return false;
        }

        $prefix = $this->getPrefix($dbConnection->dbname, 'vip_');
        $accountId = $this->normalizeToAccountId($steamId);

        $db = dbal()->database($dbConnection->dbname);

        $findServer = $db
            ->select()
            ->from($prefix . 'servers')
            ->where('serverIp', '=', $server->ip)
            ->where('port', '=', $server->port)
            ->fetchAll();

        if (empty($findServer)) {
            return false;
        }

        $sid = $findServer[0]['serverId'];

        $results = $db
            ->select()
            ->from($prefix . 'users')
            ->where('account_id', '=', $accountId)
            ->andWhere('sid', '=', $sid)
            ->fetchAll();

        if (empty($results)) {
            return false;
        }

        $record = $results[0];

        if ((int) $record['expires'] !== 0 && (int) $record['expires'] < time()) {
            return false;
        }

        $group = $params['group'] ?? '';

        return empty($group) || strtolower(trim($record['group'])) === strtolower(trim($group));
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

        [$dbConnection, $sid] = $this->validateAdditionalParams($additional, $server);

        $this->prefix = $this->getPrefix($dbConnection->dbname, 'vip_');

        $accountId = $this->normalizeToAccountId($steam->value);
        $group = $additional['group'];
        $time = (int) ($timeId ?: ($additional['time'] ?? 0));

        $db = dbal()->database($dbConnection->dbname);
        $dbusers = $db
            ->select()
            ->from($this->prefix . 'users')
            ->where('account_id', '=', $accountId)
            ->andWhere('sid', '=', $sid)
            ->fetchAll();

        $hasActiveRecord = !empty($dbusers)
            && ((int) $dbusers[0]['expires'] === 0 || (int) $dbusers[0]['expires'] >= time());

        if (!empty($dbusers) && $hasActiveRecord) {
            $dbuser = $dbusers[0];

            if ($simulate && !$ignoreErrors) {
                if (strtolower(trim($dbuser['group'])) === strtolower(trim($group))) {
                    $this->confirm(__('givecore.add_time', [
                        ':server' => $server->name,
                    ]), null, [
                        'type' => 'add_time',
                        'server' => $server->name,
                    ]);
                } else {
                    $this->confirm(__('givecore.replace_group', [
                        ':group' => $dbuser['group'],
                        ':newGroup' => $group,
                    ]), null, [
                        'type' => 'replace_group',
                        'existingGroup' => $dbuser['group'],
                        'newGroup' => $group,
                    ]);
                }
            }

            if (!$simulate) {
                $this->updateOrInsertUser($db, $accountId, $sid, $group, $time, $user, $dbuser);
            }
        } else if (!empty($dbusers)) {
            if (!$simulate) {
                $this->updateOrInsertUser($db, $accountId, $sid, $group, $time, $user, $dbusers[0]);
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

    // ── Private helpers ────────────────────────────────────────────

    protected function normalizeToAccountId(string $steamId): string
    {
        try {
            $steamID = new SteamID($steamId);

            return $steamID->GetAccountID();
        } catch (Exception $e) {
            logs()->error('Failed to normalize Steam ID: ' . $e->getMessage());

            return $steamId;
        }
    }

    private function updateVips(Server $server): void
    {
        try {
            app(RconService::class)->execute($server, 'vip_reload');
        } catch (Exception $e) {
            logs()->error($e);
        }
    }

    private function validateAdditionalParams(array $additional, Server $server): array
    {
        if (empty($additional['group'])) {
            throw BadConfigurationException::noGroup($server->name);
        }

        $dbConnection = $server->getDbConnection('FabiusVIP');
        if (!$dbConnection) {
            throw BadConfigurationException::noDbConnection('FabiusVIP', $server->name);
        }

        $prefix = $this->getPrefix($dbConnection->dbname, 'vip_');
        $db = dbal()->database($dbConnection->dbname);
        $findServer = $db
            ->select()
            ->from($prefix . 'servers')
            ->where('serverIp', '=', $server->ip)
            ->where('port', '=', $server->port)
            ->fetchAll();

        if (empty($findServer)) {
            throw BadConfigurationException::noDbConnection('FabiusVIP', $server->name);
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
            $db
                ->table($this->prefix . 'users')
                ->update([
                    'expires' => $expiresTime,
                    'group' => $group,
                ])
                ->where('account_id', '=', $accountId)
                ->andWhere('sid', '=', $sid)
                ->run();
        } else {
            $db
                ->insert($this->prefix . 'users')
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
