<?php

declare(strict_types = 1);

namespace Flute\Modules\GiveCore\Check\Drivers\Stats;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Throwable;

/**
 * Драйвер условия: проверка статистики FPS Stats
 */
class FpsStatsConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'FPS';

    public function alias(): string
    {
        return 'fpsstats_stats';
    }

    public function name(): string
    {
        return __('givecore.drivers.fpsstats.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.fpsstats.description');
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
        $metric = $params['metric'] ?? 'points';
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

            $prefix = $this->getDbPrefix($dbConnection->dbname, 'fps_');
            $db = dbal()->database($dbConnection->dbname);

            $steam64 = steam()->steamid($steamId)->ConvertToUInt64();

            $player = $db
                ->select()
                ->from($prefix . 'players')
                ->where('steam_id', $steam64)
                ->fetchAll();

            if (empty($player)) {
                return false;
            }

            $player = $player[0];

            $accountId = $player['account_id'];

            $fpsServerId = $this->findFpsServerId($db, $prefix, $server);
            if (!$fpsServerId) {
                return false;
            }

            $stats = $db
                ->select()
                ->from($prefix . 'servers_stats')
                ->where('account_id', $accountId)
                ->where('server_id', $fpsServerId)
                ->fetchAll();

            if (empty($stats)) {
                return false;
            }

            $stats = $stats[0];

            $actualValue = $this->getMetricValue($stats, $metric);
            if ($actualValue === null) {
                return false;
            }

            return $this->compareValues($actualValue, $operator, $value);
        } catch (Throwable $e) {
            logs()->warning("RoleSync FPS Stats check failed for user {$user->id}: " . $e->getMessage());

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
                    'points' => __('givecore.metrics.score'),
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
            'points' => ['points'],
            'kills' => ['kills'],
            'deaths' => ['deaths'],
            'playtime' => ['playtime'],
        ];

        $keys = $mappings[$metric] ?? [$metric];

        foreach ($keys as $key) {
            if (isset($row[$key])) {
                return (float) $row[$key];
            }
        }

        return null;
    }

    protected function findFpsServerId($db, string $prefix, Server $server): ?int
    {
        $ip = $server->ip ?? '';
        $port = $server->port ?? '';

        $row = $db
            ->select(['id'])
            ->from($prefix . 'servers')
            ->where('server_ip', $ip . ':' . $port)
            ->fetchOne();

        return $row ? (int) $row['id'] : null;
    }
}
