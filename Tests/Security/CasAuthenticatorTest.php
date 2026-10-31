<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Pumukit\CasBundle\Security\CasAuthenticator;
use Pumukit\CasBundle\Security\CasLoginRequiredException;
use Pumukit\CasBundle\Services\CASService;
use Pumukit\CasBundle\Services\CASUserService;
use Pumukit\SchemaBundle\Document\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * @internal
 *
 * @coversNothing
 */
class CasAuthenticatorTest extends TestCase
{
    public function testItOnlyHandlesTheCasLoginRouteWhenEnabled(): void
    {
        $this->assertTrue($this->authenticator()->supports($this->request('pumukit_security_cas_login')));
        $this->assertFalse($this->authenticator()->supports($this->request('pumukit_login')));
        $this->assertFalse($this->authenticator(enabled: false)->supports($this->request('pumukit_security_cas_login')));
    }

    public function testTheCasLoginIsInteractiveSoTheInteractiveLoginListenersRun(): void
    {
        $this->assertTrue($this->authenticator()->isInteractive());
    }

    public function testTheCasPrincipalIsLoadedThroughTheUserService(): void
    {
        $user = new User();
        $user->setUsername('jdoe');
        $casService = $this->createStub(CASService::class);
        $casService->method('isAuthenticated')->willReturn(true);
        $casService->method('getUser')->willReturn('principal-123');
        $casUserService = $this->createMock(CASUserService::class);
        $casUserService->expects($this->once())->method('loadUser')->with('principal-123')->willReturn($user);

        $authenticator = $this->authenticator($casService, $casUserService);
        $request = $this->request('pumukit_security_cas_login');
        $passport = $authenticator->authenticate($request);
        $token = $authenticator->createToken($passport, 'main');

        $this->assertSame($user, $token->getUser());
        $this->assertTrue($token->getAttribute(CasAuthenticator::TOKEN_ATTRIBUTE));
        $this->assertTrue($request->getSession()->isStarted());
    }

    public function testWithoutACasSessionTheUserIsSentToTheCasServer(): void
    {
        $casService = $this->createStub(CASService::class);
        $casService->method('isAuthenticated')->willReturn(false);
        $casService->method('getLoginUrl')->willReturn('https://cas.example.org/cas/login?service=https%3A%2F%2Fpumukit.example.org%2Fcas%2Flogin');
        $casUserService = $this->createMock(CASUserService::class);
        $casUserService->expects($this->never())->method('loadUser');
        $authenticator = $this->authenticator($casService, $casUserService);
        $request = $this->request('pumukit_security_cas_login');

        try {
            $authenticator->authenticate($request);
            $this->fail('The CAS login should be required');
        } catch (CasLoginRequiredException $exception) {
            $response = $authenticator->onAuthenticationFailure($request, $exception);
        }

        $this->assertSame('https://cas.example.org/cas/login?service=https%3A%2F%2Fpumukit.example.org%2Fcas%2Flogin', $response->getTargetUrl());
        $this->assertSame([], $request->getSession()->getFlashBag()->get('error'));
    }

    public function testSuccessGoesBackToThePageThatAskedForTheLogin(): void
    {
        $authenticator = $this->authenticator();
        $request = $this->request('pumukit_security_cas_login');
        $token = $authenticator->createToken($authenticator->authenticate($request), 'main');

        $this->assertSame('/homepage', $authenticator->onAuthenticationSuccess($request, $token, 'main')->getTargetUrl());

        $request->getSession()->set('_security.main.target_path', '/admin/series');
        $this->assertSame('/admin/series', $authenticator->onAuthenticationSuccess($request, $token, 'main')->getTargetUrl());
    }

    public function testFailureShowsTheReasonOnTheLoginForm(): void
    {
        $request = $this->request('pumukit_security_cas_login');

        $response = $this->authenticator()->onAuthenticationFailure($request, new CustomUserMessageAuthenticationException('Your CAS account has no email.'));

        $this->assertSame('/pumukit_login', $response->getTargetUrl());
        $this->assertSame(['Your CAS account has no email.'], $request->getSession()->getFlashBag()->get('error'));
    }

    public function testTheEntryPointSendsToCasOrToTheFormWhenDisabled(): void
    {
        $this->assertSame('/pumukit_security_cas_login', $this->authenticator()->start(new Request())->getTargetUrl());
        $this->assertSame('/pumukit_login', $this->authenticator(enabled: false)->start(new Request())->getTargetUrl());
    }

    private function authenticator(?CASService $casService = null, ?CASUserService $casUserService = null, bool $enabled = true): CasAuthenticator
    {
        if (!$casService) {
            $casService = $this->createStub(CASService::class);
            $casService->method('isAuthenticated')->willReturn(true);
            $casService->method('getUser')->willReturn('jdoe');
        }
        if (!$casUserService) {
            $user = new User();
            $user->setUsername('jdoe');
            $casUserService = $this->createStub(CASUserService::class);
            $casUserService->method('loadUser')->willReturn($user);
        }
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route) => '/'.$route);

        return new CasAuthenticator($casService, $casUserService, $urlGenerator, new NullLogger(), $enabled);
    }

    private function request(string $route): Request
    {
        $request = Request::create('/cas/login');
        $request->attributes->set('_route', $route);
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
