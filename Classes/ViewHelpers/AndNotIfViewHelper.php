<?php

namespace Univie\UniviePure\ViewHelpers;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class AndNotIfViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('condition', 'mixed', 'Objects to auto-complete', true);
        $this->registerArgument('andnot', 'int', '0 or 1', false, 0);
    }

    /**
     * renders <f:then> child if $condition and not $andnot is true, otherwise renders <f:else> child.
     *
     * @return bool true if condition is met, false otherwise
     */
    public function render(): bool
    {
        $condition = $this->arguments['condition'];
        $andnot = $this->arguments['andnot'];

        return ($condition > 0 && $andnot != 1);
    }
}