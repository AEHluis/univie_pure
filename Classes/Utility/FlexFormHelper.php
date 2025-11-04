<?php

declare(strict_types=1);

namespace Univie\UniviePure\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Service\CslRenderingService;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use Psr\Log\LoggerInterface;
use Univie\UniviePure\Service\CslDataTransformer;

/**
 * FlexForm helper for dynamic select items
 *
 * Provides methods used by FlexForms to populate select dropdowns dynamically.
 */
class FlexFormHelper
{
    /**
     * Get available CSL citation styles for FlexForm dropdown
     *
     * This method is called by TYPO3 FlexForm when rendering the Backend form.
     * It populates the citation style dropdown with all bundled CSL styles.
     *
     * @param array $config Configuration array
     * @return array Updated configuration array with items
     */
    public function getCslStyles(array &$config): array
    {
        try {
            // Get CSL rendering service
            $cslService = $this->getCslRenderingService();

            if ($cslService === null) {
                // Fallback: Add minimal default styles
                $this->addFallbackStyles($config);
                return $config;
            }

            // Get available styles from bundled CSL files
            $availableStyles = $cslService->getAvailableStyles();

            if (empty($availableStyles)) {
                // Fallback: Add minimal default styles
                $this->addFallbackStyles($config);
                return $config;
            }

            // Convert to FlexForm items format
            // TYPO3 expects: [['label' => 'Display Name', 'value' => 'style-id'], ...]
            foreach ($availableStyles as $styleId => $displayName) {
                $config['items'][] = [
                    'label' => $displayName,
                    'value' => $styleId,
                ];
            }

            // Add Fluid template options at the beginning
            array_unshift($config['items'], [
                'label' => '--- Fluid Templates ---',
                'value' => '',
                'disabled' => true,
            ]);

            array_splice($config['items'], 1, 0, [
                ['label' => 'Standard', 'value' => 'standard'],
                ['label' => 'Short', 'value' => 'short'],
                ['label' => 'Detailed', 'value' => 'detailed'],
                ['label' => 'Author', 'value' => 'author'],
                ['label' => 'Authorlist', 'value' => 'authorlist'],
            ]);

            // Add separator before CSL styles
            array_splice($config['items'], 6, 0, [[
                'label' => '--- CSL Citation Styles ---',
                'value' => '',
                'disabled' => true,
            ]]);

        } catch (\Exception $e) {
            // Log error and use fallback
            $this->addFallbackStyles($config);
        }

        return $config;
    }

    /**
     * Get CSL rendering service instance
     *
     * @return CslRenderingService|null
     */
    private function getCslRenderingService(): ?CslRenderingService
    {
        try {
            // Try to get from DI container (TYPO3 v11+)
            if (class_exists(\TYPO3\CMS\Core\DependencyInjection\ContainerBuilder::class)) {
                return GeneralUtility::makeInstance(CslRenderingService::class);
            }

            // Fallback: Manual instantiation
            $cache = GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Cache\CacheManager::class
            )->getCache('univie_pure_csl');

            $logger = GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Log\LogManager::class
            )->getLogger(__CLASS__);

            $dataTransformer = GeneralUtility::makeInstance(CslDataTransformer::class);

            return GeneralUtility::makeInstance(
                CslRenderingService::class,
                $cache,
                $logger,
                $dataTransformer
            );

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Add fallback styles when CSL service unavailable
     *
     * @param array $config Configuration array
     */
    private function addFallbackStyles(array &$config): void
    {
        $config['items'] = [
            // Fluid templates
            ['label' => '--- Fluid Templates ---', 'value' => '', 'disabled' => true],
            ['label' => 'Standard', 'value' => 'standard'],
            ['label' => 'Short', 'value' => 'short'],
            ['label' => 'Detailed', 'value' => 'detailed'],
            ['label' => 'Author', 'value' => 'author'],
            ['label' => 'Authorlist', 'value' => 'authorlist'],

            // Minimal CSL styles
            ['label' => '--- CSL Citation Styles ---', 'value' => '', 'disabled' => true],
            ['label' => 'APA', 'value' => 'apa'],
            ['label' => 'Chicago (Author-Date)', 'value' => 'chicago-author-date'],
            ['label' => 'IEEE', 'value' => 'ieee'],
            ['label' => 'Vancouver', 'value' => 'vancouver'],
            ['label' => 'Harvard', 'value' => 'harvard-cite-them-right'],
        ];
    }
}
