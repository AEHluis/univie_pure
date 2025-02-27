<?php

namespace Univie\UniviePure\Tests\Unit\Endpoints;

use PHPUnit\Framework\TestCase;
use Univie\UniviePure\Endpoints\Persons;
use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Utility\CommonUtilities;

/**
 * Test subclass of Persons that uses our fake WebService.
 */
class TestPersons extends Persons
{
    private WebService $fakeWebService;

    public function __construct()
    {
        $this->fakeWebService = new FakeWebServicePersons();
        parent::__construct($this->fakeWebService);
    }
}

/**
 * PHPUnit test cases for the Persons endpoint.
 */
class PersonsTest extends TestCase
{
    private TestPersons $persons;

    protected function setUp(): void
    {
        $this->persons = new TestPersons();
    }

    public function testGetProfileReturnsExpectedValue()
    {
        $uuid = 'person123';
        $result = $this->persons->getProfile($uuid);
        $this->assertEquals('fakeProfile', $result, 'getProfile() did not return the expected value.');
    }

    public function testGetPortalUrlReturnsExpectedValue()
    {
        $uuid = 'person123';
        $result = $this->persons->getPortalUrl($uuid);
        $this->assertEquals('http://fake-portal', $result, 'getPortalUrl() did not return the expected value.');
    }
}
