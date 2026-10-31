<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Services;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('cas')]
class CASService
{
    private bool $initialize = false;

    public function __construct(
        private readonly string $casUrl,
        private readonly string $casPort,
        private readonly string $casUri,
        private readonly array $casAllowedIpClients,
        private readonly string $casClientScheme,
        private readonly string $casClientHost,
        private readonly LoggerInterface $logger,
        private readonly string $casVersion = '2.0',
        private readonly ?string $casServerCaCert = null,
        private readonly string $env = 'prod',
    ) {}

    public function isAuthenticated(): bool
    {
        $this->prepare();

        return \phpCAS::isAuthenticated();
    }

    public function getUser(): string
    {
        $this->prepare();

        return (string) \phpCAS::getUser();
    }

    public function getAttributes(): array
    {
        $this->prepare();

        return \phpCAS::getAttributes();
    }

    public function getLoginUrl(): string
    {
        $this->prepare();

        return \phpCAS::getServerLoginURL();
    }

    public function getLogoutUrl(string $service): string
    {
        $this->prepare();

        return \phpCAS::getServerLogoutURL().'?'.http_build_query(['service' => $service]);
    }

    private function prepare(): void
    {
        if ($this->initialize) {
            return;
        }
        $this->initialize = true;

        if ('dev' === $this->env) {
            \phpCAS::setLogger($this->logger);
        }

        \phpCAS::client($this->casVersion, $this->casUrl, (int) $this->casPort, $this->casUri, $this->casClientScheme.'://'.$this->casClientHost, true);
        \phpCAS::setNoClearTicketsFromUrl();

        if ($this->casServerCaCert) {
            \phpCAS::setCasServerCACert($this->casServerCaCert);
        } else {
            \phpCAS::setNoCasServerValidation();
        }

        if ($this->casAllowedIpClients) {
            \phpCAS::handleLogoutRequests(true, $this->casAllowedIpClients);
        } else {
            \phpCAS::handleLogoutRequests(false);
        }
    }
}
