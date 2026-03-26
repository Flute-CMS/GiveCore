<?php

declare(strict_types = 1);

namespace Flute\Modules\GiveCore\Check\Drivers\Stats;

use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Nette\Utils\Json;
use Throwable;

/**
 * Драйвер условия: проверка статистики Army Ranks Ultimate
 */
class ArmyRanksUltimateConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'ARU';

    public function alias(): string
    {
        return 'aru_stats';
    }

    public function name(): string
    {
        return __('givecore.drivers.aru.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.aru.description');
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
        $metric = $params['metric'] ?? 'experience';
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

            $additional = Json::decode($dbConnection->additional, true);
            $tableName = $additional['table_name'] ?? 'aru_players';
            $prefix = $this->getDbPrefix($dbConnection->dbname, '');
            $db = dbal()->database($dbConnection->dbname);

            $steamId2 = steam()->steamid($steamId)->RenderSteam2();

            $row = $db
                ->select()
                ->from($prefix . $tableName)
                ->where('steam', $steamId2)
                ->fetchAll();

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
            logs()->warning("RoleSync ARU check failed for user {$user->id}: " . $e->getMessage());

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
                    'experience' => __('givecore.metrics.experience'),
                    'kills' => __('givecore.metrics.kills'),
                    'deaths' => __('givecore.metrics.deaths'),
                    'playtime' => __('givecore.metrics.playtime'),
                    'rank' => __('givecore.metrics.rank'),
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
            'experience' => ['experience', 'exp'],
            'kills' => ['kills'],
            'deaths' => ['deaths'],
            'playtime' => ['playtime', 'onlinetime'],
            'rank' => ['rank'],
        ];

        $keys = $mappings[$metric] ?? [$metric];

        foreach ($keys as $key) {
            if (isset($row[$key])) {
                return (float) $row[$key];
            }
        }

        return null;
    }
}
