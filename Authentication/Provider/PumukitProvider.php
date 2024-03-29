<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Authentication\Provider;

use Pumukit\CasBundle\Services\CASUserService;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class PumukitProvider implements AuthenticationProviderInterface
{
    private $userProvider;
    private $providerKey;
    private $userChecker;
    private $createUsers;
    private $CASUserService;

    public function __construct(
        UserProviderInterface $userProvider,
        $providerKey,
        UserCheckerInterface $userChecker,
        CASUserService $CASUserService,
        $createUsers = true
    ) {
        $this->userProvider = $userProvider;
        $this->providerKey = $providerKey;
        $this->userChecker = $userChecker;
        $this->createUsers = $createUsers;
        $this->CASUserService = $CASUserService;
    }

    public function authenticate(TokenInterface $token)
    {
        if (!$this->supports($token)) {
            return $token;
        }

        if (!$user = $token->getUser()) {
            throw new BadCredentialsException('No pre-authenticated principal found in request.');
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($user);
        } catch (UserNotFoundException $notFound) {
            if ($this->createUsers) {
                $user = $this->CASUserService->createDefaultUser($user);
            } else {
                throw new BadCredentialsException('Bad credentials', 0, $notFound);
            }
        } catch (\Exception $repositoryProblem) {
            $ex = new AuthenticationServiceException($repositoryProblem->getMessage(), 0, $repositoryProblem);
            $ex->setToken($token);

            throw $ex;
        }

        $this->userChecker->checkPreAuth($user);
        $this->CASUserService->updateUser($user);
        $this->userChecker->checkPostAuth($user);

        $authenticatedToken = new PreAuthenticatedToken(
            $user,
            $this->providerKey,
            $user->getRoles()
        );
        $authenticatedToken->setAttributes($token->getAttributes());

        return $authenticatedToken;
    }

    public function supports(TokenInterface $token): bool
    {
        return $token instanceof PreAuthenticatedToken;
    }
}
