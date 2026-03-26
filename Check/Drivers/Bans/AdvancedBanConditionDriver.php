<?php

declare(strict_types=1);

namespace Flute\Modules\GiveCore\Check\Drivers\Bans;

use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Throwable;

class AdvancedBanConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'AdvancedBan';

    public function alias(): string
    {
        return 'advancedban_ban';
    }

    public function name(): string
    {
        return __('givecore.drivers.advancedban_ban.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.advancedban_ban.description');
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
        $minecraftUuid = $this->getUserMinecraftUuid($user);
        if (!$minecraftUuid) {
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

            $prefix = $this->getDbPrefix($dbConnection->dbname, '');
            $db = dbal()->database($dbConnection->dbname);

            $nowMs = time() * 1000;

            $bans = $db
                ->select()
                ->from($prefix . 'Punishments')
                ->where('uuid', $minecraftUuid)
                ->where('punishmentType', 'IN', ['BAN', 'TEMP_BAN', 'IP_BAN', 'TEMP_IP_BAN'])
                ->where(static function ($q) use ($nowMs) {
                    $q->where('end', -1)->orWhere('end', '>', $nowMs);
                })
                ->fetchAll();

            return !empty($bans);
        } catch (Throwable $e) {
            logs()->warning("AdvancedBan check failed for user {$user->id}: " . $e->getMessage());

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

    protected function getUserMinecraftUuid(User $user): ?string
    {
        $mc = $user->getSocialNetwork('Minecraft');

        return $mc?->value ?: null;
    }
}
