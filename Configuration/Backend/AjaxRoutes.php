<?php

/**
 * Definitions for AJAX routes provided by EXT:univie_pure
 * Following TYPO3 core patterns for backend AJAX routes
 */
return [
    // Organizations suggest
    'univie_pure_search_organizations' => [
        'path' => '/univie_pure/search/organizations',
        'target' => \Univie\UniviePure\Controller\AjaxController::class . '::searchOrganizationsAction',
    ],

    // Persons with organization suggest
    'univie_pure_search_persons_with_org' => [
        'path' => '/univie_pure/search/persons-with-org', 
        'target' => \Univie\UniviePure\Controller\AjaxController::class . '::searchPersonsWithOrganizationAction',
    ],

    // Projects suggest
    'univie_pure_search_projects' => [
        'path' => '/univie_pure/search/projects',
        'target' => \Univie\UniviePure\Controller\AjaxController::class . '::searchProjectsAction',
    ],
];