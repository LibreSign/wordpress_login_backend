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

namespace OCP {
    use OCP\DB\QueryBuilder\IQueryBuilder;

    if (!interface_exists(IDBConnection::class)) {
        interface IDBConnection {
            public function getQueryBuilder(): IQueryBuilder;
        }
    }
}

namespace OCP\DB {
    if (!interface_exists(IResult::class)) {
        interface IResult {
            public function fetch(int $fetchMode = \PDO::FETCH_ASSOC);

            public function closeCursor(): bool;
        }
    }
}

namespace OCP\DB\QueryBuilder {
    use OCP\DB\IResult;

    if (!interface_exists(IExpressionBuilder::class)) {
        interface IExpressionBuilder {
            public function eq($x, $y, $type = null): string;
        }
    }

    if (!interface_exists(IQueryBuilder::class)) {
        interface IQueryBuilder {
            public function select(...$selects);

            public function from($from, $alias = null);

            public function where(...$predicates);

            public function expr(): IExpressionBuilder;

            public function createNamedParameter($value, $type = null, $placeHolder = null);

            public function setMaxResults($maxResults);

            public function executeQuery(): IResult;
        }
    }
}

namespace OCP\Cache {
    if (!class_exists(CappedMemoryCache::class)) {
        class CappedMemoryCache implements \ArrayAccess {
            private array $cache = [];

            public function offsetExists($offset): bool {
                return isset($this->cache[$offset]);
            }

            public function offsetGet($offset): mixed {
                return $this->cache[$offset];
            }

            public function offsetSet($offset, $value): void {
                $this->cache[$offset] = $value;
            }

            public function offsetUnset($offset): void {
                unset($this->cache[$offset]);
            }
        }
    }
}

namespace OCP\User\Backend {
    if (!class_exists(ABackend::class)) {
        abstract class ABackend {
        }
    }

    if (!interface_exists(ICheckPasswordBackend::class)) {
        interface ICheckPasswordBackend {
        }
    }

    if (!interface_exists(IGetRealUIDBackend::class)) {
        interface IGetRealUIDBackend {
        }
    }

    if (!interface_exists(IProvideEnabledStateBackend::class)) {
        interface IProvideEnabledStateBackend {
        }
    }
}

namespace Psr\Log {
    if (!interface_exists(LoggerInterface::class)) {
        interface LoggerInterface {
            public function error($message, array $context = []);
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

    if (!class_exists(PasswordUpdatedEvent::class)) {
        class PasswordUpdatedEvent extends Event {
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
