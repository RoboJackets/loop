<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller with traits included already.
 *
 * @phan-suppress PhanUnreferencedClass
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
