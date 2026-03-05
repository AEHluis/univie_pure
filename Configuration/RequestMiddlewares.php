<?php

declare(strict_types=1);

/**
 * Frontend middleware configuration for univie_pure extension
 * Registers the citation AJAX middleware for handling eID-style requests
 */
return [
    'frontend' => [
        'univie-pure/citation-ajax' => [
            'target' => \Univie\UniviePure\Middleware\CitationAjaxMiddleware::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/eid',
            ],
        ],
    ],
];
