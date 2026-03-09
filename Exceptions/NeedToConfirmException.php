<?php

namespace Flute\Modules\GiveCore\Exceptions;

class NeedToConfirmException extends \Exception
{
    protected array $values = [];

    public function __construct(array $values)
    {
        parent::__construct();
        $this->values = $values;
    }

    public function getValues(): array
    {
        return $this->values;
    }
}
