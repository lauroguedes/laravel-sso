<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /*
     * Authorization on this server always runs through policies rather than
     * inline role checks, so every controller can reach $this->authorize().
     */
    use AuthorizesRequests;
}
