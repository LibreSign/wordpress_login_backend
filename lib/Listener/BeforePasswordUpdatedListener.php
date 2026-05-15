<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Listener;

use OCA\WordPressLoginBackend\Config\QueryConfig;
use OCA\WordPressLoginBackend\Helper\HashPassword;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\User\Events\BeforePasswordUpdatedEvent;
use PDO;

/**
 * @template-implements IEventListener<Event|BeforePasswordUpdatedEvent>
 */
class BeforePasswordUpdatedListener implements IEventListener {
	private string $dsn;
	private ?PDO $pdo = null;
	private QueryConfig $queryConfig;
	public function __construct(
		private IConfig $config,
	) {
		$this->dsn = (string) $this->config->getSystemValue('wordpress_dsn', '');
		$this->queryConfig = new QueryConfig($this->config);
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforePasswordUpdatedEvent) {
			return;
		}
		$db = $this->getDatabase();
		if (!$db) {
			return;
		}
		$hashPassword = new HashPassword();
		$hash = $hashPassword->hashPassword($event->getPassword());
		$username = $event->getUser()->getUID();
		$statement = $db->prepare($this->queryConfig->setWordPressPasswordQuery());
		$statement->bindValue(':username', $username);
		$statement->bindValue(':hash', $hash);
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
