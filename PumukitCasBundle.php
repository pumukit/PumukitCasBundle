<?php

declare(strict_types=1);

namespace Pumukit\CasBundle;

use Pumukit\CasBundle\DependencyInjection\Security\Factory\PumukitFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PumukitCasBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        /** @var SecurityExtension */
        $extension = $container->getExtension('security');
        $extension->addSecurityListenerFactory(new PumukitFactory());
    }
}
