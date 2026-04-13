<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;
use Nette\Utils\Json;

class K4SystemDriver extends AbstractDriver
{
    protected const MOD_KEY = 'K4';

    public function alias(): string
    {
        return 'k4system';
    }

    public function name(): string
    {
        return __('givecore.drivers.k4system.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.k4system.description');
    }

    public function icon(): string
    {
        return 'ph.bold.chart-line-up-bold';
    }

    public function category(): string
    {
        return 'CS2';
    }

    public function sourceUrl(): ?string
    {
        return 'https://github.com/KitsuneLab-Development/K4-System';
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
            'points' => [
                'type' => 'number',
                'label' => __('givecore.drivers.k4system_deliver.points'),
                'required' => true,
                'min' => 1,
                'placeholder' => '100',
            ],
        ];
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

        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            throw new UserSocialException('Steam');
        }

        $dbConnection = $server->getDbConnection(static::MOD_KEY);
        if (!$dbConnection) {
            throw new BadConfigurationException(static::MOD_KEY, $server->name);
        }

        $points = (int) ($additional['points'] ?? 0);
        if ($points <= 0) {
            throw BadConfigurationException::missingParam('points', $server->name);
        }

        $connAdditional = Json::decode($dbConnection->additional, true);
        $tablePrefix = (string) ($connAdditional['table_prefix'] ?? '');
        $ranksTable = $tablePrefix . ($connAdditional['ranks_table'] ?? 'k4ranks');

        $prefix = $this->getPrefix($dbConnection->dbname, '');
        $db = dbal()->database($dbConnection->dbname);
        $steamId64 = steam()->steamid($steamId)->ConvertToUInt64();

        $existing = $db
            ->select()
            ->from($prefix . $ranksTable)
            ->where('steam_id', $steamId64)
            ->fetchAll();

        if (!$simulate) {
            if (empty($existing)) {
                $db->insert($prefix . $ranksTable)
                    ->values([
                        'steam_id' => $steamId64,
                        'name' => $user->name ?? 'Player',
                        'rank' => 'default',
                        'points' => $points,
                    ])
                    ->run();
            } else {
                $currentPoints = (int) $existing[0]['points'];

                $db->update($prefix . $ranksTable)
                    ->set('points', $currentPoints + $points)
                    ->where('steam_id', $steamId64)
                    ->run();
            }
        }

        return !$simulate;
    }
}
