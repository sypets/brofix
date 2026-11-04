<?php

declare(strict_types=1);
namespace Sypets\Brofix\Tests\Unit\CheckLinks\LinkTargetResponse;

use PHPUnit\Framework\Attributes\Test;
use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class LinkTargetResponseTest extends UnitTestCase
{
    #[Test]
    public function toJsonReturnsValidJsonForInvalidUtf8InExceptionMessage(): void
    {
        $subject = LinkTargetResponse::createInstanceByError(
            'unknown',
            0,
            '',
            "Unable to parse URI: https://example.org/caf\xE9"
        );

        $json = $subject->toJson();

        self::assertJson($json);
        self::assertSame(
            "Unable to parse URI: https://example.org/caf\u{FFFD}",
            LinkTargetResponse::createInstanceFromJson($json)->getExceptionMessage()
        );
    }

    #[Test]
    public function toJsonKeepsValidUtf8Unchanged(): void
    {
        $subject = LinkTargetResponse::createInstanceByError('unknown', 0, '', 'café');

        self::assertSame(
            'café',
            LinkTargetResponse::createInstanceFromJson($subject->toJson())->getExceptionMessage()
        );
    }
}
