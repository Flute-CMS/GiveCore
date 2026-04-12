<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Contracts\CheckableInterface;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Flute\Modules\GiveCore\Support\CheckableTrait;

class SimpleAdminDriver extends AbstractDriver implements CheckableInterface
{
    use CheckableTrait;

    protected const MOD_KEY = 'SimpleAdmin';

    protected string $prefix = '';

    public function alias(): string
    {
        return 'simpleadmin';
    }

    public function name(): string
    {
        return __('givecore.drivers.simpleadmin.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.simpleadmin.description');
    }

    public function icon(): string
    {
        return 'ph.bold.shield-check-bold';
    }

    public function category(): string
    {
        return 'admin';
    }

    public function sourceUrl(): ?string
    {
        return 'https://github.com/daffyyyy/CS2-SimpleAdmin';
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
                'help' => __('givecore.fields.sa_group_help'),
            ],
            'flags' => [
                'type' => 'text',
                'label' => __('givecore.fields.flags'),
                'required' => false,
                'placeholder' => __('givecore.fields.flags_placeholder'),
                'help' => __('givecore.fields.sa_flags_help'),
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

            $prefix = $this->getPrefix($dbConnection->dbname, 'sa_');
            $db = dbal()->database($dbConnection->dbname);

            $steamId64 = steam()->steamid($steamId)->ConvertToUInt64();
            $now = date('Y-m-d H:i:s');

            $admins = $db
                ->select()
                ->from($prefix . 'admins')
                ->where('player_steamid', $steamId64)
                ->where(static function ($q) use ($now) {
                    $q->where('ends', null)->orWhere('ends', '')->orWhere('ends', '>', $now);
                })
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

                if (str_starts_with($adminFlags, '#')) {
                    $groupId = substr($adminFlags, 1);
                    $groups = $db
                        ->select()
                        ->from($prefix . 'admins_groups')
                        ->where('id', $groupId)
                        ->fetchAll();

                    $adminFlags = !empty($groups) ? ($groups[0]['flags'] ?? '') : '';
                }

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
            throw new BadConfigurationException(
                'db connection SimpleAdmin does not exist (server: ' . $server->name . ')',
            );
        }

        if (empty($additional['group']) && empty($additional['flags'])) {
            throw new BadConfigurationException('group or flags is required');
        }

        $this->prefix = $this->getPrefix($dbConnection->dbname, 'sa_');
        $db = dbal()->database($dbConnection->dbname);

        $steamId64 = steam()->steamid($steam->value)->ConvertToUInt64();
        $groupName = $additional['group'] ?? '';
        $flags = $additional['flags'] ?? '';
        $immunity = (int) ($additional['immunity'] ?? 0);
        $time = $timeId ?? (int) ($additional['time'] ?? 0);

        $serverId = (int) ($server->id ?? 0);

        $now = date('Y-m-d H:i:s');

        $existing = $db
            ->select()
            ->from($this->prefix . 'admins')
            ->where('player_steamid', $steamId64)
            ->where('server_id', $serverId)
            ->fetchAll();

        if ($time === 0) {
            $ends = null;
        } else {
            $ends = date('Y-m-d H:i:s', time() + $time);
        }

        if (!empty($existing)) {
            $admin = $existing[0];

            if ($time > 0 && !empty($admin['ends'])) {
                $existingEnd = strtotime($admin['ends']);
                $base = max($existingEnd, time());
                $ends = date('Y-m-d H:i:s', $base + $time);
            }

            if (!$ignoreErrors) {
                $this->confirm(__('givecore.update_admin', [
                    ':name' => $admin['player_name'] ?? $user->name,
                    ':group' => $groupName,
                ]));
            }

            $updateData = [
                'immunity' => $immunity,
                'ends' => $ends,
            ];
            if (!empty($flags)) {
                $updateData['flags'] = $flags;
            }
            if (!empty($groupName)) {
                $updateData['group_name'] = $groupName;
            }

            $db
                ->update($this->prefix . 'admins', $updateData)
                ->where('player_steamid', $steamId64)
                ->where('server_id', $serverId)
                ->run();
        } else {
            $adminFlags = $flags;
            if (empty($adminFlags) && !empty($groupName)) {
                $groups = $db
                    ->select()
                    ->from($this->prefix . 'admins_groups')
                    ->where('name', $groupName)
                    ->fetchAll();

                if (!empty($groups)) {
                    $adminFlags = '#' . $groups[0]['id'];
                }
            }

            $db
                ->insert($this->prefix . 'admins')
                ->values([
                    'player_steamid' => $steamId64,
                    'player_name' => $user->name,
                    'flags' => $adminFlags,
                    'immunity' => $immunity,
                    'server_id' => $serverId,
                    'ends' => $ends,
                    'created' => $now,
                ])
                ->run();
        }

        return true;
    }
}
