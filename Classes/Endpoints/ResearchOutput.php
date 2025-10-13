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
                        // Use preg_replace for h2 to h4 transformation
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
        $xml .= LanguageUtility::getLocale('xml');

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
     * Transform publication data array
     *
     * @param array $publications Publications data
     * @param array $settings Configuration settings
     * @param string $lang Language code (plain format like "de_DE", not XML)
     * @return array Transformed publication data
     */
    protected function transformArray(array $publications, array $settings, string $lang): array
    {
        $array = [];
        $array['count'] = $this->getArrayValue($publications, 'count', 0);
        $i = 0;

        if (!$this->arrayKeyExists('items', $publications)) {
            return $array;
        }

        foreach ($publications['items'] as $contribution) {
            // Get portal URI for the publication
            // Language should already be plain format (e.g., "de_DE")
            $singlePub = $this->getSinglePublication($this->getArrayValue($contribution, 'uuid', ''), $lang);
            $portalUri = $this->getNestedArrayValue($singlePub, 'info.portalUrl', '');

            // Initialize rendering status for this contribution
            $allowedToRender = false;
            $allowedToRenderLuhPubsOnly = false;
            $luhPublsOnly_setting = intval($this->getArrayValue($settings, 'luhPubsOnly', 0));

            // Check for LUH publications only
            if ($luhPublsOnly_setting == 1) {
                $personAssociations = $this->getArrayValue($contribution, 'personAssociations', []);
                foreach ($personAssociations as $pA) {
                    if ($this->arrayKeyExists('organisationalUnits', $pA)) {
                        $allowedToRenderLuhPubsOnly = true;
                        break;
                    }
                }
            }

            // Variables to hold info from the "current" status
            $currentPublicationYear = '';
            $currentPublicationStatusValue = '';
            $currentPublicationStatusUri = '';

            // Process publication statuses
            $publicationStatuses = $this->getArrayValue($contribution, 'publicationStatuses', []);
            foreach ($publicationStatuses as $status) {
                $statusUri = $this->getNestedArrayValue($status, 'publicationStatus.uri', '');
                $isPublishedStatus = in_array($statusUri, [
                    '/dk/atira/pure/researchoutput/status/published',
                    '/dk/atira/pure/researchoutput/status/inpress',
                    '/dk/atira/pure/researchoutput/status/epub'
                ]);

                // Determine if this publication is generally allowed to render based on its statuses
                if ($isPublishedStatus) {
                    if ($allowedToRenderLuhPubsOnly && ($luhPublsOnly_setting == 1)) {
                        $allowedToRender = true;
                    }
                    // If not restricted to LUH pubs, or if it passes the LUH check
                    if ($luhPublsOnly_setting != 1 || ($luhPublsOnly_setting == 1 && $allowedToRenderLuhPubsOnly)) {
                        $allowedToRender = true;
                    }

                    // Apply in-press filter (can override previous $allowedToRender)
                    $inPress = $this->getArrayValue($settings, 'inPress', true);
                    if (!$inPress && $statusUri == '/dk/atira/pure/researchoutput/status/inpress') {
                        $allowedToRender = false;
                    }
                }

                // Capture details from the *current* status, regardless of $allowedToRender yet
                if ($this->getArrayValue($status, 'current', '') == 'true') {
                    $currentPublicationYear = $this->getNestedArrayValue($status, 'publicationDate.year', '');
                    // Adjust status value based on language
                    if ($lang === 'de_DE') {
                        $currentPublicationStatusValue = $this->getNestedArrayValue($status, 'publicationStatus.term.text.1.value', '');
                    } else {
                        $currentPublicationStatusValue = $this->getNestedArrayValue($status, 'publicationStatus.term.text.0.value', '');
                    }
                    $currentPublicationStatusUri = $statusUri;
                }
            }

            // Add publication details to the final array ONLY if allowed to render
            if ($allowedToRender) {
                $array['contributionToJournal'][$i]['rendering'] = $this->getNestedArrayValue($contribution, 'renderings.0.html', '');
                $array['contributionToJournal'][$i]['uuid'] = $this->getArrayValue($contribution, 'uuid', '');
                $array['contributionToJournal'][$i]['portalUri'] = $portalUri;

                // Assign the captured year and status details here, if the settings enable them
                if ($this->getArrayValue($settings, 'groupByYear', false)) {
                    $array['contributionToJournal'][$i]['year'] = $currentPublicationYear;
                }
                if ($this->getArrayValue($settings, 'showPublicationType', false)) {
                    $array['contributionToJournal'][$i]['publicationStatus']['value'] = $currentPublicationStatusValue;
                    $array['contributionToJournal'][$i]['publicationStatus']['uri'] = $currentPublicationStatusUri;
                }
                $i++;
            }
        }

        return $array;
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
        return $this->webservice->getSingleResponse('research-outputs',$uuid,'json',true,null, $lang);
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
        return $this->webservice->getSingleResponse('research-outputs', $uuid, 'xml', true, null, $lang);
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