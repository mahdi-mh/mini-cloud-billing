<?php

namespace Modules\Network\Exceptions;

use RuntimeException;

class NoFreeIpAddress extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The IP pool is empty.');
    }
}
