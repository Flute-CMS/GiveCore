<?php

namespace Flute\Modules\GiveCore\Give\Drivers;

use Flute\Core\Database\Entities\Server;
use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Exceptions\BadConfigurationException;
use Flute\Modules\GiveCore\Exceptions\GiveDriverException;
use Flute\Modules\GiveCore\Exceptions\UserSocialException;
use Flute\Modules\GiveCore\Support\AbstractDriver;

class LiteBansDriver extends AbstractDriver
{
    protected const MOD_KEY = 'LiteBans';

    public function alias(): string
    {
        return 'litebans';
    }

    public function name(): string
    {
        return __('givecore.drivers.litebans_deliver.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.litebans_deliver.description');
    }

    public function icon(): string
    {
        return 'ph.bold.prohibit-bold';
    }

    public function category(): string
    {
        return 'Minecraft';
    }

    public function sourceUrl(): ?string
    {
        return 'https://www.spigotmc.org/resources/litebans.3715/';
    }

    public function supportedGames(): array
    {
        return ['Minecraft'];
    }

    public function requiredSocial(array $config = []): ?string
    {
        return 'Minecraft';
    }

    public function deliverFields(): array
    {
        return [
            'action' => [
                'type' => 'select',
                'label' => __('givecore.drivers.litebans_deliver.action'),
                'required' => true,
                'options' => [
                    'unban' => __('givecore.drivers.litebans_deliver.action_unban'),
                    'unmute' => __('givecore.drivers.litebans_deliver.action_unmute'),
                ],
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

        $uuid = $this->getUserMinecraftUuid($user);
        if (!$uuid) {
            throw new UserSocialException('Minecraft');
        }

        $dbConnection = $server->getDbConnection(static::MOD_KEY);
        if (!$dbConnection) {
            throw new BadConfigurationException(static::MOD_KEY, $server->name);
        }

        $action = $additional['action'] ?? 'unban';
        $prefix = $this->getPrefix($dbConnection->dbname, 'litebans_');
        $db = dbal()->database($dbConnection->dbname);
        $now = date('Y-m-d H:i:s');
        $nowMs = time() * 1000;

        $table = $action === 'unmute' ? 'mutes' : 'bans';

        $activePunishments = $db
            ->select()
            ->from($prefix . $table)
            ->where('uuid', $uuid)
            ->where('active', 1)
            ->where(static function ($q) use ($nowMs) {
                $q->where('until', '<=', 0)->orWhere('until', '>', $nowMs);
            })
            ->fetchAll();

        if (empty($activePunishments)) {
            if (!$ignoreErrors) {
                throw new GiveDriverException(
                    __('givecore.drivers.litebans_deliver.no_active_punishment'),
                );
            }

            return false;
        }

        if (!$simulate) {
            foreach ($activePunishments as $punishment) {
                $db->update($prefix . $table)
                    ->set('active', 0)
                    ->set('removed_by_uuid', '#console')
                    ->set('removed_by_name', 'Web GiveCore')
                    ->set('removed_by_reason', 'Removed via GiveCore')
                    ->set('removed_by_date', $now)
                    ->where('id', $punishment['id'])
                    ->run();
            }
        }

        return !$simulate;
    }

    protected function getUserMinecraftUuid(User $user): ?string
    {
        $mc = $user->getSocialNetwork('Minecraft') ?? $user->getSocialNetwork('minecraft');

        return $mc?->value ?: null;
    }
}
