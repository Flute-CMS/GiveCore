<?php

declare(strict_types = 1);

namespace Flute\Modules\GiveCore\Check\Drivers\Admin;

use Flute\Core\Database\Entities\User;
use Flute\Modules\GiveCore\Support\AbstractCheckDriver;
use Throwable;

class SimpleAdminAdminConditionDriver extends AbstractCheckDriver
{
    protected const MOD_KEY = 'SimpleAdmin';

    public function alias(): string
    {
        return 'simpleadmin_admin';
    }

    public function name(): string
    {
        return __('givecore.drivers.simpleadmin_admin.name');
    }

    public function description(): string
    {
        return __('givecore.drivers.simpleadmin_admin.description');
    }

    public function icon(): string
    {
        return 'ph.bold.shield-star-bold';
    }

    public function category(): string
    {
        return 'admin';
    }

    public function check(User $user, array $params = []): bool
    {
        $steamId = $this->getUserSteamId($user);
        if (!$steamId) {
            return false;
        }

        $serverId = (int) ( $params['server_id'] ?? 0 );
        $minImmunity = (int) ( $params['min_immunity'] ?? 0 );
        $requiredFlags = $params['flags'] ?? '';

        try {
            $server = $this->resolveServer($serverId);
            if (!$server) {
                return false;
            }

            $dbConnection = $server->getDbConnection(static::MOD_KEY);
            if (!$dbConnection) {
                return false;
            }

            $prefix = $this->getDbPrefix($dbConnection->dbname, 'sa_');
            $db = dbal()->database($dbConnection->dbname);

            $steamId64 = steam()->steamid($steamId)->ConvertToUInt64();

            $now = date('Y-m-d H:i:s');

            $admins = $db
                ->select()
                ->from($prefix . 'admins')
                ->where('player_steamid', $steamId64)
                ->where(static function ($q) use ($now) {
                    $q->where('ends', null)->orWhere('ends', '')->orWhere('ends', '>', $now);
                })
                ->fetchAll();

            if (empty($admins)) {
                return false;
            }

            $admin = $admins[0];

            if ($minImmunity > 0 && ( $admin['immunity'] ?? 0 ) < $minImmunity) {
                return false;
            }

            if (!empty($requiredFlags)) {
                $adminFlags = $admin['flags'] ?? '';
                if (str_starts_with($adminFlags, '#')) {
                    $groupId = substr($adminFlags, 1);
                    $groups = $db
                        ->select()
                        ->from($prefix . 'admins_groups')
                        ->where('id', $groupId)
                        ->fetchAll();

                    if (empty($groups)) {
                        return false;
                    }

                    $adminFlags = $groups[0]['flags'] ?? '';
                }

                foreach (str_split($requiredFlags) as $flag) {
                    if (!str_contains($adminFlags, $flag)) {
                        return false;
                    }
                }
            }

            return true;
        } catch (Throwable $e) {
            logs()->warning("RoleSync SimpleAdmin admin check failed for user {$user->id}: " . $e->getMessage());

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
            'min_immunity' => [
                'type' => 'number',
                'label' => __('givecore.fields.min_immunity'),
                'required' => false,
                'min' => 0,
                'max' => 100,
            ],
            'flags' => [
                'type' => 'text',
                'label' => __('givecore.fields.required_flags'),
                'required' => false,
                'placeholder' => 'abcdefg',
            ],
        ];
    }
}
