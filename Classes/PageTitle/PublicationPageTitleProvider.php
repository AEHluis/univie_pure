<?php

declare(strict_types=1);

namespace Univie\UniviePure\PageTitle;

use TYPO3\CMS\Core\PageTitle\AbstractPageTitleProvider;

final class PublicationPageTitleProvider extends AbstractPageTitleProvider
{
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
