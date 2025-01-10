<?php
namespace Univie\UniviePure\ViewHelpers;



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
