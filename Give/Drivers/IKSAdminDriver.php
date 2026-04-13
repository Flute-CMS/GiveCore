<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Contracts\CheckableInterface;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Flute\Modules\GiveCore\Support\CheckableTrait;
use Nette\Utils\Json;

class IKSAdminDriver extends AbstractDriver implements CheckableInterface
{
    use CheckableTrait;

    protected const MOD_KEY = 'IKS';

    protected string $prefix = '';

    public function alias(): string
    {
        return 'iks';
    }

    public function name(): string
    {
        return __('givecore.drivers.iks.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.iks.description');
    }

    public function icon(): string
    {
        return 'ph.bold.shield-star-bold';
    }

    public function category(): string
    {
        return 'admin';
    }

    public function sourceUrl(): ?string
    {
        return 'https://github.com/Iksix/Iks_Admin';
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
                'required' => false,
                'placeholder' => __('givecore.fields.group_optional_placeholder'),
                'help' => __('givecore.fields.iks_group_help'),
            ],
            'flags' => [
                'type' => 'text',
                'label' => __('givecore.fields.flags'),
                'required' => false,
                'placeholder' => __('givecore.fields.flags_placeholder'),
                'help' => __('givecore.fields.iks_flags_help'),
            ],
            'immunity' => [
                'type' => 'number',
                'label' => __('givecore.fields.immunity'),
                'required' => false,
                'min' => 0,
                'max' => 100,
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
            ],
            'flags' => [
                'type' => 'text',
                'label' => __('givecore.fields.required_flags'),
                'required' => false,
                'placeholder' => __('givecore.fields.flags_placeholder'),
            ],
        ];
    }

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

            $dbConnection = $server->getDbConnection(static::MOD_KEY);
            if (!$dbConnection) {
                continue;
            }

            $prefix = $this->getPrefix($dbConnection->dbname, 'iks_');
            $db = dbal()->database($dbConnection->dbname);

            $steamId64 = steam()->steamid($steamId)->ConvertToUInt64();

            $admins = $db
                ->select()
                ->from($prefix . 'admins')
                ->where('steam_id', $steamId64)
                ->fetchAll();

            if (empty($admins)) {
                continue;
            }

            $admin = $admins[0];

            $minImmunity = (int) ($params['min_immunity'] ?? 0);
            if ($minImmunity > 0 && ($admin['immunity'] ?? 0) < $minImmunity) {
                continue;
            }

            $requiredFlags = $params['flags'] ?? '';
            if (!empty($requiredFlags)) {
                $adminFlags = $admin['flags'] ?? '';
                foreach (str_split($requiredFlags) as $flag) {
                    if (!str_contains($adminFlags, $flag)) {
                        continue 2;
                    }
                }
            }

            return true;
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
        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');
        if (!$steam?->value) {
            throw new UserSocialException('Steam');
        }

        $dbConnection = $server->getDbConnection(static::MOD_KEY);
        if (!$dbConnection) {
            throw BadConfigurationException::noDbConnection('IKS', $server->name);
        }

        if (empty($additional['group']) && empty($additional['flags'])) {
            throw BadConfigurationException::noGroup($server->name);
        }

        $simulate = false;
        if (array_key_exists('__simulate', $additional)) {
            $simulate = (bool) $additional['__simulate'];
            unset($additional['__simulate']);
        }

        $this->prefix = $this->getPrefix($dbConnection->dbname, 'iks_');
        $db = dbal()->database($dbConnection->dbname);

        $steamId64 = steam()->steamid($steam->value)->ConvertToUInt64();
        $groupName = $additional['group'] ?? '';
        $flags = $additional['flags'] ?? '';
        $immunity = (int) ($additional['immunity'] ?? 0);
        $time = $timeId ?? (int) ($additional['time'] ?? 0);

        $serverId = $this->resolveExternalServerId($dbConnection, $server);

        $existing = $db
            ->select()
            ->from($this->prefix . 'admins')
            ->where('steam_id', $steamId64)
            ->where('server_id', $serverId)
            ->fetchAll();

        $ends = $time === 0 ? 0 : time() + $time;

        if (!empty($existing)) {
            $admin = $existing[0];

            if ($time > 0 && (int) ($admin['end'] ?? 0) !== 0) {
                $base = max((int) $admin['end'], time());
                $ends = $base + $time;
            }

            if ($simulate && !$ignoreErrors) {
                $this->confirm(__('givecore.update_admin', [
                    ':name' => $admin['name'] ?? $user->name,
                    ':group' => $groupName,
                ]));
            }

            if (!$simulate) {
                $updateData = [];
                if (!empty($groupName)) {
                    $updateData['group_name'] = $groupName;
                }
                if (!empty($flags)) {
                    $updateData['flags'] = $flags;
                }
                $updateData['immunity'] = $immunity;
                $updateData['end'] = $ends;

                $db
                    ->update($this->prefix . 'admins', $updateData)
                    ->where('steam_id', $steamId64)
                    ->where('server_id', $serverId)
                    ->run();
            }
        } else {
            if (!$simulate) {
                $db
                    ->insert($this->prefix . 'admins')
                    ->values([
                        'steam_id' => $steamId64,
                        'name' => $user->name,
                        'flags' => $flags,
                        'immunity' => $immunity,
                        'group_name' => $groupName,
                        'server_id' => $serverId,
                        'end' => $ends,
                        'created_at' => time(),
                    ])
                    ->run();
            }
        }

        return !$simulate;
    }

    protected function resolveExternalServerId($dbConnection, Server $server): int
    {
        $dbParams = Json::decode($dbConnection->additional ?? '{}');
        $explicit = (int) ($dbParams->sid ?? $dbParams->server_id ?? 0);

        if ($explicit > 0) {
            return $explicit;
        }

        $prefix = $this->prefix ?? $this->getPrefix($dbConnection->dbname, 'iks_');
        $db = dbal()->database($dbConnection->dbname);
        $address = $server->ip . ':' . $server->port;

        try {
            $row = $db->select('id')->from($prefix . 'servers')
                ->where('address', $address)
                ->fetchAll();

            if (!empty($row)) {
                $this->persistSid($dbConnection, (int) $row[0]['id']);
                return (int) $row[0]['id'];
            }
        } catch (\Throwable $e) {
        }

        return 0;
    }

    protected function persistSid($dbConnection, int $sid): void
    {
        try {
            $data = json_decode($dbConnection->additional ?? '{}', true) ?: [];
            $data['sid'] = $sid;
            $dbConnection->additional = json_encode($data);
            $dbConnection->saveOrFail();
        } catch (\Throwable $e) {
        }
    }
}
