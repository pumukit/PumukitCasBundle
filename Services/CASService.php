<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Services;

class CASService
{
    private $casUrl;
    private $casPort;
    private $casUri;
    private $casAllowedIpClients;
    private $initialize = false;
    private $env;
    private $cacheDir;
    private $casClientScheme;
    private $casClientHost;

    public function __construct(
        string $casUrl,
        string $casPort,
        string $casUri,
        array $casAllowedIpClients,
        string $casClientScheme,
        string $casClientHost,
        string $env = 'prod',
               $cacheDir = null
    ) {
        $this->casUrl = $casUrl;
        $this->casPort = $casPort;
        $this->casUri = $casUri;
        $this->casAllowedIpClients = $casAllowedIpClients;
        $this->env = $env;
        $this->cacheDir = $cacheDir;
        $this->casClientScheme = $casClientScheme;
        $this->casClientHost = $casClientHost;
    }

    public function isAuthenticated()
    {
        if (!$this->initialize) {
            $this->prepare();
        }

        return \phpCAS::isAuthenticated();
    }

    public function getUser()
    {
        if (!$this->initialize) {
            $this->prepare();
        }

        return \phpCAS::getUser();
    }

    public function getAttributes()
    {
        if (!$this->initialize) {
            $this->prepare();
        }

        return \phpCAS::getAttributes();
    }

    public function setFixedServiceURL(string $url): void
    {
        if (!$this->initialize) {
            $this->prepare();
        }
        \phpCAS::setFixedServiceURL($url);
    }

    public function forceAuthentication(): void
    {
        if (!$this->initialize) {
            $this->prepare();
        }
        \phpCAS::forceAuthentication();
    }

    public function logoutWithRedirectService(string $url): void
    {
        if (!$this->initialize) {
            $this->prepare();
        }
        \phpCAS::logoutWithRedirectService($url);
    }

    public function logout(): void
    {
        if (!$this->initialize) {
            $this->prepare();
        }
        \phpCAS::logout();
    }

    private function prepare(): void
    {
        $this->initialize = true;

        $casClientURL = $this->casClientScheme . '://' . $this->casClientHost;
        \phpCAS::client(CAS_VERSION_3_0, $this->casUrl, (int) $this->casPort, 'cas', $casClientURL, true);

        \phpCAS::setNoCasServerValidation();
        if ('dev' == $this->env) {
            $file = $this->cacheDir ? ($this->cacheDir.'/cas.log') : '/tmp/cas.log';
            \phpCAS::setDebug($file);
        }
        if ($this->casAllowedIpClients) {
            \phpCAS::handleLogoutRequests(true, $this->casAllowedIpClients);
        } else {
            \phpCAS::handleLogoutRequests(false);
        }
    }
}
