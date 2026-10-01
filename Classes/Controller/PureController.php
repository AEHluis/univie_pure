<?php

namespace Univie\UniviePure\Controller;

use Univie\UniviePure\Service\ApiServiceInterface;
use Univie\UniviePure\Service\CslRenderingService;
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
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use Univie\UniviePure\PageTitle\PublicationPageTitleProvider;
use Univie\UniviePure\Service\Enrichment\PublicationInsightsService;
use Throwable;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/**
 * PureController
 *
 * Main controller for displaying Pure research data via OpenAPI.
 */
class PureController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    private readonly ApiServiceInterface $apiService;
    private readonly ?CslRenderingService $cslRenderingService;
    private readonly PublicationInsightsService $publicationInsightsService;

    protected string $locale;
    protected string $localeShort;

    protected function getLocale(): string
    {
        return LanguageUtility::getLocale();
    }

    protected function getLocaleShort(): string
    {
        return LanguageUtility::getLocale();
    }

    /**
     * Constructor
     */
    public function __construct(
        ConfigurationManagerInterface    $configurationManager,
        ApiServiceInterface              $apiService,
        PublicationInsightsService       $publicationInsightsService,
        ?CslRenderingService             $cslRenderingService = null
    ) {
        $this->configurationManager = $configurationManager;
        $this->apiService = $apiService;
        $this->publicationInsightsService = $publicationInsightsService;
        $this->cslRenderingService = $cslRenderingService;
        $this->locale = $this->getLocale();
        $this->localeShort = $this->getLocaleShort();
    }

    /**
     * Get the API service
     */
    protected function getApiService(): ApiServiceInterface
    {
        return $this->apiService;
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
     * listHandlerAction: Processes filtering and redirects to listAction.
     */
    public function listHandlerAction(): ResponseInterface
    {
        $currentPageNumber = 1;
        $filter = "";

        if ($this->request->hasArgument('filter')) {
            $filter = CommonUtilities::cleanSearchString($this->request->getArgument('filter'));
        }
        if ($this->request->hasArgument('currentPageNumber')) {
            $currentPageNumber = (int)CommonUtilities::cleanSearchString($this->request->getArgument('currentPageNumber'));
        }
        $arguments = [
            'currentPageNumber' => $currentPageNumber,
            'filter' => $filter
        ];

        $currentPageId = $this->request->getAttribute('routing')->getPageId();
        $this->uriBuilder->reset()->setTargetPageUid($currentPageId);
        $uri = $this->uriBuilder->uriFor('list', $arguments, 'Pure');
        return $this->redirectToUri($uri);
    }

    /**
     * listAction: Displays a list of items (publications, equipments, projects, or datasets)
     */
    public function listAction(): ResponseInterface
    {
        $currentPageNumber = (int)($this->request->hasArgument('currentPageNumber')
            ? $this->request->getArgument('currentPageNumber')
            : 1);
        $currentPageNumber = max(1, $currentPageNumber);
        $itemsPerPage = max(1, (int)($this->settings['pageSize'] ?? 20));
        $paginationMaxLinks = 10;

        $locale = $this->locale;

        if ($this->request->hasArgument('filter')) {
            $filterValue = CommonUtilities::cleanSearchString($this->request->getArgument('filter'));
            $this->settings['filter'] = $filterValue;
            $this->view->assign('filter', $filterValue);
        }

        if (isset($this->settings['what_to_display'])) {
            switch ($this->settings['what_to_display']) {
                case 'PUBLICATIONS':
                    $this->handlePublicationsList($currentPageNumber, $itemsPerPage, $paginationMaxLinks, $locale);
                    break;

                case 'EQUIPMENTS':
                    $this->handleEquipmentsList($currentPageNumber, $itemsPerPage, $paginationMaxLinks);
                    break;

                case 'PROJECTS':
                    $this->handleProjectsList($currentPageNumber, $itemsPerPage, $paginationMaxLinks);
                    break;

                case 'DATASETS':
                    $this->handleDataSetsList($currentPageNumber, $itemsPerPage, $paginationMaxLinks);
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
     * Handle publications list display
     */
    private function handlePublicationsList(
        int $currentPageNumber,
        int $itemsPerPage,
        int $paginationMaxLinks,
        string $locale
    ): void {
        try {
            $apiService = $this->getApiService();
            $offset = ($currentPageNumber - 1) * $itemsPerPage;

            $rendering = !empty($this->settings['citationStyleCustom'])
                ? $this->settings['citationStyleCustom']
                : ($this->settings['citationStyle'] ?? 'apa');

            $params = [
                'limit' => $itemsPerPage,
                'offset' => $offset,
                'locale' => $locale,
                'rendering' => $rendering,
                'sort' => $this->settings['researchOutputOrdering'] ?? '-publicationYear',
            ];
            $params = array_merge($params, CommonUtilities::buildFilterParams($this->settings));

            if (!empty($this->settings['filter'])) {
                $params['search'] = $this->settings['filter'];
            }

            try {
                $response = $apiService->getResearchOutputs($params);
            } catch (Throwable $e) {
                $this->addFlashMessage($e->getMessage(), 'Error', ContextualFeedbackSeverity::ERROR);
                $this->view->assign('error', $e->getMessage());
                return;
            }

            $items = $response['items'] ?? [];
            $totalCount = $response['count'] ?? 0;

            $allItems = array_fill(0, $totalCount, null);
            array_splice($allItems, $offset, count($items), $items);

            $paginator = new ArrayPaginator($allItems, $currentPageNumber, $itemsPerPage);
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
                'initial_no_results' => $this->settings['initialNoResults'] ?? false,
                'paginator' => $paginator,
                'publicationsForView' => $publicationsForView,
            ]);
        } catch (Throwable $outerError) {
            $this->view->assign('error', $outerError->getMessage());
        }
    }

    /**
     * Handle equipments list display
     */
    private function handleEquipmentsList(
        int $currentPageNumber,
        int $itemsPerPage,
        int $paginationMaxLinks
    ): void {
        $apiService = $this->getApiService();
        $offset = ($currentPageNumber - 1) * $itemsPerPage;

        $params = [
            'limit' => $itemsPerPage,
            'offset' => $offset,
            'locale' => $this->locale,
            'rendering' => $this->settings['rendering'] ?? 'short',
        ];
        $params = array_merge($params, CommonUtilities::buildFilterParams($this->settings));

        try {
            $response = $apiService->getEquipments($params);
        } catch (Throwable $e) {
            $this->addFlashMessage($e->getMessage(), 'Error', ContextualFeedbackSeverity::ERROR);
            $this->view->assign('error', $e->getMessage());
            return;
        }

        $items = $response['items'] ?? [];
        $totalCount = $response['count'] ?? 0;

        $allItems = array_fill(0, $totalCount, null);
        array_splice($allItems, $offset, count($items), $items);

        $paginator = new ArrayPaginator($allItems, $currentPageNumber, $itemsPerPage);
        $pagination = new NumberedPagination($paginator, $paginationMaxLinks);

        $this->view->assignMultiple([
            'what_to_display' => $this->settings['what_to_display'],
            'pagination' => $pagination,
            'paginator' => $paginator,
            'showLinkToPortal' => $this->isLinkToPortalEnabled(),
        ]);
    }

    /**
     * Handle projects list display
     */
    private function handleProjectsList(
        int $currentPageNumber,
        int $itemsPerPage,
        int $paginationMaxLinks
    ): void {
        $apiService = $this->getApiService();
        $offset = ($currentPageNumber - 1) * $itemsPerPage;

        $params = [
            'limit' => $itemsPerPage,
            'offset' => $offset,
            'locale' => $this->locale,
            'rendering' => $this->settings['rendering'] ?? 'short',
        ];
        $params = array_merge($params, CommonUtilities::buildFilterParams($this->settings));

        if (!empty($this->settings['orderProjects'])) {
            $params['sort'] = $this->settings['orderProjects'];
        }

        try {
            $response = $apiService->getProjects($params);
        } catch (Throwable $e) {
            $this->addFlashMessage($e->getMessage(), 'Error', ContextualFeedbackSeverity::ERROR);
            $this->view->assign('error', $e->getMessage());
            return;
        }

        $items = $response['items'] ?? [];
        $totalCount = $response['count'] ?? 0;

        $allItems = array_fill(0, $totalCount, null);
        array_splice($allItems, $offset, count($items), $items);

        $paginator = new ArrayPaginator($allItems, $currentPageNumber, $itemsPerPage);
        $pagination = new NumberedPagination($paginator, $paginationMaxLinks);

        $this->view->assignMultiple([
            'what_to_display' => $this->settings['what_to_display'],
            'pagination' => $pagination,
            'paginator' => $paginator,
        ]);
    }

    /**
     * Handle datasets list display
     */
    private function handleDataSetsList(
        int $currentPageNumber,
        int $itemsPerPage,
        int $paginationMaxLinks
    ): void {
        $apiService = $this->getApiService();
        $offset = ($currentPageNumber - 1) * $itemsPerPage;

        $params = [
            'limit' => $itemsPerPage,
            'offset' => $offset,
            'locale' => $this->locale,
            'rendering' => $this->settings['rendering'] ?? 'short',
        ];
        $params = array_merge($params, CommonUtilities::buildFilterParams($this->settings));

        try {
            $response = $apiService->getDataSets($params);
        } catch (Throwable $e) {
            $this->addFlashMessage($e->getMessage(), 'Error', ContextualFeedbackSeverity::ERROR);
            $this->view->assign('error', $e->getMessage());
            return;
        }

        $items = $response['items'] ?? [];
        $totalCount = $response['count'] ?? 0;

        $allItems = array_fill(0, $totalCount, null);
        array_splice($allItems, $offset, count($items), $items);

        $paginator = new ArrayPaginator($allItems, $currentPageNumber, $itemsPerPage);
        $pagination = new NumberedPagination($paginator, $paginationMaxLinks);

        $this->view->assignMultiple([
            'what_to_display' => $this->settings['what_to_display'],
            'pagination' => $pagination,
            'paginator' => $paginator,
        ]);
    }

    /**
     * showAction: Displays a single publication.
     */
    public function showAction(): ResponseInterface
    {
        $arguments = $this->request->getArguments();

        switch ($arguments['what2show'] ?? '') {
            case 'publ':
                $uuid = CommonUtilities::getArrayValue($arguments, 'uuid', '');
                $locale = $this->localeShort;

                if (empty($uuid)) {
                    $this->handleContentNotFound();
                }

                try {
                    $view = $this->apiService->getResearchOutput($uuid, [
                        'locale' => $locale,
                        'rendering' => 'detailed',
                    ]);
                } catch (Throwable $e) {
                    $view = null;
                }

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

                $titleValue = CommonUtilities::extractLocalizedText($view['title'] ?? [], $locale);
                if (!empty($titleValue)) {
                    $this->updatePageTitle($titleValue);
                }

                $citationStyles = $this->getCitationStylesMetadata();

                $this->view->assignMultiple([
                    'publication' => $view,
                    'citationStyles' => $citationStyles,
                    'publicationUuid' => $uuid,
                    'publicationInsights' => $this->publicationInsightsService->build($view, $locale),
                    'lang' => $this->locale,
                    'showLinkToPortal' => $this->isLinkToPortalEnabled(),
                ]);
                break;

            case 'proj':
                $uuid = CommonUtilities::getArrayValue($arguments, 'uuid', '');
                $locale = $this->localeShort;

                if (empty($uuid)) {
                    $this->handleContentNotFound();
                }

                try {
                    $view = $this->apiService->getProject($uuid, [
                        'locale' => $locale,
                        'rendering' => 'detailed',
                    ]);
                } catch (Throwable $e) {
                    $view = null;
                }

                if (!is_array($view) || CommonUtilities::getArrayValue($view, 'code', 0) > 200) {
                    $this->handleContentNotFound();
                }

                $titleValue = CommonUtilities::extractLocalizedText($view['title'] ?? [], $locale);
                if (!empty($titleValue)) {
                    $this->updatePageTitle($titleValue);
                }

                $this->view->assignMultiple([
                    'project' => $view,
                    'projectUuid' => $uuid,
                    'lang' => $this->locale,
                    'showLinkToPortal' => $this->isLinkToPortalEnabled(),
                ]);
                break;

            case 'dset':
                $uuid = CommonUtilities::getArrayValue($arguments, 'uuid', '');
                $locale = $this->localeShort;

                if (empty($uuid)) {
                    $this->handleContentNotFound();
                }

                try {
                    $view = $this->apiService->getDataSet($uuid, [
                        'locale' => $locale,
                        'rendering' => 'detailed',
                    ]);
                } catch (Throwable $e) {
                    $view = null;
                }

                if (!is_array($view) || CommonUtilities::getArrayValue($view, 'code', 0) > 200) {
                    $this->handleContentNotFound();
                }

                $titleValue = CommonUtilities::extractLocalizedText($view['title'] ?? [], $locale);
                if (!empty($titleValue)) {
                    $this->updatePageTitle($titleValue);
                }

                // Resolve related organizations
                $relatedOrganizations = [];
                $orgUuids = [];
                if (!empty($view['managingOrganization']['uuid'])) {
                    $orgUuids[] = $view['managingOrganization']['uuid'];
                }
                foreach ($view['organizations'] ?? [] as $org) {
                    if (!empty($org['uuid']) && !in_array($org['uuid'], $orgUuids)) {
                        $orgUuids[] = $org['uuid'];
                    }
                }
                if (!empty($orgUuids)) {
                    try {
                        $orgsResponse = $this->apiService->getOrganisationalUnitsByUuids($orgUuids, ['locale' => $locale]);
                        $relatedOrganizations = $orgsResponse['items'] ?? [];
                    } catch (Throwable $e) {
                        // Silently fail
                    }
                }

                // Resolve related projects
                $relatedProjects = [];
                $projectUuids = [];
                foreach ($view['projects'] ?? [] as $proj) {
                    if (!empty($proj['project']['uuid'])) {
                        $projectUuids[] = $proj['project']['uuid'];
                    }
                }
                if (!empty($projectUuids)) {
                    try {
                        $projResponse = $this->apiService->getProjectsByUuids($projectUuids, ['locale' => $locale]);
                        $relatedProjects = $projResponse['items'] ?? [];
                    } catch (Throwable $e) {
                        // Silently fail
                    }
                }

                // Resolve related research outputs
                $relatedResearchOutputs = [];
                $roUuids = [];
                foreach ($view['researchOutputs'] ?? [] as $ro) {
                    if (!empty($ro['researchOutput']['uuid'])) {
                        $roUuids[] = $ro['researchOutput']['uuid'];
                    }
                }
                if (!empty($roUuids)) {
                    try {
                        $roResponse = $this->apiService->getResearchOutputsByUuids($roUuids, ['locale' => $locale]);
                        $relatedResearchOutputs = $roResponse['items'] ?? [];
                    } catch (Throwable $e) {
                        // Silently fail
                    }
                }

                $this->view->assignMultiple([
                    'dataSet' => $view,
                    'dataSetUuid' => $uuid,
                    'lang' => $this->locale,
                    'showLinkToPortal' => $this->isLinkToPortalEnabled(),
                    'relatedOrganizations' => $relatedOrganizations,
                    'relatedProjects' => $relatedProjects,
                    'relatedResearchOutputs' => $relatedResearchOutputs,
                ]);
                break;

            case 'equip':
                $uuid = CommonUtilities::getArrayValue($arguments, 'uuid', '');
                $locale = $this->localeShort;

                if (empty($uuid)) {
                    $this->handleContentNotFound();
                }

                try {
                    $view = $this->apiService->getEquipment($uuid, [
                        'locale' => $locale,
                        'rendering' => 'detailed',
                    ]);
                } catch (Throwable $e) {
                    $view = null;
                }

                if (!is_array($view) || CommonUtilities::getArrayValue($view, 'code', 0) > 200) {
                    $this->handleContentNotFound();
                }

                $titleValue = CommonUtilities::extractLocalizedText($view['title'] ?? [], $locale);
                if (empty($titleValue)) {
                    $titleValue = CommonUtilities::extractLocalizedText($view['name'] ?? [], $locale);
                }
                if (!empty($titleValue)) {
                    $this->updatePageTitle($titleValue);
                }

                $relatedOrganizations = $this->resolveOrganizationReferences($view, $locale);

                $this->view->assignMultiple([
                    'equipment' => $view,
                    'equipmentUuid' => $uuid,
                    'lang' => $this->locale,
                    'showLinkToPortal' => $this->isLinkToPortalEnabled(),
                    'relatedOrganizations' => $relatedOrganizations,
                ]);
                break;

            default:
                $this->handleContentNotFound();
                break;
        }

        if (!array_key_exists('what2show', $arguments)) {
            $this->handleContentNotFound();
        }

        $this->setCrawlerBlockingDirectives();
        return $this->htmlResponse()->withHeader(
            'X-Robots-Tag',
            'noindex, nofollow, noarchive, nosnippet, noimageindex'
        );
    }

    /**
     * Get citation styles metadata for lazy loading.
     */
    private function getCitationStylesMetadata(): array
    {
        $baseStyles = [
            ['id' => 'standard', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.standard'],
            ['id' => 'bibtex', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.bibtex'],
        ];

        if ($this->cslRenderingService !== null) {
            $cslStyles = [
                ['id' => 'apa', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.apa', 'isCsl' => true],
                ['id' => 'harvard-cite-them-right', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.harvard', 'isCsl' => true],
                ['id' => 'vancouver', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.vancouver', 'isCsl' => true],
                ['id' => 'ieee', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.ieee', 'isCsl' => true],
                ['id' => 'chicago-author-date', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.chicago', 'isCsl' => true],
                ['id' => 'mla', 'labelKey' => 'LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:univiepur.publication.citation.mla', 'isCsl' => true],
            ];
            return array_merge($baseStyles, $cslStyles);
        }

        return $baseStyles;
    }

    private function isLinkToPortalEnabled(): bool
    {
        $value = CommonUtilities::getArrayValue($this->settings, 'linkToPortal', false);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function resolveOrganizationReferences(array $item, string $locale): array
    {
        $orgUuids = [];

        if (!empty($item['managingOrganization']['uuid'])) {
            $orgUuids[] = $item['managingOrganization']['uuid'];
        }

        foreach ($item['organizations'] ?? [] as $org) {
            if (!empty($org['uuid'])) {
                $orgUuids[] = $org['uuid'];
            }
        }

        $orgUuids = array_values(array_unique($orgUuids));
        if (empty($orgUuids)) {
            return [];
        }

        try {
            $response = $this->apiService->getOrganisationalUnitsByUuids($orgUuids, ['locale' => $locale]);
            return $response['items'] ?? [];
        } catch (Throwable) {
            return [];
        }
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
        }
    }

    private function getPublicationVisibilityKey(array $publication): string
    {
        return (string)CommonUtilities::getNestedArrayValue($publication, 'visibility.key', '');
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
        }
    }

    /**
     * Prevent indexing/crawling for publication detail pages.
     */
    private function setCrawlerBlockingDirectives(): void
    {
        $robotsMeta = 'noindex, nofollow, noarchive, nosnippet, noimageindex';

        try {
            GeneralUtility::makeInstance(PageRenderer::class)->addHeaderData(
                '<meta name="robots" content="' . htmlspecialchars($robotsMeta, ENT_QUOTES, 'UTF-8') . '" />'
            );
        } catch (Throwable) {
        }
    }
}
