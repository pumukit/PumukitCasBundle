<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

class PumukitCasExtension extends Extension implements PrependExtensionInterface
{
    public const LOG_CHANNEL = 'cas';

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('monolog')) {
            $container->prependExtensionConfig('monolog', [
                'channels' => [self::LOG_CHANNEL],
                'handlers' => [
                    self::LOG_CHANNEL => [
                        'type' => 'rotating_file',
                        'path' => '%kernel.logs_dir%/cas.log',
                        'level' => 'info',
                        'max_files' => 30,
                        'channels' => [self::LOG_CHANNEL],
                        'bubble' => false,
                        'priority' => 100,
                    ],
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('pumukit_security.cas_enable', $config['enable']);
        $container->setParameter('pumukit_security.cas_url', (string) $config['cas_url']);
        $container->setParameter('pumukit_security.cas_port', (string) $config['cas_port']);
        $container->setParameter('pumukit_security.cas_uri', (string) $config['cas_uri']);
        $container->setParameter('pumukit_security.cas_version', $config['cas_version']);
        $container->setParameter('pumukit_security.cas_server_ca_cert', $config['cas_server_ca_cert']);
        $container->setParameter('pumukit_security.cas_allowed_ip_clients', $config['cas_allowed_ip_clients']);
        $container->setParameter('pumukit_security.create_users', $config['create_users']);

        $container->setParameter('pumukit_security.cas_id_key', $config['CAS_ID_KEY']);
        $container->setParameter('pumukit_security.cas_cn_key', $config['CAS_CN_KEY']);
        $container->setParameter('pumukit_security.cas_mail_key', $config['CAS_MAIL_KEY']);
        $container->setParameter('pumukit_security.cas_givenname_key', $config['CAS_GIVENNAME_KEY']);
        $container->setParameter('pumukit_security.cas_surname_key', $config['CAS_SURNAME_KEY']);
        $container->setParameter('pumukit_security.cas_group_key', $config['CAS_GROUP_KEY']);
        $container->setParameter('pumukit_security.cas_origin_key', $config['ORIGIN']);

        $container->setParameter('pumukit_security.profile_mapping', $config['profile_mapping']);
        $container->setParameter('pumukit_security.permission_profiles_attribute', $config['permission_profiles_attribute']);
        $container->setParameter('pumukit_security.default_permission_profile', $config['default_permission_profile']);
        $container->setParameter('pumukit_security.force_override_permission_profile', $config['force_override_permission_profile']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('pumukit_cas.yaml');
    }
}
