<?php
namespace Univie\UniviePure\Utility;

use Univie\UniviePure\Service\WebService;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Christian Klettner <christian.klettner@univie.ac.at>, univie
 *           TYPO3-Team LUIS Uni-Hannover <typo3@luis.uni-hannover.de>, LUH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Helpers for all endpoints
 *
 */
class CommonUtilities
{

    /**
     * xml for frontend locale
     * @ return String xml
     */
    public static function getLocale()
    {
        //TODO: get sys_language_uid, check for allowed languages in service, compare, prepare a fallback
        $lang = ($GLOBALS['TSFE']->config['config']['language'] == 'de') ? 'de_DE' : 'en_GB';
        return '<locales><locale>' . $lang . '</locale></locales>';
    }

    /**
     * get backend locale
     * @ return String locale
     */
    public static function getBackendLanguage()
    {
        return ($GLOBALS['BE_USER']->uc['lang'] == 'de') ? 'de_DE' : 'en_EN';
    }

    /**
     * page size entered in flexform
     * @return String xml
     */
    public static function getPageSize($pageSize)
    {
        if ($pageSize == 0 || $pageSize === null) {
            $pageSize = 20;
        }
        return '<size>' . $pageSize . '</size>';
    }

    /**
     * keep track of the counter
     * @return String xml
     */
    public static function getOffset($pageSize,$currentPage)
    {

        $offset = $currentPage;
        $offset = ($offset - 1 < 0) ? 0 : $offset - 1;
        return '<offset>' . $offset * (int)$pageSize . '</offset>';
    }

    /**
     * Either send a request for a unit or for persons
     * @return String xml
     */
    public static function getPersonsOrOrganisationsXml($settings){
        $xml = "";
        //either for organisations or for persons or for projects:
        //If settings.chooseSelector equals 0 => organisational units, case 1 => persons, case 2 => projects:
        switch ($settings['chooseSelector']) {
            case 0:
                //Resarch-output for organisations:
                $xml = self::getOrganisationsXml($settings);
                break;
            case 1:
                //Research-output for persons:
                $xml = self::getPersonsXml($settings);
                break;
          /*  case 2:
                //Research-output for projects:
                $xml = self::getProjectsXml($settings);
                break;
          */
        }
        return $xml;
    }



    /**
     * Organisations query
     * @return String xml
     */
    public static function getOrganisationsXml($settings)
    {
        //if search is entered organisations may be omitted:
        if ($settings['selectorOrganisations'] == '' && $settings['narrowBySearch'] != '') {
            return '';
        }
        $xml = '<forOrganisationalUnits><uuids>';
        $organisations = explode(',', $settings['selectorOrganisations']);
        foreach ((array)$organisations as $org) {
            if (strpos($org, "|")) {
                $tmp = explode("|", $org);
                $org = $tmp[0];
            }
            $xml .= '<uuid>' . $org . '</uuid>';
            //check for sub units:
            if ($settings['includeSubUnits'] == 1) {
                $subUnits = self::getSubUnits($org);
                if (is_array($subUnits) && count($subUnits) > 1) {
                    foreach ($subUnits as $subUnit) {

                        if ($subUnit['uuid'] != $org) {
                            $xml .= '<uuid>' . $subUnit['uuid'] . '</uuid>';
                        }
                    }
                }
            }
        }
        $xml .= '</uuids><hierarchyDepth>100</hierarchyDepth></forOrganisationalUnits>';

        return $xml;
    }

    /**
     * Persons query
     * @return String xml
     */
    public static function getPersonsXml($settings)
    {
        //if search is entered persons may be omitted:
        if ($settings['selectorPersons'] == '' && $settings['narrowBySearch'] != '') {
            return '';
        }
        //otherwise allways write the xml. If persons are empty nothing is returned from ucris:
        $xml = '<forPersons><uuids>';
        $persons = explode(',', $settings['selectorPersons']);
        foreach ((array)$persons as $person) {
            if (strpos($person, "|")) {
                $tmp = explode("|", $person);
                $person = $tmp[0];
            }
            $xml .= '<uuid>' . $person . '</uuid>';
        }
        $xml .= '</uuids></forPersons>';
        return $xml;

    }

    /**
     * Projects query
     * @return String xml | boolean
     */
    public static function getProjectsXml($settings)
    {
        $xml = false;
        if ($settings['selectorProjects'] == '' && $settings['narrowBySearch'] != '') {
            $xml = '';
        }elseif ($settings['chooseSelector'] == 2){
            $xmlProjects = '<?xml version="1.0"?>
			<projectsQuery>';
            $projects = explode(',', $settings['selectorProjects']);
            $xmlProjects .= "<uuids>";
            foreach ((array)$projects as $project) {
                if (strpos($project, "|")) {
                    $tmp = explode("|", $project);
                    $project = $tmp[0];
                }
                $xmlProjects .= '<uuid>' . $project . '</uuid>';
            }
            $xmlProjects .= "</uuids>";
            $xmlProjects .= '<size>99999</size><linkingStrategy>string</linkingStrategy>
				<locales><locale>de_DE</locale></locales>
				<fields><field>relatedResearchOutputs.uuid</field></fields>
				<orderings><ordering>title</ordering></orderings>';

            $xmlProjects .= '</projectsQuery>';
            $webservice = new WebService;
            $publications = $webservice->getJson('projects', $xmlProjects);
            $xml = "";
            if (is_array($publications['items'])) {
                $xml .= "<uuids>";
                foreach ($publications['items'] as $researchOutputs) {
                    if (!empty($researchOutputs)) {
                        foreach ($researchOutputs['relatedResearchOutputs'] as $researchOutput) {
                            $xml .= '<uuid>' . $researchOutput['uuid'] . '</uuid>';
                        }
                    }
                }
                $xml .= "</uuids>";

            }
        }


        return $xml;
    }

    /**
     * Projects query
     * @return String xml | boolean
     */
    public static function getProjectsForDatasetsXml($settings)
    {
        $xml = false;
        if ($settings['selectorProjects'] == '' && $settings['narrowBySearch'] != '') {
            $xml = '';
        }elseif ($settings['chooseSelector'] == 2){
            $xmlProjects = '<?xml version="1.0"?>
			<projectsQuery>';
            $projects = explode(',', $settings['selectorProjects']);
            $xmlProjects .= "<uuids>";
            foreach ((array)$projects as $project) {
                if (strpos($project, "|")) {
                    $tmp = explode("|", $project);
                    $project = $tmp[0];
                }
                $xmlProjects .= '<uuid>' . $project . '</uuid>';
            }
            $xmlProjects .= "</uuids>";
            $xmlProjects .= '<size>99999</size><linkingStrategy>string</linkingStrategy>
				<locales><locale>de_DE</locale></locales>
				<fields><field>relatedDataSets.uuid</field></fields>
				<orderings><ordering>title</ordering></orderings>';

            $xmlProjects .= '</projectsQuery>';
            $webservice = new WebService;
            $datasets = $webservice->getJson('projects', $xmlProjects);

            $xml = "";
            if (is_array($datasets['items'])) {
                $xml .= "<uuids>";
                foreach ($datasets['items'] as $d) {
                    if (!empty($d)) {

                        foreach ($d['relatedDataSets'] as $i) {
                            $xml .= '<uuid>' . $i['uuid'] . '</uuid>';
                        }
                    }
                }
                $xml .= "</uuids>";
            }
        }
        
        return $xml;
    }


    /**
     * query sub organisations for a unit
     * @return array subUnits Array of all Units connected
     */
    public static function getSubUnits($orgId)
    {
        $orgName = self::getNameForUuid($orgId);
        $xml = '<?xml version="1.0"?>
				<organisationalUnitsQuery>
					<size>300</size>
					<fields><field>uuid</field></fields>
					<orderings><ordering>type</ordering></orderings>
					<returnUsedContent>true</returnUsedContent>
					<navigationLink>true</navigationLink>
                    <searchString>"' . $orgName . '"</searchString>
				</organisationalUnitsQuery>';
        $webservice = new WebService;
        $subUnits = $webservice->getJson('organisational-units', $xml);
        if ($subUnits['count'] > 1) {
            return $subUnits['items'];
        }
    }

    /*
     * query name by uuid
     * @return string name
     */
    public static function getNameForUuid($orgId)
    {
        $xml = '<?xml version="1.0"?>
				<organisationalUnitsQuery>
					<uuids><uuid>' . $orgId . '</uuid></uuids>
					  <size>1</size>
					  <offset>0</offset>
					<locales><locale>de_DE</locale></locales>
					<fields><field>name.text.value</field></fields>
				</organisationalUnitsQuery>';
        $webservice = new WebService;
        $orgName = $webservice->getJson('organisational-units', $xml);
        if ($orgName['count'] == 1) {
            return $orgName['items'][0]['name']['text'][0]['value'];
        }
    }


}

?>
