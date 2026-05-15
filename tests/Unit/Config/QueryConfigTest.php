<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Tests\Unit\Config;

use OCA\WordPressLoginBackend\Config\QueryConfig;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

class QueryConfigTest extends TestCase {
    public function testReturnsDefaultQueryWhenConfigIsMissing(): void {
        $config = $this->createMock(IConfig::class);
        $config->method('getSystemValue')
            ->with(QueryConfig::KEY, [])
            ->willReturn([]);

        $queryConfig = new QueryConfig($config);

        $this->assertSame(
            QueryConfig::DEFAULT_GET_WORDPRESS_USER,
            $queryConfig->getWordPressUserQuery(),
        );
    }

    public function testReturnsCustomWordPressUserQueryWhenConfigured(): void {
        $customQuery = 'SELECT user_pass AS password, user_login AS uid, user_login AS displayname, 1 AS enabled FROM custom_users WHERE user_login = :username';
        $config = $this->createMock(IConfig::class);
        $config->method('getSystemValue')
            ->with(QueryConfig::KEY, [])
            ->willReturn([
                QueryConfig::KEY_GET_WORDPRESS_USER => $customQuery,
            ]);

        $queryConfig = new QueryConfig($config);

        $this->assertSame($customQuery, $queryConfig->getWordPressUserQuery());
    }

    public function testReturnsCustomDisabledUsersQueryWhenConfigured(): void {
        $customQuery = 'SELECT user_login AS uid FROM custom_disabled_users WHERE user_login LIKE :search';
        $config = $this->createMock(IConfig::class);
        $config->method('getSystemValue')
            ->with(QueryConfig::KEY, [])
            ->willReturn([
                QueryConfig::KEY_GET_DISABLED_USERS => $customQuery,
            ]);

        $queryConfig = new QueryConfig($config);

        $this->assertSame($customQuery, $queryConfig->getDisabledUsersQuery());
    }

    public function testReturnsDefaultWhenConfiguredQueryIsEmpty(): void {
        $config = $this->createMock(IConfig::class);
        $config->method('getSystemValue')
            ->with(QueryConfig::KEY, [])
            ->willReturn([
                QueryConfig::KEY_SET_WORDPRESS_PASSWORD => '   ',
            ]);

        $queryConfig = new QueryConfig($config);

        $this->assertSame(
            QueryConfig::DEFAULT_SET_WORDPRESS_PASSWORD,
            $queryConfig->setWordPressPasswordQuery(),
        );
    }
}
