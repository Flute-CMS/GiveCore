<?php

declare(strict_types = 1);

namespace Flute\Modules\GiveCore\Check\Drivers\Admin;

use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Throwable;

class AmxAdminConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'Amx';

    public function alias(): string
    {
        return 'amx_admin';
    }

    public function name(): string
    {
        return __('givecore.drivers.amx_admin.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.amx_admin.description');
    }

    public function icon(): string
    {
        return 'ph.bold.shield-star-bold';
    }

    public function category(): string
    {
        return 'admin';
    }

    public function check(User $user, array $params = []): bool
    {
        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            return false;
        }

        $serverId = (int) ( $params['server_id'] ?? 0 );
        $requiredFlags = $params['flags'] ?? '';

        try {
            $server = $this->resolveServer($serverId);
            if (!$server) {
                return false;
            }

            $dbConnection = $server->getDbConnection(static::MOD_KEY);
            if (!$dbConnection) {
                return false;
            }

            $prefix = $this->getDbPrefix($dbConnection->dbname, 'amx_');
            $db = dbal()->database($dbConnection->dbname);

            $steamId2 = steam()->steamid($steamId)->RenderSteam2();
            $steamIdShort = substr($steamId2, 6);

            $admins = $db
                ->select()
                ->from($prefix . 'amxadmins')
                ->where(static function ($q) use ($steamId2, $steamIdShort) {
                    $q
                        ->where('steamid', $steamId2)
                        ->orWhere('steamid', 'VALVE_' . $steamIdShort)
                        ->orWhere('steamid', 'STEAM_' . $steamIdShort);
                })
                ->fetchAll();

            if (empty($admins)) {
                return false;
            }

            $admin = $admins[0];

            $expires = (int) ( $admin['expired'] ?? 0 );
            if ($expires > 0 && $expires < time()) {
                return false;
            }

            if (!empty($requiredFlags)) {
                $adminFlags = $admin['access'] ?? '';
                foreach (str_split($requiredFlags) as $flag) {
                    if (!str_contains($adminFlags, $flag)) {
                        return false;
                    }
                }
            }

            return true;
        } catch (Throwable $e) {
            logs()->warning("RoleSync AMX admin check failed for user {$user->id}: " . $e->getMessage());

            return false;
        }
    }

    public function checkFields(): array
    {
        return [
            'server_id' => [
                'type' => 'select',
                'label' => __('givecore.fields.server'),
                'required' => false,
                'options' => [0 => __('givecore.fields.any_server')] + $this->getAvailableServerOptions(),
            ],
            'flags' => [
                'type' => 'text',
                'label' => __('givecore.fields.required_flags'),
                'required' => false,
                'placeholder' => 'abcdefg',
            ],
        ];
    }
}
