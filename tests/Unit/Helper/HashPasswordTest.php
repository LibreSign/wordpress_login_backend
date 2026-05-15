<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\WordPressLoginBackend\Tests\Unit\Helper;

use OCA\WordPressLoginBackend\Helper\HashPassword;
use PHPUnit\Framework\TestCase;

class HashPasswordTest extends TestCase {
    public function testHashPasswordReturnsWordPressPrefix(): void {
        $hashPassword = new HashPassword();

        $hash = $hashPassword->hashPassword('my-safe-password');

        $this->assertStringStartsWith('$wp', $hash);
    }

    public function testValidateReturnsTrueForCorrectPassword(): void {
        $hashPassword = new HashPassword();
        $hash = $hashPassword->hashPassword('my-safe-password');

        $isValid = $hashPassword->validate('my-safe-password', $hash);

        $this->assertTrue($isValid);
    }

    public function testValidateReturnsFalseForWrongPassword(): void {
        $hashPassword = new HashPassword();
        $hash = $hashPassword->hashPassword('my-safe-password');

        $isValid = $hashPassword->validate('different-password', $hash);

        $this->assertFalse($isValid);
    }

    public function testHashPasswordReturnsAsteriskWhenPasswordIsTooLong(): void {
        $hashPassword = new HashPassword();
        $tooLongPassword = str_repeat('a', 4097);

        $hash = $hashPassword->hashPassword($tooLongPassword);

        $this->assertSame('*', $hash);
    }
}
