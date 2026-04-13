<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Core\Rcon\RconService;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\GiveDriverException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;

class FreshBansUnbanDriver extends AbstractDriver
{
    protected const MOD_KEY = 'FreshBans';

    public function alias(): string
    {
        return 'freshbans';
    }

    public function name(): string
    {
        return __('givecore.drivers.freshbans.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.freshbans.description');
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
        return 'https://dev-cs.ru/resources/freshbans.90/';
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

        $prefix = $this->getPrefix($dbConnection->dbname, 'freshbans_');
        $db = dbal()->database($dbConnection->dbname);

        $steamId2 = steam()->steamid($steam->value)->RenderSteam2();

        $activeBans = $db
            ->select()
            ->from($prefix . 'bans')
            ->where('player_id', $steamId2)
            ->where('expired', 0)
            ->fetchAll();

        if (empty($activeBans)) {
            $valveId = 'VALVE_' . substr($steamId2, 6);
            $activeBans = $db
                ->select()
                ->from($prefix . 'bans')
                ->where('player_id', $valveId)
                ->where('expired', 0)
                ->fetchAll();
        }

        if (empty($activeBans)) {
            if (!$ignoreErrors) {
                throw new GiveDriverException(__('givecore.drivers.freshbans.no_active_ban'));
            }

            return false;
        }

        if (!$simulate) {
            foreach ($activeBans as $ban) {
                $db->update($prefix . 'bans', ['expired' => 1])
                    ->where('id', $ban['id'])
                    ->run();
            }

            try {
                $rconService = app(RconService::class);
                if ($rconService->isAvailable($server)) {
                    $rconService->execute($server, 'amx_reloadadmins');
                }
            } catch (\Throwable $e) {
            }
        }

        return !$simulate;
    }
}
