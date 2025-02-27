<?php
namespace Univie\UniviePure\Cache\Warmup;

use TYPO3\CMS\Core\Cache\CacheWarmupInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Utility\ClassificationScheme;
use TYPO3\CMS\Core\Log\LogManager;

class UniviePureCacheWarmer implements CacheWarmupInterface
{
public function warmup(): void
{
// Debugging: Write to a log file
file_put_contents('/var/www/html/typo3temp/cache_warmup.log', "Cache warmup started\n", FILE_APPEND);

$logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
$logger->info('Cache warmer started.');

// Instantiate ClassificationScheme
$classificationScheme = GeneralUtility::makeInstance(ClassificationScheme::class);

// Preloading different caches
$config = ['items' => []];

$classificationScheme->getOrganisations($config);
$classificationScheme->getPersons($config);
$classificationScheme->getProjects($config);
$classificationScheme->getTypesFromPublications($config);

// Debugging: Confirm cache warmup completion
file_put_contents('/var/www/html/typo3temp/cache_warmup.log', "Cache warmup completed\n", FILE_APPEND);
$logger->info('Custom cache "univie_pure" has been warmed up.');
}
}