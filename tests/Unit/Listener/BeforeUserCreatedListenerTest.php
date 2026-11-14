<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Tests\Unit\Listener;

use InvalidArgumentException;
use OCA\WordPressLoginBackend\Db\WordPressUsers;
use OCA\WordPressLoginBackend\Listener\BeforeUserCreatedListener;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\User\Events\BeforeUserCreatedEvent;
use PDO;
use PHPUnit\Framework\TestCase;

class BeforeUserCreatedListenerTest extends TestCase {
    private string $databaseFile;
    private PDO $database;
    private array $admins = [];
    private array $delegatedAdmins = [];

    protected function setUp(): void {
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'wordpress');
        $this->database = new PDO('sqlite:' . $this->databaseFile);
        $this->database->exec('CREATE TABLE wp_users (ID INTEGER PRIMARY KEY, user_login TEXT COLLATE NOCASE, user_email TEXT, user_pass TEXT, display_name TEXT)');
        $this->database->exec('CREATE TABLE wp_wc_orders (customer_id INTEGER, status TEXT, type TEXT)');
        $this->database->exec("INSERT INTO wp_users (user_login, user_email, user_pass, display_name) VALUES ('ana', 'ana@example.org', 'hash', 'Ana')");
    }

    protected function tearDown(): void {
        unlink($this->databaseFile);
    }

    public function testRefusesASubAdminCreatingAWordPressLogin(): void {
        $this->expectException(InvalidArgumentException::class);

        $this->createUser('bruno', 'ana');
    }

    public function testLetsASubAdminCreateAnUidThatIsNotAWordPressLogin(): void {
        $this->expectNotToPerformAssertions();

        $this->createUser('bruno', 'carla');
    }

    public function testLetsAnAdminCreateAWordPressLogin(): void {
        $this->admins[] = 'admin';
        $this->expectNotToPerformAssertions();

        $this->createUser('admin', 'ana');
    }

    public function testLetsADelegatedAdminCreateAWordPressLogin(): void {
        $this->delegatedAdmins[] = 'manager';
        $this->expectNotToPerformAssertions();

        $this->createUser('manager', 'ana');
    }

    public function testLetsACreationWithoutSessionCreateAWordPressLogin(): void {
        $this->expectNotToPerformAssertions();

        $this->createUser(null, 'ana');
    }

    private function createUser(?string $creatorUid, string $uid): void {
        $config = $this->createMock(IConfig::class);
        $config->method('getSystemValue')->willReturnMap([
            ['wordpress_dsn', '', 'sqlite:' . $this->databaseFile],
            ['wordpress_queries', [], []],
        ]);
        $creator = null;
        if ($creatorUid !== null) {
            $creator = $this->createMock(IUser::class);
            $creator->method('getUID')->willReturn($creatorUid);
        }
        $userSession = $this->createMock(IUserSession::class);
        $userSession->method('getUser')->willReturn($creator);
        $groupManager = $this->createMock(IGroupManager::class);
        $groupManager->method('isAdmin')->willReturnCallback(fn (string $userId) => in_array($userId, $this->admins, true));
        $groupManager->method('isDelegatedAdmin')->willReturnCallback(fn (string $userId) => in_array($userId, $this->delegatedAdmins, true));

        $listener = new BeforeUserCreatedListener($userSession, $groupManager, new WordPressUsers($config));
        $listener->handle(new BeforeUserCreatedEvent($uid, 'password'));
    }
}
