<?php

declare(strict_types=1);

namespace Academe\Opayo\Pi\Factory;

use Academe\Opayo\Pi\Response;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class ResponseFactoryTest extends TestCase
{
    public function testUnrecognisedDataThrowsInsteadOfFallingThrough()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageMatches('/Unrecognised response data \(HTTP 200\)/');

        ResponseFactory::fromData(['something' => 'unexpected'], 200);
    }

    public function testEmpty204IsStillNoContent()
    {
        $this->assertInstanceOf(Response\NoContent::class, ResponseFactory::fromData([], 204));
    }

    public function testErrorsAreStillAnErrorCollection()
    {
        $this->assertInstanceOf(
            Response\ErrorCollection::class,
            ResponseFactory::fromData(['errors' => [['code' => 1003, 'description' => 'Missing mandatory field']]], 422)
        );
    }
}
