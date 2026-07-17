<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // These two traits give every controller access to $this->authorize(...)
    // (Policy checks) and $this->validate(...) (inline validation).
    // Laravel 11+ removed them from the base Controller by default — adding
    // them back here, once, means every controller gets them automatically.
    use AuthorizesRequests, ValidatesRequests;
}