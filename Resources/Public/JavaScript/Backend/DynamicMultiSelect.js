/**
 * Dynamic MultiSelect for TYPO3 Backend FormEngine
 * Non-module version for immediate execution
 */
(function() {
    'use strict';

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
        // Find all multiselect filter textfields
        const filterFields = document.querySelectorAll('.t3js-formengine-multiselect-filter-textfield');
        
        filterFields.forEach((field, index) => {
            // Skip if already initialized
            if (field.dataset.dynamicSelectInit) {
                return;
            }
            
            const wrapper = field.closest('.form-multigroup-wrap');
            if (!wrapper) {
                return;
            }
            
            // Try different selectors for the available items select
            const availableSelect = wrapper.querySelector('.t3js-formengine-select-itemstoselect') ||
                                  wrapper.querySelector('select[size]:not([data-formengine-input-name*="_list"])');
            
            if (!availableSelect) {
                return;
            }
            
            // Look for field name in the broader form context
            const formSection = wrapper.closest('.form-section');
            const formPalette = wrapper.closest('.form-palette-field');
            const flexformContainer = wrapper.closest('[class*="flexform"]');
            
            // Find the actual form field name from the data attribute on select or nearby hidden input
            const selectedSelect = wrapper.querySelector('.t3js-formengine-select-selecteditems');
            const dataFieldName = selectedSelect ? selectedSelect.getAttribute('data-formengine-input-name') : '';
            
            // Look for the actual field name in various places
            let fieldIdentifier = dataFieldName || '';
            
            // Method 1: Check data-formengine-input-name on various elements
            const elementsWithDataAttr = wrapper.querySelectorAll('[data-formengine-input-name]');
            elementsWithDataAttr.forEach(elem => {
                const name = elem.getAttribute('data-formengine-input-name') || '';
                if (name) {
                    fieldIdentifier += ' ' + name;
                }
            });
            
            // Method 2: Look for hidden inputs that contain the actual field name
            const allHiddenInputs = wrapper.querySelectorAll('input[type="hidden"]');
            allHiddenInputs.forEach(input => {
                const inputName = input.name || '';
                const inputId = input.id || '';
                // Look for Pure extension specific field patterns
                if (inputName.includes('selector') || inputId.includes('selector') || 
                    inputName.includes('Person') || inputName.includes('Project') || 
                    inputName.includes('Organisation') ||
                    inputName.includes('[settings][selector')) {
                    fieldIdentifier += ' ' + inputName + ' ' + inputId;
                }
            });
            
            // Method 3: Check the name attribute of the select elements themselves
            const availableSelectName = availableSelect.getAttribute('name') || '';
            const selectedSelectName = selectedSelect ? selectedSelect.getAttribute('name') || '' : '';
            if (availableSelectName || selectedSelectName) {
                fieldIdentifier += ' ' + availableSelectName + ' ' + selectedSelectName;
            }
            
            // Also check the label text
            const labelElement = wrapper.closest('.form-group')?.querySelector('label.t3js-formengine-label');
            const labelText = labelElement ? labelElement.textContent.trim() : '';
            
            
            let endpoint = null;
            
            // Convert field identifier and label to lowercase for case-insensitive matching
            const fieldIdentifierLower = fieldIdentifier.toLowerCase();
            const labelTextLower = labelText.toLowerCase();
            
            
            // Check field identifier or use label text as fallback
            // Support both English and German labels
            if (fieldIdentifierLower.includes('selectororganisations') || 
                fieldIdentifierLower.includes('organisation') ||
                labelTextLower.includes('organisation')) {
                endpoint = 'organizations';
            } else if (fieldIdentifierLower.includes('selectorpersonswithorganization')) {
                endpoint = 'personsWithOrg';
            } else if (fieldIdentifierLower.includes('selectorpersons') || 
                       fieldIdentifierLower.includes('person') ||
                       labelTextLower.includes('person') || 
                       labelTextLower.includes('personen')) {
                endpoint = 'persons';
            } else if (fieldIdentifierLower.includes('selectorprojects') || 
                       fieldIdentifierLower.includes('project') ||
                       labelTextLower.includes('project') || 
                       labelTextLower.includes('projekt')) {
                endpoint = 'projects';
            }
            
            // Additional check: Make sure this is a Pure extension field
            // by verifying the field identifier contains settings.selector
            const isPureField = fieldIdentifierLower.includes('settings') && 
                               (fieldIdentifierLower.includes('selectororganisations') || 
                                fieldIdentifierLower.includes('selectorpersons') || 
                                fieldIdentifierLower.includes('selectorprojects'));
            
            if (!endpoint || !isPureField) {
                return;
            }
            
            // Mark as initialized
            field.dataset.dynamicSelectInit = 'true';
            
            // Store original options (first 8)
            const originalOptions = Array.from(availableSelect.options).slice(0, 8);
            
            // Setup search handler
            this.setupSearchHandler(field, availableSelect, endpoint, originalOptions);
        });
    };
    
    DynamicMultiSelect.prototype.setupSearchHandler = function(field, selectElement, endpoint, originalOptions) {
        let searchTimeout;
        
        // Create search handler
        const handleSearch = (event) => {
            const searchTerm = event.target.value.trim();
            
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
        
    };
    
    DynamicMultiSelect.prototype.performSearch = function(searchTerm, selectElement, endpoint) {
        const url = this.ajaxUrls[endpoint];
        
        if (!url) {
            console.error('[Pure AJAX] No URL for endpoint:', endpoint);
            return;
        }
        
        
        // Get CSRF token - try multiple methods
        let csrfToken = '';
        
        // Method 1: From ModuleData if available
        if (typeof TYPO3 !== 'undefined' && TYPO3.settings && TYPO3.settings.ajaxUrls && TYPO3.settings.ajaxUrls._token) {
            csrfToken = TYPO3.settings.ajaxUrls._token;
        }
        
        // Method 2: From hidden form field
        if (!csrfToken) {
            const tokenField = document.querySelector('input[name="__trustedProperties"]');
            if (tokenField) {
                csrfToken = tokenField.value;
            }
        }
        
        // Method 3: From URL parameter in existing AJAX URLs
        if (!csrfToken && url.includes('token=')) {
            const tokenMatch = url.match(/token=([^&]+)/);
            if (tokenMatch) {
                csrfToken = tokenMatch[1];
            }
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('searchTerm', searchTerm);
        
        // Build full URL - the URL from TYPO3.settings should already include the token
        let fullUrl = url;
        
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
            
            if (!response.ok) {
                // Try to get error details
                return response.text().then(text => {
                    throw new Error('HTTP ' + response.status + ': ' + text.substring(0, 200));
                });
            }
            return response.json();
        })
        .then(data => {
            // Check if we got an error response
            if (data.error) {
                console.error('[Pure AJAX] API Error:', data.error);
                return;
            }
            
            if (data.results) {
                this.updateOptions(selectElement, data.results);
            } else {
                this.updateOptions(selectElement, []);
            }
        })
        .catch(error => {
            console.error('[Pure AJAX] Search error:', error);
            // Show empty results on error
            this.updateOptions(selectElement, []);
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
        
    };
    
    // Initialize when TYPO3 is ready
    function initWhenReady() {
        if (typeof TYPO3 !== 'undefined' && TYPO3.settings && TYPO3.settings.ajaxUrls) {
            new DynamicMultiSelect();
        } else {
            setTimeout(initWhenReady, 100);
        }
    }
    
    // Start initialization
    initWhenReady();
})();
