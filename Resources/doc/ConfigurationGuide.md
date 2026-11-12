CAS configuration
=================

Firewall
--------

Add the CAS authenticator to the `main` firewall of `config/packages/security.yaml`. With CAS as entry point,
the protected pages (the back-office) send the anonymous users to the CAS server; the PuMuKIT login form keeps
working for the local users:

```yaml
security:
  firewalls:
    main:
      pattern: ^/
      provider: pumukit
      context: pumukit
      custom_authenticators:
        - Pumukit\CasBundle\Security\CasAuthenticator
        - Pumukit\SchemaBundle\Security\LoginFormAuthenticator
      entry_point: Pumukit\CasBundle\Security\CasAuthenticator
      remember_me:
        secret: '%kernel.secret%'
        lifetime: 604800
        path: /
      logout:
        path: pumukit_logout
```

The firewall must be called `main`: the logout of a CAS session is handled by a listener of that firewall, which
sends the user to the CAS server logout and back to the portal.

The CAS login is at `/cas/login`. The URL that the CAS server redirects to is built from the
`router.request_context.scheme` and `router.request_context.host` parameters (`PUMUKIT_SCHEME` and
`PUMUKIT_FRONTEND_HOST`).

Bundle
------

```yaml
pumukit_cas:
  cas_url: 'login.example.org'
  cas_port: 443
  cas_uri: 'cas'
  permission_profiles_attribute: role
  default_permission_profile: Viewer
  force_override_permission_profile: true
  profile_mapping:
    attendee: my_custom_profile_attendee
    moderator: my_custom_profile_moderator
```

| Option                              | Default     | Description                                                                                       |
|-------------------------------------|-------------|---------------------------------------------------------------------------------------------------|
| `enable`                            | `true`      | If false, the CAS routes are not loaded, the authenticator only sends to the PuMuKIT login form as entry point, without changing the firewall, nothing else of the bundle is loaded (CAS services, logout listener, `cas` log channel) and the CAS server options are not required |
| `cas_url`, `cas_port`, `cas_uri`    | (required if enabled) | Host, port and path of the CAS server                                                              |
| `cas_version`                       | `2.0`       | CAS protocol version: `1.0`, `2.0` or `3.0` (`/p3/serviceValidate`)                                |
| `cas_server_ca_cert`                | `null`      | CA certificate that signs the CAS server certificate. If null, the certificate is not validated: set it in production |
| `cas_allowed_ip_clients`            | `[]`        | IPs allowed to send single logout requests; if empty, single logout is accepted from any client  |
| `create_users`                      | `true`      | Create the users that log in for the first time                                                    |
| `CAS_ID_KEY`                        | `UID`       | Attribute with the username (the CAS principal is used if it is missing)                          |
| `CAS_MAIL_KEY`                      | `MAIL`      | Attribute with the email (required to create a user)                                              |
| `CAS_GIVENNAME_KEY`, `CAS_SURNAME_KEY` | `GIVENNAME`, `SURNAME` | Attributes with the full name                                                  |
| `CAS_CN_KEY`                        | `CN`        | Accepted for compatibility but not used: the full name is built from `CAS_GIVENNAME_KEY` and `CAS_SURNAME_KEY` |
| `CAS_GROUP_KEY`                     | `GROUP`     | Attribute with the groups; they are created with the `ORIGIN` origin and kept in sync on every login |
| `permission_profiles_attribute`     | `role`      | Attribute with the role mapped through `profile_mapping` (`null` to always use the default profile) |
| `profile_mapping`                   | (required if enabled) | CAS role → name of the PuMuKIT permission profile                                                  |
| `default_permission_profile`        | `Viewer`    | Profile for a missing or unmapped role; the default profile of PuMuKIT is used if it does not exist |
| `force_override_permission_profile` | `true`      | Update the permission profile on every login                                                       |
| `ORIGIN`                            | `cas`       | Origin of the users and groups created by the bundle; only these users are updated from CAS      |

Single logout
-------------

The single logout requests of the CAS server are posted to `/cas/login`. They only work with the native PHP session
handler (the default one of PuMuKIT, `framework.session.handler_id: ~`).

Logs
----

The bundle logs to its own `cas` channel, in `%kernel.logs_dir%/cas-YYYY-MM-DD.log` (`PUMUKIT_LOG_MAX_FILES` files kept, 15 by default): logins, created
users, permission profile changes and failed logins. In the `dev` environment the phpCAS traces are added.
