<?php
namespace Univie\UniviePure\Utility;

use Univie\UniviePure\Utility\CommonUtilities;
use Univie\UniviePure\Service\WebService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Core\Database\ConnectionPool;

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
 * ClassificationScheme and structural queries
 *
 *
 * /ws/rest/classificationschemehierarchy?baseUri=/dk/atira/pure/organisation/organisationtypes
 * /ws/rest/classificationschemehierarchy?baseUri=/dk/atira/pure/researchoutput/researchoutputtypes
 * /ws/rest/classificationschemehierarchy?baseUri=/dk/atira/pure/activity/activitytypes
 * /ws/rest/classificationschemehierarchy?baseUri=/dk/atira/pure/person/employmenttypes
 *
 */
class ClassificationScheme
{

    const RESEARCHOUTPUT = '/dk/atira/pure/researchoutput/researchoutputtypes';

    const PROJECTS = '/dk/atira/pure/upm/fundingprogramme';

    /**
     * @var $lang String
     */
    protected $locale = '';

    /**
     * set common stuff
     */
    public function __construct()
    {
        //Set the backend language:
        $this->locale = CommonUtilities::getBackendLanguage();
    }

    /**
     * getter for locale
     * @return String locale, frontend language
     */
    private function getLocale()
    {
        return $this->locale;
    }



    /**
     *
     * Organisation from which publications should be displayed
     */
    public function getOrganisations(&$config)
    {
        $postData = trim('<?xml version="1.0"?>
            <organisationalUnitsQuery>
            <size>999999</size>
            <locales>
            <locale>' . $this->getLocale() . '</locale>
            </locales>
            <fields>
            <field>uuid</field>
            <field>name.text.value</field>
            </fields>
            <orderings>
            <ordering>name</ordering>
            </orderings>
            <returnUsedContent>true</returnUsedContent>
            </organisationalUnitsQuery>');

        $timestamp = $this->getOrganisationsFromCache($this->getLocale())[1];
        if (time() < (int)$timestamp + 6 * 3600) {
            $organisations = $this->getOrganisationsFromCache($this->getLocale())[0];
            if ($organisations){
                if(array_key_exists('items',$organisations)){
                    if (count($organisations) < 10){
                        $webservice = new WebService;
                        $organisations = $webservice->getJson('organisational-units', $postData);
                        $this->storeOrganisationsToCache($organisations,$this->getLocale());
                    }
                }
            }else {
                $webservice = new WebService;
                $organisations = $webservice->getJson('organisational-units', $postData);
                $this->storeOrganisationsToCache($organisations,$this->getLocale());
            }

        } else {
            $webservice = new WebService;
            $organisations = $webservice->getJson('organisational-units', $postData);
            $this->storeOrganisationsToCache($organisations,$this->getLocale());

        }


        if (is_array($organisations)) {
            foreach ($organisations['items'] as $org) {
                $item = [$org['name']['text']['0']['value'], $org['uuid']];
                array_push($config['items'], $item);
            }
        }
    }

    /*
     * Persons list for select user func:
     */
    public function getPersons(&$config)
    {

        $personXML = trim('<?xml version="1.0"?>
<personsQuery>
<size>999999</size>
<fields>
<field>uuid</field>
<field>name.*</field>
</fields>
<orderings>
<ordering>lastName</ordering>
</orderings>
<employmentStatus>ACTIVE</employmentStatus></personsQuery>');


        $timestamp = $this->getPersonsFromCache()[1];
        if (time() < (int)$timestamp + 6 * 3600) {
            $persons = $this->getPersonsFromCache()[0];
            if($persons){
                if(array_key_exists('items',$persons)) {
                    if (count($persons) < 10) {
                        $webservice = new WebService;
                        $persons = $webservice->getJson('persons', $personXML);
                        $this->storePersonsToCache($persons);
                    }
                }
            }else{
                $webservice = new WebService;
                $persons = $webservice->getJson('persons', $personXML);
                $this->storePersonsToCache($persons);
            }

        } else {
            $webservice = new WebService;
            $persons = $webservice->getJson('persons', $personXML);
            $this->storePersonsToCache($persons);
        }
        if (is_array($persons)) {
            foreach ($persons['items'] as $p) {
                $config['items'][] = [
                    $p['name']['lastName'] . ', ' . $p['name']['firstName'],
                    $p['uuid'],
                ];

            }
        }

    }

    private function getTypesFromPublicationsFromCache()
    {

        $result = [];
        try {
            $query = $GLOBALS['TYPO3_DB']->sql_query("SELECT mvalue, unixtimestamp FROM tx_univie_pure_cache WHERE mkey = 'getTypesFromPublications'");
            if ($query === false) {
                return false;
            }

            $mydata = $query->fetch_assoc();
            $result[] = unserialize($mydata['mvalue']);
            $result[] = $mydata['unixtimestamp'];
        } catch (Exception $e) {
            echo $e->getMessage();
            $result = false;
        }

        return $result;
    }

    public function getOrganisationsFromCache($lang)
    {
        $key = "getOrganisations" . $lang;
        $result = [];
        try {
            $query = $GLOBALS['TYPO3_DB']->sql_query("SELECT mvalue, unixtimestamp FROM tx_univie_pure_cache WHERE mkey = '". $key ."'");
            if ($query === false) {
                return false;
            }

            $mydata = $query->fetch_assoc();
            $result[] = unserialize($mydata['mvalue']);
            $result[] = $mydata['unixtimestamp'];
        } catch (Exception $e) {
            echo $e->getMessage();
            $result = false;
        }

        return $result;
    }

    private function storeTypesFromPublicationsToCache($data)
    {
        $data = $this->mysql_escape_no_conn(serialize($data));
        try {
            $GLOBALS['TYPO3_DB']->sql_query("DELETE FROM tx_univie_pure_cache where mkey = 'getTypesFromPublications'");
            $current_unix_time = time();
            $query = $GLOBALS['TYPO3_DB']->sql_query("INSERT INTO tx_univie_pure_cache (mkey, mvalue, unixtimestamp) VALUES('getTypesFromPublications', '" . $data . "'," . $current_unix_time . " ) ON DUPLICATE KEY UPDATE mkey='getTypesFromPublications', mvalue='" . $data . "', unixtimestamp=" . $current_unix_time . " ");
            if ($query === true) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            $result = false;
        }

        return $result;
    }


    public function storeOrganisationsToCache($data,$locale)
    {
        $key = "getOrganisations" . $locale;
        $data = $this->mysql_escape_no_conn(serialize($data));
        try {
            $GLOBALS['TYPO3_DB']->sql_query("DELETE FROM tx_univie_pure_cache where mkey = '" . $key . "'");
            $current_unix_time = time();
            $query = $GLOBALS['TYPO3_DB']->sql_query("INSERT INTO tx_univie_pure_cache (mkey, mvalue, unixtimestamp) VALUES('".$key."', '" . $data . "'," . $current_unix_time . " ) ON DUPLICATE KEY UPDATE mkey='".$key."', mvalue='" . $data . "', unixtimestamp=" . $current_unix_time . " ");
            if ($query === true) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            $result = false;
        }

        return $result;
    }


    public function getPersonsFromCache()
    {
        $result = [];
        try {
            $query = $GLOBALS['TYPO3_DB']->sql_query("SELECT mvalue, unixtimestamp FROM tx_univie_pure_cache WHERE mkey = 'getPersons'");
            if ($query === false) {
                return false;
            }

            $mydata = $query->fetch_assoc();
            $result[] = unserialize($mydata['mvalue']);
            $result[] = $mydata['unixtimestamp'];
        } catch (Exception $e) {
            echo $e->getMessage();
            $result = false;
        }

        return $result;
    }

    public function storePersonsToCache($data)
    {
        $data = $this->mysql_escape_no_conn(serialize($data));
        try {
            $GLOBALS['TYPO3_DB']->sql_query("DELETE FROM tx_univie_pure_cache where mkey = 'getPersons'");
            $current_unix_time = time();
            $query = $GLOBALS['TYPO3_DB']->sql_query("INSERT INTO tx_univie_pure_cache (mkey, mvalue, unixtimestamp) VALUES('getPersons', '" . $data . "'," . $current_unix_time . " ) ON DUPLICATE KEY UPDATE mkey='getPersons', mvalue='" . $data . "', unixtimestamp=" . $current_unix_time . " ");
            if ($query === true) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            $result = false;
        }

        return $result;
    }



    public function getProjectsFromCache($lang)
    {
        $key = "getProjects" . $lang;
        $result = [];
        try {
            $query = $GLOBALS['TYPO3_DB']->sql_query("SELECT mvalue, unixtimestamp FROM tx_univie_pure_cache WHERE mkey = '".$key."'");
            if ($query === false) {
                return false;
            }

            $mydata = $query->fetch_assoc();
            $result[] = unserialize($mydata['mvalue']);
            $result[] = $mydata['unixtimestamp'];
        } catch (Exception $e) {
            echo $e->getMessage();
            $result = false;
        }

        return $result;
    }


    public function storeProjectsToCache($data,$locale)
    {
        $key = "getProjects" . $locale;
        $data = $this->mysql_escape_no_conn(serialize($data));
        try {
            $GLOBALS['TYPO3_DB']->sql_query("DELETE FROM tx_univie_pure_cache where mkey = '".$key."'");
            $current_unix_time = time();
            $query = $GLOBALS['TYPO3_DB']->sql_query("INSERT INTO tx_univie_pure_cache (mkey, mvalue, unixtimestamp) VALUES('".$key."', '" . $data . "'," . $current_unix_time . " ) ON DUPLICATE KEY UPDATE mkey='".$key."', mvalue='" . $data . "', unixtimestamp=" . $current_unix_time . " ");
            if ($query === true) {
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            $result = false;
        }

        return $result;
    }

    private function mysql_escape_no_conn($input)
    {

        if (is_array($input)) {
            return array_map(__METHOD__, $input);
        }
        if (!empty($input) && is_string($input)) {
            return str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"],
                ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'],
                $input);
        }

        return $input;
    }

    /*
     * Projects list for select project func
     */
    public function getProjects(&$config)
    {
        $projectsXML = trim('<?xml version="1.0"?><projectsQuery><size>999999</size><locales><locale>' . $this->getLocale() . '</locale></locales>
<fields>
<field>uuid</field>
<field>acronym</field>
<field>title.*</field>
</fields>
<orderings>
<ordering>title</ordering>
</orderings>
<workflowSteps>
<workflowStep>validated</workflowStep>
</workflowSteps>
</projectsQuery>');
        $timestamp = $this->getProjectsFromCache($this->getLocale())[1];
            if (time() < (int)$timestamp + 6 * 3600) {
                $projects = $this->getProjectsFromCache($this->getLocale())[0];
                if ( $projects){
                    if(array_key_exists('items',$projects)){
                        if (count($projects['items']) < 10){
                            $webservice = new WebService;
                            $projects = $webservice->getJson('projects', $projectsXML);
                            $this->storeProjectsToCache($projects,$this->getLocale());
                        }
                    }
                }else{
                    $webservice = new WebService;
                    $projects = $webservice->getJson('projects', $projectsXML);
                    $this->storeProjectsToCache($projects,$this->getLocale());
                }


            } else {
                $webservice = new WebService;
                $projects = $webservice->getJson('projects', $projectsXML);
                $this->storeProjectsToCache($projects,$this->getLocale());
        }
        if (is_array($projects)) {
            foreach ($projects['items'] as $proj) {
                $title = $proj['title']['text'][0]['value'];
                if ($proj['acronym']) {
                    if (strpos($title, $proj['acronym']) === false) {
                        $title = $proj['acronym'] . ' - ' . $title;
                    }
                }
                $config['items'][] = [
                    $title,
                    $proj['uuid'],
                ];
            }
        }
    }


    /**
     * structural query for publication types
     * @return String xml
     */
    public function getTypesFromPublications(&$config)
    {
        $classificationXML = trim('<?xml version="1.0"?>
<classificationSchemesQuery>
<size>99999</size>
<offset>0</offset>
<locales>
<locale>' . $this->getLocale() . '</locale>
</locales>
<returnUsedContent>true</returnUsedContent>
<navigationLink>true</navigationLink> 
<baseUri>' . self::RESEARCHOUTPUT . '</baseUri>
</classificationSchemesQuery>
');
        $timestamp = $this->getTypesFromPublicationsFromCache()[1];
        if (time() < (int)$timestamp + 6 * 3600) {
            $publicationTypes = $this->getTypesFromPublicationsFromCache()[0];
            if(array_key_exists('items',$publicationTypes)) {
                if (count($publicationTypes) < 3) {
                    $webservice = new WebService;
                    $publicationTypes = $webservice->getJson('classification-schemes', $classificationXML);
                    $this->storeTypesFromPublicationsToCache($publicationTypes);
                }
            }
        } else {
            $webservice = new WebService;
            $publicationTypes = $webservice->getJson('classification-schemes', $classificationXML);
            $this->storeTypesFromPublicationsToCache($publicationTypes);
        }
        if (is_array($publicationTypes)) {
            $sorted = $this->sortClassification($publicationTypes);
            $this->sorted2items($sorted, $config);
        }
    }

    /**
     * sort hierarchical
     */
    public function sorted2items($sorted, &$config)
    {
        foreach ($sorted as $optGroup) {
            $label = '----- ' . $optGroup['title'] . ': -----';
            $item = [$label, '--div--'];
            array_push($config['items'], $item);
            foreach ($optGroup['child'] as $opt) {
                $item = [$opt['title'], $opt['uri']];
                array_push($config['items'], $item);
            }
        }

    }


    /**
     * structural query for activity types
     * @return String xml
     */
    public function getTypesFromActivities(&$config)
    {
        $classificationXML = trim('<?xml version="1.0"?>
<classificationSchemesQuery>
<size>999999</size>
<locales>
<locale>' . $this->getLocale() . '</locale>
</locales>
<returnUsedContent>true</returnUsedContent>
<navigationLink>true</navigationLink>
<baseUri>' . self::ACTIVITIES . '</baseUri>
</classificationSchemesQuery>
');
        $webservice = new WebService;
        $activityTypes = $webservice->getJson('classification-schemes', $classificationXML);
        $sorted = $this->sortClassification($activityTypes);
        $this->sorted2items($sorted, $config);
    }

    /**
     * Sort classifications to hierarchical tree
     * first in api/511
     * @return array hierarchicalTree
     */
    public function sortClassification($unsorted)
    {
        $sorted = [];
        $i = 0;
        foreach ($unsorted['items'][0]['containedClassifications'] as $parent) {
            if (($parent['disabled'] != true) && ($this->classificationHasChild($parent))) {

                $sorted[$i]['uri'] = $parent['uri'];
                $sorted[$i]['title'] = $parent['term']['text'][0]['value'];
                $j = 0;
                foreach ($parent['classificationRelations'] as $child) {

                    if ($child['relationType']['uri'] == '/dk/atira/pure/core/hierarchies/child') {
                        if (!$this->isChildEnabledOnRootLevel($unsorted, $child['relatedTo'][0]['uri'])) {
                            $sorted[$i]['child'][$j]['uri'] = $child['relatedTo']['uri'];
                            $sorted[$i]['child'][$j]['title'] = $child['relatedTo']['term']['text'][0]['value'];
                            $j++;
                        }
                    }
                }
                $i++;

            }
        }

        return $sorted;
    }

    /*
     * Check for children
     */
    public function classificationHasChild($parent)
    {
        $has = false;
        if (array_key_exists('classificationRelations', $parent)) {
            foreach ($parent['classificationRelations'] as $child) {
                if ($child['relationType']['uri'] == '/dk/atira/pure/core/hierarchies/child') {
                    if ($child['relatedTo']['term']['text'][0]['value'] != '<placeholder>') {
                        $has = true;
                        break;
                    }
                }
            }
        }
        return $has;
    }

    /*
     * Child is just a pointer to entry in root level. If disabled it is only visible on the root level:
     */
    public function isChildEnabledOnRootLevel($roots, $childUri)
    {
        foreach ($roots['items'][0]['containedClassifications'] as $root) {
            if ($root['uri'] == $childUri) {
                return $root['disabled'];
            }
        }
    }

    /**
     * structural query for press-media types
     * @return String xml
     */
    public function getTypesFromPressMedia(&$config)
    {
        $classificationXML = '<?xml version="1.0"?>
					<classificationSchemesQuery>
					  <size>999999</size>
					  <locales>
					    <locale>' . $this->getLocale() . '</locale>
					  </locales>
					  <returnUsedContent>true</returnUsedContent>
					  <navigationLink>true</navigationLink>
					  <baseUri>' . self::PRESSMEDIA . '</baseUri>
					</classificationSchemesQuery>
					';

        $webservice = new WebService;
        $activityTypes = $webservice->getJson('classification-schemes', $classificationXML);
        foreach ($activityTypes['items']['0']['containedClassifications'] as $type) {
            $item = [$type['value'], $type['uri']];
            array_push($config['items'], $item);
        }
    }

    /**
     * structural query for project types
     * @return String xml
     */
    public function getTypesFromProjects(&$config)
    {
        $classificationXML = '<?xml version="1.0"?>
					<classificationSchemesQuery>
					  <size>999999</size>
					  <locales>
					    <locale>' . $this->getLocale() . '</locale>
					  </locales>
					  <returnUsedContent>true</returnUsedContent>
					  <navigationLink>true</navigationLink>
					  <baseUri>' . self::PROJECTS . '</baseUri>
					</classificationSchemesQuery>
					';

        $webservice = new WebService;
        $projectsTypes = $webservice->getJson('classification-schemes', $classificationXML);
        foreach ($projectsTypes['items']['0']['containedClassifications'] as $type) {
            $item = [$type['value'], $type['uri']];
            array_push($config['items'], $item);
        }
    }

    /**
     * get uuid for email
     * @param $email
     * @return String uuid
     */
    public function getUuidForEmail($email)
    {
        $uuid = '123456789';//return some nonsens
        $xml = '<?xml version="1.0"?>
				<personsQuery>
				  <searchString>' . $email . '</searchString>
				  <locales>
				    <locale>' . $this->getLocale() . '</locale>
				  </locales>
				  <fields>name</fields>
				</personsQuery>';
        $webservice = new WebService;
        $uuids = $webservice->getXml('persons', $xml);
        if ($uuids['count'] == 1) {
            $uuid = $uuids['person']['@attributes']['uuid'];
        }
        return $uuid;
    }

    /**
     * itemsProcFunc for TCA, show selector for Units, Persons Projects for Research-Output, Units, Persons otherwise
     */
    public function getItemsToChoose(&$config, $PA)
    {
        array_push($config['items'], [
            $GLOBALS['LANG']->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectBlank',
                true),
            -1
        ]);
        array_push($config['items'], [
            $GLOBALS['LANG']->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByUnit',
                true),
            0
        ]);
        array_push($config['items'], [
            $GLOBALS['LANG']->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByPerson',
                true),
            1
        ]);
        //Do this only for PUBLICATIONS:
        $settings = $config['flexParentDatabaseRow']['pi_flexform'];
        if (($settings['data']['sDEF']['lDEF']['settings.what_to_display']['vDEF'][0] == 'PUBLICATIONS') || ($settings['data']['sDEF']['lDEF']['settings.what_to_display']['vDEF'][0] == 'DATASETS')) {
            array_push($config['items'], [
                $GLOBALS['LANG']->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang_tca.xml:flexform.common.selectByProject',
                    true),
                2
            ]);
        }
    }
}



?>