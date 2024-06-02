<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Request;
use Closure;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\State\Scope;

class Sentry
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound('sentry')) {
            \Sentry\configureScope(static function (Scope $scope): void {
                if (auth()->check()) {
                    $scope->setUser([
                        'id' => auth()->user()->id,
                        'username' => auth()->user()->username,
                    ]);
                }

                $scope->addEventProcessor(static function (Event $event, EventHint $hint): Event {
                    $request = $event->getRequest();

                    if (array_key_exists('headers', $request)) {
                        /**
                         * Type hint so phpstan knows what this is.
                         *
                         * @var array<string,array<string>> $request['headers']
                         */
                        $request['headers'] = collect($request['headers'])->map(
                            static function (array $values, string $header): array {
                                if (
                                    strcasecmp($header, 'X-Xsrf-Token') === 0 ||
                                    strcasecmp($header, 'X-Csrf-Token') === 0
                                ) {
                                    return ['[redacted]'];
                                }

                                return $values;
                            }
                        );
                    }

                    $event->setRequest($request);

                    return $event;
                });
            });
        }

        return $next($request);
    }
}
