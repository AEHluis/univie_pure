<?php

declare(strict_types=1);

namespace Univie\UniviePure\Tests\Unit\Service\OpenApi;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Univie\UniviePure\Service\OpenApi\OpenApiException;
use Univie\UniviePure\Service\OpenApi\OpenApiResponseParser;

class OpenApiResponseParserTest extends TestCase
{
    private LoggerInterface|MockObject $logger;
    private OpenApiResponseParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parser = new OpenApiResponseParser($this->logger);
    }

    #[Test]
    public function parseAcceptsProblemJsonAsJsonErrorResponse(): void
    {
        $response = new Response(
            404,
            ['Content-Type' => 'application/problem+json'],
            json_encode([
                'type' => '/error',
                'title' => 'Content not found',
                'status' => 404,
                'detail' => "Content with id 'project-uuid' not found",
            ], JSON_THROW_ON_ERROR)
        );

        $this->logger
            ->expects($this->never())
            ->method('error');

        $this->expectException(OpenApiException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage("Content with id 'project-uuid' not found");

        $this->parser->parse($response);
    }
}
