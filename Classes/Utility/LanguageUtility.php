<?php
declare(strict_types=1);

namespace Univie\UniviePure\Utility;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class LanguageUtility
{

    /**
     * Get XML locale string for API requests
     *
     * @return string XML formatted locale string
     */
    public static function getLocale(): string
    {
        $lang = 'de_DE'; // Default fallback

        if (($GLOBALS['TYPO3_REQUEST'] ?? null) !== null) {
            $language = $GLOBALS['TYPO3_REQUEST']->getAttribute('language');
            if ($language instanceof \TYPO3\CMS\Core\Site\Entity\SiteLanguage) {
                $languageCode = $language->getLocale()->getLanguageCode();
                $lang = $languageCode === 'de' ? 'de_DE' : 'en_GB';
            }
        }

        return '<locales><locale>' . $lang . '</locale></locales>';
    }

    /**
     * Get backend user locale
     *
     * @return string The locale string (e.g. 'de_DE' or 'en_GB')
     */
    public static function getBackendLanguage(): string
    {
        // Default fallback
        $locale = 'en_US';

        // Get the backend user from the TYPO3 context
        $context = GeneralUtility::makeInstance(Context::class);

        $backendUser = $context->getPropertyFromAspect('backend.user', 'id')
            ? $GLOBALS['BE_USER']
            : null;

        if ($backendUser !== null && isset($backendUser->uc['lang'])) {
            // Map language code to locale
            $locale = match ($backendUser->uc['lang']) {
                'de' => 'de_DE',
                'en' => 'en_GB',
                default => 'de_DE'
            };
        }


        return $locale;
    }

}