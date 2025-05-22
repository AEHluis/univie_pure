<?php

namespace Univie\UniviePure\Endpoints;

use Univie\UniviePure\Service\WebService;
use Univie\UniviePure\Utility\CommonUtilities;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Utility\LanguageUtility;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class Persons extends Endpoints
{

    private readonly WebService $webservice;

    public function __construct(WebService $webservice)
    {
        $this->webservice = $webservice;
    }

    public function getProfile($uuid)
    {
        $xml = '<?xml version="1.0"?>
				<personsQuery>
				<uuids>' . $uuid . '</uuids>
				<rendering>short</rendering>
				<linkingStrategy>portalLinkingStrategy</linkingStrategy>';

        //set locale:
        $xml .= LanguageUtility::getLocale('xml');

        $xml .= '</personsQuery>';

        $profile = $this->webservice->getJson('persons', $xml);

        if (
            is_array($profile)
            && isset($profile['items'][0]['rendering'][0]['value'])
        ) {
            return $profile['items'][0]['rendering'][0]['value'];
        }
        return null;
    }

    public function getPortalUrl($uuid)
    {
        $xml = '<?xml version="1.0"?>
				<personsQuery>
				<uuids>' . $uuid . '</uuids>
				<fields>info.portalUrl</fields>
				<linkingStrategy>portalLinkingStrategy</linkingStrategy>';

        //set locale:
        $xml .= LanguageUtility::getLocale('xml');
        $xml .= '</personsQuery>';
        $portalUrl = $this->webservice->getJson('persons', $xml);

        if (
            is_array($portalUrl)
            && isset($portalUrl['items'][0]['info']['portalUrl'])
        ) {
            return $portalUrl['items'][0]['info']['portalUrl'];
        }
        return null;
    }
}
