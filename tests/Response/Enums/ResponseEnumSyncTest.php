<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Response\Enums;

use PHPUnit\Framework\TestCase;
use Academe\Opayo\Pi\Response\Secure3D;
use Academe\Opayo\Pi\Response\Model\AvsCvcCheck;

/**
 * The response enums duplicate the legacy class constants while the package
 * supports PHP 8.1 (constants cannot be derived from enum cases until
 * PHP 8.2). These tests fail if the two drift apart.
 */
class ResponseEnumSyncTest extends TestCase
{
    public function testSecure3DStatusMatchesConstants()
    {
        $this->assertSame(
            [
                Secure3D::STATUS3D_AUTHENTICATED,
                Secure3D::STATUS3D_NOTCHECKED,
                Secure3D::STATUS3D_NOTAUTHENTICATED,
                Secure3D::STATUS3D_ERROR,
                Secure3D::STATUS3D_CARDNOTENROLLED,
                Secure3D::STATUS3D_ISSUERNOTENROLLED,
                Secure3D::STATUS3D_MALFORMEDORINVALID,
                Secure3D::STATUS3D_ATTEMPTONLY,
                Secure3D::STATUS3D_INCOMPLETE,
            ],
            array_map(fn ($case) => $case->value, Secure3DStatus::cases())
        );
    }

    public function testAvsCvcCheckStatusMatchesConstants()
    {
        $this->assertSame(
            [
                AvsCvcCheck::AVSCVCCHECK_STATUS_ALLMATCHED,
                AvsCvcCheck::AVSCVCCHECK_STATUS_SECURITYCODEMATCHONLY,
                AvsCvcCheck::AVSCVCCHECK_STATUS_ADDRESSMATCHONLY,
                AvsCvcCheck::AVSCVCCHECK_STATUS_NOMATCHES,
                AvsCvcCheck::AVSCVCCHECK_STATUS_NOTCHECKED,
            ],
            array_map(fn ($case) => $case->value, AvsCvcCheckStatus::cases())
        );
    }

    public function testAvsCvcCheckResultMatchesConstants()
    {
        $this->assertSame(
            [
                AvsCvcCheck::AVSCVCCHECK_RESULT_MATCHED,
                AvsCvcCheck::AVSCVCCHECK_RESULT_NOTPROVIDED,
                AvsCvcCheck::AVSCVCCHECK_RESULT_NOTCHECKED,
                AvsCvcCheck::AVSCVCCHECK_RESULT_NOTMATCHED,
            ],
            array_map(fn ($case) => $case->value, AvsCvcCheckResult::cases())
        );
    }
}
