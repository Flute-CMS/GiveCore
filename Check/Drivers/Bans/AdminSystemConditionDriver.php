<?php

declare(strict_types = 1);

namespace Flute\Modules\GiveCore\Check\Drivers\Bans;

use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Throwable;

class AdminSystemConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'AdminSystem';

    public function alias(): string
    {
        return 'adminsystem_ban';
    }

    public function name(): string
    {
        return __('givecore.drivers.adminsystem_ban.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.adminsystem_ban.description');
    }

    public function icon(): string
    {
        return 'ph.bold.prohibit-bold';
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
        $checkType = $params['check_type'] ?? 'has_ban';

        try {
            $server = $this->resolveServer($serverId);
            if (!$server) {
                return false;
            }

            $dbConnection = $server->getDbConnection(static::MOD_KEY);
            if (!$dbConnection) {
                return false;
            }

            $prefix = $this->getDbPrefix($dbConnection->dbname, 'as_');
            $db = dbal()->database($dbConnection->dbname);

            $accountId = steam()->steamid($steamId)->GetAccountID();

            $bans = $db
                ->select()
                ->from($prefix . 'bans')
                ->where('client_id', $accountId)
                ->where('active', 1)
                ->where(static function ($q) {
                    $q->where('time', 0)->orWhere('time', '>', time());
                })
                ->fetchAll();

            $hasBan = !empty($bans);

            return $checkType === 'has_ban' ? $hasBan : !$hasBan;
        } catch (Throwable $e) {
            logs()->warning("RoleSync AdminSystem check failed for user {$user->id}: " . $e->getMessage());

            return false;
        }
    }

    public function checkFields(): array
    {
        $servers = [0 => __('givecore.fields.any_server')] + $this->getAvailableServerOptions();

        return [
            'server_id' => [
                'type' => 'select',
                'label' => __('givecore.fields.server'),
                'required' => false,
                'options' => $servers,
            ],
            'check_type' => [
                'type' => 'select',
                'label' => __('givecore.fields.check_type'),
                'required' => true,
                'options' => [
                    'has_ban' => __('givecore.fields.has_ban'),
                    'no_ban' => __('givecore.fields.no_ban'),
                ],
            ],
        ];
    }
}
