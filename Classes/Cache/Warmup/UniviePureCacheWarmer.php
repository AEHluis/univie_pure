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

        // For CLI output
        echo PHP_EOL . '=== Warming up T3LUH FIS caches ===' . PHP_EOL;
        flush();

        // Only warm up our specific cache
        if ($event->hasGroup('all') || $event->hasGroup('univie_pure')) {
            echo 'T3LUH FIS Cache warmup started.' . PHP_EOL;
            $logger->info('T3LUH FIS Cache warmup started.');
            flush();

            // Process each supported language
            foreach ($this->supportedLanguages as $language) {

                echo PHP_EOL . "Processing language: {$language}" . PHP_EOL;
                $logger->info("Processing language: {$language}");
                flush();

                // Set the language for the current operation
                $this->setTemporaryLanguage($language);

                // Preloading different caches
                $config = ['items' => []];

                echo "Custom cache \"T3LUH FIS\" ... doing organisations for {$language}." . PHP_EOL;
                flush();
                $logger->info("Custom cache \"T3LUH FIS\" ... doing organisations for {$language}.");
                $this->classificationScheme->getOrganisations($config);

                echo "Custom cache \"T3LUH FIS\" ... doing projects for {$language}." . PHP_EOL;
                flush();
                $logger->info("Custom cache \"T3LUH FIS\" ... doing projects for {$language}.");
                $this->classificationScheme->getProjects($config);
            }

            $config = ['items' => []];

            echo 'Custom cache "T3LUH FIS" ... doing persons.' . PHP_EOL;
            flush();
            $logger->info('Custom cache "T3LUH FIS" ... doing persons.');
            $this->classificationScheme->getPersons($config);

            echo 'Custom cache "T3LUH FIS" ... doing classification-schemes.' . PHP_EOL;
            flush();
            $logger->info('Custom cache "T3LUH FIS" ... doing classification-schemes.');
            $this->classificationScheme->getTypesFromPublications($config);

            echo 'Custom cache "T3LUH FIS" has been warmed up.' . PHP_EOL;
            flush();
            $logger->info('Custom cache "T3LUH FIS" has been warmed up.');
        }
    }

    private function setTemporaryLanguage(string $language): void
    {
        $this->classificationScheme->setLocale($language);
    }
}
