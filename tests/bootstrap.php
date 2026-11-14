<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace {
    require_once __DIR__ . '/../vendor/autoload.php';
}

namespace OCP {
    if (!interface_exists(IConfig::class)) {
        interface IConfig {
            public function getSystemValue($key, $default = '');

            public function getUserValue($userId, $appName, $key, $default = '');

            public function setUserValue($userId, $appName, $key, $value, $preCondition = null);
        }
    }
}

namespace OCP {
    if (!interface_exists(IUser::class)) {
        interface IUser {
            public function getUID();
        }
    }
}

namespace OCP {
    if (!interface_exists(IGroup::class)) {
        interface IGroup {
            public function getGID();

            public function getUsers();
        }
    }

    if (!interface_exists(IGroupManager::class)) {
        interface IGroupManager {
            public function search(string $search, ?int $limit = null, ?int $offset = 0);
        }
    }

    if (!interface_exists(IUserManager::class)) {
        interface IUserManager {
            public function get($uid);
        }
    }
}

namespace OCP\Group {
    use OCP\IGroup;
    use OCP\IUser;

    if (!interface_exists(ISubAdmin::class)) {
        interface ISubAdmin {
            public function isSubAdminOfGroup(IUser $user, IGroup $group): bool;
        }
    }
}

namespace OCP\AppFramework {
    if (!class_exists(App::class)) {
        class App {
            public function __construct(string $appName, array $urlParams = []) {
            }
        }
    }
}

namespace OCP\AppFramework\Bootstrap {
    if (!interface_exists(IBootstrap::class)) {
        interface IBootstrap {
        }
    }
}

namespace OCP\EventDispatcher {
    if (!class_exists(Event::class)) {
        class Event {
        }
    }

    if (!interface_exists(IEventListener::class)) {
        interface IEventListener {
            public function handle(Event $event): void;
        }
    }
}

namespace OCP\User\Events {
    use OCP\EventDispatcher\Event;
    use OCP\IUser;

    if (!class_exists(BeforePasswordUpdatedEvent::class)) {
        class BeforePasswordUpdatedEvent extends Event {
            public function __construct(
                private IUser $user,
                private string $password,
            ) {
            }

            public function getUser(): IUser {
                return $this->user;
            }

            public function getPassword(): string {
                return $this->password;
            }
        }
    }
}
