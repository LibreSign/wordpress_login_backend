<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Tests\Unit\Listener;

use OCA\WordPressLoginBackend\Helper\HashPassword;
use OCA\WordPressLoginBackend\Listener\BeforePasswordUpdatedListener;
use OCP\IConfig;
use OCP\IUser;
use OCP\User\Events\BeforePasswordUpdatedEvent;
use PDO;
use PHPUnit\Framework\TestCase;

class BeforePasswordUpdatedListenerTest extends TestCase {
    private string $databaseFile;
    private PDO $database;

    protected function setUp(): void {
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'wordpress');
        $this->database = new PDO('sqlite:' . $this->databaseFile);
        $this->database->exec('CREATE TABLE wp_users (ID INTEGER PRIMARY KEY, user_login TEXT, user_email TEXT, user_pass TEXT, display_name TEXT)');
        $this->database->exec('CREATE TABLE wp_wc_orders (customer_id INTEGER, status TEXT, type TEXT)');
    }

    protected function tearDown(): void {
        unlink($this->databaseFile);
    }

    public function testWritesTheNewPasswordToWordPress(): void {
        $this->createWordPressUser('ana', (new HashPassword())->hashPassword('old password'));

        $this->changePassword('ana', 'new password');

        $this->assertTrue((new HashPassword())->validate('new password', $this->wordPressHashOf('ana')));
    }

    public function testKeepsTheWordPressHashThatAlreadyMatchesTheNewPassword(): void {
        $hash = (new HashPassword())->hashPassword('new password');
        $this->createWordPressUser('ana', $hash);

        $this->changePassword('ana', 'new password');

        $this->assertSame($hash, $this->wordPressHashOf('ana'));
    }

    public function testIgnoresAUserMissingFromWordPress(): void {
        $this->createWordPressUser('ana', 'hash of ana');

        $this->changePassword('bruno', 'new password');

        $this->assertSame('hash of ana', $this->wordPressHashOf('ana'));
    }

    private function createWordPressUser(string $login, string $hash): void {
        $statement = $this->database->prepare('INSERT INTO wp_users (user_login, user_email, user_pass, display_name) VALUES (:login, :email, :hash, :login)');
        $statement->execute(['login' => $login, 'email' => $login . '@example.org', 'hash' => $hash]);
    }

    private function wordPressHashOf(string $login): string {
        $statement = $this->database->prepare('SELECT user_pass FROM wp_users WHERE user_login = :login');
        $statement->execute(['login' => $login]);
        return (string)$statement->fetchColumn();
    }

    private function changePassword(string $uid, string $password): void {
        $config = $this->createMock(IConfig::class);
        $config->method('getSystemValue')->willReturnMap([
            ['wordpress_dsn', '', 'sqlite:' . $this->databaseFile],
            ['wordpress_queries', [], []],
        ]);
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn($uid);

        (new BeforePasswordUpdatedListener($config))->handle(new BeforePasswordUpdatedEvent($user, $password));
    }
}
