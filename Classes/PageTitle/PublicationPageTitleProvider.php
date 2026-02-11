<?php

declare(strict_types=1);

namespace Univie\UniviePure\PageTitle;

use TYPO3\CMS\Core\PageTitle\AbstractPageTitleProvider;

/**
 * Page title provider for publication detail pages.
 *
 * This provider sets the HTML page title dynamically based on
 * the publication title from the Pure API.
 */
final class PublicationPageTitleProvider extends AbstractPageTitleProvider
{
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
