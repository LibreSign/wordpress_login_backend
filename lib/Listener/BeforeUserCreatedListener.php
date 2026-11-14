<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Listener;

use InvalidArgumentException;
use OCA\WordPressLoginBackend\Db\WordPressUsers;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\User\Events\BeforeUserCreatedEvent;

/**
 * @template-implements IEventListener<Event|BeforeUserCreatedEvent>
 */
class BeforeUserCreatedListener implements IEventListener {
	public function __construct(
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private WordPressUsers $wordPressUsers,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeUserCreatedEvent) {
			return;
		}
		$creator = $this->userSession->getUser();
		if ($creator === null) {
			return;
		}
		$creatorUid = $creator->getUID();
		if ($this->groupManager->isAdmin($creatorUid) || $this->groupManager->isDelegatedAdmin($creatorUid)) {
			return;
		}
		if ($this->wordPressUsers->findByLogin($event->getUid()) !== null) {
			throw new InvalidArgumentException('This account id is reserved');
		}
	}
}
