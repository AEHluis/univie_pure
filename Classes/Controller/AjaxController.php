<?php
declare(strict_types=1);

namespace Univie\UniviePure\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Service\WebService;

/**
 * AJAX Controller for dynamic loading of Pure data in backend forms
 * Following TYPO3 core patterns for backend AJAX controllers
 */
class AjaxController
{
    protected WebService $webService;

    public function __construct()
    {
        $this->webService = GeneralUtility::makeInstance(WebService::class);
    }

    /**
     * Search organizations via AJAX
     */
    public function searchOrganizationsAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $searchTerm = trim($parsedBody['searchTerm'] ?? '');
        
        if (strlen($searchTerm) < 3) {
            return new JsonResponse(['results' => []]);
        }

        $locale = 'de_DE'; // Could be made configurable
        
        $postData = trim('<?xml version="1.0"?>
            <organisationalUnitsQuery>
            <size>20</size>
            <offset>0</offset>
            <locales>
            <locale>' . htmlspecialchars($locale, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</locale>
            </locales>
            <fields>
            <field>uuid</field>
            <field>name.text.value</field>
            </fields>
            <orderings>
            <ordering>name</ordering>
            </orderings>
            <returnUsedContent>true</returnUsedContent>
            <searchString>' . htmlspecialchars($searchTerm, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>
            </organisationalUnitsQuery>');

        $organisations = $this->webService->getJson('organisational-units', $postData);
        $results = [];

        if (is_array($organisations) && isset($organisations['items'])) {
            foreach ($organisations['items'] as $org) {
                $results[] = [
                    'value' => $org['uuid'],
                    'label' => $org['name']['text']['0']['value']
                ];
            }
        }

        return new JsonResponse(['results' => $results]);
    }

    /**
     * Search persons via AJAX
     */
    public function searchPersonsAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $searchTerm = trim($parsedBody['searchTerm'] ?? '');
        
        if (strlen($searchTerm) < 3) {
            return new JsonResponse(['results' => []]);
        }

        $personXML = trim('<?xml version="1.0"?>
            <personsQuery>
            <size>20</size>
            <offset>0</offset>
            <fields>
            <field>uuid</field>
            <field>name.*</field>
            </fields>
            <orderings>
            <ordering>lastName</ordering>
            </orderings>
            <employmentStatus>ACTIVE</employmentStatus>
            <searchString>' . htmlspecialchars($searchTerm, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>
            </personsQuery>');

        $persons = $this->webService->getJson('persons', $personXML);
        $results = [];

        if (is_array($persons) && isset($persons['items'])) {
            foreach ($persons['items'] as $person) {
                $results[] = [
                    'value' => $person['uuid'],
                    'label' => $person['name']['lastName'] . ', ' . $person['name']['firstName']
                ];
            }
        }

        return new JsonResponse(['results' => $results]);
    }

    /**
     * Search persons with organization via AJAX
     */
    public function searchPersonsWithOrganizationAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $searchTerm = trim($parsedBody['searchTerm'] ?? '');
        
        if (strlen($searchTerm) < 3) {
            return new JsonResponse(['results' => []]);
        }

        $personXML = trim('<?xml version="1.0"?>
            <personsQuery>
            <size>20</size>
            <offset>0</offset>
            <fields>
            <field>uuid</field>
            <field>name.*</field>
            <field>honoraryStaffOrganisationAssociations.uuid</field>
            <field>honoraryStaffOrganisationAssociations.period.*</field>
            <field>honoraryStaffOrganisationAssociations.organisationalUnit.uuid</field>
            <field>honoraryStaffOrganisationAssociations.organisationalUnit.name.*</field>
            </fields>
            <orderings>
            <ordering>lastName</ordering>
            </orderings>
            <employmentStatus>ACTIVE</employmentStatus>
            <searchString>' . htmlspecialchars($searchTerm, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>
            </personsQuery>');

        $persons = $this->webService->getJson('persons', $personXML);
        $results = [];

        if (is_array($persons) && isset($persons['items'])) {
            foreach ($persons['items'] as $person) {
                $personName = $person['name']['lastName'] . ', ' . $person['name']['firstName'];
                $organizationNames = $this->getActiveOrganizationNames($person);
                
                if (!empty($organizationNames)) {
                    $displayName = $personName . ' (' . implode(', ', $organizationNames) . ')';
                } else {
                    $displayName = $personName;
                }
                
                $results[] = [
                    'value' => $person['uuid'],
                    'label' => $displayName
                ];
            }
        }

        return new JsonResponse(['results' => $results]);
    }

    /**
     * Search projects via AJAX
     */
    public function searchProjectsAction(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        $searchTerm = trim($parsedBody['searchTerm'] ?? '');
        
        if (strlen($searchTerm) < 3) {
            return new JsonResponse(['results' => []]);
        }

        $projectXML = trim('<?xml version="1.0"?>
            <projectsQuery>
            <size>20</size>
            <offset>0</offset>
            <fields>
            <field>uuid</field>
            <field>title.*</field>
            <field>acronym</field>
            </fields>
            <orderings>
            <ordering>title</ordering>
            </orderings>
            <workflowSteps>
            <workflowStep>validated</workflowStep>
            </workflowSteps>
            <searchString>' . htmlspecialchars($searchTerm, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</searchString>
            </projectsQuery>');

        $projects = $this->webService->getJson('projects', $projectXML);
        $results = [];

        if (is_array($projects) && isset($projects['items'])) {
            foreach ($projects['items'] as $project) {
                $title = $project['title']['text'][0]['value'] ?? $project['title']['value'] ?? 'Unknown Project';
                if (!empty($project['acronym']) && strpos($title, $project['acronym']) === false) {
                    $title = $project['acronym'] . ' - ' . $title;
                }
                
                $results[] = [
                    'value' => $project['uuid'],
                    'label' => $title
                ];
            }
        }

        return new JsonResponse(['results' => $results]);
    }

    /**
     * Extract active organization names from person data
     */
    private function getActiveOrganizationNames(array $person): array
    {
        $organizationNames = [];
        
        if (!isset($person['honoraryStaffOrganisationAssociations'])) {
            return $organizationNames;
        }
        
        $associations = $person['honoraryStaffOrganisationAssociations'];
        
        // Handle single association
        if (isset($associations['organisationalUnit'])) {
            $associations = [$associations];
        }
        
        foreach ($associations as $association) {
            // Check if association is currently active
            if (isset($association['period']['endDate']) && 
                !empty($association['period']['endDate']) &&
                strtotime($association['period']['endDate']) < time()) {
                continue; // Skip inactive associations
            }
            
            if (isset($association['organisationalUnit']['name']['text'])) {
                $orgNames = $association['organisationalUnit']['name']['text'];
                
                // Handle multiple name formats
                if (is_array($orgNames)) {
                    foreach ($orgNames as $nameEntry) {
                        if (isset($nameEntry['value'])) {
                            $orgName = $nameEntry['value'];
                            break; // Take first available name
                        }
                    }
                } else {
                    $orgName = $orgNames;
                }
                
                if (!empty($orgName) && !in_array($orgName, $organizationNames)) {
                    $organizationNames[] = $orgName;
                }
            }
        }
        
        return $organizationNames;
    }
}