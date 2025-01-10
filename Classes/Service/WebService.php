<?php
namespace Univie\UniviePure\Service;

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Utility\DotEnv;
use TYPO3\CMS\Core\Core\Environment;
/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Christian Klettner <christian.klettner@univie.ac.at>, univie
 *           TYPO3-Team LUIS Uni-Hannover <typo3@luis.uni-hannover.de>, LUH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * PublicationController
 */
class WebService
{

    /**
     * @var $server String
     */
    protected $server = '';

    /**
     * @var $proxy String
     */
    protected $proxy = '';

    /**
     * @var $apiKey String
     */
    protected $apiKey = '';

    /**
     * @var $versionPath String
     */
    protected $versionPath = '';

    /**
     * init
     */
    public function __construct()
    {
        $dotEnv = new DotEnv(Environment::getPublicPath() . "/.env");
        $dotEnv->load();
        $this->setServer($dotEnv->variables["PURE_URI"]);
        $this->setApiKey($dotEnv->variables["PURE_APIKEY"]);
        $this->setVersionPath($dotEnv->variables["PURE_ENDPOINT"]);
        $this->setProxy($dotEnv->variables["PURE_PROXY"]);
    }

    /**
     * setter for proxy
     */
    private function setProxy($proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * setter for server
     */
    private function setServer($server)
    {
        $this->server = $server;
    }

    /**
     * setter for api-key
     */
    private function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * setter for version path e.g. /ws/api/59/
     */
    private function setVersionPath($versionPath)
    {
        $this->versionPath = $versionPath;
    }

    /**
     * the call to the web service
     * @return String json or XML
     */
    public function getSingleResponse($endpoint, $uuid, $responseType = "json", $decoded = true, $renderer = 'html', $lang = NULL)
    {
        $url = $this->getServer() . $this->getVersionPath() . $endpoint . '/' . $uuid;
        if ($renderer == "bibtex") {
            $url = $url . "?rendering=BIBTEX";
            if ($lang) {
                $url = $url . "&locale=" . $lang;
            }
        }
        if ($renderer == "detailsPortal") {
            $url = $url . "?rendering=detailsPortal";
            if ($lang) {
                $url = $url . "&locale=" . $lang;
            }
        }
        if ($renderer == "standard") {
            $url = $url . "?rendering=standard";
            if ($lang) {
                $url = $url . "&locale=" . $lang;
            }
        }


        $headers = array("api-key: " . $this->getApiKey() . "", "Content-Type: application/xml", "Accept: application/" . $responseType . "", "charset=utf-8");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_PRIVATE, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); //timeout in seconds
        $relPath = './typo3temp/var/cache/';

        $cacheFile = $relPath . md5($endpoint . $uuid . $lang . $responseType . $renderer) . ".cache";
        if (file_exists($cacheFile)) {
            if ((time() - filemtime($cacheFile) > 4 * 3600) || (filesize($cacheFile) < 350)) {
                // file older than 8 hours
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
                $response = curl_exec($ch);
                $this->writecacheFile($cacheFile, $response);
                curl_close($ch);
            } else {
                // file younger than 8 hours
                $response = file_get_contents($cacheFile);
            }
        } else {
            $response = curl_exec($ch);
            $this->writecacheFile($cacheFile, $response);
            curl_close($ch);
        }

        if ($responseType == "json") {
            if ($decoded) {
                $result = json_decode($response, true);
                $this->checkReturnCodeErrorMsg($result);
                return $result;
            } else {
                return $response;
            }
        } else {
            if ($decoded) {
                if (strpos($response, "DOCTYPE HTML PUBLIC") === false) {
                    // xml response FIS-server should return valid xml
                    return simplexml_load_string($response, null, LIBXML_NOCDATA);
                } else {
                    // FIS-server has crashed and returns html with error messages...
                    $this->checkReturnCodeErrorMsg(['data' => '500', 'title' => 'FIS-Server response Issue']);
                }
            } else {
                return $response;
            }
        }
    }

    /** getter for proxy
     * @return String proxy
     */
    private function getProxy()
    {
        return $this->proxy;
    }


    /** getter for server
     * @return String server
     */
    private function getServer()
    {
        return $this->server;
    }

    /**
     * getter for version path
     * @return String versionPath
     */
    private function getVersionPath()
    {
        return $this->versionPath;
    }

    /**
     * getter for api-key
     * @return String api-key
     */
    private function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * write cache file
     * @return null
     */
    private function writecacheFile($cacheFile, $response)
    {
        if ($response != false) {
            if (strlen($response) > 311) {
                file_put_contents($cacheFile, $response);
            }
        }
        return null;
    }

    private function checkReturnCodeErrorMsg($data)
    {
        if (isset($data['code']) && $data['code'] != '200') {
            /** @var $flashMessage FlashMessage */
            $flashMessage = GeneralUtility::makeInstance(
                'TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                htmlspecialchars($data['title']) . " - " . htmlspecialchars($data['description']),
                htmlspecialchars('PURE API Error'),
                FlashMessage::ERROR, false);
            /** @var $flashMessageService FlashMessageService */
            $flashMessageService = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessageService');
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);

        }
    }

    /**
     * the call to the web service
     * @return String json
     */
    public function getAlternativeSingleResponse($endpoint, $q, $responseType = "json", $lang = "de_DE")
    {
        $url = $this->getServer() . $this->getVersionPath() . $endpoint . '?q=' . $q . "&locale=" . $lang;
        $headers = array("api-key: " . $this->getApiKey() . "", "Content-Type: application/xml", "Accept: application/" . $responseType . "", "charset=utf-8");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_PRIVATE, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); //timeout in seconds
        $relPath = './typo3temp/var/cache/';

        $cacheFile = $relPath . md5($endpoint . $q . $lang . $responseType) . ".alternative.cache";
        if (file_exists($cacheFile)) {
            if ((time() - filemtime($cacheFile) > 4 * 3600) || (filesize($cacheFile) < 350)) {
                // file older than 8 hours
                if (file_exists($cacheFile)) {
                    unlink($cacheFile);
                }
                $response = curl_exec($ch);
                $this->writecacheFile($cacheFile, $response);
                curl_close($ch);
            } else {
                // file younger than 8 hours
                $response = file_get_contents($cacheFile);
            }
        } else {
            $response = curl_exec($ch);
            $this->writecacheFile($cacheFile, $response);
            curl_close($ch);
        }
        if ($responseType == "json") {
            $result = json_decode($response, true);
            $this->checkReturnCodeErrorMsg($result);
            return $result;
        } else {
            if (strpos($response, "DOCTYPE HTML PUBLIC") === false) {
                // xml response FIS-server should return valid xml
                return simplexml_load_string($response, null, LIBXML_NOCDATA);
            } else {
                // FIS-server has crashed and returns html with error messages...
                $this->checkReturnCodeErrorMsg(['data' => '500', 'title' => 'FIS-Server response Issue']);
            }


        }
    }

    /**
     * request a json result
     * @return array result
     */
    public function getJson($endpoint, $data_string)
    {
        $json = $this->getResponse($endpoint, $data_string, 'json');
        $result = json_decode($json, true);
        $this->checkReturnCodeErrorMsg($result);
        return $result;

    }

    /**
     * the call to the web service
     * @return String json or XML
     */
    public function getResponse($endpoint, $data_string, $responseType)
    {
        $url = $this->getServer() . $this->getVersionPath() . $endpoint;
        $headers = array("api-key: " . $this->getApiKey() . "", "Content-Type: application/xml", "Accept: application/" . $responseType . "", "charset=utf-8");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_PRIVATE, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); //timeout in seconds

        $relPath = './typo3temp/var/cache/';

        // do not cache FIS-Search requests
        if (strpos($data_string, "searchString") !== false) {
            $response = curl_exec($ch);
        } else {
            $cacheFile = $relPath . md5($endpoint . $data_string . $responseType) . ".default.cache";
            if (file_exists($cacheFile)) {
                if ((time() - filemtime($cacheFile) > 4 * 3600) || (filesize($cacheFile) < 350)) {
                    // file older than 8 hours
                    if (file_exists($cacheFile)) {
                        unlink($cacheFile);
                    }
                    $response = curl_exec($ch);
                    $this->writecacheFile($cacheFile, $response);
                    curl_close($ch);
                } else {
                    // file younger than 8 hours
                    $response = file_get_contents($cacheFile);
                }
            } else {
                $response = curl_exec($ch);
                $this->writecacheFile($cacheFile, $response);
                curl_close($ch);
            }
        }

        return $response;
    }

    public function getXml($endpoint, $data_string)
    {
        $xmlResult = $this->getResponse($endpoint, $data_string, 'xml');
        $xml = simplexml_load_string($xmlResult, null, LIBXML_NOCDATA);

        $result = json_decode(json_encode((array)$xml), 1);
        $this->checkReturnCodeErrorMsg($result);
        return $result;
    }
}

?>
