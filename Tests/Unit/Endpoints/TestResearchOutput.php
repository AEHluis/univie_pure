<?php
namespace Univie\UniviePure\Tests\Unit\Endpoints;

use Univie\UniviePure\Endpoints\ResearchOutput;
use Univie\UniviePure\Utility\CommonUtilities;
use Univie\UniviePure\Utility\LanguageUtility;

class TestResearchOutput extends ResearchOutput
{
    /**
     * Override the constructor to avoid parent's initialization.
     */
    public function __construct()
    {
        // Do nothing to bypass parent's __construct()
    }

    /**
     * Override the creation of the WebService to return our fake.
     *
     * @return FakeWebServiceResearchOutput
     */
    protected function createWebService()
    {
        return new FakeWebServiceResearchOutput();
    }

    /**
     * Override getPublicationList to match the parent class signature
     * and use our fake implementation.
     *
     * @param array $settings Configuration settings
     * @param int $currentPageNumber Current page number
     * @param string $lang Language code
     * @return array Publication data
     */
    public function getPublicationList(array $settings, int $currentPageNumber, string $lang = 'de_DE'): array
    {
        // Set default page size if not provided
        $pageSize = isset($settings['pageSize']) ? $settings['pageSize'] : 20;
        if ($pageSize == 0) {
            $settings['pageSize'] = 20;
        }

        // Get the publication list from our fake implementation
        $results_short = $this->getRealPublicationList($settings, $currentPageNumber, $lang);

        // Process and format the results
        if (is_array($results_short) && isset($results_short['count']) && $results_short['count'] > 0) {
            if (array_key_exists("contributionToJournal", $results_short)) {
                foreach ($results_short["contributionToJournal"] as $index => $contributionToJournal) {
                    if (isset($contributionToJournal["rendering"])) {
                        $new_render = $contributionToJournal["rendering"];
                        $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $new_render);
                        $results_short["contributionToJournal"][$index]["rendering"] = $new_render;
                    }
                }
            }
        }

        // Calculate offset
        $results_short['offset'] = (((int)$currentPageNumber - 1) * (int)$settings['pageSize']);

        return $results_short;
    }

    /**
     * Override getAlternativeSinglePublication() to return a fixed fake portal URL response.
     *
     * @param string $uuid
     * @param string $lang
     * @return array
     */
    public function getAlternativeSinglePublication(string $uuid, string $lang = 'de_DE'): array
    {
        return [
            'items' => [
                [
                    'info' => [
                        'portalUrl' => 'http://fake-portal-for-' . $uuid
                    ]
                ]
            ]
        ];
    }

    /**
     * Override getPortalRendering() to use our fake WebService.
     *
     * @param string $uuid
     * @param string $lang
     * @return string
     */
    public function getPortalRendering($uuid, $lang)
    {
        $webservice = $this->createWebService();
        return $webservice->getSingleResponse('research-outputs', $uuid, 'xml', true, 'detailsPortal', $lang);
    }

    /**
     * Override getStandardRendering() to use our fake WebService.
     *
     * @param string $uuid
     * @param string $lang
     * @return string
     */
    public function getStandardRendering($uuid, $lang)
    {
        $webservice = $this->createWebService();
        return $webservice->getSingleResponse('research-outputs', $uuid, 'xml', true, 'standard', $lang);
    }

    /**
     * Override getBibtex() to use our fake WebService.
     *
     * @param string $uuid
     * @param string $lang
     * @return string
     */
    public function getBibtex($uuid, $lang)
    {
        $webservice = $this->createWebService();
        return $webservice->getSingleResponse('research-outputs', $uuid, 'xml', true, 'bibtex', $lang);
    }

    /**
     * Override getRealPublicationList() so that it uses our fake WebService and
     * a simplified transformation.
     *
     * @param array $settings
     * @param int   $currentPageNumber
     * @param string $lang
     * @return array
     */
    public function getRealPublicationList(array $settings, int $currentPageNumber, string $lang): array
    {
        $xml = '<?xml version="1.0"?>
                <researchOutputsQuery>';
        $xml .= CommonUtilities::getProjectsXml($settings);
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);
        $xml .= CommonUtilities::getOffset($settings['pageSize'], $currentPageNumber);
        $xml .= '<linkingStrategy>noLinkingStrategy</linkingStrategy>';
        $xml .= LanguageUtility::getLocale();
        $xml .= '<renderings><rendering>' . $settings['rendering'] . '</renderings>';
        $pubtype = "";
        $settings['showPublicationType'] = 1;
        if ($settings['showPublicationType'] == 1) {
            $pubtype = $this->getFieldForPublicationType();
        }
        $xml .= '<fields>
                    ' . $pubtype . '
                    <field>uuid</field>
                    <field>rendering</field>
                    <field>publicationStatuses</field>
                    <field>personAssociations</field>
                 </fields>';
        if (!array_key_exists('researchOutputOrdering', $settings) || strlen($settings['researchOutputOrdering']) == 0) {
            $settings['researchOutputOrdering'] = '-publicationYear';
        }
        $xml .= '<orderings><ordering>' . $settings['researchOutputOrdering'] . '</ordering></orderings>';
        $xml .= '<returnUsedContent>true</returnUsedContent>';
        $xml .= '<navigationLink>true</navigationLink>';
        if (($settings['narrowByPublicationType'] == 1) && ($settings['selectorPublicationType'] != '')) {
            $xml .= $this->getResearchTypesXml($settings['selectorPublicationType']);
        }
        if ($settings['peerReviewedOnly'] == 1) {
            $xml .= '<peerReviews><peerReview>PEER_REVIEW</peerReview></peerReviews>';
        }
        if ($settings['notPeerReviewedOrNotSetOnly'] == 1) {
            $xml .= '<peerReviews><peerReview>NOT_PEER_REVIEW</peerReview><peerReview>NOT_SET</peerReview></peerReviews>';
        }
        if ($settings['publishedBeforeDate']) {
            $xml .= '<publishedBeforeDate>' . $settings['publishedBeforeDate'] . '</publishedBeforeDate>';
        }
        if ($settings['publishedAfterDate']) {
            $xml .= '<publishedAfterDate>' . $settings['publishedAfterDate'] . '</publishedAfterDate>';
        }
        $xml .= '<workflowSteps>
                    <workflowStep>approved</workflowStep>
                    <workflowStep>forApproval</workflowStep>
                    <workflowStep>forRevalidation</workflowStep>
                    <workflowStep>validated</workflowStep>
                  </workflowSteps>';
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);
        if ($settings['narrowBySearch'] || $settings['filter']) {
            $xml .= $this->getSearchXml($settings);
        }
        $xml .= '</researchOutputsQuery>';

        $webservice = $this->createWebService();
        $publications = $webservice->getJson('research-outputs', $xml);
        return $this->transformArray($publications, $settings, $lang);
    }

    /**
     * Override transformArray() to ensure that the returned array has a key "contributionToJournal".
     *
     * In our fake JSON response, the key "contributionToJournal" is already present.
     * If it is not, we create it.
     *
     * @param array $publications
     * @param array $settings
     * @param string $lang
     * @return array
     */
    protected function transformArray(array $publications, array $settings, string $lang): array
    {
        if (!isset($publications['contributionToJournal'])) {
            // If missing, create it from items.
            if (isset($publications['items'])) {
                $publications['contributionToJournal'] = $publications['items'];
            } else {
                $publications['contributionToJournal'] = [];
            }
        }
        return $publications;
    }

    /**
     * Helper method to safely get array values with defaults
     *
     * @param array $array The array to get value from
     * @param string|int $key The key to look for
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The value or default
     */
    protected function getArrayValue($array, $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    /**
     * Helper method to calculate offset
     *
     * @param int $pageSize Page size
     * @param int $currentPageNumber Current page number
     * @return int Calculated offset
     */
    protected function calculateOffset(int $pageSize, int $currentPageNumber): int
    {
        return ($currentPageNumber - 1) * $pageSize;
    }

    /**
     * Dummy implementation of getFieldForPublicationType().
     *
     * @return string
     */
    public function getFieldForPublicationType(): string
    {
        return '<field>publicationStatuses.publicationStatus.*</field>';
    }

    /**
     * Dummy implementation of getResearchTypesXml().
     *
     * @param string $researchTypes
     * @return string
     */
    public function getResearchTypesXml(string $researchTypes): string
    {
        $xml = "<typeUris>";
        $types = explode(',', $researchTypes);
        foreach ((array)$types as $type) {
            if (strpos($type, "|") !== false) {
                $tmp = explode("|", $type);
                $type = $tmp[0];
            }
            $xml .= '<typeUri>' . $type . '</typeUri>';
        }
        $xml .= "</typeUris>";
        return $xml;
    }

    /**
     * Dummy implementation of getSearchXml().
     *
     * @param array $settings
     * @return string
     */
    public function getSearchXml($settings): string
    {
        $terms = $settings['narrowBySearch'];
        if ($settings['filter']) {
            $terms .= ' ' . $settings['filter'];
        }
        return '<searchString>' . trim($terms) . '</searchString>';
    }
}
