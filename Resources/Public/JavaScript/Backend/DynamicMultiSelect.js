/**
 * Dynamic MultiSelect for TYPO3 Backend FormEngine
 * Non-module version for immediate execution
 */
(function() {
    'use strict';
    
    console.log('[DynamicMultiSelect] Script loaded');

    function DynamicMultiSelect() {
        // Try to get AJAX URLs from TYPO3 settings
        if (TYPO3.settings && TYPO3.settings.ajaxUrls) {
            this.ajaxUrls = {
                organizations: TYPO3.settings.ajaxUrls.univie_pure_search_organizations,
                persons: TYPO3.settings.ajaxUrls.univie_pure_search_persons,
                personsWithOrg: TYPO3.settings.ajaxUrls.univie_pure_search_persons_with_org,
                projects: TYPO3.settings.ajaxUrls.univie_pure_search_projects
            };
        } else {
            // Fallback to direct URLs
            this.ajaxUrls = {
                organizations: '/typo3/ajax/univie_pure/search/organizations',
                persons: '/typo3/ajax/univie_pure/search/persons',
                personsWithOrg: '/typo3/ajax/univie_pure/search/persons-with-org',
                projects: '/typo3/ajax/univie_pure/search/projects'
            };
        }
        
        console.log('[DynamicMultiSelect] AJAX URLs:', this.ajaxUrls);
        console.log('[DynamicMultiSelect] Available AJAX routes:', TYPO3.settings ? Object.keys(TYPO3.settings.ajaxUrls || {}) : 'none');
        
        this.init();
    }
    
    DynamicMultiSelect.prototype.init = function() {
        // Try immediate init and on DOM ready
        this.setupFields();
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.setupFields();
            });
        }
        
        // Also listen for FormEngine ready
        document.addEventListener('typo3:formengine:fieldChanged', () => {
            this.setupFields();
        });
    };
    
    DynamicMultiSelect.prototype.setupFields = function() {
        console.log('[DynamicMultiSelect] Setting up fields...');
        
        // Find all multiselect filter textfields
        const filterFields = document.querySelectorAll('.t3js-formengine-multiselect-filter-textfield');
        console.log('[DynamicMultiSelect] Found filter fields:', filterFields.length);
        
        filterFields.forEach((field, index) => {
            // Skip if already initialized
            if (field.dataset.dynamicSelectInit) {
                return;
            }
            
            const wrapper = field.closest('.form-multigroup-wrap');
            if (!wrapper) {
                console.log('[DynamicMultiSelect] No wrapper found for field', index);
                return;
            }
            
            // Try different selectors for the available items select
            const availableSelect = wrapper.querySelector('.t3js-formengine-select-itemstoselect') ||
                                  wrapper.querySelector('select[size]:not([data-formengine-input-name*="_list"])');
            
            if (!availableSelect) {
                console.log('[DynamicMultiSelect] No available select found for field', index);
                return;
            }
            
            // Look for field name in the broader form context
            const formSection = wrapper.closest('.form-section');
            const formPalette = wrapper.closest('.form-palette-field');
            const flexformContainer = wrapper.closest('[class*="flexform"]');
            
            // Find the actual form field name from the data attribute on select or nearby hidden input
            const selectedSelect = wrapper.querySelector('.t3js-formengine-select-selecteditems');
            const dataFieldName = selectedSelect ? selectedSelect.getAttribute('data-formengine-input-name') : '';
            
            // Look for hidden inputs that contain the actual field name
            const allHiddenInputs = wrapper.querySelectorAll('input[type="hidden"]');
            let fieldIdentifier = dataFieldName || '';
            
            // Search through hidden inputs for field names
            allHiddenInputs.forEach(input => {
                const inputName = input.name || '';
                const inputId = input.id || '';
                if (inputName.includes('selector') || inputId.includes('selector')) {
                    fieldIdentifier += ' ' + inputName + ' ' + inputId;
                    console.log('[DynamicMultiSelect] Found hidden input:', inputName, inputId);
                }
            });
            
            // Also check the label text
            const labelElement = wrapper.closest('.form-group')?.querySelector('label.t3js-formengine-label');
            const labelText = labelElement ? labelElement.textContent.trim() : '';
            
            console.log('[DynamicMultiSelect] Field context:', {
                fieldIdentifier: fieldIdentifier,
                labelText: labelText,
                selectId: availableSelect.id,
                formSectionClass: formSection?.className
            });
            
            let endpoint = null;
            
            // Check field identifier or use label text as fallback
            if (fieldIdentifier.includes('selectorOrganisations') || labelText.includes('organisation')) {
                endpoint = 'organizations';
            } else if (fieldIdentifier.includes('selectorPersonsWithOrganization')) {
                endpoint = 'personsWithOrg';
            } else if (fieldIdentifier.includes('selectorPersons') || labelText.includes('person')) {
                endpoint = 'persons';
            } else if (fieldIdentifier.includes('selectorProjects') || labelText.includes('project')) {
                endpoint = 'projects';
            }
            
            console.log('[DynamicMultiSelect] Selected endpoint:', endpoint);
            
            if (!endpoint) {
                return;
            }
            
            // Mark as initialized
            field.dataset.dynamicSelectInit = 'true';
            
            // Store original options (first 5)
            const originalOptions = Array.from(availableSelect.options).slice(0, 5);
            
            // Setup search handler
            this.setupSearchHandler(field, availableSelect, endpoint, originalOptions);
        });
    };
    
    DynamicMultiSelect.prototype.setupSearchHandler = function(field, selectElement, endpoint, originalOptions) {
        let searchTimeout;
        
        // Create search handler
        const handleSearch = (event) => {
            const searchTerm = event.target.value.trim();
            console.log('[DynamicMultiSelect] Search term:', searchTerm);
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 3) {
                // Restore original options
                this.updateOptions(selectElement, originalOptions.map(opt => ({
                    value: opt.value,
                    label: opt.textContent
                })));
                return;
            }
            
            // Debounce search
            searchTimeout = setTimeout(() => {
                this.performSearch(searchTerm, selectElement, endpoint);
            }, 300);
        };
        
        // Remove existing handlers and add new one
        field.removeEventListener('input', field._dynamicSearchHandler);
        field._dynamicSearchHandler = handleSearch;
        field.addEventListener('input', handleSearch);
        
        console.log('[DynamicMultiSelect] Search handler attached to field');
    };
    
    DynamicMultiSelect.prototype.performSearch = function(searchTerm, selectElement, endpoint) {
        const url = this.ajaxUrls[endpoint];
        
        if (!url) {
            console.error('[DynamicMultiSelect] No URL for endpoint:', endpoint);
            return;
        }
        
        console.log('[DynamicMultiSelect] Performing search:', url, searchTerm);
        
        // Get CSRF token from page
        let csrfToken = '';
        const tokenField = document.querySelector('input[name="__trustedProperties"]');
        if (tokenField) {
            csrfToken = tokenField.value;
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('searchTerm', searchTerm);
        
        // Build full URL with token if needed
        let fullUrl = url;
        if (csrfToken && !url.includes('token=')) {
            fullUrl = url + (url.includes('?') ? '&' : '?') + 'token=' + csrfToken;
        }
        
        // Perform AJAX request
        fetch(fullUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(response => {
            console.log('[DynamicMultiSelect] Response status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('[DynamicMultiSelect] Search results:', data);
            if (data.results) {
                this.updateOptions(selectElement, data.results);
            }
        })
        .catch(error => {
            console.error('[DynamicMultiSelect] Search error:', error);
        });
    };
    
    DynamicMultiSelect.prototype.updateOptions = function(selectElement, results) {
        // Get currently selected values
        const selectedBox = selectElement.closest('.form-multigroup-wrap').querySelector('.t3js-formengine-select-selecteditems');
        const selectedValues = [];
        
        if (selectedBox) {
            Array.from(selectedBox.options).forEach(opt => {
                selectedValues.push(opt.value);
            });
        }
        
        // Clear and update options
        selectElement.innerHTML = '';
        
        results.forEach(item => {
            // Skip if already selected
            if (selectedValues.includes(item.value)) {
                return;
            }
            
            const option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label;
            selectElement.appendChild(option);
        });
        
        console.log('[DynamicMultiSelect] Updated options:', results.length);
    };
    
    // Initialize when TYPO3 is ready
    function initWhenReady() {
        if (typeof TYPO3 !== 'undefined' && TYPO3.settings && TYPO3.settings.ajaxUrls) {
            console.log('[DynamicMultiSelect] TYPO3 ready, initializing...');
            new DynamicMultiSelect();
        } else {
            console.log('[DynamicMultiSelect] Waiting for TYPO3...');
            setTimeout(initWhenReady, 100);
        }
    }
    
    // Start initialization
    initWhenReady();
})();