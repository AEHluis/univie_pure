<?php

namespace Univie\UniviePure\Tests\Functional;

use Univie\UniviePure\Service\ApiServiceInterface;
use Univie\UniviePure\Service\OpenApi\OpenApiService;

/**
 * Basic test case to verify testing infrastructure works
 */
class BasicTest extends BaseFunctionalTestCase
{
    /**
     * @test
     */
    public function basicTestWorks(): void
    {
        $this->assertTrue(true, 'This test should always pass');
    }

    /**
     * @test
     */
    public function environmentCheck(): void
    {
        $this->assertTrue(
            interface_exists(ApiServiceInterface::class),
            'ApiServiceInterface should be autoloadable'
        );

        $this->assertTrue(
            class_exists(OpenApiService::class),
            'OpenApiService class should be autoloadable'
        );
    }
}
