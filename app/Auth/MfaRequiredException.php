<?php

namespace App\Auth;

use App\Models\User;
use RuntimeException;

class MfaRequiredException extends RuntimeException
{
    public function __construct(public User $user)
    {
        parent::__construct('Two-factor authentication is required.');
    }
}
