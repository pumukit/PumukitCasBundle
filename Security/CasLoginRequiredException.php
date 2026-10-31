<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Security;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class CasLoginRequiredException extends AuthenticationException
{
    public function __construct(private readonly string $loginUrl)
    {
        parent::__construct('The CAS login is required.');
    }

    public function getLoginUrl(): string
    {
        return $this->loginUrl;
    }
}
