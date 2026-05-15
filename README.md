<!--
 - SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# WordPress login backend

Integrate WordPress login with Nextcloud

## What this app do?

Autenticate at Nextcloud using username and password of WordPress.

## How to configure
```bash
occ config:system:set wordpress_dsn --value "mysql:host=myserverhostname;port=3306;dbname=woocommerce;user=root;password=root"
```

Replace the values by your database settings.

### Custom SQL queries (optional)

You can override default SQL queries using the `wordpress_queries` system key,
following the same idea used by `user_backend_sql_raw`.

Supported query keys:

- `get_wordpress_user`
- `get_disabled_users`
- `set_wordpress_password`

Example:

```bash
occ config:system:set wordpress_queries --type json --value '{"get_wordpress_user":"SELECT user_pass AS password, user_login AS uid, display_name AS displayname, 1 AS enabled FROM wp_users WHERE user_login = :username OR user_email = :username","get_disabled_users":"SELECT user_login AS uid FROM wp_users WHERE user_login LIKE :search","set_wordpress_password":"UPDATE wp_users SET user_pass = :hash WHERE user_login = :username"}'
```

The backend expects these named parameters:

- `:username` for user lookup and password updates
- `:search` for disabled-user listing
- `:hash` for password updates

## Support, customization and enterprise

If you need help using this app, or if you want custom development, priority
support, or an enterprise support plan, please contact us.

- Community support and bug reports:
	https://github.com/libresign/wordpress_login_backend/issues
- Professional support and customizations:
	contact@librecode.coop

LibreSign-related enterprise support can also be discussed through the same
contact channel.
