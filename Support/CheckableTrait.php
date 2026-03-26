<?php

namespace Flute\Modules\GiveCore\Support;

use Throwable;

/**
 * Default checkBulk() implementation for drivers implementing CheckableInterface.
 */
trait CheckableTrait
{
    public function checkBulk(array $users, array $params = []): array
    {
        $result = [];

        foreach ($users as $user) {
            try {
                $result[$user->id] = $this->check($user, $params);
            } catch (Throwable $e) {
                $result[$user->id] = false;
            }
        }

        return $result;
    }
}
