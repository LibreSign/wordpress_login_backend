<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Tests\Unit\Listener;

use OCA\WordPressLoginBackend\Db\WordPressUsers;
use OCA\WordPressLoginBackend\Helper\HashPassword;
use OCA\WordPressLoginBackend\Listener\PasswordUpdatedListener;
use OCP\IConfig;
use OCP\IUser;
use OCP\User\Events\PasswordUpdatedEvent;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PasswordUpdatedListenerTest extends TestCase {
    private string $databaseFile;
    private PDO $database;
    private array $linkedLogins = [];
    private array $queries = [];

    protected function setUp(): void {
        $this->databaseFile = tempnam(sys_get_temp_dir(), 'wordpress');
        $this->database = new PDO('sqlite:' . $this->databaseFile);
        $this->database->exec('CREATE TABLE wp_users (ID INTEGER PRIMARY KEY, user_login TEXT COLLATE NOCASE, user_email TEXT, user_pass TEXT, display_name TEXT)');
        $this->database->exec('CREATE TABLE wp_wc_orders (customer_id INTEGER, status TEXT, type TEXT)');
    }

    protected function tearDown(): void {
        unlink($this->databaseFile);
    }

    public function testWritesTheNewPasswordToWordPress(): void {
        $this->createWordPressUser('ana', (new HashPassword())->hashPassword('old password'));
        $this->linkedLogins['ana'] = 'ana';

        $this->changePassword('ana', 'new password');

        $this->assertTrue((new HashPassword())->validate('new password', $this->wordPressHashOf('ana')));
    }

    public function testKeepsTheWordPressHashThatAlreadyMatchesTheNewPassword(): void {
        $hash = (new HashPassword())->hashPassword('new password');
        $this->createWordPressUser('ana', $hash);
        $this->linkedLogins['ana'] = 'ana';

        $this->changePassword('ana', 'new password');

        $this->assertSame($hash, $this->wordPressHashOf('ana'));
    }

    public function testWritesTheNewPasswordWhenAnotherUserHasTheLoginAsEmail(): void {
        $this->createWordPressUser('bruno', (new HashPassword())->hashPassword('new password'), 'ana@example.org');
        $this->createWordPressUser('ana@example.org', (new HashPassword())->hashPassword('old password'), 'ana@example.net');
        $this->linkedLogins['ana@example.org'] = 'ana@example.org';

        $this->changePassword('ana@example.org', 'new password');

        $this->assertTrue((new HashPassword())->validate('new password', $this->wordPressHashOf('ana@example.org')));
    }

    public function testKeepsTheHashOfALoginThatDiffersOnlyInCase(): void {
        $this->createWordPressUser('ana', (new HashPassword())->hashPassword('old password'));
        $this->createWordPressUser('Ana', 'hash of Ana', 'other@example.org');
        $this->linkedLogins['ana'] = 'ana';

        $this->changePassword('ana', 'new password');

        $this->assertSame('hash of Ana', $this->wordPressHashOf('Ana'));
    }

    public function testIgnoresAUserMissingFromWordPress(): void {
        $this->createWordPressUser('ana', 'hash of ana');
        $this->linkedLogins['bruno'] = 'bruno';

        $this->changePassword('bruno', 'new password');

        $this->assertSame('hash of ana', $this->wordPressHashOf('ana'));
    }

    public function testIgnoresAnAccountThatIsNotLinkedToWordPress(): void {
        $this->createWordPressUser('ana', 'hash of ana');

        $this->changePassword('ana', 'new password');

        $this->assertSame('hash of ana', $this->wordPressHashOf('ana'));
    }

    public function testIgnoresAnAccountLinkedToAnotherLogin(): void {
        $this->createWordPressUser('ana', 'hash of ana');
        $this->linkedLogins['ana'] = 'bruno';

        $this->changePassword('ana', 'new password');

        $this->assertSame('hash of ana', $this->wordPressHashOf('ana'));
    }

    public function testLogsAFailedUpdateWithoutThrowing(): void {
        $this->createWordPressUser('ana', 'hash of ana');
        $this->linkedLogins['ana'] = 'ana';
        $this->queries['set_wordpress_password'] = 'UPDATE missing_table SET user_pass = :hash WHERE user_login = :username';
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $this->changePassword('ana', 'new password', $logger);

        $this->assertSame('hash of ana', $this->wordPressHashOf('ana'));
    }

    private function createWordPressUser(string $login, string $hash, ?string $email = null): void {
        $statement = $this->database->prepare('INSERT INTO wp_users (user_login, user_email, user_pass, display_name) VALUES (:login, :email, :hash, :login)');
        $statement->execute(['login' => $login, 'email' => $email ?? $login . '@example.org', 'hash' => $hash]);
    }

    private function wordPressHashOf(string $login): string {
        $statement = $this->database->prepare('SELECT user_pass FROM wp_users WHERE user_login = :login COLLATE BINARY');
        $statement->execute(['login' => $login]);
        return (string)$statement->fetchColumn();
    }

    private function changePassword(string $uid, string $password, ?LoggerInterface $logger = null): void {
        $config = $this->createMock(IConfig::class);
        $config->method('getSystemValue')->willReturnMap([
            ['wordpress_dsn', '', 'sqlite:' . $this->databaseFile],
            ['wordpress_queries', [], $this->queries],
        ]);
        $config->method('getUserValue')->willReturnCallback(
            fn (string $userId, string $appName, string $key) => $appName === 'wordpress_login_backend' && $key === 'wordpress_login'
                ? ($this->linkedLogins[$userId] ?? '')
                : '',
        );
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn($uid);

        $listener = new PasswordUpdatedListener($config, new WordPressUsers($config), $logger ?? $this->createMock(LoggerInterface::class));
        $listener->handle(new PasswordUpdatedEvent($user, $password));
    }
}
