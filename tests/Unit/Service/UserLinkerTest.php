<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Tests\Unit\Service;

use OCA\WordPressLoginBackend\Db\WordPressUsers;
use OCA\WordPressLoginBackend\Service\UserLinker;
use OCP\Group\ISubAdmin;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PDO;
use PHPUnit\Framework\TestCase;

class UserLinkerTest extends TestCase {
    private string $databaseFile;
    private PDO $database;
    private array $users = [];
    private array $groups = [];
    private array $subAdmins = [];
    private array $linkedLogins = [];

    protected function setUp(): void {
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'wordpress');
        $this->database = new PDO('sqlite:' . $this->databaseFile);
        $this->database->exec('CREATE TABLE wp_users (ID INTEGER PRIMARY KEY, user_login TEXT, user_email TEXT, user_pass TEXT, display_name TEXT)');
        $this->database->exec('CREATE TABLE wp_wc_orders (customer_id INTEGER, status TEXT, type TEXT)');
    }

    protected function tearDown(): void {
        unlink($this->databaseFile);
    }

    public function testLinksTheGroupOwnerThatHasAWordPressLogin(): void {
        $this->createWordPressUser('ana');
        $this->createGroup('ana', ['ana'], 'ana');

        $result = $this->link(false);

        $this->assertSame(['ana'], $result['linked']);
        $this->assertSame(['ana' => 'ana'], $this->linkedLogins);
    }

    public function testDoesNotLinkAGroupMemberEvenWithAWordPressLogin(): void {
        $this->createWordPressUser('ana');
        $this->createWordPressUser('bruno');
        $this->createGroup('ana', ['ana', 'bruno'], 'ana');

        $result = $this->link(false);

        $this->assertSame(['bruno'], $result['skipped']);
        $this->assertArrayNotHasKey('bruno', $this->linkedLogins);
    }

    public function testDoesNotLinkAGroupOwnerThatIsNotItsSubAdmin(): void {
        $this->createWordPressUser('ana');
        $this->createGroup('ana', ['ana'], null);

        $result = $this->link(false);

        $this->assertSame([], $result['linked']);
        $this->assertSame([], $this->linkedLogins);
    }

    public function testDoesNotLinkAGroupOwnerWithoutAWordPressLogin(): void {
        $this->createWordPressUser('bruno', 'ana');
        $this->createGroup('ana', ['ana'], 'ana');

        $result = $this->link(false);

        $this->assertSame(['ana'], $result['skipped']);
        $this->assertSame([], $this->linkedLogins);
    }

    public function testDryRunListsWithoutLinking(): void {
        $this->createWordPressUser('ana');
        $this->createGroup('ana', ['ana'], 'ana');

        $result = $this->link(true);

        $this->assertSame(['ana'], $result['linked']);
        $this->assertSame([], $this->linkedLogins);
    }

    private function createWordPressUser(string $login, ?string $email = null): void {
        $statement = $this->database->prepare('INSERT INTO wp_users (user_login, user_email, user_pass, display_name) VALUES (:login, :email, :hash, :login)');
        $statement->execute(['login' => $login, 'email' => $email ?? $login . '@example.org', 'hash' => 'hash']);
    }

    private function createGroup(string $groupId, array $memberIds, ?string $subAdminId): void {
        $members = array_map(fn (string $uid) => $this->user($uid), $memberIds);
        $group = $this->createMock(IGroup::class);
        $group->method('getGID')->willReturn($groupId);
        $group->method('getUsers')->willReturn($members);
        $this->groups[] = $group;
        if ($subAdminId !== null) {
            $this->subAdmins[] = [$subAdminId, $groupId];
        }
    }

    private function user(string $uid): IUser {
        if (!isset($this->users[$uid])) {
            $user = $this->createMock(IUser::class);
            $user->method('getUID')->willReturn($uid);
            $this->users[$uid] = $user;
        }
        return $this->users[$uid];
    }

    private function link(bool $dryRun): array {
        $config = $this->createMock(IConfig::class);
        $config->method('getSystemValue')->willReturnMap([
            ['wordpress_dsn', '', 'sqlite:' . $this->databaseFile],
            ['wordpress_queries', [], []],
        ]);
        $config->method('setUserValue')->willReturnCallback(function (string $uid, string $appName, string $key, string $value): void {
            if ($appName === 'wordpress_login_backend' && $key === 'wordpress_login') {
                $this->linkedLogins[$uid] = $value;
            }
        });
        $groupManager = $this->createMock(IGroupManager::class);
        $groupManager->method('search')->with('')->willReturn($this->groups);
        $userManager = $this->createMock(IUserManager::class);
        $userManager->method('get')->willReturnCallback(fn (string $uid) => $this->users[$uid] ?? null);
        $subAdmin = $this->createMock(ISubAdmin::class);
        $subAdmin->method('isSubAdminOfGroup')->willReturnCallback(
            fn (IUser $user, IGroup $group) => in_array([$user->getUID(), $group->getGID()], $this->subAdmins, true),
        );

        return (new UserLinker($config, $groupManager, $subAdmin, $userManager, new WordPressUsers($config)))->link($dryRun);
    }
}
