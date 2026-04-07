<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Exception;
use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Contracts\CheckableInterface;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Flute\Modules\GiveCore\Support\CheckableTrait;
use xPaw\SteamID\SteamID;

use function mt_rand;

class SourceBansDriver extends AbstractDriver implements CheckableInterface
{
    use CheckableTrait;

    protected const MOD_KEY = 'SourceBans';

    protected string $prefix = '';

    // ── Metadata ───────────────────────────────────────────────────

    public function alias(): string
    {
        return 'sourcebans';
    }

    public function name(): string
    {
        return __('givecore.drivers.sourcebans.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.sourcebans.description');
    }

    public function icon(): string
    {
        return 'ph.bold.shield-star-bold';
    }

    public function category(): string
    {
        return 'admin';
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
                'label' => __('givecore.fields.admin_group'),
                'required' => false,
                'placeholder' => __('givecore.fields.group_optional_placeholder'),
                'help' => __('givecore.fields.sb_group_help'),
            ],
            'flags' => [
                'type' => 'text',
                'label' => __('givecore.fields.flags'),
                'required' => false,
                'placeholder' => __('givecore.fields.flags_placeholder'),
                'help' => __('givecore.fields.sb_flags_help'),
            ],
            'immunity' => [
                'type' => 'number',
                'label' => __('givecore.fields.immunity'),
                'required' => false,
                'min' => 0,
                'max' => 100,
            ],
            'password' => [
                'type' => 'text',
                'label' => __('givecore.fields.password'),
                'required' => false,
                'placeholder' => __('givecore.fields.password_placeholder'),
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
                'default' => 0,
            ],
            'flags' => [
                'type' => 'text',
                'label' => __('givecore.fields.required_flags'),
                'required' => false,
                'placeholder' => __('givecore.fields.flags_placeholder'),
            ],
        ];
    }

    // ── Condition check ────────────────────────────────────────────

    public function check(User $user, array $params = []): bool
    {
        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            return false;
        }

        $authId = $this->convertSteamId($steamId);

        $servers = !empty($params['server_id'])
            ? [$this->getServerById((int) $params['server_id'])]
            : $this->getServersWithConnection(static::MOD_KEY);

        foreach ($servers as $server) {
            if (!$server) {
                continue;
            }

            $dbConnection = $server->getDbConnection('SourceBans');
            if (!$dbConnection) {
                continue;
            }

            $prefix = $this->getPrefix($dbConnection->dbname, 'sb_');
            $db = dbal()->database($dbConnection->dbname);

            $admin = $db
                ->select()
                ->from($prefix . 'admins')
                ->where('authid', '=', $authId)
                ->fetchAll();

            if (empty($admin)) {
                continue;
            }

            $record = $admin[0];

            $minImmunity = (int) ( $params['min_immunity'] ?? 0 );
            if ($minImmunity > 0 && (int) ( $record['immunity'] ?? 0 ) < $minImmunity) {
                continue;
            }

            $requiredFlags = $params['flags'] ?? '';
            if (!empty($requiredFlags)) {
                $srvFlags = $record['srv_flags'] ?? '';
                if (!$this->hasFlags($srvFlags, $requiredFlags)) {
                    continue;
                }
            }

            return true;
        }

        return false;
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

        $this->prefix = $this->getPrefix($dbConnection->dbname, 'sb_');

        $db = dbal()->database($dbConnection->dbname);
        $authId = $this->convertSteamId($steam->value);
        $groupName = $additional['group'] ?? '';
        $flags = $additional['flags'] ?? '';
        $immunity = (int) ( $additional['immunity'] ?? 0 );
        $password = $additional['password'] ?? $this->generatePassword();

        $existingAdmin = $db
            ->select()
            ->from($this->prefix . 'admins')
            ->where('authid', '=', $authId)
            ->fetchAll();

        if (!empty($existingAdmin)) {
            $admin = $existingAdmin[0];
            if (!$ignoreErrors) {
                $this->confirm(__('givecore.update_admin', [
                    ':name' => $admin['user'],
                    ':group' => $groupName ?: $flags,
                ]), null, [
                    'type' => 'update_admin',
                    'existingGroup' => $admin['user'],
                    'newGroup' => $groupName ?: $flags,
                ]);
            }

            if (!$simulate) {
                $updateData = ['immunity' => $immunity];
                if (!empty($groupName)) {
                    $updateData['srv_group'] = $groupName;
                }
                if (!empty($flags)) {
                    $updateData['srv_flags'] = $flags;
                }

                $db
                    ->update($this->prefix . 'admins', $updateData)
                    ->where('aid', '=', $admin['aid'])
                    ->run();
            }

            $adminId = $admin['aid'];
        } else {
            if (!$simulate) {
                $insertData = [
                    'user' => $user->name,
                    'authid' => $authId,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'gid' => 0,
                    'email' => $user->email ?? '',
                    'srv_group' => $groupName,
                    'srv_flags' => $flags,
                    'immunity' => $immunity,
                    'lastvisit' => time(),
                ];

                $db
                    ->insert($this->prefix . 'admins')
                    ->values($insertData)
                    ->run();

                $results = $db
                    ->select('aid')
                    ->from($this->prefix . 'admins')
                    ->where('authid', '=', $authId)
                    ->orderBy('aid', 'DESC')
                    ->fetchAll();

                if (empty($results)) {
                    throw new Exception('Failed to get admin ID after creation');
                }

                $adminId = $results[0]['aid'];
            } else {
                $adminId = 0;
            }
        }

        $sourceBansServerId = 0;

        if (!empty($server->ip) && !empty($server->port)) {
            $servers = $db
                ->select('sid')
                ->from($this->prefix . 'servers')
                ->where('ip', '=', $server->ip)
                ->andWhere('port', '=', $server->port)
                ->fetchAll();

            if (!empty($servers)) {
                $sourceBansServerId = $servers[0]['sid'];
            }
        }

        if (!$simulate) {
            $this->assignServerToAdmin($db, $adminId, $sourceBansServerId);
        }

        return !$simulate;
    }

    // ── Private helpers ────────────────────────────────────────────

    protected function hasFlags(string $userFlags, string $requiredFlags): bool
    {
        for ($i = 0; $i < strlen($requiredFlags); $i++) {
            if (strpos($userFlags, $requiredFlags[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    protected function assignServerToAdmin($db, $adminId, $serverId): void
    {
        $existingAssignment = $db
            ->select()
            ->from($this->prefix . 'admins_servers_groups')
            ->where('admin_id', '=', $adminId)
            ->andWhere('server_id', '=', $serverId)
            ->fetchAll();

        if (empty($existingAssignment)) {
            $db
                ->insert($this->prefix . 'admins_servers_groups')
                ->values([
                    'admin_id' => $adminId,
                    'server_id' => $serverId,
                    'group_id' => -1,
                    'srv_group_id' => -1,
                ])
                ->run();
        }
    }

    protected function generatePassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $password;
    }

    protected function convertSteamId(string $steamId): string
    {
        try {
            $steamID = new SteamID($steamId);

            return $steamID->RenderSteam2();
        } catch (Exception $e) {
            logs()->error('Failed to convert Steam ID: ' . $e->getMessage());

            return $steamId;
        }
    }

    protected function validateAdditionalParams(array $additional, Server $server): array
    {
        if (empty($additional['group']) && empty($additional['flags'])) {
            throw new BadConfigurationException('group or flags is required');
        }

        $dbConnection = $server->getDbConnection('SourceBans');

        if (!$dbConnection) {
            throw new BadConfigurationException('db connection SourceBans is not exists');
        }

        return [$dbConnection, 0];
    }
}
