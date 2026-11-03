<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\CasBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @internal
 *
 * @coversNothing
 */
class ConfigurationTest extends TestCase
{
    private const SERVER = [
        'cas_url' => 'cas.example.org',
        'cas_port' => 443,
        'cas_uri' => 'cas',
        'profile_mapping' => ['PAS' => 'Viewer'],
    ];

    public function testTheEnabledBundleNeedsTheServer(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('cas_uri, profile_mapping');

        $this->process(['cas_uri' => '', 'profile_mapping' => []] + self::SERVER);
    }

    public function testTheEnabledBundleWithItsServerIsValid(): void
    {
        $this->assertTrue($this->process(self::SERVER)['enable']);
    }

    public function testTheDisabledBundleNeedsNoServer(): void
    {
        $config = $this->process(['enable' => false]);

        $this->assertFalse($config['enable']);
        $this->assertSame([], $config['profile_mapping']);
    }

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
