<?php
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Backend;

use OCA\WordPressLoginBackend\Config\QueryConfig;
use OCA\WordPressLoginBackend\Helper\HashPassword;
use OCP\Cache\CappedMemoryCache;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\User\Backend\ABackend;
use OCP\User\Backend\ICheckPasswordBackend;
use OCP\User\Backend\IGetRealUIDBackend;
use OCP\User\Backend\IProvideEnabledStateBackend;
use PDO;

class UserBackend extends ABackend implements
	IGetRealUIDBackend,
	IProvideEnabledStateBackend,
	ICheckPasswordBackend {
	private string $dsn;
	private ?PDO $pdo = null;
	private QueryConfig $queryConfig;
	public function __construct(
		private CappedMemoryCache $cache,
		private IConfig $config,
		private IDBConnection $dbConn,
	) {
		$this->dsn = (string) $this->config->getSystemValue('wordpress_dsn', '');
		$this->queryConfig = new QueryConfig($this->config);
	}

	public function getBackendName()
	{
		return 'WordpressLogin';
	}

	public function deleteUser($uid)
	{
		return false;
	}

	public function getUsers($search = '', $limit = null, $offset = null)
	{
		$limit = $this->fixLimit($limit);

		$users = $this->getDisplayNames($search, $limit, $offset);
		$userIds = array_map(function ($uid) {
			return (string)$uid;
		}, array_keys($users));
		sort($userIds, SORT_STRING | SORT_FLAG_CASE);
		return $userIds;
	}

	public function getDisplayName($uid): string {
		$uid = (string)$uid;
		$this->loadUser($uid);
		return empty($this->cache[$uid]['displayname']) ? $uid : $this->cache[$uid]['displayname'];
	}

	public function getDisplayNames($search = '', $limit = null, $offset = null) {
		return [];
	}

	public function hasUserListings() {
		return true;
	}

	public function userExists($uid) {
		$user = $this->loadUser($uid);
		return !!$user;
	}

	public function checkPassword(string $loginName, string $password) {
		$user = $this->loadUser($loginName);
		if (!$user) {
			return false;
		}
		$hashPassword = new HashPassword();
		$isOk = $hashPassword->checkPassword($password, $user['password']);
		if ($isOk) {
			return $loginName;
		}
		return false;
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

	/**
	 * Load an user in the cache
	 *
	 * @param string $uid the username
	 * @return boolean true if user was found, false otherwise
	 */
	private function loadUser($uid) {
		$uid = (string)$uid;
		if (!isset($this->cache[$uid])) {
			//guests $uid could be NULL or ''
			if ($uid === '') {
				$this->cache[$uid] = false;
				return true;
			}

			$nextcloudUser = $this->loadUserFromNextcloudDatabase($uid);
			if (!$nextcloudUser) {
				return false;
			}

			$db = $this->getDatabase();
			if (!$db) {
				$this->cache[$uid] = false;
				return false;
			}
			$statement = $db->prepare($this->queryConfig->getWordPressUserQuery());
			$statement->execute(['username' => $uid]);
			$row = $statement->fetch(PDO::FETCH_ASSOC);

			// "uid" is primary key, so there can only be a single result
			if ($row !== false) {
				$this->cache[$uid] = [
					'uid' => (string)$row['uid'],
					'displayname' => (string)$row['displayname'],
					'password' => (string)$row['password'],
					'enabled' => (bool)$row['enabled'],
				];
			} else {
				$this->cache[$uid] = false;
				return false;
			}
		}

		return $this->cache[$uid];
	}

	private function loadUserFromNextcloudDatabase(string $uid): array|false {
		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('uid', 'displayname', 'password')
			->from('users')
			->where(
				$qb->expr()->eq(
					'uid_lower',
					$qb->createNamedParameter(mb_strtolower($uid))
				)
			)
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		return $row;
	}

	public function getRealUID(string $uid): string {
		if (!$this->userExists($uid)) {
			throw new \RuntimeException($uid . ' does not exist');
		}

		return $this->cache[$uid]['uid'];
	}

	public function isUserEnabled(string $uid, callable $queryDatabaseValue): bool
	{
		$user = $this->loadUser($uid);
		if (!$user) {
			return false;
		}
		return $user['enabled'];
	}

	public function getDisabledUserList(?int $limit = null, int $offset = 0, string $search = ''): array
	{
		$db = $this->getDatabase();
		if (!$db) {
			return [];
		}
		$sql = $this->queryConfig->getDisabledUsersQuery();

		$limit = $this->fixLimit($limit);
		if (!is_null($limit) && $limit > 0) {
			$sql.= ' LIMIT :offset,:limit';
		}
		$statement = $db->prepare($sql);
		if (!is_null($limit) && $limit > 0) {
			$statement->bindParam(':limit', $limit, PDO::PARAM_INT);
			$statement->bindParam(':offset', $offset, PDO::PARAM_INT);
		}
		$search = '%'. $search . '%';
		$statement->bindParam(':search', $search);
		$statement->execute();
		$result = $statement->fetchAll(PDO::FETCH_ASSOC);
		return array_column($result, 'uid');
	}

	public function setUserEnabled(string $uid, bool $enabled, callable $queryDatabaseValue, callable $setDatabaseValue): bool
	{
		// Only is possible at WordPress side
		return false;
	}

	private function fixLimit($limit) {
		if (is_int($limit) && $limit >= 0) {
			return $limit;
		}

		return null;
	}
}
