<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Tests\Unit\Backend;

use OCA\WordPressLoginBackend\Backend\UserBackend;
use OCA\WordPressLoginBackend\Db\WordPressUsers;
use OCA\WordPressLoginBackend\Helper\HashPassword;
use OCP\Cache\CappedMemoryCache;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use PDO;
use PHPUnit\Framework\TestCase;

class UserBackendTest extends TestCase {
    private string $databaseFile;
    private PDO $database;
    private array $nextcloudUids = [];
    private array $linkedLogins = [];

    protected function setUp(): void {
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'wordpress');
        $this->database = new PDO('sqlite:' . $this->databaseFile);
        $this->database->exec('CREATE TABLE wp_users (ID INTEGER PRIMARY KEY, user_login TEXT COLLATE NOCASE, user_email TEXT, user_pass TEXT, display_name TEXT)');
        $this->database->exec('CREATE TABLE wp_wc_orders (customer_id INTEGER, status TEXT, type TEXT)');
    }

    protected function tearDown(): void {
        unlink($this->databaseFile);
    }

    public function testAuthenticatesALinkedAccount(): void {
        $this->createWordPressUser('ana', 'secret');
        $this->nextcloudUids[] = 'ana';
        $this->linkedLogins['ana'] = 'ana';

        $this->assertSame('ana', $this->backend()->checkPassword('ana', 'secret'));
    }

    public function testReturnsTheNextcloudUidWhenTheLoginDiffersInCase(): void {
        $this->createWordPressUser('ana', 'secret');
        $this->nextcloudUids[] = 'ana';
        $this->linkedLogins['ana'] = 'ana';

        $this->assertSame('ana', $this->backend()->checkPassword('ANA', 'secret'));
    }

    public function testRefusesAnAccountThatIsNotLinkedToWordPress(): void {
        $this->createWordPressUser('ana', 'secret');
        $this->nextcloudUids[] = 'ana';

        $this->assertFalse($this->backend()->checkPassword('ana', 'secret'));
    }

    public function testRefusesAnAccountLinkedToAnotherLogin(): void {
        $this->createWordPressUser('ana', 'secret');
        $this->nextcloudUids[] = 'ana';
        $this->linkedLogins['ana'] = 'bruno';

        $this->assertFalse($this->backend()->checkPassword('ana', 'secret'));
    }

    public function testRefusesTheWordPressUserWhoseEmailIsTheUid(): void {
        $this->createWordPressUser('bruno', 'password of bruno', 'ana@example.org');
        $this->createWordPressUser('ana@example.org', 'password of ana', 'ana@example.net');
        $this->nextcloudUids[] = 'ana@example.org';
        $this->linkedLogins['ana@example.org'] = 'ana@example.org';

        $backend = $this->backend();

        $this->assertFalse($backend->checkPassword('ana@example.org', 'password of bruno'));
        $this->assertSame('ana@example.org', $backend->checkPassword('ana@example.org', 'password of ana'));
    }

    public function testRefusesAWordPressLoginThatDiffersOnlyInCase(): void {
        $this->createWordPressUser('Ana', 'secret');
        $this->nextcloudUids[] = 'ana';
        $this->linkedLogins['ana'] = 'ana';

        $this->assertFalse($this->backend()->checkPassword('ana', 'secret'));
    }

    public function testRefusesALoginMissingFromNextcloud(): void {
        $this->createWordPressUser('ana', 'secret');
        $this->linkedLogins['ana'] = 'ana';

        $this->assertFalse($this->backend()->checkPassword('ana', 'secret'));
    }

    private function createWordPressUser(string $login, string $password, ?string $email = null): void {
        $statement = $this->database->prepare('INSERT INTO wp_users (user_login, user_email, user_pass, display_name) VALUES (:login, :email, :hash, :login)');
        $statement->execute([
            'login' => $login,
            'email' => $email ?? $login . '@example.org',
            'hash' => (new HashPassword())->hashPassword($password),
        ]);
    }

    private function backend(): UserBackend {
        $config = $this->createMock(IConfig::class);
        $config->method('getSystemValue')->willReturnMap([
            ['wordpress_dsn', '', 'sqlite:' . $this->databaseFile],
            ['wordpress_queries', [], []],
        ]);
        $config->method('getUserValue')->willReturnCallback(
            fn (string $userId, string $appName, string $key) => $appName === 'wordpress_login_backend' && $key === 'wordpress_login'
                ? ($this->linkedLogins[$userId] ?? '')
                : '',
        );

        return new UserBackend(new CappedMemoryCache(), $config, $this->nextcloudDatabase(), new WordPressUsers($config));
    }

    private function nextcloudDatabase(): IDBConnection {
        $lowercaseUid = null;
        $expression = $this->createMock(IExpressionBuilder::class);
        $expression->method('eq')->willReturn('');
        $queryBuilder = $this->createMock(IQueryBuilder::class);
        foreach (['select', 'from', 'where', 'setMaxResults'] as $method) {
            $queryBuilder->method($method)->willReturnSelf();
        }
        $queryBuilder->method('expr')->willReturn($expression);
        $queryBuilder->method('createNamedParameter')->willReturnCallback(function (string $value) use (&$lowercaseUid): string {
            $lowercaseUid = $value;
            return ':uid';
        });
        $queryBuilder->method('executeQuery')->willReturnCallback(function () use (&$lowercaseUid): IResult {
            $row = false;
            foreach ($this->nextcloudUids as $uid) {
                if (mb_strtolower($uid) === $lowercaseUid) {
                    $row = ['uid' => $uid, 'displayname' => $uid, 'password' => ''];
                }
            }
            $result = $this->createMock(IResult::class);
            $result->method('fetch')->willReturn($row);
            return $result;
        });
        $connection = $this->createMock(IDBConnection::class);
        $connection->method('getQueryBuilder')->willReturn($queryBuilder);
        return $connection;
    }
}
