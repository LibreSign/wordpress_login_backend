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
