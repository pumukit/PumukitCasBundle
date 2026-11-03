<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const REQUIRED = ['cas_url', 'cas_port', 'cas_uri', 'profile_mapping'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pumukit_cas');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->validate()
            ->ifTrue(static fn (array $config): bool => $config['enable'] && [] !== self::missing($config))
            ->then(static function (array $config): never {
                throw new \InvalidArgumentException('pumukit_cas needs '.implode(', ', self::missing($config)).' when it is enabled');
            })
            ->end()
            ->children()
            ->booleanNode('enable')
            ->defaultTrue()
            ->info('If false, the CAS login is switched off while the bundle stays registered: /cas/login and the entry point fall back to the PuMuKIT login form, and the CAS server options are not required')
            ->end()
            ->scalarNode('cas_url')
            ->defaultNull()
            ->info('The hostname of the CAS server')
            ->end()
            ->scalarNode('cas_port')
            ->defaultNull()
            ->info('The port the CAS server is running on')
            ->end()
            ->scalarNode('cas_uri')
            ->defaultNull()
            ->info('The URI the CAS server is responding on')
            ->end()
            ->enumNode('cas_version')
            ->values(['1.0', '2.0', '3.0'])
            ->defaultValue('2.0')
            ->info('CAS protocol version used to validate the tickets (3.0 for the /p3/serviceValidate endpoint)')
            ->end()
            ->scalarNode('cas_server_ca_cert')
            ->defaultNull()
            ->info('Path of the CA certificate that signs the CAS server certificate. If null, the CAS server certificate is not validated')
            ->end()
            ->arrayNode('cas_allowed_ip_clients')
            ->prototype('scalar')
            ->info('Array of allowed IP clients')
            ->end()
            ->end()
            ->booleanNode('create_users')
            ->defaultTrue()
            ->info('Authorize application to create not found users')
            ->end()
            ->scalarNode('CAS_ID_KEY')
            ->defaultValue('UID')
            ->end()
            ->scalarNode('CAS_CN_KEY')
            ->defaultValue('CN')
            ->end()
            ->scalarNode('CAS_MAIL_KEY')
            ->defaultValue('MAIL')
            ->end()
            ->scalarNode('CAS_GIVENNAME_KEY')
            ->defaultValue('GIVENNAME')
            ->end()
            ->scalarNode('CAS_SURNAME_KEY')
            ->defaultValue('SURNAME')
            ->end()
            ->scalarNode('CAS_GROUP_KEY')
            ->defaultValue('GROUP')
            ->end()
            ->scalarNode('permission_profiles_attribute')
            ->defaultValue('role')
            ->info('Default attribute for permission profiles on CAS response')
            ->end()
            ->booleanNode('force_override_permission_profile')
            ->defaultTrue()
            ->info('If set true, the permission profile returned on CAS will set to user.')
            ->end()
            ->scalarNode('default_permission_profile')
            ->defaultValue('Viewer')
            ->info('Default Permission Profile name if none is defined through CAS')
            ->end()
            ->arrayNode('profile_mapping')
            ->useAttributeAsKey('name')
            ->prototype('scalar')->end()
            ->end()
            ->scalarNode('ORIGIN')
            ->defaultValue('cas')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    private static function missing(array $config): array
    {
        return array_values(array_filter(self::REQUIRED, static fn (string $key): bool => is_array($config[$key]) ? [] === $config[$key] : '' === trim((string) $config[$key])));
    }
}
