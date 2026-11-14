<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Db;

use OCA\WordPressLoginBackend\Config\QueryConfig;
use OCP\IConfig;
use PDO;

class WordPressUsers {
	private ?PDO $pdo = null;
	private QueryConfig $queryConfig;

	public function __construct(
		private IConfig $config,
	) {
		$this->queryConfig = new QueryConfig($this->config);
	}

	public function findByLogin(string $login): ?array {
		$database = $this->getDatabase();
		if (!$database) {
			return null;
		}
		$statement = $database->prepare($this->queryConfig->getWordPressUserQuery());
		$statement->execute(['username' => $login]);
		foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
			if ((string)$row['uid'] === $login) {
				return $row;
			}
		}
		return null;
	}

	public function updatePassword(string $login, string $hash): void {
		$database = $this->getDatabase();
		if (!$database) {
			return;
		}
		$statement = $database->prepare($this->queryConfig->setWordPressPasswordQuery());
		$statement->bindValue(':username', $login);
		$statement->bindValue(':hash', $hash);
		$statement->execute();
	}

	private function getDatabase(): ?PDO {
		$dsn = (string)$this->config->getSystemValue('wordpress_dsn', '');
		if ($dsn === '') {
			return null;
		}
		if (!$this->pdo instanceof PDO) {
			$this->pdo = new PDO($dsn);
		}
		return $this->pdo;
	}
}
