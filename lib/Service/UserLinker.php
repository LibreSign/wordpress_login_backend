<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Service;

use OCA\WordPressLoginBackend\AppInfo\Application;
use OCA\WordPressLoginBackend\Db\WordPressUsers;
use OCP\Group\ISubAdmin;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class UserLinker {
	public function __construct(
		private IConfig $config,
		private IGroupManager $groupManager,
		private ISubAdmin $subAdmin,
		private IUserManager $userManager,
		private WordPressUsers $wordPressUsers,
	) {
	}

	/**
	 * @return array{linked: list<string>, skipped: list<string>}
	 */
	public function link(bool $dryRun): array {
		$linked = [];
		$skipped = [];
		foreach ($this->groupManager->search('') as $group) {
			$groupId = $group->getGID();
			$owner = $this->userManager->get($groupId);
			if ($owner instanceof IUser
				&& $owner->getUID() === $groupId
				&& $this->subAdmin->isSubAdminOfGroup($owner, $group)
				&& $this->wordPressUsers->findByLogin($groupId) !== null) {
				$linked[$groupId] = $groupId;
			}
			foreach ($group->getUsers() as $member) {
				$skipped[$member->getUID()] = $member->getUID();
			}
		}
		$skipped = array_diff_key($skipped, $linked);
		if (!$dryRun) {
			foreach ($linked as $uid) {
				$this->config->setUserValue($uid, Application::APP_ID, Application::LINKED_LOGIN_KEY, $uid);
			}
		}
		return [
			'linked' => array_values($linked),
			'skipped' => array_values($skipped),
		];
	}
}
