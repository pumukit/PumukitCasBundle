<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Pumukit\CasBundle\Routing\RouteLoader;
use Pumukit\CasBundle\Security\CasAuthenticator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 *
 * @coversNothing
 */
class RouteLoaderTest extends TestCase
{
    public function testNoRouteIsLoadedWhenItIsDisabled(): void
    {
        $this->assertCount(0, $this->load(false));
    }

    public function testTheCasRoutesAreLoadedWhenItIsEnabled(): void
    {
        $routes = $this->load(true);

        $this->assertSame('/cas/login', $routes->get(CasAuthenticator::LOGIN_ROUTE)?->getPath());
        $this->assertSame('/cas/logout', $routes->get('pumukit_security_cas_logout')?->getPath());
    }

    private function load(bool $enabled): RouteCollection
    {
        $loader = new RouteLoader($enabled);
        $loader->setResolver(new LoaderResolver([$loader, new YamlFileLoader(new FileLocator(dirname(__DIR__, 2).'/Resources/config'))]));

        return $loader->load('routes/cas.yml', RouteLoader::TYPE);
    }
}
