<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Pumukit\CasBundle\Security\CasAuthenticator;
use Pumukit\CasBundle\Security\DisabledAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 *
 * @coversNothing
 */
class DisabledAuthenticatorTest extends TestCase
{
    public function testItHandlesNoRequest(): void
    {
        $request = Request::create('/cas/login');
        $request->attributes->set('_route', CasAuthenticator::LOGIN_ROUTE);

        $this->assertFalse($this->authenticator()->supports($request));
    }

    public function testTheEntryPointSendsToTheLoginForm(): void
    {
        $this->assertSame('/pumukit_login', $this->authenticator()->start(new Request())->headers->get('Location'));
    }

    private function authenticator(): DisabledAuthenticator
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route) => '/'.$route);

        return new DisabledAuthenticator($urlGenerator);
    }
}
