<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Core\Rcon\RconService;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\GiveDriverException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;

class AmxUnbanDriver extends AbstractDriver
{
    protected const MOD_KEY = 'Amx';

    public function alias(): string
    {
        return 'amxunban';
    }

    public function name(): string
    {
        return __('givecore.drivers.amxunban.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.amxunban.description');
    }

    public function icon(): string
    {
        return 'ph.bold.shield-slash-bold';
    }

    public function category(): string
    {
        return 'CS 1.6';
    }

    public function sourceUrl(): ?string
    {
        return 'https://github.com/alliedmodders/amxmodx';
    }

    public function supportedGames(): array
    {
        return ['CS 1.6', 'Half-Life'];
    }

    public function requiredSocial(array $config = []): ?string
    {
        return 'Steam';
    }

    public function deliverFields(): array
    {
        return [];
    }

    public function deliver(
        User $user,
        Server $server,
        array $additional = [],
        ?int $timeId = null,
        bool $ignoreErrors = false,
    ): bool {
        $simulate = false;
        if (array_key_exists('__simulate', $additional)) {
            $simulate = (bool) $additional['__simulate'];
            unset($additional['__simulate']);
        }

        $steam = $user->getSocialNetwork('Steam') ?? $user->getSocialNetwork('HttpsSteam');
        if (!$steam?->value) {
            throw new UserSocialException('Steam');
        }

        $dbConnection = $server->getDbConnection(static::MOD_KEY);
        if (!$dbConnection) {
            throw new BadConfigurationException(static::MOD_KEY, $server->name);
        }

        $prefix = $this->getPrefix($dbConnection->dbname, 'amx_');
        $db = dbal()->database($dbConnection->dbname);

        $steamId2 = steam()->steamid($steam->value)->RenderSteam2();

        $activeBans = $this->findActiveBans($db, $prefix, $steamId2);

        if (empty($activeBans)) {
            $valveId = 'VALVE_' . substr($steamId2, 6);
            $activeBans = $this->findActiveBans($db, $prefix, $valveId);
        }

        if (empty($activeBans)) {
            if (!$ignoreErrors) {
                throw new GiveDriverException(__('givecore.drivers.amxunban.no_active_ban'));
            }

            return false;
        }

        if (!$simulate) {
            foreach ($activeBans as $ban) {
                $db->update($prefix . 'bans', [
                    'expired' => 1,
                    'unban_type' => -2,
                    'ban_closed' => $user->id,
                ])
                    ->where('bid', $ban['bid'])
                    ->run();
            }

            $this->sendRcon($server, 'amx_reloadadmins');
        }

        return !$simulate;
    }

    protected function findActiveBans($db, string $prefix, string $playerId): array
    {
        $bans = $db
            ->select()
            ->from($prefix . 'bans')
            ->where('player_id', $playerId)
            ->where('expired', 0)
            ->fetchAll();

        $now = time();

        return array_filter($bans, static function ($ban) use ($now) {
            $banLength = (int) ($ban['ban_length'] ?? 0);
            $banCreated = (int) ($ban['ban_created'] ?? 0);

            if ($banLength === 0) {
                return true;
            }

            return ($banCreated + $banLength * 60) > $now;
        });
    }

    protected function sendRcon(Server $server, string $command): void
    {
        try {
            $rconService = app(RconService::class);

            if ($rconService->isAvailable($server)) {
                $rconService->execute($server, $command);
            }
        } catch (\Throwable $e) {
            // RCON is optional
        }
    }
}
