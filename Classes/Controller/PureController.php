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
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Localization\LocalizationFactory;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Frontend\Page\PageRepository;



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
    private readonly FlashMessageService $flashMessageService;

    protected function getLocale(): string
    {
        return LanguageUtility::getLocale('xml');
    }
    protected function getLocaleShort(): string
    {
        return LanguageUtility::getLocale(null);
    }
    /**
     * Constructor – dependencies are injected here.
     */
    public function __construct(
        ConfigurationManagerInterface $configurationManager,
        ResearchOutput                $researchOutput,
        Projects                      $projects,
        Equipments                    $equipments,
        DataSets                      $dataSets,
        FlashMessageService           $flashMessageService
    )
    {
        $this->configurationManager = $configurationManager;
        $this->researchOutput = $researchOutput;
        $this->dataSets = $dataSets;
        $this->projects = $projects;
        $this->equipments = $equipments;
        $this->flashMessageService = $flashMessageService;
        $this->locale = $this->getLocale();
        $this->localeShort = $this->getLocaleShort();
    }

    /**
     * Initialize settings from the ConfigurationManager.
     */
    public function initialize(): void
    {
        $settings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS
        );
        if (isset($settings['pageSize']) && $settings['pageSize'] == 0) {
            $settings['pageSize'] = 20;
        }
        $this->settings = $settings;
    }

    /**
     * A helper function to sanitize strings (to help prevent SQL injection).
     */
    private function clean_string(string $content): string
    {
        $content = strtolower($content);
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
            'filter' => $filter,
            'lang' => $this->locale
        ];
        $this->uriBuilder->reset()->setTargetPageUid($GLOBALS['TSFE']->id);
        $this->uriBuilder->reset()->setLanguage($this->locale);
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
        $paginationMaxLinks = 10;


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
                    $view = $pub->getPublicationList($this->settings, $currentPageNumber, $this->locale);
                    if (isset($view['error'])) {
                        $this->addFlashMessage($view['message'], 'Error', ContextualFeedbackSeverity::ERROR);
                        $this->view->assign('error', $view['message']);
                    } else {
                        $publications = array_fill(0, $view['count'], null);
                        $contributionToJournal = $view["contributionToJournal"] ?? [];
                        $contributionCount = is_array($contributionToJournal) ? count($contributionToJournal) : 0;
                        array_splice($publications, $view['offset'], $contributionCount, $contributionToJournal);

                        $paginator = new ArrayPaginator($publications, $currentPageNumber, $this->settings['pageSize']);
                        $pagination = new NumberedPagination($paginator, $paginationMaxLinks);

                        $this->view->assignMultiple([
                            'what_to_display' => $this->settings['what_to_display'],
                            'pagination' => $pagination,
                            'initial_no_results' => $this->settings['initialNoResults'],
                            'paginator' => $paginator,
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

                        $paginator = new ArrayPaginator($equipmentsArray, $currentPageNumber, $this->settings['pageSize']);
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

                        $paginator = new ArrayPaginator($projectsArray, $currentPageNumber, $this->settings['pageSize']);
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

                        $paginator = new ArrayPaginator($dataSetsArray, $currentPageNumber, $this->settings['pageSize']);
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
                // Get publication data
                $view = $pub->getSinglePublication($uuid);

                // Check if publication exists and is valid
                if (!is_array($view) || CommonUtilities::getArrayValue($view, 'code', 0) > 200) {
                    $this->handleContentNotFound();
                }

                // Update page title if available
                $titleValue = CommonUtilities::getNestedArrayValue($view, 'title.value', '');
                if (!empty($titleValue)) {
                    $this->updatePageTitle($titleValue);
                }

                // Assign data to view
                $this->view->assignMultiple([
                    'publication' => $view,
                    'bibtex' => $bibtex,
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


    function updatePageTitle(string $title): void
    {
        $concatenatedTitles = [$title];

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $context = GeneralUtility::makeInstance(Context::class);

        if (!$context->hasAspect('frontend.page')) {
            return; // Kontext nicht verfügbar – kein Fehler werfen
        }

        $currentPageId = $context->getAspect('frontend.page')->get('id');

        $currentSite = $siteFinder->getSiteByPageId((int)$currentPageId);
        $rootLineTitle = $currentSite->getConfiguration()['rootPageTitle'] ?? 'Home';

        $languageService = self::getLanguageService();
        $universityName = $languageService->sL('LLL:EXT:univie_pure/Resources/Private/Language/locallang.xlf:university_name');

        $concatenatedTitles[] = $rootLineTitle;
        $concatenatedTitles[] = $universityName;
        $concatenatedTitles = array_unique($concatenatedTitles);
        $pageTitle = trim(implode(" – ", $concatenatedTitles));

        $GLOBALS['TSFE']->getPageRenderer()->setTitle($pageTitle);
        $GLOBALS['TSFE']->indexedDocTitle = $pageTitle;
    }
    
     /**
     * Get the TYPO3 language service.
     *
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        if (isset($GLOBALS['LANG'])) {
            return $GLOBALS['LANG'];
        }
        
        // In TYPO3 12, use the language service from request or create with required parameters
        $context = GeneralUtility::makeInstance(Context::class);
        $languageAspect = $context->getAspect('language');
        $localizationFactory = GeneralUtility::makeInstance(LocalizationFactory::class);
        
        // Create language service with proper constructor arguments
        $languageService = new LanguageService(
            GeneralUtility::makeInstance(Locales::class),
            $localizationFactory,
            GeneralUtility::makeInstance(CacheManager::class)->getCache('runtime')
        );
        $languageService->init($languageAspect->get('id'));
        
        return $languageService;
    }
}