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

class Persons
{
    public function getProfile($uuid)
    {
        $xml = '<?xml version="1.0"?>
				<personsQuery>
				<uuids>' . $uuid . '</uuids>
				<rendering>short</rendering>
				<linkingStrategy>portalLinkingStrategy</linkingStrategy>';

        //set locale:
        $xml .= CommonUtilities::getLocale();

        $xml .= '</personsQuery>';

        $webservice = new WebService;
        $profile = $webservice->getJson('persons', $xml);

        return $profile['items'][0]['rendering'][0]['value'];

    }

    public function getPortalUrl($uuid)
    {
        $xml = '<?xml version="1.0"?>
				<personsQuery>
				<uuids>' . $uuid . '</uuids>
				<fields>info.portalUrl</fields>
				<linkingStrategy>portalLinkingStrategy</linkingStrategy>';

        //set locale:
        $xml .= CommonUtilities::getLocale();

        $xml .= '</personsQuery>';

        $webservice = new WebService;
        $portalUrl = $webservice->getJson('persons', $xml);

        return $portalUrl['items'][0]['info']['portalUrl'];
    }
}
