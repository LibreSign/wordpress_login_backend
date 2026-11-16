<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Listener;

use OCA\WordPressLoginBackend\AppInfo\Application;
use OCA\WordPressLoginBackend\Db\WordPressUsers;
use OCA\WordPressLoginBackend\Helper\HashPassword;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\User\Events\PasswordUpdatedEvent;
use PDOException;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event|PasswordUpdatedEvent>
 */
class PasswordUpdatedListener implements IEventListener {
	public function __construct(
		private IConfig $config,
		private WordPressUsers $wordPressUsers,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof PasswordUpdatedEvent) {
			return;
		}
		$username = $event->getUser()->getUID();
		if ($this->config->getUserValue($username, Application::APP_ID, Application::LINKED_LOGIN_KEY) !== $username) {
			return;
		}
		$hashPassword = new HashPassword();
		try {
			$wordPressUser = $this->wordPressUsers->findByLogin($username);
			if ($wordPressUser === null || $hashPassword->validate($event->getPassword(), (string)$wordPressUser['password'])) {
				return;
			}
			$this->wordPressUsers->updatePassword($username, $hashPassword->hashPassword($event->getPassword()));
		} catch (PDOException $exception) {
			$this->logger->error('Could not update the WordPress password of {uid}', [
				'uid' => $username,
				'exception' => $exception,
			]);
		}
	}
}
