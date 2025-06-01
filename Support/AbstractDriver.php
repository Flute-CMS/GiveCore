<?php

namespace Flute\Modules\GiveCore\Support;

use Flute\Modules\GiveCore\Contracts\DriverInterface;
use Flute\Modules\GiveCore\Exceptions\NeedToConfirmException;
use Flute\Modules\GiveCore\Exceptions\NeedToSelectException;

abstract class AbstractDriver implements DriverInterface
{
    public function confirm(string $message, ?string $id = null)
    {
        $id = $id ?? sha1($message);

        $validate = filter_var(request()->input($id, false), FILTER_VALIDATE_BOOLEAN);

        if (!$validate)
            throw new NeedToConfirmException([
                'confirm' => [
                    'message' => $message,
                    'id' => $id
                ]
            ]);
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

        if (!$validate || !$found)
            throw new NeedToSelectException([
                'select' => [
                    'message' => $message,
                    'id' => $id,
                    'values' => $values,
                ]
            ]);

        return $validate;
    }

    public function getPrefix(string $dbname, string $defaultPrefix = '') : string
    {
        $db = config('database.databases.'.$dbname)['prefix'];

        if(empty($db)) {
            return $defaultPrefix;
        }

        return "";
    }
}