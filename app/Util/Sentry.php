<?php

declare(strict_types=1);

namespace App\Util;

use Closure;
use Illuminate\Support\Str;
use Sentry\SentrySdk;
use Sentry\Tracing\SamplingContext;
use Sentry\Tracing\SpanContext;

class Sentry
{
    /**
     * URLs that should be ignored for performance tracing.
     *
     * @phan-read-only
     *
     * @var array<string>
     */
    private static array $ignoreUrls = [
        '/health',
        '/ping',
    ];

    /**
     * Wrap a closure with a child span for Sentry performance tracing.
     *
     * @template ReturnType
     *
     * @param  Closure(): ReturnType  $closure
     * @return ReturnType
     */
    public static function wrapWithChildSpan(string $span_name, Closure $closure): ReturnType
    {
        $parentSpan = SentrySdk::getCurrentHub()->getSpan();

        if ($parentSpan !== null) {
            $context = new SpanContext();
            $context->setOp($span_name);
            $span = $parentSpan->startChild($context);
            SentrySdk::getCurrentHub()->setSpan($span);
        }

        $result = $closure();

        if ($parentSpan !== null) {
            // @phan-suppress-next-line PhanPossiblyUndeclaredVariable
            $span->finish();
            SentrySdk::getCurrentHub()->setSpan($parentSpan);
        }

        return $result;
    }

    public static function tracesSampler(SamplingContext $context): float
    {
        if ($context->getParentSampled() === true) {
            return 1;
        }

        $transactionData = $context->getTransactionContext()?->getData();

        if (
            $transactionData !== null &&
            array_key_exists('url', $transactionData) &&
            (
                in_array($transactionData['url'], self::$ignoreUrls, true) ||
                Str::startsWith($transactionData['url'], '/horizon/')
            )
        ) {
            return 0;
        }

        return 1;
    }
}
