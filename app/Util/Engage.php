<?php

declare(strict_types=1);

namespace App\Util;

use App\Models\User;
use Dom\Element;
use Dom\HTMLDocument;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\RedirectMiddleware;
use Illuminate\Support\Facades\Log;
use LdapRecord\Container;
use Psr\Http\Message\ResponseInterface;

class Engage
{
    /**
     * The CAS REST API endpoint to exchange a username and password for a ticket-granting ticket.
     */
    private const string CAS_TICKETS_URL = 'https://sso.gatech.edu/cas/v1/tickets';

    /**
     * The CAS service for CampusLabs federation, used to request and redeem service tickets.
     */
    private const string CAS_SERVICE_URL = 'https://federation.campuslabs.com/auth/signin/';

    /**
     * The URL that starts the Engage single sign-on flow.
     */
    private const string ENGAGE_LOGIN_URL = 'https://gatech.campuslabs.com/engage/account/login?returnUrl=/engage/';

    /**
     * The Engage home page, used to verify that authentication succeeded.
     */
    private const string ENGAGE_HOME_URL = 'https://gatech.campuslabs.com/engage/';

    private const array REDIRECT_STATUS_CODES = [301, 302, 303, 307, 308];

    private static ?Client $client = null;

    /**
     * Returns an HTTP client with an authenticated Engage session.
     */
    public static function client(): Client
    {
        if (self::$client === null) {
            $client = new Client([
                'cookies' => new CookieJar(),
                'headers' => [
                    'User-Agent' => 'RoboJackets Loop on '.config('app.url'),
                ],
                'allow_redirects' => false,
                'http_errors' => false,
                'connect_timeout' => 5,
                'timeout' => 30,
            ]);

            self::logIn($client);

            self::$client = $client;
        }

        return self::$client;
    }

    /**
     * Simplify a finance stage name.
     *
     * @psalm-pure
     */
    public static function cleanFinanceStageName(string $input): string
    {
        if (str_contains($input, ':')) {
            $parts = explode(':', $input);

            return trim($parts[1]);
        }

        return $input;
    }

    /**
     * Return a User given an email address (actually a User Principal Name).
     */
    public static function getUserByEmailAddress(string $email): User
    {
        $parts = explode('@', $email);

        if (User::whereUsername($parts[0])->exists()) {
            $user = User::whereUsername($parts[0])->sole();

            $user->givePermissionTo('access-engage');

            return $user;
        }

        $result = Sentry::wrapWithChildSpan(
            'ldap.get_user_by_username',
            static fn (): array|\LdapRecord\Query\Collection => Container::getDefaultConnection()
                ->query()
                ->where('uid', '=', $parts[0])
                ->select('sn', 'givenName', 'primaryUid', 'mail')
                ->get()
        );

        if (count($result) === 0) {
            throw new Exception('User '.$parts[0].' not in Whitepages');
        }

        $user = User::create([
            'first_name' => $result[0]['givenname'][0],
            'last_name' => $result[0]['sn'][0],
            'username' => $result[0]['primaryuid'][0],
            'email' => $email,
        ]);

        $user->givePermissionTo('access-engage');

        return $user;
    }

    /**
     * Authenticate to Engage using the CAS REST API.
     *
     * This mirrors the flow a browser would follow, except the CAS login form is replaced with the
     * CAS REST protocol so no human interaction is required.
     */
    private static function logIn(Client $client): void
    {
        Log::info('Starting Engage single sign-on redirect chain');
        self::startSingleSignOn($client);

        Log::info('Requesting CAS ticket-granting ticket');
        $ticket_granting_ticket_url = self::getTicketGrantingTicketUrl($client);

        Log::info('Exchanging ticket-granting ticket for service ticket');
        $service_ticket = self::getServiceTicket($client, $ticket_granting_ticket_url);

        Log::info('Redeeming service ticket with CampusLabs federation');
        [$response, $url] = self::requestFollowingRedirects(
            $client,
            'GET',
            self::CAS_SERVICE_URL.'?ticket='.rawurlencode($service_ticket)
        );

        if ($response->getStatusCode() !== 200) {
            throw new Exception(
                'Unexpected HTTP '.$response->getStatusCode().' response from CampusLabs federation'
            );
        }

        Log::info('Completing CampusLabs single sign-on callbacks');
        self::submitAutoPostForms($client, $response, $url);

        [$response] = self::requestFollowingRedirects($client, 'GET', self::ENGAGE_HOME_URL);

        if ($response->getStatusCode() !== 200) {
            throw new Exception(
                'Unexpected HTTP '.$response->getStatusCode().' response from Engage after single sign-on'
            );
        }

        Log::info('Engage authentication complete');
    }

    /**
     * Follow the Engage login redirects through CampusLabs IdentityServer and federation.
     *
     * Stops once CAS is about to be invoked, after federation has set FpContext and Engage and
     * IdentityServer have the correlation cookies needed to finish the login.
     */
    private static function startSingleSignOn(Client $client): void
    {
        $url = self::ENGAGE_LOGIN_URL;

        for ($redirects = 0; $redirects < 15; $redirects++) {
            $response = $client->get($url);
            $location = $response->getHeaderLine('Location');

            if ($location === '' || ! in_array($response->getStatusCode(), self::REDIRECT_STATUS_CODES, true)) {
                throw new Exception(
                    'Unexpected HTTP '.$response->getStatusCode().' response from '.$url.' while starting single '
                        .'sign-on'
                );
            }

            $next_url = strval(UriResolver::resolve(new Uri($url), new Uri($location)));

            if (
                str_contains($next_url, 'cas/login') ||
                str_contains($next_url, 'login.gatech.edu') ||
                str_contains($next_url, 'sso.gatech.edu')
            ) {
                return;
            }

            $url = $next_url;
        }

        throw new Exception('Exceeded redirect limit while starting Engage single sign-on');
    }

    /**
     * Exchange the configured username and password for a ticket-granting ticket URL.
     */
    private static function getTicketGrantingTicketUrl(Client $client): string
    {
        $response = $client->post(self::CAS_TICKETS_URL, [
            'form_params' => [
                'username' => config('services.engage.username'),
                'password' => config('services.engage.password'),
            ],
            // Duo interactions, if any, are handled by CAS during this request
            'timeout' => 60,
        ]);

        if ($response->getStatusCode() === 401) {
            throw new Exception('CAS rejected the configured Engage username and password');
        }

        if ($response->getStatusCode() === 423) {
            throw new Exception('CAS returned HTTP 423, the account may be locked');
        }

        $form = self::parseForm($response->getBody()->getContents(), self::CAS_TICKETS_URL);

        if ($form === null) {
            throw new Exception(
                'Failed to parse ticket-granting ticket URL from HTTP '.$response->getStatusCode().' CAS response'
            );
        }

        return $form['action'];
    }

    /**
     * Exchange a ticket-granting ticket for a service ticket for CampusLabs federation.
     */
    private static function getServiceTicket(Client $client, string $ticket_granting_ticket_url): string
    {
        $response = $client->post($ticket_granting_ticket_url, [
            'form_params' => [
                'service' => self::CAS_SERVICE_URL,
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new Exception(
                'Unexpected HTTP '.$response->getStatusCode().' response from CAS while retrieving service ticket'
            );
        }

        return $response->getBody()->getContents();
    }

    /**
     * Submit browser-style auto-POST forms (WS-Fed and OIDC callbacks) until none remain.
     */
    private static function submitAutoPostForms(Client $client, ResponseInterface $response, string $url): void
    {
        for ($submissions = 0; $submissions < 10; $submissions++) {
            $form = self::parseForm($response->getBody()->getContents(), $url);

            if ($form === null) {
                return;
            }

            if ($form['method'] === 'post') {
                [$response, $url] = self::requestFollowingRedirects($client, 'POST', $form['action'], [
                    'form_params' => $form['fields'],
                    'timeout' => 60,
                ]);
            } else {
                [$response, $url] = self::requestFollowingRedirects($client, 'GET', $form['action'], [
                    'query' => $form['fields'],
                    'timeout' => 60,
                ]);
            }

            if ($response->getStatusCode() !== 200) {
                throw new Exception(
                    'Unexpected HTTP '.$response->getStatusCode().' response while completing single sign-on '
                        .'callbacks'
                );
            }
        }

        throw new Exception('Exceeded form submission limit while completing CampusLabs single sign-on');
    }

    /**
     * Send a request following redirects, and return the response along with the effective URL.
     *
     * @param  array<string,array<string,string>|int>  $options
     * @return array{0: ResponseInterface, 1: string}
     */
    private static function requestFollowingRedirects(
        Client $client,
        string $method,
        string $url,
        array $options = []
    ): array {
        $options['allow_redirects'] = [
            'max' => 10,
            'track_redirects' => true,
        ];

        $response = $client->request($method, $url, $options);

        $redirect_history = $response->getHeader(RedirectMiddleware::HISTORY_HEADER);

        return [$response, $redirect_history === [] ? $url : end($redirect_history)];
    }

    /**
     * Parse the first form from an HTML document, resolving the action against the document URL.
     *
     * @return array{action: string, method: string, fields: array<string,string>}|null
     */
    private static function parseForm(string $html, string $document_url): ?array
    {
        $form = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8')->querySelector('form');

        if ($form === null) {
            return null;
        }

        $action = $form->getAttribute('action');

        if ($action === null || $action === '') {
            return null;
        }

        $fields = [];

        foreach ($form->querySelectorAll('input') as $input) {
            if (! $input instanceof Element) {
                continue;
            }

            $name = $input->getAttribute('name');

            if ($name === null || $name === '') {
                continue;
            }

            $fields[$name] = $input->getAttribute('value') ?? '';
        }

        return [
            'action' => strval(UriResolver::resolve(new Uri($document_url), new Uri($action))),
            'method' => strtolower($form->getAttribute('method') ?? 'get'),
            'fields' => $fields,
        ];
    }
}
