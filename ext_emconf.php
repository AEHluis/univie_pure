<?php
$EM_CONF[$_EXTKEY] = array(
    'title' => 'T3LUH FIS',
    'description' => 'This extension allows you to seamlessly integrate academic content from the Elsevier Pure Research Information System (API v524) into your TYPO3 website, displaying publications, projects, datasets, and equipment details. Based on the Vienna Pure extension, our implementation has been specifically optimized to meet the requirements of Leibniz University Hannover, while also being designed for global use and continuous improvement.',
    'category' => 'plugin',
    'author' => 'TYPO3-Team LUIS LUH',
    'author_email' => 'typo3@luis.uni-hannover.de',
    'state' => 'beta',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '12.7.524',
    'constraints' => array(
        'depends' => array(
            'php' => '8.2.0-8.4.99',
            'typo3' => '12.0.0-12.99.99',
            'numbered_pagination' => '2.0.1-2.99.99',
        ),
        'conflicts' => array(
        ),
        'suggests' => array(
        ),
    ),
);
