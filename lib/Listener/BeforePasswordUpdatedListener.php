<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Listener;

use OCA\WordPressLoginBackend\Helper\HashPassword;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\User\Events\BeforePasswordUpdatedEvent;
use PDO;

/**
 * @template-implements IEventListener<Event|BeforePasswordUpdatedEvent>
 */
class BeforePasswordUpdatedListener implements IEventListener {
	private string $dsn;
	private ?PDO $pdo = null;
	public function __construct(
		private IConfig $config,
	) {
		$this->dsn = (string) $this->config->getSystemValue('wordpress_dsn', '');
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforePasswordUpdatedEvent) {
			return;
		}
		$hashPassword = new HashPassword();
		$hash = $hashPassword->hashPassword($event->getPassword());
		$statement = $this->getDatabase()->prepare(
			"UPDATE wp_users SET user_pass = :hash WHERE user_login = :username"
		);
		$statement->bindParam(':username', $event->getUser()->getUID());
		$statement->bindParam(':hash', $hash);
		$statement->execute();
	}

	private function getDatabase(): ?PDO {
		if (!$this->dsn) {
			return null;
		}
		if (!$this->pdo instanceof PDO) {
			$this->pdo = new PDO($this->dsn);
		}
		return $this->pdo;
	}
}
