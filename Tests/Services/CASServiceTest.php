<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Tests\Services;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Pumukit\CasBundle\Services\CASService;

/**
 * @internal
 *
 * @coversNothing
 */
class CASServiceTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testTheLoginUrlPointsTheCasServerBackToTheCasLoginOfPumukit(): void
    {
        $this->assertSame(
            'https://cas.example.org/cas/login?service=https%3A%2F%2Fpumukit.example.org%2Fcas%2Flogin',
            $this->service()->getLoginUrl()
        );
    }

    #[RunInSeparateProcess]
    public function testTheLogoutUrlSendsTheUserBackToThePortal(): void
    {
        $this->assertSame(
            'https://cas.example.org/cas/logout?service=https%3A%2F%2Fpumukit.example.org%2F',
            $this->service()->getLogoutUrl('https://pumukit.example.org/')
        );
    }

    #[RunInSeparateProcess]
    public function testANonStandardPortIsKept(): void
    {
        $this->assertStringStartsWith('https://cas.example.org:8443/cas/login?', $this->service('8443')->getLoginUrl());
    }

    private function service(string $port = '443'): CASService
    {
        $_SERVER['HTTP_HOST'] = 'pumukit.example.org';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '443';
        $_SERVER['REQUEST_URI'] = '/cas/login';
        $_SERVER['PHP_SELF'] = '/cas/login';

        return new CASService('cas.example.org', $port, 'cas', [], 'https', 'pumukit.example.org', new NullLogger());
    }
}
