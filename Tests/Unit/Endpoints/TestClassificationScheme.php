<?php
namespace Univie\UniviePure\Tests\Unit\Endpoints;

use Univie\UniviePure\Utility\ClassificationScheme;

class TestClassificationScheme extends ClassificationScheme
{
    /**
     * Override the constructor to bypass parent's initialization.
     */
    public function __construct()
    {
        // Do nothing to avoid parent's __construct()
    }

    /**
     * Override creation of WebService to return our fake.
     *
     * @return FakeWebServiceClassification
     */
    protected function createWebService()
    {
        return new FakeWebServiceClassification();
    }

    /**
     * Override getOrganisations() so that it uses our fake WebService.
     *
     * @param array &$config
     */
    public function getOrganisations(&$config)
    {
        // Build a minimal XML query.
        $postData = '<?xml version="1.0"?>
            <organisationalUnitsQuery>
                <size>999999</size>
                <locales><locale>' . $this->getLocale() . '</locale></locales>
                <fields>
                    <field>uuid</field>
                    <field>name.text.value</field>
                </fields>
                <orderings><ordering>name</ordering></orderings>
                <returnUsedContent>true</returnUsedContent>
            </organisationalUnitsQuery>';

        // Use our fake WebService instead of "new WebService".
        $webservice = $this->createWebService();
        $organisations = $webservice->getJson('organisational-units', $postData);

        if (is_array($organisations) && isset($organisations['items'])) {
            foreach ($organisations['items'] as $org) {
                $config['items'][] = [$org['name']['text'][0]['value'], $org['uuid']];
            }
        }
    }

    /**
     * Override getPersons() so that it uses our fake WebService.
     *
     * @param array &$config
     */
    public function getPersons(&$config)
    {
        $personXML = '<?xml version="1.0"?>
<personsQuery>
    <size>999999</size>
    <fields>
        <field>uuid</field>
        <field>name.*</field>
    </fields>
    <orderings><ordering>lastName</ordering></orderings>
    <employmentStatus>ACTIVE</employmentStatus>
</personsQuery>';

        $webservice = $this->createWebService();
        $persons = $webservice->getJson('persons', $personXML);

        if (is_array($persons) && isset($persons['items'])) {
            foreach ($persons['items'] as $p) {
                $config['items'][] = [$p['name']['lastName'] . ', ' . $p['name']['firstName'], $p['uuid']];
            }
        }
    }

    /**
     * Override getLocale() to return a fixed backend language.
     *
     * @return string
     */
    private function getLocale()
    {
        // For testing, simply return 'de_DE'
        return 'de_DE';
    }
}