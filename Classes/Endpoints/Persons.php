<?php

declare(strict_types=1);

namespace Univie\UniviePure\Endpoints;

use Univie\UniviePure\Service\WebService;
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

    /**
     * Retrieves the profile rendering for a given person UUID.
     *
     * @param string $uuid The UUID of the person
     * @return string|null The HTML rendering of the profile, or null
     */
    public function getProfile(string $uuid): ?string
    {
        // Ensure UUID is a string and escape it for XML
        $escapedUuid = htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0"?>
				<personsQuery>
				<uuids>' . $escapedUuid . '</uuids>
				<rendering>short</rendering>
				<linkingStrategy>portalLinkingStrategy</linkingStrategy>';
        //set locale:
        $xml .= LanguageUtility::getLocale('xml');
        $xml .= '</personsQuery>';

        $profile = $this->webservice->getJson('persons', $xml);
        $renderingValue = $this->getNestedArrayValue($profile, 'items.0.rendering.0.value');

        return is_string($renderingValue) ? $renderingValue : null;
    }

    /**
     * Retrieves the portal URL for a given person UUID.
     *
     * @param string $uuid The UUID of the person.
     * @return string|null The portal URL, or null if not found.
     */
    public function getPortalUrl(string $uuid): ?string
    {
        // Ensure UUID is a string and escape it for XML
        $escapedUuid = htmlspecialchars($uuid, ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0"?>
				<personsQuery>
				<uuids>' . $escapedUuid . '</uuids>
				<fields>info.portalUrl</fields>
				<linkingStrategy>portalLinkingStrategy</linkingStrategy>';

        //set locale:
        $xml .= LanguageUtility::getLocale('xml');
        $xml .= '</personsQuery>';

        $portalUrlData = $this->webservice->getJson('persons', $xml);
        $portalUrl = $this->getNestedArrayValue($portalUrlData, 'items.0.info.portalUrl');

        // Ensure the returned value is a string or null
        return is_string($portalUrl) ? $portalUrl : null;
    }
}