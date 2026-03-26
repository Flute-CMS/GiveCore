<?php

declare(strict_types=1);

namespace Flute\Modules\GiveCore\Check\Drivers\Bans;

use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Throwable;

class ZenithBansConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'ZenithBans';

    public function alias(): string
    {
        return 'zenithbans_ban';
    }

    public function name(): string
    {
        return __('givecore.drivers.zenithbans_ban.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.zenithbans_ban.description');
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

        $serverId = (int) ($params['server_id'] ?? 0);

        try {
            $server = $this->resolveServer($serverId);
            if (!$server) {
                return false;
            }

            $dbConnection = $server->getDbConnection(static::MOD_KEY);
            if (!$dbConnection) {
                return false;
            }

            $prefix = $this->getDbPrefix($dbConnection->dbname, 'zenith_bans_');
            $db = dbal()->database($dbConnection->dbname);

            $steamId64 = steam()->steamid($steamId)->ConvertToUInt64();

            $player = $db->select(['id'])
                ->from($prefix . 'players')
                ->where('steam_id', (int) $steamId64)
                ->fetchAll();

            if (empty($player)) {
                return false;
            }

            $playerId = (int) $player[0]['id'];
            $now = date('Y-m-d H:i:s');

            $bans = $db
                ->select()
                ->from($prefix . 'punishments')
                ->where('player_id', $playerId)
                ->where('type', 'ban')
                ->where('status', 'active')
                ->where(static function ($q) use ($now) {
                    $q->where('expires_at', null)->orWhere('expires_at', '>', $now);
                })
                ->fetchAll();

            return !empty($bans);
        } catch (Throwable $e) {
            logs()->warning("Zenith Bans check failed for user {$user->id}: " . $e->getMessage());

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
