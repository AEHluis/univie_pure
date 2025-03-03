<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoints;

use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Utility\CommonUtilities;
use Univie\UniviePure\Utility\LanguageUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class ResearchOutput extends Endpoints
{
    private readonly WebService $webservice;

    public function __construct(WebService $webservice)
    {
        $this->webservice = $webservice;
    }

    /**
     * Produce XML for the list query of research-output
     *
     * @param array $settings Configuration settings
     * @param int $currentPageNumber Current page number
     * @return array Publication data
     */
    public function getPublicationList(array $settings, int $currentPageNumber, string $lang): array
    {
        // Set default page size if not provided
        $settings['pageSize'] = $this->getArrayValue($settings, 'pageSize', 20);

        $results_short = [];
        $results_portal = [];

        // Handle special rendering case "luhlong"
        if ($this->getArrayValue($settings, 'rendering') == "luhlong") {
            // Get results with portal-short rendering
            $settings['rendering'] = 'portal-short';
            $results_short = $this->getRealPublicationList($settings, $currentPageNumber, $lang);

            // Get results with detailsPortal rendering
            $settings['rendering'] = 'detailsPortal';
            $results_portal = $this->getRealPublicationList($settings, $currentPageNumber, $lang);

            // Combine the results
            if (is_array($results_short) && array_key_exists('contributionToJournal', $results_short)) {
                foreach ($results_short['contributionToJournal'] as $i => $r) {
                    if (isset($results_portal['contributionToJournal'][$i]['rendering'])) {
                        $results_short['contributionToJournal'][$i]['rendering'] =
                            $r['rendering'] . $results_portal['contributionToJournal'][$i]['rendering'];
                    }
                }
            }
        } else {
            $results_short = $this->getRealPublicationList($settings, $currentPageNumber, $lang);
        }

        // Process and format the results
        if (is_array($results_short) && $this->getArrayValue($results_short, 'count', 0) > 0) {
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
        $results_short['offset'] = $this->calculateOffset((int)($settings['pageSize']), (int)($currentPageNumber));

        return $results_short;
    }

    /**
     * Produce XML for the list query of research-output
     *
     * @param array $settings Configuration settings
     * @param int $currentPageNumber Current page number
     * @return array Publication data
     */
    public function getRealPublicationList(array $settings, int $currentPageNumber, string $lang): array
    {
        $xml = '<?xml version="1.0"?><researchOutputsQuery>';

        // Get projects XML if available
        $xml .= CommonUtilities::getProjectsXml($settings);

        // Set page size
        $xml .= CommonUtilities::getPageSize($this->getArrayValue($settings, 'pageSize', 20));

        // Set offset
        $xml .= CommonUtilities::getOffset($this->getArrayValue($settings, 'pageSize', 20), $currentPageNumber);

        $xml .= '<linkingStrategy>noLinkingStrategy</linkingStrategy>';

        // Add locale information
        $xml .= LanguageUtility::getLocale();

        // Set rendering
        $rendering = $this->getArrayValue($settings, 'rendering', 'portal-short');
        $xml .= '<renderings><rendering>' . $rendering . '</rendering></renderings>';

        // Handle publication type
        $pubtype = "";
        if ($this->getArrayValue($settings, 'showPublicationType', 0) == 1) {
            $pubtype = $this->getFieldForPublicationType();
        }

        // Add fields
        $xml .= '<fields>
                ' . $pubtype . '
                    <field>uuid</field>
                    <field>renderings.*</field>
                    <field>publicationStatuses.*</field>
                    <field>personAssociations.*</field>';

        // Add grouping if enabled
        if ($this->getArrayValue($settings, 'groupByYear', 0) == 1) {
            $xml .= $this->getFieldForGrouping();
        }
        $xml .= '</fields>';

        // Set ordering
        $ordering = $this->getArrayValue($settings, 'researchOutputOrdering', '-publicationYear');
        $xml .= '<orderings><ordering>' . $ordering . '</ordering></orderings>';

        $xml .= '<returnUsedContent>true</returnUsedContent>';
        $xml .= '<navigationLink>true</navigationLink>';

        // Add research types if enabled
        if (($this->getArrayValue($settings, 'narrowByPublicationType', 0) == 1) &&
            ($this->getArrayValue($settings, 'selectorPublicationType', '') != '')) {
            $xml .= $this->getResearchTypesXml($settings['selectorPublicationType']);
        }

        // Add peer review filter if enabled
        if ($this->getArrayValue($settings, 'peerReviewedOnly', 0) == 1) {
            $xml .= '<peerReviews><peerReview>PEER_REVIEW</peerReview></peerReviews>';
        }

        // Add not peer reviewed filter if enabled
        if ($this->getArrayValue($settings, 'notPeerReviewedOrNotSetOnly', 0) == 1) {
            $xml .= '<peerReviews><peerReview>NOT_PEER_REVIEW</peerReview><peerReview>NOT_SET</peerReview></peerReviews>';
        }

        // Add date filters if provided
        $publishedBeforeDate = $this->getArrayValue($settings, 'publishedBeforeDate', '');
        if ($publishedBeforeDate) {
            $xml .= '<publishedBeforeDate>' . $publishedBeforeDate . '</publishedBeforeDate>';
        }

        $publishedAfterDate = $this->getArrayValue($settings, 'publishedAfterDate', '');
        if ($publishedAfterDate) {
            $xml .= '<publishedAfterDate>' . $publishedAfterDate . '</publishedAfterDate>';
        }

        // Add workflow steps
        $xml .= '<workflowSteps>
                    <workflowStep>approved</workflowStep>
                    <workflowStep>forApproval</workflowStep>
                    <workflowStep>forRevalidation</workflowStep>
                    <workflowStep>validated</workflowStep>
                  </workflowSteps>';

        // Add persons or organisations XML
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);

        // Add search terms if provided
        if ($this->getArrayValue($settings, 'narrowBySearch') || $this->getArrayValue($settings, 'filter')) {
            $xml .= $this->getSearchXml($settings);
        }
        $xml .= '</researchOutputsQuery>';

        // Get and transform the publications data
        $publications = $this->webservice->getJson('research-outputs', $xml);
        if ($publications) {
            return $this->transformArray($publications, $settings, $lang);
        } else {
            return [
                'error' => 'SERVER_NOT_AVAILABLE',
                'message' => LocalizationUtility::translate('error.server_unavailable', 'univie_pure')
            ];
        }
    }

    /**
     * Get the year field for grouping
     *
     * @return string XML for year field
     */
    protected function getFieldForGrouping(): string
    {
        return '<field>publicationStatuses.publicationDate.year</field>';
    }

    /**
     * Get the publication type field
     *
     * @return string XML for publication type field
     */
    protected function getFieldForPublicationType(): string
    {
        return '<field>publicationStatuses.publicationStatus.*</field>';
    }


    /**
     * Generate XML for research types
     *
     * @param string $researchTypes Comma-separated list of research types
     * @return string XML for research types
     */
    protected function getResearchTypesXml(string $researchTypes): string
    {
        $xml = "<typeUris>";
        $types = explode(',', $researchTypes);

        foreach ((array)$types as $type) {
            if (strpos($type, "|")) {
                $tmp = explode("|", $type);
                $type = $tmp[0];
            }
            $xml .= '<typeUri>' . $type . '</typeUri>';
        }

        $xml .= "</typeUris>";
        return $xml;
    }

    /**
     * Generate XML for persons
     *
     * @param string $personsList Comma-separated list of persons
     * @return string XML for persons
     */
    protected function getPersonsXml(string $personsList): string
    {
        $xml = '<forPersons>';
        $persons = explode(',', $personsList);

        foreach ((array)$persons as $person) {
            if (strpos($person, "|")) {
                $tmp = explode("|", $person);
                $person = $tmp[0];
            }
            $xml .= '<uuids>' . $person . '</uuids>';
        }

        $xml .= '</forPersons>';
        return $xml;
    }

    /**
     * Generate XML for organisations
     *
     * @param string $organisationList Comma-separated list of organisations
     * @return string XML for organisations
     */
    protected function getOrganisationsXml(string $organisationList): string
    {
        $xml = '<forOrganisationalUnits><uuids>';
        $organisations = explode(',', $organisationList);

        foreach ((array)$organisations as $org) {
            if (strpos($org, "|")) {
                $tmp = explode("|", $org);
                $org = $tmp[0];
            }
            $xml .= '<uuid>' . $org . '</uuid>';
        }

        $xml .= '</uuids></forOrganisationalUnits>';
        return $xml;
    }

    /**
     * Group publications by year
     *
     * @param array $publications The publications array to process
     * @return array The grouped publications
     */
    protected function groupByYear(array $publications): array
    {
        // Initialize result array
        $array = [
            'count' => $this->getNestedArrayValue($publications, 'count', 0),
            'contributionToJournal' => []
        ];

        // Get sort key safely
        $sortkey = $this->getNestedArrayValue(
            $publications,
            'contributionToJournal.publicationStatuses.publicationStatus.publicationDate.year',
            0
        );

        $i = 0;

        // Process each contribution
        foreach ($publications as $key => $contribution) {
            // Skip non-array items or special keys like 'count'
            if (!is_array($contribution) || $key === 'count') {
                continue;
            }

            // Get values safely
            $year = $this->getNestedArrayValue(
                $contribution,
                'publicationStatuses.publicationDate.year',
                'Unknown Year'
            );

            $rendering = $this->getNestedArrayValue(
                $contribution,
                'rendering.0.value',
                ''
            );

            $uuid = $this->getNestedArrayValue(
                $contribution,
                'uuid',
                ''
            );

            // Add to result array
            $array['contributionToJournal'][$i] = [
                'year' => $year,
                'rendering' => $rendering,
                'uuid' => $uuid
            ];

            $i++;
        }

        return $array;
    }


    protected function transformArray(array $publications, array $settings, string $lang): array
    {
        $array = ['count' => $this->getArrayValue($publications, 'count', 0), 'contributionToJournal' => []];
        $i = 0;

        if (!$this->arrayKeyExists('items', $publications)) {
            return $array;
        }

        foreach ($publications['items'] as $contribution) {
            // Get portal URI for the publication
            $singlePub = $this->getAlternativeSinglePublication($contribution['uuid'] ?? '', $lang);
            $portalUri = $this->getNestedArrayValue($singlePub, 'items.0.info.portalUrl', '');

            // Use a dedicated method to determine if publication should be rendered
            if ($this->shouldRenderPublication($contribution, $settings)) {
                $array['contributionToJournal'][$i] = [
                    'rendering' => $this->getNestedArrayValue($contribution, 'renderings.0.html', ''),
                    'uuid' => $this->getArrayValue($contribution, 'uuid', ''),
                    'portalUri' => $portalUri
                ];

                // Add year for grouping if enabled
                if ($this->getArrayValue($settings, 'groupByYear', false)) {
                    $array['contributionToJournal'][$i]['year'] = $this->getNestedArrayValue(
                        $contribution,
                        'publicationStatuses.0.publicationDate.year',
                        ''
                    );
                }

                // Add publication status if enabled
                if ($this->getArrayValue($settings, 'showPublicationType', false)) {
                    $array['contributionToJournal'][$i]['publicationStatus'] = [
                        'value' => $this->getNestedArrayValue(
                            $contribution,
                            'publicationStatuses.0.publicationStatus.term.text.0.value',
                            ''
                        ),
                        'uri' => $this->getNestedArrayValue(
                            $contribution,
                            'publicationStatuses.0.publicationStatus.uri',
                            ''
                        )
                    ];
                }

                $i++;
            }
        }

        return $array;
    }

// New helper method to determine if a publication should be rendered
    private function shouldRenderPublication(array $contribution, array $settings): bool
    {
        $luhPublsOnly = (int)$this->getArrayValue($settings, 'luhPubsOnly', 0) === 1;

        // Check if LUH publication requirement is met
        $isLuhPublication = false;
        if ($luhPublsOnly) {
            $personAssociations = $this->getArrayValue($contribution, 'personAssociations', []);
            foreach ($personAssociations as $pA) {
                if ($this->arrayKeyExists('organisationalUnits', $pA)) {
                    $isLuhPublication = true;
                    break;
                }
            }

            if (!$isLuhPublication) {
                return false;
            }
        }

        // Check publication status
        $publicationStatuses = $this->getArrayValue($contribution, 'publicationStatuses', []);
        foreach ($publicationStatuses as $status) {
            $statusUri = $this->getNestedArrayValue($status, 'publicationStatus.uri', '');
            $isPublishedStatus = in_array($statusUri, [
                '/dk/atira/pure/researchoutput/status/published',
                '/dk/atira/pure/researchoutput/status/inpress',
                '/dk/atira/pure/researchoutput/status/epub'
            ]);

            if ($isPublishedStatus && $this->getArrayValue($status, 'current', '') === 'true') {
                // Check for in-press filter
                if ($statusUri === '/dk/atira/pure/researchoutput/status/inpress' &&
                    !$this->getArrayValue($settings, 'inPress', true)) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }


    /**
     * Query for single publication using alternative response
     *
     * @param string $uuid Publication UUID
     * @param string $lang Language code
     * @return array Publication data
     */
    public function getAlternativeSinglePublication(string $uuid, string $lang = 'de_DE')
    {
        return $this->webservice->getAlternativeSingleResponse('research-outputs', $uuid, "json", $lang);
    }

    /**
     * Query for single publication
     *
     * @param string $uuid Publication UUID
     * @param string $lang Language code
     * @return array|string|\SimpleXMLElement|null Publication data
     */
    public function getSinglePublication(string $uuid, string $lang = 'de_DE')
    {
        return $this->webservice->getSingleResponse('research-outputs', $uuid);
    }

    /**
     * Query for bibtex response
     *
     * @param string $uuid Publication UUID
     * @param string $lang Language code
     * @return array|string|\SimpleXMLElement|null Bibtex data
     */
    public function getBibtex(string $uuid, string $lang)
    {
        return $this->webservice->getSingleResponse('research-outputs', $uuid, 'xml', true, 'bibtex', $lang);
    }

    /**
     * Query for portalRenderings response
     *
     * @param string $uuid Publication UUID
     * @param string $lang Language code
     * @return array|string|\SimpleXMLElement|null Portal rendering data
     */
    public function getPortalRendering(string $uuid, string $lang)
    {
        return $this->webservice->getSingleResponse('research-outputs', $uuid, 'xml', true, 'detailsPortal', $lang);
    }

    /**
     * Query for getStandardRendering response
     *
     * @param string $uuid Publication UUID
     * @param string $lang Language code
     * @return array|string|\SimpleXMLElement|null Standard rendering data
     */
    public function getStandardRendering(string $uuid, string $lang)
    {
        return $this->webservice->getSingleResponse('research-outputs', $uuid, 'xml', true, 'standard', $lang);
    }
}
