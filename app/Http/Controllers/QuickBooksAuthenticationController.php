<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Util\QuickBooks;
use App\Util\Sentry;
use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class QuickBooksAuthenticationController
{
    public function redirectToQuickBooks(): RedirectResponse
    {
        return redirect(QuickBooks::getDataService()->getOAuth2LoginHelper()->getAuthorizationCodeURL());
    }

    public function handleCallback(Request $request): RedirectResponse|Response
    {
        if ($request->has('error')) {
            (new RegisterErrorViewPaths())();

            return response()->view(
                'error.generic',
                [
                    'title' => 'QuickBooks Authentication Canceled',
                    'code' => '401',
                    'message' => 'QuickBooks Authentication Canceled',
                ],
                401
            );
        } elseif ($request->has(['code', 'realmId'])) {
            if ($request->realmId !== config('quickbooks.company.id')) {
                (new RegisterErrorViewPaths())();

                return response()->view(
                    'error.generic',
                    [
                        'title' => 'QuickBooks Company Mismatch',
                        'code' => '400',
                        'message' => 'QuickBooks Company Mismatch',
                    ],
                    400
                );
            }

            $tokens = Sentry::wrapWithChildSpan(
                'quickbooks.oauth2_exchange_auth_code_for_tokens',
                static fn (): OAuth2AccessToken => QuickBooks::getDataService()
                    ->getOAuth2LoginHelper()
                    ->exchangeAuthorizationCodeForToken(
                        $request->code,
                        $request->realmId
                    )
            );

            $user = $request->user();
            $user->quickbooks_tokens = $tokens;
            $user->save();

            return redirect(route('nova.pages.home'));
        } else {
            throw new BadRequestException('Unexpected query parameters');
        }
    }
}
