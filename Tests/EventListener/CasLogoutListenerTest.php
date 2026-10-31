<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Pumukit\CasBundle\EventListener\CasLogoutListener;
use Pumukit\CasBundle\Security\CasAuthenticator;
use Pumukit\CasBundle\Services\CASService;
use Pumukit\SchemaBundle\Document\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * @internal
 *
 * @coversNothing
 */
class CasLogoutListenerTest extends TestCase
{
    public function testACasSessionIsClosedOnTheCasServer(): void
    {
        $event = new LogoutEvent(new Request(), $this->token(true));

        $this->listener()->onLogout($event);

        $this->assertInstanceOf(RedirectResponse::class, $event->getResponse());
        $this->assertSame('https://cas.example.org/cas/logout?service=https%3A%2F%2Fpumukit.example.org%2F', $event->getResponse()->getTargetUrl());
    }

    public function testALocalSessionKeepsTheDefaultLogout(): void
    {
        $event = new LogoutEvent(new Request(), $this->token(false));

        $this->listener()->onLogout($event);

        $this->assertNull($event->getResponse());
    }

    public function testAnExistingResponseIsKept(): void
    {
        $event = new LogoutEvent(new Request(), $this->token(true));
        $event->setResponse(new RedirectResponse('/elsewhere'));

        $this->listener()->onLogout($event);

        $this->assertSame('/elsewhere', $event->getResponse()->getTargetUrl());
    }

    private function listener(): CasLogoutListener
    {
        $casService = $this->createStub(CASService::class);
        $casService->method('getLogoutUrl')->willReturnCallback(static fn (string $service) => 'https://cas.example.org/cas/logout?'.http_build_query(['service' => $service]));
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://pumukit.example.org/');

        return new CasLogoutListener($casService, $urlGenerator);
    }

    private function token(bool $cas): UsernamePasswordToken
    {
        $user = new User();
        $user->setUsername('jdoe');
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);
        if ($cas) {
            $token->setAttribute(CasAuthenticator::TOKEN_ATTRIBUTE, true);
        }

        return $token;
    }
}
