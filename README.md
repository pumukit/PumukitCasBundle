PuMuKIT CAS Authentication
==========================

Bundle based on [Symfony](http://symfony.com/) to work with the [PuMuKIT Video Platform 6.0](https://github.com/pumukit/PuMuKIT/tree/6.0.x) or higher.

This bundle allows PuMuKIT to use [CAS](https://apereo.github.io/cas/) as single sign-on, through
[phpCAS](https://github.com/apereo/phpCAS). Users that log in for the first time are created from the CAS
attributes (email, full name, groups and permission profile) and updated on every login.

Installation
------------

```bash
composer require teltek/pumukit-cas-bundle
```

If Symfony Flex does not do it, add the bundle to `config/bundles.php`:

```php
Pumukit\CasBundle\PumukitCasBundle::class => ['all' => true],
```

and its routes:

```yaml
pumukit_cas:
  resource: "@PumukitCasBundle/Resources/config/routing.yml"
  prefix: /
```

Then configure the bundle and the firewall as explained in the [configuration guide](Resources/doc/ConfigurationGuide.md)
and clear the cache:

```bash
php bin/console cache:clear
```

Upgrading from 3.0.x (PuMuKIT 5)
--------------------------------

The authentication was rewritten for the authenticator system of Symfony 7: the `pumukit` firewall key, the
`pumukit.security.handler.logout` success handler and `anonymous: true` do not exist anymore. Update
`config/packages/security.yaml` as shown in the configuration guide. The `pumukit_cas` options are the same; there
are three new optional ones (`enable`, `cas_version` and `cas_server_ca_cert`). With `enable: false`, the bundle can
stay installed without the CAS server options.

Behaviour changes:

- The CAS login and logout redirections go through Symfony instead of calling `exit` inside phpCAS.
- The logout only goes through the CAS server when the user logged in with CAS.
- The username created from `CAS_ID_KEY` is also used to find the user on the next logins (before, a user whose
  CAS principal differs from that attribute was created again).
- Multi-valued CAS attributes are supported: the first value for the email and the names, every value for the
  groups, and the first mapped value for the permission profile.
- A role that is missing or not in `profile_mapping` gives `default_permission_profile` (before, the login failed
  or the default profile of PuMuKIT was used).
- The bundle logs to its own `cas` channel, in `var/log/cas-YYYY-MM-DD.log`; the phpCAS traces are added in the
  `dev` environment.
