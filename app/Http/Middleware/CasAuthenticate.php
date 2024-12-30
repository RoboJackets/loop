<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CasAuthenticate
{
    /**
     * Auth facade.
     *
     * @var \Illuminate\Contracts\Auth\Guard
     *
     * @phan-read-only
     */
    protected $auth;

    /**
     * CAS library interface.
     *
     * @var \Subfission\Cas\CasManager
     *
     * @phan-read-only
     */
    protected $cas;

    /**
     * List of attributes that may be set during masquerade.
     *
     * @var array<string>
     *
     * @phan-read-only
     */
    private static $attributes = [
        'email_primary',
        'givenName',
        'sn',
    ];

    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
        // @phan-suppress-next-line PhanUndeclaredClassReference
        $this->cas = app('cas');
    }

    /**
     * Handle an incoming request.
     *
     * @phan-suppress PhanPluginInconsistentReturnMethod
     */
    public function handle(Request $request, Closure $next)
    {
        // Run the user update only if they don't have an active session
        if ($this->cas->isAuthenticated() && $request->user() === null) {
            if ($this->cas->isMasquerading()) {
                $masq_attrs = [];
                foreach (self::$attributes as $attribute) {
                    $masq_attrs[$attribute] = config('cas.cas_masquerade_'.$attribute);
                }
                $this->cas->setAttributes($masq_attrs);
            }

            $user = User::where('username', $this->cas->user())->first();
            if ($user === null) {
                $user = new User();
            }
            $user->username = $this->cas->user();
            $user->first_name = $this->cas->getAttribute('givenName');
            $user->last_name = $this->cas->getAttribute('sn');
            if ($user->email === null || ! Str::endsWith($user->email, 'robojackets.org')) {
                $user->email = $this->cas->getAttribute('email_primary');
            }
            $user->save();

            Auth::login($user);
        }

        if ($this->cas->isAuthenticated() && $request->user() !== null) {
            // User is authenticated and already has an existing session
            return $next($request);
        }

        // User is not authenticated and does not have an existing session
        if ($request->ajax() || $request->wantsJson()) {
            return response('Unauthorized', 401);
        }

        $this->cas->authenticate();
    }
}
