<?php

declare(strict_types=1);

namespace App\Http;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policies\Policy;
use Spatie\Csp\Value;

class ContentSecurityPolicy extends Policy
{
    public function configure(): void
    {
        if (config('csp.report_uri') !== '' && config('csp.report_uri') !== null) {
            $this->reportTo(
                config('csp.report_uri')
                .'&sentry_environment='.config('app.env')
                .'&sentry_release='.config('sentry.release')
            );
        }
        $this->addDirective(Directive::BASE, config('app.url'));
        $this->addDirective(Directive::BLOCK_ALL_MIXED_CONTENT, Value::NO_VALUE);
        $this->addDirective(Directive::DEFAULT, Keyword::SELF);
        $this->addDirective(Directive::FORM_ACTION, [
            config('app.url'),
        ]);
        $this->addDirective(Directive::STYLE_ELEM, [
            Keyword::SELF,
            Keyword::UNSAFE_INLINE,
            'https://fonts.bunny.net',
            Keyword::REPORT_SAMPLE,
        ]);
        $this->addDirective(Directive::STYLE, [
            Keyword::SELF,
            Keyword::UNSAFE_INLINE,
            'https://fonts.bunny.net',
            Keyword::REPORT_SAMPLE,
        ]);
        $this->addDirective(Directive::FONT, [
            'https://fonts.bunny.net',
            Keyword::SELF,
        ]);
        $this->addDirective(Directive::SCRIPT, [
            Keyword::SELF,
            Keyword::UNSAFE_EVAL,
            Keyword::UNSAFE_INLINE,
            Keyword::REPORT_SAMPLE,
        ]);
        $this->addDirective(Directive::SCRIPT_ELEM, [
            Keyword::SELF,
            Keyword::UNSAFE_INLINE,
            Keyword::REPORT_SAMPLE,
        ]);
        $this->addDirective(Directive::IMG, [
            Keyword::SELF,
            'blob:',
            'data: w3.org/svg/2000',
        ]);
        $this->addDirective(Directive::OBJECT, Keyword::NONE);
        $this->addDirective(Directive::WORKER, Keyword::NONE);
        $this->addDirective(Directive::FRAME_ANCESTORS, Keyword::NONE);
        $this->addDirective(Directive::FRAME, Keyword::NONE);
        $this->addDirective(Directive::CHILD, Keyword::NONE);
        if (config('sentry.dsn') !== '' && config('sentry.dsn') !== null) {
            $this->addDirective(Directive::CONNECT, [
                Keyword::SELF,
                // @phan-suppress-next-line PhanTypeMismatchArgumentInternal
                'https://'.parse_url(config('sentry.dsn'), PHP_URL_HOST),
            ]);
        }
    }
}
