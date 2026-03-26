<?php

declare(strict_types=1);

namespace Flute\Modules\GiveCore\Check\Drivers\Stats;

use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Nette\Utils\Json;
use Throwable;

class K4SystemConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'K4';

    public function alias(): string
    {
        return 'k4system_stats';
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
        return 'stats';
    }

    public function check(User $user, array $params = []): bool
    {
        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            return false;
        }

        $serverId = (int) ($params['server_id'] ?? 0);
        $metric = $params['metric'] ?? 'points';
        $operator = $params['operator'] ?? '>=';
        $value = (float) ($params['value'] ?? 0);

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
            $prefix = $this->getDbPrefix($dbConnection->dbname, '');
            $db = dbal()->database($dbConnection->dbname);

            $steamId64 = steam()->steamid($steamId)->ConvertToUInt64();

            $actualValue = $this->fetchMetricValue($db, $prefix, $additional, $steamId64, $metric);
            if ($actualValue === null) {
                return false;
            }

            return $this->compareValues($actualValue, $operator, $value);
        } catch (Throwable $e) {
            logs()->warning("K4-System check failed for user {$user->id}: " . $e->getMessage());

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
                    'round_win' => __('givecore.metrics.round_win'),
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

    protected function fetchMetricValue(
        $db,
        string $prefix,
        array $additional,
        string $steamId64,
        string $metric,
    ): ?float {
        $tablePrefix = (string) ($additional['table_prefix'] ?? '');

        // Points are in k4ranks table
        if ($metric === 'points') {
            $ranksTable = $prefix . $tablePrefix . ($additional['ranks_table'] ?? 'k4ranks');
            $row = $db->select(['points'])
                ->from($ranksTable)
                ->where('steam_id', $steamId64)
                ->fetchAll();

            return !empty($row) ? (float) $row[0]['points'] : null;
        }

        // Playtime is in k4times table
        if ($metric === 'playtime') {
            $timesTable = $prefix . $tablePrefix . ($additional['times_table'] ?? 'k4times');
            $row = $db->select(['all'])
                ->from($timesTable)
                ->where('steam_id', $steamId64)
                ->fetchAll();

            return !empty($row) ? (float) $row[0]['all'] : null;
        }

        // Other metrics are in k4stats table
        $statsTable = $prefix . $tablePrefix . ($additional['stats_table'] ?? 'k4stats');
        $row = $db->select()
            ->from($statsTable)
            ->where('steam_id', $steamId64)
            ->fetchAll();

        if (empty($row)) {
            return null;
        }

        return isset($row[0][$metric]) ? (float) $row[0][$metric] : null;
    }
}
