<?php
/**
 * @copyright Copyright (c) 2024 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace OCA\WordPressLoginBackend\Backend;

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
	private ?IDBConnection $dbConn = null;
	public function __construct(
		private CappedMemoryCache $cache,
		private IConfig $config,
	) {
		$this->dsn = (string) $this->config->getSystemValue('wordpress_dsn', '');
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

	/**
	 * FIXME: This function should not be required!
	 */
	private function fixDI() {
		if ($this->dbConn === null) {
			$this->dbConn = \OC::$server->getDatabaseConnection();
		}
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
			$statement = $db->prepare(<<<SQL
				SELECT u.user_pass AS password,
				       u.user_login AS uid,
				       u.display_name AS displayname,
				       CASE WHEN o.status = 'wc-active' AND o.type = 'shop_subscription' THEN 1
				            ELSE 0
				            END AS enabled
				  FROM wp_wc_orders o
				  JOIN wp_users u ON o.customer_id = u.ID
				 WHERE o.status IN ('wc-active')
				   AND o.type = 'shop_subscription'
				   AND (u.user_login = :username OR u.user_email = :username)
				SQL
			);
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

	private function loadUserFromNextcloudDatabase($uid) {
		$this->fixDI();

		$qb = $this->dbConn->getQueryBuilder();
		$qb->select('uid', 'displayname', 'password')
			->from('users')
			->where(
				$qb->expr()->eq(
					'uid_lower', $qb->createNamedParameter(mb_strtolower($uid))
				)
			);
		$result = $qb->execute();
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
		$sql = <<<SQL
			SELECT u.user_login AS uid
			  FROM wp_wc_orders o
			  JOIN wp_users u ON o.customer_id = u.ID
			 WHERE (o.status NOT IN ('wc-active') OR o.type <> 'shop_subscription')
			   AND (u.user_login LIKE :search) OR (u.user_email LIKE :search)
			GROUP BY u.user_login
			SQL;

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
		return $statement->fetchColumn();
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
