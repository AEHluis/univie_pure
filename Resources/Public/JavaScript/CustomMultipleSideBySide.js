define([], function() {
    // Change CSS only for MultipleSideBySide-Elements if "Projects" are selected...
    if(document.documentElement.innerHTML.indexOf('[pi_flexform][data][Common][lDEF][settings.selectorProjects][vDEF]') > -1){
        var magicelement = document.querySelectorAll('.form-multigroup-item')
        magicelement.forEach( el => {
            el.style.display = "table";
            el.style.width = "100%";
        });
    }
});