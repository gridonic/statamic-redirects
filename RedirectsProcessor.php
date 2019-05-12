<?php

namespace Statamic\Addons\Redirects;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Statamic\API\Content;
use Statamic\API\Str;
use Statamic\Exceptions\RedirectException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RedirectsProcessor
{
    const WILDCARD_NAME = 'any';

    /**
     * @var RedirectsManager
     */
    private $manualRedirectsManager;

    /**
     * @var AutoRedirectsManager
     */
    private $autoRedirectsManager;

    /**
     * @var RedirectsLogger
     */
    private $redirectsLogger;

    /**
     * @var array
     */
    private $routeCollections = [
        'manual' => null,
        'auto' => null,
    ];

    public function __construct(ManualRedirectsManager $manualRedirectsManager, AutoRedirectsManager $autoRedirectsManager, RedirectsLogger $redirectsLogger)
    {
        $this->manualRedirectsManager = $manualRedirectsManager;
        $this->redirectsLogger = $redirectsLogger;
        $this->autoRedirectsManager = $autoRedirectsManager;
    }

    /**
     * Redirect the request by throwing a RedirectException, if a redirect route is found.
     *
     * Manual redirects take precedence over the auto ones.
     *
     * @param Request $request
     *
     * @throws RedirectException
     */
    public function redirect(Request $request)
    {
        $this->performManualRedirect($request);
        $this->performAutoRedirect($request);
    }

    private function performAutoRedirect(Request $request)
    {
        $route = $this->matchRedirectRoute('auto', $request);
        if ($route === false) {
            return;
        }

        $redirect = $this->autoRedirectsManager->get($route);
        if ($redirect === null) {
            return;
        }

        $this->redirectsLogger
            ->logAutoRedirect($request->getPathInfo())
            ->flush();

        $this->throwRedirectException($redirect->getToUrl(), 301);
    }

    private function performManualRedirect(Request $request)
    {
        $route = $this->matchRedirectRoute('manual', $request);
        if ($route === false) {
            return;
        }

        $redirect = $this->manualRedirectsManager->get($route);
        if ($redirect === null) {
            return;
        }

        // Bail if the request's locale does not match the configured one.
        if ($redirect->getLocale() && $redirect->getLocale() !== site_locale()) {
            return;
        }

        $redirectUrl = $this->normalizeRedirectUrl($redirect->getTo(), $route, $request);

        if (!$redirectUrl) {
            return;
        }

        $statusCode = $redirect->getStatusCode();

        // Check if the redirect is only executed in a time range.
        if ($redirect->getStartDate() || $redirect->getEndDate()) {
            $now = time();
            if ($redirect->getStartDate() && ($redirect->getStartDate()->getTimestamp() > $now)) {
                return;
            }

            if ($redirect->getEndDate() && ($redirect->getEndDate()->getTimestamp() < $now)) {
                return;
            }

            // If start and end date are specified, this is a temporary redirect by design (302).
            $statusCode = ($redirect->getStartDate() && $redirect->getEndDate()) ? 302 : $statusCode;
        }

        if ($redirect->isRetainQueryStrings() && $request->getQueryString()) {
            $redirectUrl .= '?' . $request->getQueryString();
        }

        $this->redirectsLogger
            ->logManualRedirect($route)
            ->flush();

        $this->throwRedirectException($redirectUrl, $statusCode);
    }

    /**
     * Normalize the given target URL to an URL we can redirect to.
     *
     * @param string $targetUrl The URL we should redirect to
     * @param string $matchedRoute The matched route by the current request
     * @param Request $request
     *
     * @return string|null
     */
    private function normalizeRedirectUrl($targetUrl, $matchedRoute, Request $request)
    {
        // The target URL is relative, check for parameters and replace them.
        if (Str::startsWith($targetUrl, '/')) {
            $wildcardParameter = $this->getWildcardParameter();
            if (strpos($matchedRoute, $wildcardParameter) !== false) {
                // The special {any} parameter captures any number of URL segments.
                $pattern = str_replace(['/', $wildcardParameter], ["\/", '(.*)'], $matchedRoute);
                preg_match('%' . $pattern . '%', $request->getPathInfo(), $matches);

                return str_replace($wildcardParameter, $matches[1], $targetUrl);
            } else if (preg_match_all('%\{(\w+)\}%', $matchedRoute, $matches)) {
                // Any other parameters capture exactly one URL segment.
                $segmentsRoute = explode('/', ltrim($matchedRoute, '/'));
                $segmentsRequestPath = explode('/', ltrim($request->getPathInfo(), '/'));
                $replacements = [];
                foreach ($matches[0] as $parameter) {
                    // Find the position of the placeholder within the route.
                    $pos = array_search($parameter, $segmentsRoute);
                    if ($pos === false) {
                        continue;
                    }
                    $replacements[$parameter] = $segmentsRequestPath[$pos];
                }

                return str_replace($matches[0], $replacements, $targetUrl);
            }

            return $targetUrl;
        }

        /** @var \Statamic\Contracts\Data\Content\Content $content */
        $content = Content::find($targetUrl);
        if ($content && $content->uri()) {
            $localizedContent = $content->in(site_locale());

            return $localizedContent->url();
        }

        return null;
    }

    private function throwRedirectException($url, $statusCode)
    {
        throw (new RedirectException())
            ->setUrl($url)
            ->setCode($statusCode);
    }

    private function matchRedirectRoute($which, Request $request)
    {
        $this->loadRouteCollections($which);

        try {
            /** @var Route $route */
            $route = $this->routeCollections[$which]->match($request);

            return $route->getUri();
        } catch (NotFoundHttpException $e) {
            return false;
        }
    }

    private function loadRouteCollections($which)
    {
        if ($this->routeCollections[$which] !== null) {
            return;
        }

        $this->routeCollections[$which] = new RouteCollection();

        $redirects = ($which === 'manual') ? $this->manualRedirectsManager->all() : $this->autoRedirectsManager->all();

        foreach ($redirects as $redirect) {
            $data = $redirect->toArray();

            $route = new Route(['GET'], $data['from'], function () {});

            if (strpos($data['from'], $this->getWildcardParameter()) !== false) {
                $route->where(self::WILDCARD_NAME, '(.*)');
            }

            $this->routeCollections[$which]->add($route);
        }
    }

    private function getWildcardParameter()
    {
        return sprintf('{%s}', self::WILDCARD_NAME);
    }
}
