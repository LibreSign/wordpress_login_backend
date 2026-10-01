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
