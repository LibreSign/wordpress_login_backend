<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Config;

use OCP\IConfig;

class QueryConfig {
    public const KEY = 'wordpress_queries';
    public const KEY_GET_WORDPRESS_USER = 'get_wordpress_user';
    public const KEY_GET_DISABLED_USERS = 'get_disabled_users';
    public const KEY_SET_WORDPRESS_PASSWORD = 'set_wordpress_password';

    public const DEFAULT_GET_WORDPRESS_USER = <<<'SQL'
        SELECT u.user_pass AS password,
               u.user_login AS uid,
               u.display_name AS displayname,
               CASE
                   WHEN EXISTS (
                       SELECT 1
                       FROM wp_wc_orders o
                       WHERE o.customer_id = u.ID
                         AND o.status = 'wc-active'
                         AND o.type = 'shop_subscription'
                   ) THEN 1
                   ELSE 0
               END AS enabled
          FROM wp_users u
         WHERE (u.user_login = :username OR u.user_email = :username)
        SQL;

    public const DEFAULT_GET_DISABLED_USERS = <<<'SQL'
        SELECT u.user_login AS uid
          FROM wp_wc_orders o
          JOIN wp_users u ON o.customer_id = u.ID
         WHERE (o.status NOT IN ('wc-active') OR o.type <> 'shop_subscription')
           AND (u.user_login LIKE :search OR u.user_email LIKE :search)
        GROUP BY u.user_login
        SQL;

    public const DEFAULT_SET_WORDPRESS_PASSWORD =
        'UPDATE wp_users SET user_pass = :hash WHERE user_login = :username';

    public function __construct(
        private IConfig $config,
    ) {
    }

    public function getWordPressUserQuery(): string {
        return $this->getQueryOrDefault(
            self::KEY_GET_WORDPRESS_USER,
            self::DEFAULT_GET_WORDPRESS_USER,
        );
    }

    public function getDisabledUsersQuery(): string {
        return $this->getQueryOrDefault(
            self::KEY_GET_DISABLED_USERS,
            self::DEFAULT_GET_DISABLED_USERS,
        );
    }

    public function setWordPressPasswordQuery(): string {
        return $this->getQueryOrDefault(
            self::KEY_SET_WORDPRESS_PASSWORD,
            self::DEFAULT_SET_WORDPRESS_PASSWORD,
        );
    }

    private function getQueryOrDefault(string $queryKey, string $default): string {
        $queries = $this->config->getSystemValue(self::KEY, []);
        if (!is_array($queries)) {
            return $default;
        }

        $query = $queries[$queryKey] ?? null;
        if (!is_string($query)) {
            return $default;
        }

        $query = trim($query);
        if ($query === '') {
            return $default;
        }

        return $query;
    }
}
