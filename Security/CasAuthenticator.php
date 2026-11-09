<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Security;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Pumukit\CasBundle\Services\CASService;
use Pumukit\CasBundle\Services\CASUserService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

#[WithMonologChannel('cas')]
class CasAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface, InteractiveAuthenticatorInterface
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'pumukit_security_cas_login';
    public const TOKEN_ATTRIBUTE = 'pumukit_cas';
    private const FORM_LOGIN_ROUTE = 'pumukit_login';

    public function __construct(
        private readonly CASService $casService,
        private readonly CASUserService $casUserService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
    ) {}

    public function supports(Request $request): ?bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        if ($request->hasSession()) {
            $request->getSession()->start();
        }

        if (!$this->casService->isAuthenticated()) {
            throw new CasLoginRequiredException($this->casService->getLoginUrl());
        }
        $principal = $this->casService->getUser();

        return new SelfValidatingPassport(new UserBadge($principal, fn (string $identifier) => $this->casUserService->loadUser($identifier)));
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);
        $token->setAttribute(self::TOKEN_ATTRIBUTE, true);

        return $token;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->logger->info('CAS login', ['user' => $token->getUserIdentifier()]);

        $targetPath = $request->hasSession() ? $this->getTargetPath($request->getSession(), $firewallName) : null;

        return new RedirectResponse($targetPath ?: $this->urlGenerator->generate('homepage'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($exception instanceof CasLoginRequiredException) {
            return new RedirectResponse($exception->getLoginUrl());
        }

        $this->logger->warning('CAS login failed', ['reason' => $exception->getMessageKey(), 'message' => $exception->getMessage()]);

        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('error', strtr($exception->getMessageKey(), $exception->getMessageData()));
        }

        return new RedirectResponse($this->urlGenerator->generate(self::FORM_LOGIN_ROUTE));
    }

    public function isInteractive(): bool
    {
        return true;
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }
}
