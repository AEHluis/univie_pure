<?php
namespace Univie\UniviePure\ViewHelpers;

/*
 * This file is part of the "T3LUH FIS" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

class AndNotIfViewHelper extends \TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper
{
    
    
    
    
    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('condition', QueryResultInterface::class, 'Objects to auto-complete', true);
        $this->registerArgument('andnot', 'int', '0 or 1', 0);
    }
    
    
    

    
    /**
     * renders <f:then> child if $condition and not $andnot is true, otherwise renders <f:else> child.
     *
     * @return string the rendered string
     */
    public function render() {
        $condition= $this->arguments['condition'];
        $andnot = $this->arguments['andnot'];
        if ($condition > 0 && $andnot != 1 ){
            return true;
        }else {
            return false;
        }
    }
}
