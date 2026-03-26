<?php

declare(strict_types = 1);

namespace Flute\Modules\GiveCore\Check\Drivers\Stats;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Throwable;

/**
 * Драйвер условия: проверка статистики HLStatsX:CE
 */
class HlStatsxCeConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'HLStatsxCE';

    public function alias(): string
    {
        return 'hlstatsce_stats';
    }

    public function name(): string
    {
        return __('givecore.drivers.hlstatsce.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.hlstatsce.description');
    }

    public function icon(): string
    {
        return 'ph.bold.chart-line-up-bold';
    }

    public function category(): string
    {
        return 'stats';
    }

    public function check(User $user, array $params = []): bool
    {
        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            return false;
        }

        $serverId = (int) ( $params['server_id'] ?? 0 );
        $metric = $params['metric'] ?? 'skill';
        $operator = $params['operator'] ?? '>=';
        $value = (float) ( $params['value'] ?? 0 );

        try {
            $server = $this->resolveServer($serverId);
            if (!$server) {
                return false;
            }

            $dbConnection = $server->getDbConnection(static::MOD_KEY);
            if (!$dbConnection) {
                return false;
            }

            $prefix = $this->getDbPrefix($dbConnection->dbname, '');
            $db = dbal()->database($dbConnection->dbname);

            $tables = $this->getTableLookup($db);
            $uniqueIdsTable = $tables['hlstats_playeruniqueids'] ?? $tables['playeruniqueids'] ?? null;
            $playersTable = $tables['hlstats_players'] ?? $tables['players'] ?? 'hlstats_Players';

            if (!$uniqueIdsTable) {
                return false;
            }

            $steamId2 = steam()->steamid($steamId)->RenderSteam2();
            $steamId3 = steam()->steamid($steamId)->RenderSteam3();

            // HLStatsX:CE stores short Steam2 format: "1:443018908" (without STEAM_X: prefix)
            $steamId2Parts = explode(':', $steamId2);
            $steamId2Short = count($steamId2Parts) >= 3 ? implode(':', array_slice($steamId2Parts, 1)) : null;

            // Also try STEAM_0: variant (many databases store STEAM_0 instead of STEAM_1)
            $steamId2Alt = null;
            if (str_starts_with($steamId2, 'STEAM_1:')) {
                $steamId2Alt = 'STEAM_0:' . substr($steamId2, 8);
            }

            $idRow = $db
                ->select(['playerId'])
                ->from($prefix . $uniqueIdsTable)
                ->where(static function ($q) use ($steamId2, $steamId3, $steamId2Short, $steamId2Alt) {
                    $q->where('uniqueId', $steamId2)->orWhere('uniqueId', $steamId3);
                    if ($steamId2Short !== null) {
                        $q->orWhere('uniqueId', $steamId2Short);
                    }
                    if ($steamId2Alt !== null) {
                        $q->orWhere('uniqueId', $steamId2Alt);
                    }
                })
                ->fetchAll();

            if (empty($idRow)) {
                return false;
            }

            $idRow = $idRow[0];

            $playerId = (int) $idRow['playerId'];
            $game = $this->getGame($db, $prefix, $tables, $server);

            $query = $db
                ->select()
                ->from($prefix . $playersTable)
                ->where('playerId', $playerId);

            if ($game !== null) {
                $query->where('game', $game);
            }

            $row = $query->fetchAll();

            if (empty($row)) {
                return false;
            }

            $row = $row[0];

            $actualValue = $this->getMetricValue($row, $metric);
            if ($actualValue === null) {
                return false;
            }

            return $this->compareValues($actualValue, $operator, $value);
        } catch (Throwable $e) {
            logs()->warning("RoleSync HLStatsX:CE check failed for user {$user->id}: " . $e->getMessage());

            return false;
        }
    }

    public function checkFields(): array
    {
        return [
            'server_id' => [
                'type' => 'select',
                'label' => __('givecore.fields.server'),
                'required' => true,
                'options' => $this->getAvailableServerOptions(),
            ],
            'metric' => [
                'type' => 'select',
                'label' => __('givecore.fields.metric'),
                'required' => true,
                'options' => [
                    'skill' => __('givecore.metrics.score'),
                    'kills' => __('givecore.metrics.kills'),
                    'deaths' => __('givecore.metrics.deaths'),
                    'playtime' => __('givecore.metrics.playtime'),
                ],
            ],
            'operator' => [
                'type' => 'select',
                'label' => __('givecore.fields.operator'),
                'required' => true,
                'options' => [
                    '>=' => '>= (' . __('givecore.operator.gte') . ')',
                    '>' => '> (' . __('givecore.operator.gt') . ')',
                    '=' => '= (' . __('givecore.operator.eq') . ')',
                    '<' => '< (' . __('givecore.operator.lt') . ')',
                    '<=' => '<= (' . __('givecore.operator.lte') . ')',
                ],
            ],
            'value' => [
                'type' => 'number',
                'label' => __('givecore.fields.value'),
                'required' => true,
                'min' => 0,
            ],
        ];
    }

    protected function getMetricValue(array $row, string $metric): ?float
    {
        $mappings = [
            'skill' => ['skill'],
            'kills' => ['kills'],
            'deaths' => ['deaths'],
            'playtime' => ['connection_time'],
        ];

        $keys = $mappings[$metric] ?? [$metric];

        foreach ($keys as $key) {
            if (isset($row[$key])) {
                return (float) $row[$key];
            }
        }

        return null;
    }

    protected function getTableLookup($db): array
    {
        try {
            $tables = $db->getTables();
            $lookup = [];
            foreach ($tables as $table) {
                $name = $table->getName();
                $lower = strtolower($name);

                if ($lower === 'hlstats_p' || $lower === 'p') {
                    continue;
                }

                $lookup[$lower] = $name;
            }

            return $lookup;
        } catch (Throwable) {
            return [];
        }
    }

    protected function getGame($db, string $prefix, array $tables, Server $server): ?string
    {
        $serversTable = $tables['hlstats_servers'] ?? $tables['servers'] ?? 'hlstats_Servers';

        try {
            $ip = (string) ( $server->ip ?? '' );
            $port = (int) ( $server->port ?? 0 );

            $rows = $db
                ->select(['game'])
                ->from($prefix . $serversTable)
                ->where('address', $ip)
                ->where('port', $port)
                ->fetchAll();

            if (empty($rows)) {
                $rows = $db
                    ->select(['game'])
                    ->from($prefix . $serversTable)
                    ->limit(1)
                    ->fetchAll();
            }

            $game = (string) ( $rows[0]['game'] ?? '' );

            return $game !== '' ? $game : null;
        } catch (Throwable) {
            return null;
        }
    }
}
