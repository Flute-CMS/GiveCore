<?php

namespace Flute\Modules\GiveCore\Exceptions;

use Exception;

class UserSocialException extends Exception
{
    public function __construct(string $social = '', int $code = 0, ?\Throwable $previous = null)
    {
        $message = __('givecore.errors.social_required', ['social' => $social]);

        parent::__construct($message, $code, $previous);
    }
}
