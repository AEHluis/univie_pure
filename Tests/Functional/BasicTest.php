<?php

namespace Univie\UniviePure\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Endpoints\ResearchOutput;
use Univie\UniviePure\Endpoints\Equipments;

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
    public function webServiceMockWorks(): void
    {
        $webServiceMock = $this->createMockWebService();
        $this->assertInstanceOf(WebService::class, $webServiceMock);
    }

    /**
     * @test
     */
    public function environmentCheck(): void
    {
        $this->assertTrue(
            class_exists(WebService::class),
            'WebService class should be autoloadable'
        );

        $this->assertTrue(
            class_exists(ResearchOutput::class),
            'ResearchOutput class should be autoloadable'
        );

        $this->assertTrue(
            class_exists(Equipments::class),
            'Equipments class should be autoloadable'
        );
    }
}