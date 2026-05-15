<?php
/**
 * SPDX-FileCopyrightText: 2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\AppInfo;

use OCA\WordPressLoginBackend\Listener\BeforePasswordUpdatedListener;
use \OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\BeforePasswordUpdatedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'wordpress_login_backend';

	public function __construct(array $urlParams = array()) {
		parent::__construct(Application::APP_ID, $urlParams);
	}

	public function boot(IBootContext $context): void {
		$userBackend = $context->getAppContainer()->get(\OCA\WordPressLoginBackend\Backend\UserBackend::class);
		$userManager = $context->getAppContainer()->get('OCP\IUserManager');
		$userManager->registerBackend($userBackend);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforePasswordUpdatedEvent::class, BeforePasswordUpdatedListener::class);
	}
}
