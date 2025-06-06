<?php

namespace App\Exceptions;

use InvalidArgumentException;

class EmptyAnswer extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct("Answer cannot be empty.");
    }
} 