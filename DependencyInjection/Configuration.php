<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pumukit_security');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode('cas_url')
            ->isRequired()
            ->info('The hostname of the CAS server')
            ->end()
            ->scalarNode('cas_port')
            ->isRequired()
            ->info('The port the CAS server is running on')
            ->end()
            ->scalarNode('cas_uri')
            ->isRequired()
            ->info('The URI the CAS server is responding on')
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
            ->isRequired()
            ->requiresAtLeastOneElement()
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
}
