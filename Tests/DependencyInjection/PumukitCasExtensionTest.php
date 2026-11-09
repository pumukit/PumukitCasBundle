<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\CasBundle\DependencyInjection\PumukitCasExtension;
use Pumukit\CasBundle\EventListener\CasLogoutListener;
use Pumukit\CasBundle\Routing\RouteLoader;
use Pumukit\CasBundle\Security\CasAuthenticator;
use Pumukit\CasBundle\Security\DisabledAuthenticator;
use Pumukit\CasBundle\Services\CASService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * @internal
 *
 * @coversNothing
 */
class PumukitCasExtensionTest extends TestCase
{
    private const SERVER = [
        'cas_url' => 'cas.example.org',
        'cas_port' => 443,
        'cas_uri' => 'cas',
        'profile_mapping' => ['PAS' => 'Viewer'],
    ];

    public function testTheDisabledBundleOnlyRegistersTheRouteLoaderAndTheInertAuthenticator(): void
    {
        $container = $this->load(['enable' => false]);

        $this->assertSame([RouteLoader::class, CasAuthenticator::class], $this->casServices($container));
        $this->assertSame(DisabledAuthenticator::class, $container->getDefinition(CasAuthenticator::class)->getClass());
        $this->assertSame('%pumukit_security.cas_enable%', $container->getDefinition(RouteLoader::class)->getArgument(0));
        $this->assertFalse($container->getParameter('pumukit_security.cas_enable'));
        $this->assertFalse($container->hasAlias('pumukit.casservice'));
    }

    public function testTheEnabledBundleRegistersItsServices(): void
    {
        $container = $this->load(self::SERVER);

        $this->assertTrue($container->hasDefinition(CASService::class));
        $this->assertTrue($container->hasDefinition(CasLogoutListener::class));
        $this->assertTrue($container->hasDefinition(RouteLoader::class));
        $this->assertSame(CasAuthenticator::class, $container->getDefinition(CasAuthenticator::class)->getClass());
        $this->assertTrue($container->getParameter('pumukit_security.cas_enable'));
    }

    public function testTheDisabledBundleAddsNoLogChannel(): void
    {
        $this->assertSame([], $this->prepend(['enable' => false])->getExtensionConfig('monolog'));
    }

    public function testTheEnabledBundleAddsItsLogChannel(): void
    {
        $this->assertSame([PumukitCasExtension::LOG_CHANNEL], $this->prepend(self::SERVER)->getExtensionConfig('monolog')[0]['channels']);
    }

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new PumukitCasExtension())->load([$config], $container);

        return $container;
    }

    private function prepend(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void {}

            public function getAlias(): string
            {
                return 'monolog';
            }
        });
        $extension = new PumukitCasExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), $config);
        $extension->prepend($container);

        return $container;
    }

    private function casServices(ContainerBuilder $container): array
    {
        return array_values(array_filter(array_keys($container->getDefinitions()), static fn (string $id): bool => str_starts_with($id, 'Pumukit\CasBundle\\')));
    }
}
