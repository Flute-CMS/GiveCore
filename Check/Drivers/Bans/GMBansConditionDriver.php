<?php

declare(strict_types = 1);

namespace Flute\Modules\GiveCore\Check\Drivers\Bans;

use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Throwable;

class GMBansConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'GMBans';

    public function alias(): string
    {
        return 'gmbans_ban';
    }

    public function name(): string
    {
        return __('givecore.drivers.gmbans_ban.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.gmbans_ban.description');
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

            $prefix = $this->getDbPrefix($dbConnection->dbname, 'amx_');
            $db = dbal()->database($dbConnection->dbname);

            $steamId2 = steam()->steamid($steamId)->RenderSteam2();

            $now = time();

            $bans = $db
                ->select()
                ->from($prefix . 'bans')
                ->where('player_id', $steamId2)
                ->where('expired', 0)
                ->fetchAll();

            if (empty($bans)) {
                return false;
            }

            $ban = $bans[0];

            $banLength = (int) ( $ban['ban_length'] ?? 0 );
            $banCreated = (int) ( $ban['ban_created'] ?? 0 );

            if ($banLength === 0) {
                return true;
            }

            $expires = $banCreated + ( $banLength * 60 );

            return $expires > $now;
        } catch (Throwable $e) {
            logs()->warning("RoleSync GMBans ban check failed for user {$user->id}: " . $e->getMessage());

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
