<?php
namespace Univie\UniviePure\Endpoints;

use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Utility\CommonUtilities;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/*
 * (c) 2017 Christian Klettner <christian.klettner@univie.ac.at>, univie
 *          TYPO3-Team LUIS Uni-Hannover <typo3@luis.uni-hannover.de>, LUH
 *
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

class ResearchOutput
{

    /**
     * produce xml for the list query of research-output
     * @return array $publications
     */
    public function getPublicationList($settings,$currentPageNumber)
    {
        if ($settings['pageSize'] == 0) {
            $settings['pageSize'] = 20;
        }
        $results_short = [];
        $results_portal = [];
        if ($settings['rendering'] == "luhlong") {
            foreach (['portal-short', 'detailsPortal'] as $i => $render) {
                $settings['rendering'] = 'portal-short';
                $results_short = $this->getRealPublicationList($settings,$currentPageNumber);
                $settings['rendering'] = 'detailsPortal';
                $results_portal = $this->getRealPublicationList($settings,$currentPageNumber);
            }
            if (array_key_exists('contributionToJournal',$results_short)){
                foreach ($results_short['contributionToJournal'] as $i => $r) {
                    $results_short['contributionToJournal'][$i]['rendering'] = $r['rendering'] . $results_portal['contributionToJournal'][$i]['rendering'];
                }
            }
        } else {
            $results_short = $this->getRealPublicationList($settings,$currentPageNumber);
        }
        if (is_array($results_short)){
            if ($results_short['count'] > 0){
                if (array_key_exists("contributionToJournal",$results_short)){
                    foreach ($results_short["contributionToJournal"] as $index=>$contributionToJournal) {
                        $new_render = $contributionToJournal["rendering"];
                        $new_render = preg_replace('#<h2 class="title">(.*?)</h2>#is', '<h4 class="title">$1</h4>', $new_render);
                        $results_short["contributionToJournal"][$index]["rendering"] =$new_render;
                    }
                }
            }
        }
        $offset = (((int)$currentPageNumber - 1) * (int)$settings['pageSize']);
        $results_short['offset'] = $offset;

        return $results_short;

    }

    /**
     * produce xml for the list query of research-output
     * @return array $publications
     */
    public function getRealPublicationList($settings,$currentPageNumber)
    {

        $xml = '<?xml version="1.0"?>
				<researchOutputsQuery>';
        //getProjects
        $xml .= CommonUtilities::getProjectsXml($settings);


        //set page size:
        $xml .= CommonUtilities::getPageSize($settings['pageSize']);

        //set offset:
        $xml .= CommonUtilities::getOffset($settings['pageSize'],$currentPageNumber);

        $xml .= '<linkingStrategy>noLinkingStrategy</linkingStrategy>';

        $xml .= CommonUtilities::getLocale();

        $xml .= '<renderings><rendering>' . $settings['rendering'] . '</rendering></renderings>';
        $pubtype = "";
        //show publication type:
        $settings['showPublicationType'] = 1;
        if ($settings['showPublicationType'] == 1) {
            $pubtype = $this->getFieldForPublicationType();
        }

        $xml .= '<fields>
                ' . $pubtype . '
                    <field>uuid</field>
                    <field>renderings.*</field>
                    <field>publicationStatuses.*</field>
                    <field>personAssociations.*</field>';
        //grouping:
        if ($settings['groupByYear'] == 1) {
            $xml .= $this->getFieldForGrouping();
        }
        $xml .= '</fields>';

        //ordering:
        if (!array_key_exists('researchOutputOrdering', $settings)
            || strlen($settings['researchOutputOrdering']) == 0) {
            $settings['researchOutputOrdering'] = '-publicationYear';
        }//backwardscompatibility
        $xml .= '<orderings><ordering>' . $settings['researchOutputOrdering'] . '</ordering></orderings>';

        $xml .= '<returnUsedContent>true</returnUsedContent>';

        $xml .= '<navigationLink>true</navigationLink>';
        //classification scheme types:
        if (($settings['narrowByPublicationType'] == 1) && ($settings['selectorPublicationType'] != '')) {
            $xml .= $this->getResearchTypesXml($settings['selectorPublicationType']);
        }

        //peer-reviewed:
        if ($settings['peerReviewedOnly'] == 1) {
            $xml .= '<peerReviews><peerReview>PEER_REVIEW</peerReview></peerReviews>';
        }

        //notPeerReviewedOrNotSetOnly:
        if ($settings['notPeerReviewedOrNotSetOnly'] == 1) {
            $xml .= '<peerReviews><peerReview>NOT_PEER_REVIEW</peerReview><peerReview>NOT_SET</peerReview></peerReviews>';
        }

        //published before date:
        if ($settings['publishedBeforeDate']) {
            $xml .= '<publishedBeforeDate>' . $settings['publishedBeforeDate'] . '</publishedBeforeDate>';
        }
        //published after date:
        if ($settings['publishedAfterDate']) {
            $xml .= '<publishedAfterDate>' . $settings['publishedAfterDate'] . '</publishedAfterDate>';
        }


        $xml .= '<workflowSteps>
				    <workflowStep>approved</workflowStep>
                    <workflowStep>forApproval</workflowStep>
                    <workflowStep>forRevalidation</workflowStep>
                    <workflowStep>validated</workflowStep>
				  </workflowSteps>';

        //either for organisations or for persons, both must not be submitted:
        $xml .= CommonUtilities::getPersonsOrOrganisationsXml($settings);

        //search AND filter:
        if ($settings['narrowBySearch'] || $settings['filter']) {
            $xml .= $this->getSearchXml($settings);
        }

        $xml .= '</researchOutputsQuery>';

        $webservice = new WebService;
        $publications = $webservice->getJson('research-outputs', $xml);

        $publications = $this->transformArray($publications, $settings);
        return $publications;
    }

    /*
     * Get the year for grouping
     * @return string xml
     */
    public function getFieldForGrouping()
    {
        $xml = '<field>publicationStatuses.publicationDate.year</field>';
        return $xml;
    }

    /*
     * get the publication type (value, uri)
     * @return string xml
     */
    public function getFieldForPublicationType()
    {
        $xml = '<field>publicationStatuses.publicationStatus.*</field>';
        return $xml;
    }

    /**
     * xml for search string
     * @return string xml
     */
    public function getSearchXml($settings)
    {
        $terms = $settings['narrowBySearch'];
        //combine the backend filter and the frontend form:
        if ($settings['filter']) {
            $terms .= ' ' . $settings['filter'];
        }
        return '<searchString>' . trim($terms) . '</searchString>';

    }

    /**
     * query for classificationscheme
     * @return string xml
     */
    public function getResearchTypesXml($researchTypes)
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
     * result set for manually chosen persons
     * @return string xml
     */
    public function getPersonsXml($personsList)
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
     * result set for organisational units
     * @return string xml
     */
    public function getOrganisationsXml($organisationList)
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
     * restructure array: group by year
     * @return array array
     */
    public function groupByYear($publications)
    {
        $sortkey = $publications['contributionToJournal']['publicationStatuses']['publicationStatus']['publicationDate']['year'];
        $array = [];
        $array['count'] = $publications['count'];
        $i = 0;
        foreach ($publications as $contribution) {
            $array['contributionToJournal'][$i]['year'] = $contribution['publicationStatuses']['publicationDate']['year'];
            $array['contributionToJournal'][$i]['rendering'] = $contribution['rendering'][0]['value'];
            $array['contributionToJournal'][$i]['uuid'] = $contribution['uuid'];
            $i++;
        }
        return $array;
    }

    /**
     * restructure array
     * @return array array
     */
    public function transformArray($publications, $settings)
    {
        $lang = ($GLOBALS['TSFE']->config['config']['language'] == 'de') ? 'de_DE' : 'en_GB';

        $array = [];
        $array['count'] = $publications['count'];
        $i = 0;

        if (is_array($publications) || is_object($publications)) {
            if (array_key_exists('items', $publications)) {
                foreach ($publications['items'] as $contribution) {
                    $portalUri = $this->getAlternativeSinglePublication($contribution['uuid'], $lang)['items'][0]['info']['portalUrl'];
                    $allowedToRender = false;
                    $allowedToRenderLuhPubsOnly = false;
                    if (array_key_exists("luhPubsOnly",$settings)){
                        $luhPublsOnly_setting = intval($settings['luhPubsOnly']);
                    }

                    if ($luhPublsOnly_setting == 1) {
                        foreach ($contribution['personAssociations'] as $pA) {
                            if (isset($pA['organisationalUnits'])) {
                                $allowedToRenderLuhPubsOnly = true;
                                break;
                            }
                        }
                    }

                    foreach ($contribution['publicationStatuses'] as $status) {
                        if (
                            ($status['publicationStatus']['uri'] == '/dk/atira/pure/researchoutput/status/published') ||
                            ($status['publicationStatus']['uri'] == '/dk/atira/pure/researchoutput/status/inpress') ||
                            ($status['publicationStatus']['uri'] == '/dk/atira/pure/researchoutput/status/epub')
                        ) {
                            if ($allowedToRenderLuhPubsOnly && ($luhPublsOnly_setting == 1)) {
                                $allowedToRender = true;
                            }
                            if ($luhPublsOnly_setting != 1) {
                                $allowedToRender = true;
                            }
                            if (array_key_exists('inPress', $settings)) {
                                if ((!$settings['inPress']) && ($status['publicationStatus']['uri'] == '/dk/atira/pure/researchoutput/status/inpress')) {
                                    $allowedToRender = false;
                                }
                            }
                        }

                        if ($allowedToRender) {
                            if ($status['current'] == 'true') {
                                if ($settings['groupByYear']) {
                                    $array['contributionToJournal'][$i]['year'] = $status['publicationDate']['year'];
                                }
                                if ($settings['showPublicationType']) {
                                    $array['contributionToJournal'][$i]['publicationStatus']['value'] = $status['publicationStatus']['term']['text'][0]['value'];
                                    $array['contributionToJournal'][$i]['publicationStatus']['uri'] = $status['publicationStatus']['uri'];
                                }
                            }
                        }
                    }
                    if ($allowedToRender) {
                        $array['contributionToJournal'][$i]['rendering'] = $contribution['renderings'][0]['html'];
                        $array['contributionToJournal'][$i]['uuid'] = $contribution['uuid'];
                        $array['contributionToJournal'][$i]['portalUri'] = $portalUri;
                        $i++;
                    }

                }
            }
        }
        return $array;
    }

    /**
     * query for single publication
     * @return string xml
     */
    public function getAlternativeSinglePublication($uuid, $lang='de_DE')
    {
        $webservice = new WebService;
        return $webservice->getAlternativeSingleResponse('research-outputs', $uuid,"json", $lang);
    }

    /**
     * query for single publication
     * @return string xml
     */
    public function getSinglePublication($uuid, $lang='de_DE')
    {
        $webservice = new WebService;
        return $webservice->getSingleResponse('research-outputs', $uuid);
    }

    /**
     * query for bibtex response
     * @return string bibtex
     */
    public function getBibtex($uuid, $lang)
    {
        $webservice = new WebService;
        return $webservice->getSingleResponse('research-outputs', $uuid, 'xml', true, 'bibtex', $lang);
    }

    /**
     * query for portalRenderings response
     * @return string
     */
    public function getPortalRendering($uuid, $lang)
    {
        $webservice = new WebService;
        return $webservice->getSingleResponse('research-outputs', $uuid, 'xml', true, 'detailsPortal', $lang);

    }

    /**
     * query for getStandardRendering response
     * @return string
     */
    public function getStandardRendering($uuid, $lang)
    {
        $webservice = new WebService;
        return $webservice->getSingleResponse('research-outputs', $uuid, 'xml', true, 'standard', $lang);
    }



}

?>

