<?php

namespace Univie\UniviePure\Cache\Warmup;

use Univie\UniviePure\Utility\ClassificationScheme;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Cache\Event\CacheWarmupEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Univie\UniviePure\Utility\LanguageUtility;


class UniviePureCacheWarmer
{
    private array $supportedLanguages = ['de_DE', 'en_GB'];
    
    public static function getIdentifier(): string
    {
        return 'univie-pure-cache-warmer';
    }
    public function __construct(
        private readonly ClassificationScheme $classificationScheme,
        private readonly FrontendInterface $cache,
        private readonly LogManager $logManager
    ) {}

    public function __invoke(CacheWarmupEvent $event): void
    {
        // Your existing warmup code
        if ($event->hasGroup('all') || $event->hasGroup('univie_pure')) {
            $this->warmup($event);
        }
    }

    public function warmup(CacheWarmupEvent $event): void
    {
        $logger = $this->logManager->getLogger(__CLASS__);

        // Only warm up our specific cache
        if ($event->hasGroup('all') || $event->hasGroup('univie_pure')) {
            $logger->info('T3LUH FIS Cache warmup started.');

            try {
                // Process each supported language
                foreach ($this->supportedLanguages as $language) {
                    $logger->info("Processing language: {$language}");
                    $this->setTemporaryLanguage($language);

                    // Preloading different caches
                    $config = ['items' => []];

                    $logger->info("Custom cache \"T3LUH FIS\" ... doing organisations for {$language}.");
                    $this->classificationScheme->getOrganisations($config);

                    $logger->info("Custom cache \"T3LUH FIS\" ... doing projects for {$language}.");
                    $this->classificationScheme->getProjects($config);
                }

                $config = ['items' => []];

                $logger->info('Custom cache "T3LUH FIS" ... doing persons.');
                $this->classificationScheme->getPersons($config);

                $logger->info('Custom cache "T3LUH FIS" ... doing publication types.');
                $this->classificationScheme->getTypesFromPublications($config);

                $logger->info('Custom cache "T3LUH FIS" has been warmed up.');
            } catch (\Throwable $e) {
                $logger->warning('T3LUH FIS cache warmup skipped because API preload failed.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function setTemporaryLanguage(string $language): void
    {
        $this->classificationScheme->setLocale($language);
    }
}
