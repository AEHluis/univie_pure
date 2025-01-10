<?php
namespace Univie\UniviePure\Controller;

use Univie\UniviePure\Endpoints\DataSets;
use Univie\UniviePure\Endpoints\ResearchOutput;
use Univie\UniviePure\Endpoints\Projects;
use Univie\UniviePure\Endpoints\Equipments;
use T3luh\T3luhlib\Utils\Page;
use TYPO3\CMS\Extbase\Object\ObjectManager;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Page\PageAccessFailureReasons;
use TYPO3\CMS\Core\Http\ImmediateResponseException;

use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use GeorgRinger\NumberedPagination\NumberedPagination;

/*
 * (c) 2016 Christian Klettner <christian.klettner@univie.ac.at>, univie
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

/**
 * PublicationController
 */
class PureController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    /**
     * @TYPO3\CMS\Extbase\Annotation\Inject
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * @var array
     */
    protected $settings = array();

    /**
     * Get settings from ConfigurationManager ziehen
     */
    public function initialize()
    {
        $settings = $this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS
        );
        if($settings['pageSize'] == 0){
            $settings['pageSize'] = 20;
        }
        $this->settings = $settings;
    }

    /**
     *  (slightly) stupid function to prevent sql injections
     */
    private function clean_string($content)
    {
        $content = strtolower($content);
        $content = preg_replace("/\(([^()]*+|(?R))*\)/"," ", $content);
        $content = preg_replace('/[^\p{L}\p{N} .–_]/u', " ", urldecode($content));

        return $content;
    }

    /**
     * filter in listAction is submitted to this action
     * listHandlerAction then redirecting the request to
     * listAction back in order to build a useful speaking url
     * without referer links.
     *
     * @return void
     */
    public function listHandlerAction() {
        $lang = $this->settings['lang'];
        $currentPageNumber = 1;
        $filter = "";

        if ($this->request->hasArgument('filter')) {
            $filter = $this->clean_string($this->request->getArgument('filter'));
        }
        if ($this->request->hasArgument('currentPageNumber')) {
            $currentPageNumber = $this->clean_string($this->request->getArgument('currentPageNumber'));
        }
        $arguments['currentPageNumber']= $currentPageNumber;
        $arguments['filter']= $filter;
        $arguments['lang'] = $lang;
        $this->uriBuilder->reset()->setTargetPageUid($GLOBALS['TSFE']->id);
        $this->uriBuilder->reset()->setLanguage($lang);
        $uri = $this->uriBuilder->uriFor('list', $arguments, 'Pure');
        $this->redirectToUri($uri);
    }

    /**
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        $currentPageNumber = 1;
        $paginationMaxLinks = 10;
        if (isset($_GET['tx_univiepure_univiepure']['currentPageNumber'])) {
            $currentPageNumber = (integer)$_GET['tx_univiepure_univiepure']['currentPageNumber'] ;
        }

        $lang = ($GLOBALS['TSFE']->config['config']['language'] == 'de') ? 'de_DE' : 'en_GB';
        //reduce the list:
        if ($this->request->hasArgument('filter')) {
            $this->settings['filter'] = $this->clean_string($this->request->getArgument('filter'));
            $this->view->assign('filter', $this->clean_string($this->request->getArgument('filter')));
            $this->settings['initialNoResults'] = 0;
        }elseif ($_GET['filter']){
            $this->settings['filter'] = $this->clean_string($_GET['filter']);
            $this->view->assign('filter', $this->clean_string($_GET['filter']));
            $this->settings['initialNoResults'] = 0;
        }

        switch ($this->settings['what_to_display']) {
            case 'PUBLICATIONS':
                $pub = new ResearchOutput;
                $view = $pub->getPublicationList($this->settings,$currentPageNumber);
                /*
                 * ArrayPaginator expects an array with all elements to calculate pagination in terms of currentPageNo. and
                 * pageSize. We cannot provide this due to performance issues and web service timeouts.
                 * So we initialize an array with the total amount of elements set to null.
                 * Then we overwrite the results of the web service request at the current offset position.
                 */
                $publications = array_fill(0, $view['count'], null);
                array_splice($publications, $view['offset'], count($view["contributionToJournal"]), $view["contributionToJournal"]);
                $paginator = new ArrayPaginator($publications, $currentPageNumber, $this->settings['pageSize']);
                $pagination = new NumberedPagination($paginator, $paginationMaxLinks);

                $this->view->assignMultiple(
                    [
                        'what_to_display' => $this->settings['what_to_display'],
                        'pagination' => $pagination,
                        'initial_no_results', $this->settings['initialNoResults'],
                        'paginator' => $paginator,
                    ]
                );
                break;

            case 'EQUIPMENTS':
                $equipments = new Equipments;
                $view = $equipments->getEquipmentsList($this->settings, $currentPageNumber);
                $equipments = array_fill(0, $view['count'], null);
                array_splice($equipments, $view['offset'], count($view["items"]), $view["items"]);
                $paginator = new ArrayPaginator($equipments, $currentPageNumber, $this->settings['pageSize']);
                $pagination = new NumberedPagination($paginator, $paginationMaxLinks);

                $this->view->assignMultiple(
                    [
                        'what_to_display' => $this->settings['what_to_display'],
                        'pagination' => $pagination,
                        'paginator' => $paginator,
                        'initial_no_results', $this->settings['initialNoResults'],
                        'showLinkToPortal' => $this->settings['linkToPortal']
                    ]
                );

                break;
            case 'PROJECTS':
                $projects = new Projects;
                $view = $projects->getProjectsList($this->settings, $currentPageNumber);
                $projects = array_fill(0, $view['count'], null);
                array_splice($projects, $view['offset'], count($view["items"]), $view["items"]);
                $paginator = new ArrayPaginator($projects, $currentPageNumber, $this->settings['pageSize']);
                $pagination = new NumberedPagination($paginator, $paginationMaxLinks);


                $this->view->assignMultiple(
                    [
                        'what_to_display' => $this->settings['what_to_display'],
                        'pagination' => $pagination,
                        'paginator' => $paginator,
                        'initial_no_results', $this->settings['initialNoResults']
                    ]
                );
                break;
            case 'DATASETS':
                $dataSets = new DataSets;
                $view = $dataSets->getDataSetsList($this->settings, $currentPageNumber);
                $dataSets = array_fill(0, $view['count'], null);
                array_splice($dataSets, $view['offset'], count($view["items"]), $view["items"]);
                $paginator = new ArrayPaginator($dataSets, $currentPageNumber, $this->settings['pageSize']);
                $pagination = new NumberedPagination($paginator, $paginationMaxLinks);


                $this->view->assignMultiple(
                    [
                        'what_to_display' => $this->settings['what_to_display'],
                        'pagination' => $pagination,
                        'paginator' => $paginator,
                        'initial_no_results', $this->settings['initialNoResults']
                    ]
                );
                break;
            default:
                //Should never occur
                $this->handleContentNotFound();
                break;
        }
    }


    /**
     * action show
     *
     * @param \Univie\UniviePure\Domain\Model\Publication $publication
     * @return void
     */
    public function showAction()
    {
        $arguments = $this->request->getArguments();
        switch ($arguments['what2show']) {
            case 'publ':
                $lang = ($GLOBALS['TSFE']->config['config']['language'] == 'de') ? 'de_DE' : 'en_GB';
                $pub = new ResearchOutput;
                $bibtexXml = $pub->getBibtex($arguments['uuid'], $lang);
                $bibtex = (string)$bibtexXml->renderings[0]->rendering;
                if(is_array($arguments)) {
                    if(array_key_exists('uuid', $arguments)) {
                        $view = $pub->getSinglePublication($arguments['uuid']);
                        if(is_array($view)) {
                            if (array_key_exists('code', $view)) {
                                if (intval($view['code']) > 200) {
                                    $this->handleContentNotFound();
                                }
                            }

                            if (array_key_exists('title', $view)) {
                                if (is_array($view['title'])){
                                    if (array_key_exists('value', $view['title'])) {
                                        Page::updatePageTitle($view['title']['value']);
                                    }
                                }
                            }
                        }
                        $this->view->assignMultiple(array(
                            'publication' => $view,
                            'bibtex' => $bibtex,
                            'lang' => $lang,
                            'showLinkToPortal' => $this->settings['linkToPortal']
                        ));
                    }else{
                        $this->handleContentNotFound();
                    }
                }else {
                    $this->handleContentNotFound();
                }
                break;
            default:
                $this->handleContentNotFound();
        }
        if(!array_key_exists('what2show', $arguments)) {
            $this->handleContentNotFound();
        }

    }


    public function handleContentNotFound()
    {
        $response = GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction($GLOBALS['TYPO3_REQUEST'], '');
        throw new ImmediateResponseException($response, 1591428020);
    }


    private function printArrayList($array)
    {
        $echo = "<ul>";
        if (is_array($array)){
            foreach($array as $k => $v) {
                if (is_array($v)) {
                    $echo .= "<li>" . $k . "</li>";
                    $echo .= $this->printArrayList($v);
                    continue;
                }
                $echo .= "<li>" . $v . "</li>";
            }
        }


        $echo .= "</ul>";
        return $echo;
    }



}
?>