<?php

namespace Univie\UniviePure\Controller;

use Univie\UniviePure\Endpoints\DataSets;
use Univie\UniviePure\Endpoints\ResearchOutput;
use Univie\UniviePure\Endpoints\Projects;
use Univie\UniviePure\Endpoints\Equipments;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Utility\LanguageUtility;
use Univie\UniviePure\Utility\CommonUtilities;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use GeorgRinger\NumberedPagination\NumberedPagination;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Univie\UniviePure\PageTitle\PublicationPageTitleProvider;
use Throwable;



/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/**
 * PureController
 */
class PureController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * @var array
     */
    protected $settings = [];

    private readonly ResearchOutput $researchOutput;
    private readonly Projects $projects;
    private readonly Equipments $equipments;
    private readonly DataSets $dataSets;
    protected string $locale;
    protected string $localeShort;
    protected string $localeXml;

    protected function getLocale(): string
    {
        return LanguageUtility::getLocale(null); // Plain string like "de_DE"
    }
    protected function getLocaleShort(): string
    {
        return LanguageUtility::getLocale(null);
    }
    protected function getLocaleXml(): string
    {
        return LanguageUtility::getLocale('xml'); // XML for API calls only
    }
    /**
     * Constructor – dependencies are injected here.
     */
    public function __construct(
        ConfigurationManagerInterface    $configurationManager,
        ResearchOutput                   $researchOutput,
        Projects                         $projects,
        Equipments                       $equipments,
        DataSets                         $dataSets
    )
    {
        $this->configurationManager = $configurationManager;
        $this->researchOutput = $researchOutput;
        $this->dataSets = $dataSets;
        $this->projects = $projects;
        $this->equipments = $equipments;
        $this->locale = $this->getLocale(); // Plain string for URLs
        $this->localeShort = $this->getLocaleShort();
        $this->localeXml = $this->getLocaleXml(); // XML for API requests
    }

    /**
     * Initialize settings from the ConfigurationManager.
     */
    public function initialize(): void
    {
        $settings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS
        );
        $pageSize = (int)($settings['pageSize'] ?? 20);
        $settings['pageSize'] = $pageSize > 0 ? $pageSize : 20;
        $this->settings = $settings;
    }

    /**
     * A helper function to sanitize strings (to help prevent SQL injection).
     */
    private function clean_string(string $content): string
    {
        $content = strtolower($content);
        // Maximum length to prevent DoS
        $content = substr($content, 0, 500);

        // Remove control characters and potential injection patterns
        $content = filter_var($content, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', trim($content));

        // Remove potentially dangerous characters
        $content = preg_replace('/[<>"\';&\x00-\x1F\x7F]/u', '', $content);

        $content = preg_replace("/\(([^()]*+|(?R))*\)/", " ", $content);
        $content = preg_replace('/[^\p{L}\p{N} .–_]/u', " ", urldecode($content));
        return $content;
    }

    /**
     * listHandlerAction: Processes filtering and redirects to listAction to build a clean speaking URL.
     *
     * @return ResponseInterface
     */
    public function listHandlerAction(): ResponseInterface
    {
        $currentPageNumber = 1;
        $filter = "";

        if ($this->request->hasArgument('filter')) {
            $filter = $this->clean_string($this->request->getArgument('filter'));
        }
        if ($this->request->hasArgument('currentPageNumber')) {
            $currentPageNumber = (int)$this->clean_string($this->request->getArgument('currentPageNumber'));
        }
        $arguments = [
            'currentPageNumber' => $currentPageNumber,
            'filter' => $filter
            // Note: 'lang' parameter removed - TYPO3 handles locale via site configuration
        ];

        // Get current page ID from request (TYPO3 12 compatible)
        $currentPageId = $this->request->getAttribute('routing')->getPageId();
        $this->uriBuilder->reset()->setTargetPageUid($currentPageId);
        // Note: setLanguage() expects language ID, not locale string
        // The current language is already set by TYPO3 request, so we don't need to set it again
        $uri = $this->uriBuilder->uriFor('list', $arguments, 'Pure');
        return $this->redirectToUri($uri);
    }

    /**
     * listAction: Displays a list of items (publications, equipments, projects, or datasets)
     *
     * @return ResponseInterface
     */
    public function listAction(): ResponseInterface
    {
        // Get pagination parameters from request
        $currentPageNumber = (int)($this->request->hasArgument('currentPageNumber')
            ? $this->request->getArgument('currentPageNumber')
            : 1);
        $currentPageNumber = max(1, $currentPageNumber);
        $itemsPerPage = max(1, (int)($this->settings['pageSize'] ?? 20));
        $paginationMaxLinks = 10;

        // Use locale from TYPO3 site language (not from URL parameter)
        $locale = $this->locale;

        // Process filter from request
        if ($this->request->hasArgument('filter')) {
            $filterValue = $this->clean_string($this->request->getArgument('filter'));
            $this->settings['filter'] = $filterValue;
            $this->view->assign('filter', $filterValue);
        }

        if (isset($this->settings['what_to_display'])) {
            switch ($this->settings['what_to_display']) {
                case 'PUBLICATIONS':
                    $pub = $this->researchOutput;
                    $view = $pub->getPublicationList($this->settings, $currentPageNumber, $locale);
                    if (isset($view['error'])) {
                        $this->addFlashMessage($view['message'], 'Error', ContextualFeedbackSeverity::ERROR);
                        $this->view->assign('error', $view['message']);
                    } else {
                        $publications = array_fill(0, $view['count'], null);
                        $contributionToJournal = $view["contributionToJournal"] ?? [];
                        $contributionCount = is_array($contributionToJournal) ? count($contributionToJournal) : 0;
                        array_splice($publications, $view['offset'], $contributionCount, $contributionToJournal);

                        $paginator = new ArrayPaginator($publications, $currentPageNumber, $itemsPerPage);
                        $pagination = new NumberedPagination($paginator, $paginationMaxLinks);

                        $publicationsForView = $paginator->getPaginatedItems();
                        if (!empty($this->settings['groupByYear'])) {
                            $publicationsForView = array_values(array_filter(
                                $publicationsForView,
                                static fn($item) => $item !== null
                            ));
                        }

                        $this->view->assignMultiple([
                            'what_to_display' => $this->settings['what_to_display'],
                            'pagination' => $pagination,
                            'initial_no_results' => $this->settings['initialNoResults'],
                            'paginator' => $paginator,
                            'publicationsForView' => $publicationsForView,
                        ]);
                    }
                    break;

                case 'EQUIPMENTS':

                    $view = $this->equipments->getEquipmentsList($this->settings, $currentPageNumber);
                    if (isset($view['error'])) {
                        $this->addFlashMessage($view['message'], 'Error', ContextualFeedbackSeverity::ERROR);
                        $this->view->assign('error', $view['message']);
                    } else {
                        $equipmentsArray = array_fill(0, $view['count'], null);
                        $items = (isset($view['items']) && is_array($view['items'])) ? $view['items'] : [];
                        array_splice($equipmentsArray, $view['offset'], count($items), $items);

                        $paginator = new ArrayPaginator($equipmentsArray, $currentPageNumber, $itemsPerPage);
                        $pagination = new NumberedPagination($paginator, $paginationMaxLinks);

                        $this->view->assignMultiple([
                            'what_to_display' => $this->settings['what_to_display'],
                            'pagination' => $pagination,
                            'paginator' => $paginator,
                            'showLinkToPortal' => $this->settings['linkToPortal'] ?? null,
                        ]);
                    }
                    break;

                case 'PROJECTS':
                    $view = $this->projects->getProjectsList($this->settings, $currentPageNumber);
                    if (isset($view['error'])) {
                        $this->addFlashMessage($view['message'], 'Error', ContextualFeedbackSeverity::ERROR);
                        $this->view->assign('error', $view['message']);
                    } else {
                        $projectsArray = array_fill(0, $view['count'], null);
                        $items = (isset($view['items']) && is_array($view['items'])) ? $view['items'] : [];
                        array_splice($projectsArray, $view['offset'], count($items), $items);

                        $paginator = new ArrayPaginator($projectsArray, $currentPageNumber, $itemsPerPage);
                        $pagination = new NumberedPagination($paginator, $paginationMaxLinks);

                        $this->view->assignMultiple([
                            'what_to_display' => $this->settings['what_to_display'],
                            'pagination' => $pagination,
                            'paginator' => $paginator,
                        ]);
                    }

                    break;

                case 'DATASETS':
                    $view = $this->dataSets->getDataSetsList($this->settings, $currentPageNumber);
                    if (isset($view['error'])) {
                        $this->addFlashMessage($view['message'], 'Error', ContextualFeedbackSeverity::ERROR);
                        $this->view->assign('error', $view['message']);
                    } else {
                        $dataSetsArray = array_fill(0, $view['count'], null);
                        $items = (isset($view['items']) && is_array($view['items'])) ? $view['items'] : [];
                        array_splice($dataSetsArray, $view['offset'], count($items), $items);

                        $paginator = new ArrayPaginator($dataSetsArray, $currentPageNumber, $itemsPerPage);
                        $pagination = new NumberedPagination($paginator, $paginationMaxLinks);

                        $this->view->assignMultiple([
                            'what_to_display' => $this->settings['what_to_display'],
                            'pagination' => $pagination,
                            'paginator' => $paginator,
                        ]);
                    }

                    break;

                default:
                    $this->handleContentNotFound();
                    break;
            }
        } else {
            $this->handleContentNotFound();
        }

        return $this->htmlResponse();
    }

    /**
     * showAction: Displays a single publication.
     *
     * @return ResponseInterface
     */
    public function showAction(): ResponseInterface
    {

        $arguments = $this->request->getArguments();
        switch ($arguments['what2show'] ?? '') {
            case 'publ':
                $pub = $this->researchOutput;
                $uuid = CommonUtilities::getArrayValue($arguments, 'uuid', '');
                $locale = $this->localeShort;

                // Only proceed if we have a valid UUID
                if (empty($uuid)) {
                    $this->handleContentNotFound();
                }

                // Get bibtex data
                $bibtexXml = $pub->getBibtex($uuid, $locale);
                $bibtex = CommonUtilities::getNestedArrayValue($bibtexXml,'renderings.rendering','') ;
                $citations = $this->buildCitationRenderings($pub, $uuid, $locale, $bibtexXml);
                // Get publication data
                $view = $pub->getSinglePublication($uuid, $locale);

                // Check if publication exists and is valid
                if (!is_array($view) || CommonUtilities::getArrayValue($view, 'code', 0) > 200) {
                    $this->handleContentNotFound();
                }

                $visibilityKey = $this->getPublicationVisibilityKey($view);
                $isRestricted = $this->isRestrictedVisibility($visibilityKey);
                $isInCampus = $this->isInCampusRequest();

                if ($isRestricted && !$isInCampus) {
                    $this->handleContentNotFound();
                }

                $this->setMetaAccessHeader($isRestricted ? 'luhintern' : 'default');

                // Update page title if available
                $titleValue = CommonUtilities::getNestedArrayValue($view, 'title.value', '');
                if (!empty($titleValue)) {
                    $this->updatePageTitle($titleValue);
                }

                // Assign data to view
                $this->view->assignMultiple([
                    'publication' => $view,
                    'bibtex' => $bibtex,
                    'citations' => $citations,
                    'lang' => $this->locale,
                    'showLinkToPortal' => CommonUtilities::getArrayValue($this->settings, 'linkToPortal', null),
                ]);
                break;

            default:
                $this->handleContentNotFound();
                break;
        }
        if (!array_key_exists('what2show', $arguments)) {
            $this->handleContentNotFound();
        }
        return $this->htmlResponse();
    }

    /**
     * Handles content not found situations.
     */
    public function handleContentNotFound(): void
    {
        $response = GeneralUtility::makeInstance(ErrorController::class)
            ->pageNotFoundAction($GLOBALS['TYPO3_REQUEST'], '');
        throw new ImmediateResponseException($response, 1591428020);
    }


    /**
     * Updates the HTML page title via custom PageTitleProvider.
     */
    protected function updatePageTitle(string $title): void
    {
        $title = trim(strip_tags($title));
        if ($title === '') {
            return;
        }

        GeneralUtility::makeInstance(PublicationPageTitleProvider::class)->setTitle($title);

        try {
            GeneralUtility::makeInstance(PageRenderer::class)->setTitle($title);
        } catch (Throwable) {
            // Keep provider/TSFE fallback paths active.
        }

        if (isset($GLOBALS['TSFE'])) {
            $GLOBALS['TSFE']->indexedDocTitle = $title;
        }
    }

    private function getPublicationVisibilityKey(array $publication): string
    {
        $visibilityKey = (string)CommonUtilities::getNestedArrayValue($publication, 'visibility.@attributes.key', '');
        if ($visibilityKey === '') {
            $visibilityKey = (string)CommonUtilities::getNestedArrayValue($publication, 'visibility.key', '');
        }
        return $visibilityKey;
    }

    private function isRestrictedVisibility(string $visibilityKey): bool
    {
        return in_array(strtoupper($visibilityKey), ['RESTRICTED_IP', 'CAMPUS'], true);
    }

    private function isInCampusRequest(): bool
    {
        if (class_exists(\T3luh\T3luhlib\PhpUtility::class)) {
            return \T3luh\T3luhlib\PhpUtility::user_checkIP();
        }
        return false;
    }

    private function setMetaAccessHeader(string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        try {
            GeneralUtility::makeInstance(PageRenderer::class)->addHeaderData('<meta access="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" />');
        } catch (Throwable) {
            // Ignore if header injection is unavailable in current rendering context.
        }
    }

    private function buildCitationRenderings(ResearchOutput $pub, string $uuid, string $locale, mixed $bibtexXml): array
    {
        $styles = [
            ['id' => 'standard', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.standard', 'renderer' => 'standard'],
            ['id' => 'harvard', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.harvard', 'renderer' => 'harvard'],
            ['id' => 'apa', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.apa', 'renderer' => 'apa'],
            ['id' => 'vancouver', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.vancouver', 'renderer' => 'vancouver'],
            ['id' => 'author', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.author', 'renderer' => 'author'],
            ['id' => 'bibtex', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.bibtex', 'renderer' => 'bibtex'],
            ['id' => 'ris', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.ris', 'renderer' => 'ris'],
        ];

        $citations = [];
        foreach ($styles as $style) {
            $response = $style['id'] === 'bibtex'
                ? $bibtexXml
                : $pub->getCitationRendering($uuid, $style['renderer'], $locale);
            $content = $this->extractCitationContent($response);

            if ($content === '') {
                continue;
            }

            if (in_array($style['id'], ['bibtex', 'ris'], true)) {
                $content = $this->normalizePreformattedCitation($content, $style['id']);
            }

            $citations[] = [
                'id' => $style['id'],
                'labelKey' => $style['labelKey'],
                'content' => $content,
                'isPreformatted' => in_array($style['id'], ['bibtex', 'ris'], true),
            ];
        }

        return $citations;
    }

    private function extractCitationContent(mixed $response): string
    {
        if (is_string($response)) {
            $content = trim($response);
            return $this->isRendererErrorPayload($content) ? '' : $content;
        }

        if (!is_array($response)) {
            return '';
        }

        $paths = [
            'renderings.rendering',
            'renderings.0.html',
            'renderings.rendering.0.html',
            'rendering',
            'data',
        ];

        foreach ($paths as $path) {
            $value = CommonUtilities::getNestedArrayValue($response, $path, null);
            $text = $this->flattenCitationValue($value);
            if ($text !== '') {
                return $this->isRendererErrorPayload($text) ? '' : $text;
            }
        }

        return '';
    }

    private function flattenCitationValue(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (!is_array($value)) {
            return '';
        }
        if (isset($value['html']) && is_string($value['html'])) {
            return trim($value['html']);
        }

        $parts = [];
        foreach ($value as $item) {
            $piece = $this->flattenCitationValue($item);
            if ($piece !== '') {
                $parts[] = $piece;
            }
        }
        return trim(implode("\n", $parts));
    }

    private function isRendererErrorPayload(string $content): bool
    {
        $trimmed = ltrim($content);
        return str_starts_with($trimmed, 'Unknown render style');
    }

    private function normalizePreformattedCitation(string $content, string $styleId): string
    {
        $text = preg_replace('#<br\s*/?>#i', "\n", $content);
        $text = preg_replace('#</p>\s*<p[^>]*>#i', "\n\n", (string)$text);
        $text = strip_tags((string)$text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text);

        if ($styleId === 'bibtex') {
            $text = $this->formatBibtexForReadability($text);
        }

        return $text;
    }

    private function formatBibtexForReadability(string $bibtex): string
    {
        $text = trim($bibtex);
        if (!str_starts_with($text, '@')) {
            return $text;
        }

        // FIS often returns BibTeX in a single line with double spaces between fields.
        $text = preg_replace('/^(@[^{]+\{[^,]+),\s{2,}/', "$1,\n  ", $text) ?? $text;
        $text = preg_replace('/,\s{2,}([a-zA-Z_][a-zA-Z0-9_]*\s*=)/', ",\n  $1", $text) ?? $text;
        $text = preg_replace('/\n\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*/', "\n  $1 = ", $text) ?? $text;
        $text = preg_replace('/,\s*}$/', "\n}", $text) ?? $text;

        return $text;
    }
}
