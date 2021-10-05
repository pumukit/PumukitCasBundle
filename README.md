PuMuKIT CAS Authentication
==========================

This bundle allows PuMuKIT use CAS as SSO.

How to install bundle
```bash
composer require teltek/pumukit-cas-bundle
```

if not, add this to config/bundles.php

```
Pumukit/CasBundle/PumukitCasBundle::class => ['all' => true]
```

Then execute the following commands

```bash
php bin/console cache:clear
php bin/console cache:clear --env=prod
php bin/console assets:install
```

