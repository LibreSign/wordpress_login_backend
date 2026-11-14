<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Command;

use OCA\WordPressLoginBackend\Service\UserLinker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LinkUsers extends Command {
	public function __construct(
		private UserLinker $userLinker,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('wordpress-login-backend:link-users')
			->setDescription('Link the accounts that own a group of the same name to the WordPress user with that login')
			->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the accounts without linking them');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$dryRun = (bool)$input->getOption('dry-run');
		$result = $this->userLinker->link($dryRun);
		foreach ($result['linked'] as $uid) {
			$output->writeln(($dryRun ? 'Would link: ' : 'Linked: ') . $uid);
		}
		foreach ($result['skipped'] as $uid) {
			$output->writeln('Skipped: ' . $uid);
		}
		return 0;
	}
}
