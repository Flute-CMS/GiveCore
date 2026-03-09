<?php

namespace Flute\Modules\GiveCore\Support;

use Flute\Modules\GiveCore\Contracts\DriverInterface;
use Flute\Modules\GiveCore\Exceptions\NeedToConfirmException;
use Flute\Modules\GiveCore\Exceptions\NeedToSelectException;

abstract class AbstractDriver implements DriverInterface
{
    public function confirm(string $message, ?string $id = null)
    {
        $id ??= sha1($message);

        $validate = filter_var(request()->input($id, false), FILTER_VALIDATE_BOOLEAN);

        if (!$validate) {
            throw new NeedToConfirmException([
                'confirm' => [
                    'message' => $message,
                    'id' => $id,
                ],
            ]);
        }
    }

    public function select(array $values, string $message)
    {
        $id = sha1($message);

        $validate = request()->input($id, false);

        $found = false;

        foreach ($values as $key => $value) {
            if ($value['value'] === $validate) {
                $found = true;

                continue;
            }
        }

        if (!$validate || !$found) {
            throw new NeedToSelectException([
                'select' => [
                    'message' => $message,
                    'id' => $id,
                    'values' => $values,
                ],
            ]);
        }

        return $validate;
    }

    /**
     * Return prefix that should be prepended to table names **only** if the
     * connection itself does not already define one.
     *
     * 1. First we try to read it from database.databases.<dbname>.prefix – this is
     *    where our dynamic connections are usually stored.
     * 2. Fallback to database.connections.<dbname>.prefix – some parts of the
     *    code (e.g. during install) may register connections there.
     *
     * If a non-empty prefix is found in either place we must NOT add the
     * defaultPrefix to avoid duplicated names like "vip_vip_users". Otherwise we
     * apply the provided default prefix (e.g. "vip_", "sb_" …).
     */
    public function getPrefix(string $dbname, string $defaultPrefix = ''): string
    {
        $prefix = config("database.databases.$dbname.prefix");

        if ($prefix === null) {
            $prefix = config("database.connections.$dbname.prefix");
        }

        return empty($prefix) ? $defaultPrefix : '';
    }
}
