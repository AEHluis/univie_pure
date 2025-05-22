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
        // Ensure UUID is a string and escape it for XML
        $escapedUuid = htmlspecialchars((string)$uuid, ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0"?>
				<personsQuery>
				<uuids>' . $escapedUuid . '</uuids>
				<rendering>short</rendering>
				<linkingStrategy>portalLinkingStrategy</linkingStrategy>';

        //set locale:
        $xml .= LanguageUtility::getLocale('xml');

        $xml .= '</personsQuery>';

        $profile = $this->webservice->getJson('persons', $xml);

        // Use getNestedArrayValue for safer access
        $renderingValue = $this->getNestedArrayValue($profile, 'items.0.rendering.0.value');
        return $renderingValue; // Corrected: return the found value
    }

    public function getPortalUrl($uuid)
    {
        // Ensure UUID is a string and escape it for XML
        $escapedUuid = htmlspecialchars((string)$uuid, ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0"?>
				<personsQuery>
				<uuids>' . $escapedUuid . '</uuids>
				<fields>info.portalUrl</fields>
				<linkingStrategy>portalLinkingStrategy</linkingStrategy>';

        //set locale:
        $xml .= LanguageUtility::getLocale('xml');
        $xml .= '</personsQuery>';
        $portalUrlData = $this->webservice->getJson('persons', $xml);

        // Use getNestedArrayValue for safer and cleaner access
        return $this->getNestedArrayValue($portalUrlData, 'items.0.info.portalUrl');
    }
}