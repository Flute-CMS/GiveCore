<?php

declare(strict_types = 1);

namespace Flute\Modules\GiveCore\Check\Drivers\Bans;

use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Throwable;

class SimpleAdminConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'SimpleAdmin';

    public function alias(): string
    {
        return 'simpleadmin_ban';
    }

    public function name(): string
    {
        return __('givecore.drivers.simpleadmin_ban.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.simpleadmin_ban.description');
    }

    public function icon(): string
    {
        return 'ph.bold.gavel-bold';
    }

    public function category(): string
    {
        return 'ban';
    }

    public function check(User $user, array $params = []): bool
    {
        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            return false;
        }

        $serverId = (int) ( $params['server_id'] ?? 0 );

        try {
            $server = $this->resolveServer($serverId);
            if (!$server) {
                return false;
            }

            $dbConnection = $server->getDbConnection(static::MOD_KEY);
            if (!$dbConnection) {
                return false;
            }

            $prefix = $this->getDbPrefix($dbConnection->dbname, 'sa_');
            $db = dbal()->database($dbConnection->dbname);

            $steamId64 = steam()->steamid($steamId)->ConvertToUInt64();

            $now = date('Y-m-d H:i:s');

            $bans = $db
                ->select()
                ->from($prefix . 'bans')
                ->where('player_steamid', $steamId64)
                ->where('status', 'ACTIVE')
                ->where(static function ($q) use ($now) {
                    $q->where('ends', null)->orWhere('ends', '')->orWhere('ends', '>', $now);
                })
                ->fetchAll();

            return !empty($bans);
        } catch (Throwable $e) {
            logs()->warning("RoleSync SimpleAdmin ban check failed for user {$user->id}: " . $e->getMessage());

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
        ];
    }
}
