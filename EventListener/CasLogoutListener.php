<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\EventListener;

use Pumukit\CasBundle\Security\CasAuthenticator;
use Pumukit\CasBundle\Services\CASService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class CasLogoutListener
{
    public function __construct(
        private readonly CASService $casService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function onLogout(LogoutEvent $event): void
    {
        if (null !== $event->getResponse() || !$event->getToken()?->hasAttribute(CasAuthenticator::TOKEN_ATTRIBUTE)) {
            return;
        }

        $home = $this->urlGenerator->generate('pumukit_webtv_index_index', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $event->setResponse(new RedirectResponse($this->casService->getLogoutUrl($home)));
    }
}
