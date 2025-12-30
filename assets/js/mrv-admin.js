/**
 * Shopvision - Admin JavaScript
 * Modular architecture with improved live preview
 */

/**
 * Product Picker Module
 */
(function() {
    'use strict';

    const searchInput = document.getElementById('mrv-product-search');
    const searchResults = document.getElementById('mrv-search-results');
    const selectedList = document.getElementById('mrv-selected-list');
    const selectedCount = document.getElementById('mrv-selected-count');
    const emptyState = document.getElementById('mrv-empty-state');

    if (!searchInput || !searchResults || !selectedList) return;

    let searchTimeout = null;
    let selectedIds = new Set();

    // Initialize selected IDs from existing items
    selectedList.querySelectorAll('.mrv-selected-item').forEach(item => {
        selectedIds.add(item.dataset.id);
    });

    // Search products via AJAX
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        clearTimeout(searchTimeout);

        if (query.length < 2) {
            searchResults.classList.remove('active');
            searchResults.innerHTML = '';
            return;
        }

        searchResults.innerHTML = '<div class="mrv-search-loading">' + (mrvAdminI18n.searching || 'Searching...') + '</div>';
        searchResults.classList.add('active');

        searchTimeout = setTimeout(() => {
            fetchProducts(query);
        }, 300);
    });

    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.mrv-product-picker-search')) {
            searchResults.classList.remove('active');
        }
    });

    // Fetch products via our own AJAX handler
    function fetchProducts(query) {
        const formData = new FormData();
        formData.append('action', 'mrv_search_products');
        formData.append('term', query);
        formData.append('security', mrvAdmin.nonce);

        fetch(mrvAdmin.ajaxUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            renderSearchResults(data);
        })
        .catch(() => {
            searchResults.innerHTML = '<div class="mrv-search-no-results">' + (mrvAdminI18n.error || 'Something went wrong. Please try again.') + '</div>';
        });
    }

    // Render search results
    function renderSearchResults(products) {
        if (!products || Object.keys(products).length === 0) {
            searchResults.innerHTML = '<div class="mrv-search-no-results">' + (mrvAdminI18n.noResults || 'No products found') + '</div>';
            return;
        }

        let html = '';
        for (const [id, name] of Object.entries(products)) {
            const isSelected = selectedIds.has(id);
            const disabledClass = isSelected ? 'disabled' : '';
            const disabledText = isSelected ? ' (' + (mrvAdminI18n.alreadySelected || '(already selected)') + ')' : '';

            html += `
                <div class="mrv-search-result-item ${disabledClass}" data-id="${id}" data-name="${escapeHtml(name)}">
                    <div class="mrv-search-result-info">
                        <span class="mrv-search-result-name">${escapeHtml(name)}${disabledText}</span>
                    </div>
                </div>
            `;
        }

        searchResults.innerHTML = html;

        // Add click handlers
        searchResults.querySelectorAll('.mrv-search-result-item:not(.disabled)').forEach(item => {
            item.addEventListener('click', () => addProduct(item.dataset.id, item.dataset.name));
        });
    }

    // Add product to selection
    function addProduct(id, name) {
        if (selectedIds.has(id)) return;

        selectedIds.add(id);

        // Remove empty state
        if (emptyState) {
            emptyState.remove();
        }

        const itemHtml = `
            <div class="mrv-selected-item" data-id="${id}">
                <div class="mrv-product-info">
                    <span class="mrv-product-name">${escapeHtml(name)}</span>
                </div>
                <button type="button" class="mrv-remove-product" data-id="${id}">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <input type="hidden" name="mrv_enabled_products[]" value="${id}">
            </div>
        `;

        selectedList.insertAdjacentHTML('beforeend', itemHtml);

        // Add remove handler
        const newItem = selectedList.querySelector(`.mrv-selected-item[data-id="${id}"]`);
        newItem.querySelector('.mrv-remove-product').addEventListener('click', () => removeProduct(id));

        updateCount();

        // Clear search
        searchInput.value = '';
        searchResults.classList.remove('active');
        searchResults.innerHTML = '';
    }

    // Remove product from selection
    function removeProduct(id) {
        selectedIds.delete(id);

        const item = selectedList.querySelector(`.mrv-selected-item[data-id="${id}"]`);
        if (item) {
            item.remove();
        }

        updateCount();

        // Show empty state if no products
        if (selectedIds.size === 0) {
            selectedList.innerHTML = `
                <div class="mrv-empty-state" id="mrv-empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    <p>${mrvAdminI18n.noProductsSelected || 'No products selected'}</p>
                    <span>${mrvAdminI18n.useSearchBar || 'Use the search bar above to add products.'}</span>
                </div>
            `;
        }
    }

    // Update selected count
    function updateCount() {
        if (selectedCount) {
            selectedCount.textContent = selectedIds.size;
        }
    }

    // Initialize remove handlers for existing items
    selectedList.querySelectorAll('.mrv-remove-product').forEach(btn => {
        btn.addEventListener('click', () => removeProduct(btn.dataset.id));
    });

    // Bulk actions
    const selectAllBtn = document.getElementById('mrv-select-all-products');
    const deselectAllBtn = document.getElementById('mrv-deselect-all-products');

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<span class="mrv-spinner"></span> Laden...';

            // Fetch all product IDs via AJAX
            const formData = new FormData();
            formData.append('action', 'mrv_get_all_products');
            formData.append('security', mrvAdmin.nonce);

            fetch(mrvAdmin.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.ids) {
                    // Clear current selection
                    selectedIds.clear();
                    selectedList.innerHTML = '';

                    const productIds = data.data.ids;
                    const total = data.data.total;

                    // Add all product IDs (without loading full details for performance)
                    for (const id of productIds) {
                        selectedIds.add(id.toString());
                    }

                    // Show summary instead of individual items for large catalogs
                    if (total > 100) {
                        selectedList.innerHTML = `
                            <div class="mrv-bulk-summary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;color:#22c55e;">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                <p><strong>${total} producten</strong> geselecteerd</p>
                                <span>Klik op "Wijzigingen opslaan" om te bevestigen.</span>
                            </div>
                        `;

                        // Add hidden inputs for all IDs
                        for (const id of productIds) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'mrv_enabled_products[]';
                            input.value = id;
                            selectedList.appendChild(input);
                        }
                    } else {
                        // For smaller catalogs, show individual items (need to fetch details)
                        selectedList.innerHTML = `
                            <div class="mrv-bulk-summary">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;color:#22c55e;">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                <p><strong>${total} producten</strong> geselecteerd</p>
                                <span>Klik op "Wijzigingen opslaan" om te bevestigen.</span>
                            </div>
                        `;

                        for (const id of productIds) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'mrv_enabled_products[]';
                            input.value = id;
                            selectedList.appendChild(input);
                        }
                    }

                    updateCount();
                }
            })
            .catch(err => {
                console.error('Error fetching products:', err);
                alert(mrvAdminI18n.error || 'Something went wrong. Please try again.');
            })
            .finally(() => {
                // Reset button
                selectAllBtn.disabled = false;
                selectAllBtn.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;">
                        <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    Alle producten selecteren
                `;
            });
        });
    }

    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function() {
            // Clear all selections
            selectedIds.clear();
            selectedList.innerHTML = `
                <div class="mrv-empty-state" id="mrv-empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                    <p>${mrvAdminI18n.noProductsSelected || 'No products selected'}</p>
                    <span>${mrvAdminI18n.useSearchBar || 'Use the search bar above to add products.'}</span>
                </div>
            `;
            updateCount();
        });
    }

    // Escape HTML helper
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();

/**
 * Styling Tab - Live Preview Module
 */
(function($) {
    'use strict';

    // Wait for DOM ready
    $(function() {
        // Elements - updated IDs
        const $accentColorPicker = $('#mrv_accent_color');
        const $textColorPicker = $('#mrv_button_text_color');
        const $hoverBgColorPicker = $('#mrv_hover_bg_color');
        const $hoverTextColorPicker = $('#mrv_hover_text_color');
        const $styleInputs = $('input[name="mrv_button_style"]');
        const $radiusInputs = $('input[name="mrv_button_radius"]');
        const $iconToggle = $('#mrv_icon_enabled');
        const $iconInputs = $('input[name="mrv_icon_type"]');
        const $previewBtn = $('#mrv-live-preview-btn');
        const $previewIcon = $('#mrv-preview-icon');
        const $iconGridWrap = $('.mrv-icon-grid-wrap');

        // Store current colors for hover effect
        let currentColors = {
            normal: {},
            hover: {}
        };

        // Exit if not on styling page
        if (!$accentColorPicker.length || !$previewBtn.length) return;

        // Icon SVG paths - from mrvAdmin.icons if available, else defaults
        const icons = (typeof mrvAdmin !== 'undefined' && mrvAdmin.icons) ? mrvAdmin.icons : {
            camera: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
            eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
            home: '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            sparkle: '<path d="M12 3l1.5 6.5L20 12l-6.5 2.5L12 21l-1.5-6.5L4 12l6.5-2.5z"/>',
            wand: '<path d="M15 4V2"/><path d="M15 16v-2"/><path d="M8 9h2"/><path d="M20 9h2"/><path d="M17.8 11.8L19 13"/><path d="M15 9h.01"/><path d="M17.8 6.2L19 5"/><path d="m3 21 9-9"/><path d="M12.2 6.2L11 5"/>'
        };

        // Initialize WordPress color pickers with debounced update
        let colorUpdateTimeout;

        $accentColorPicker.wpColorPicker({
            change: function(event, ui) {
                clearTimeout(colorUpdateTimeout);
                colorUpdateTimeout = setTimeout(updatePreview, 50);
            },
            clear: function() {
                updatePreview();
            }
        });

        $textColorPicker.wpColorPicker({
            change: function(event, ui) {
                clearTimeout(colorUpdateTimeout);
                colorUpdateTimeout = setTimeout(updatePreview, 50);
            },
            clear: function() {
                updatePreview();
            }
        });

        $hoverBgColorPicker.wpColorPicker({
            change: function(event, ui) {
                clearTimeout(colorUpdateTimeout);
                colorUpdateTimeout = setTimeout(updatePreview, 50);
            },
            clear: function() {
                updatePreview();
            }
        });

        $hoverTextColorPicker.wpColorPicker({
            change: function(event, ui) {
                clearTimeout(colorUpdateTimeout);
                colorUpdateTimeout = setTimeout(updatePreview, 50);
            },
            clear: function() {
                updatePreview();
            }
        });

        // Close color pickers when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.wp-picker-container').length) {
                $('.wp-picker-container').find('.wp-picker-holder').hide();
                $('.wp-picker-container').removeClass('wp-picker-active');
            }
        });

        // Style selector change - update card states
        $styleInputs.on('change', function() {
            updateCardStates('.mrv-button-style-card', 'mrv_button_style');
            updatePreview();
        });

        // Radius selector change - update card states
        $radiusInputs.on('change', function() {
            updateCardStates('.mrv-radius-card', 'mrv_button_radius');
            updatePreview();
        });

        // Icon toggle change
        $iconToggle.on('change', function() {
            const enabled = $(this).is(':checked');

            // Show/hide icon grid
            if (enabled) {
                $iconGridWrap.slideDown(200);
            } else {
                $iconGridWrap.slideUp(200);
            }

            updatePreview();
        });

        // Icon type change - update box states
        $iconInputs.on('change', function() {
            updateCardStates('.mrv-icon-box', 'mrv_icon_type');
            updatePreview();
        });

        // Update card selected states
        function updateCardStates(selector, inputName) {
            $(selector).each(function() {
                const $card = $(this);
                const $input = $card.find('input[name="' + inputName + '"]');
                if ($input.is(':checked')) {
                    $card.addClass('is-selected');
                } else {
                    $card.removeClass('is-selected');
                }
            });
        }

        // Update preview function
        function updatePreview() {
            // Get current values
            const accentColor = $accentColorPicker.val() || '#2563eb';
            const textColor = $textColorPicker.val() || accentColor; // Fallback to accent
            const hoverBgColor = $hoverBgColorPicker.val() || accentColor; // Fallback to accent
            const hoverTextColor = $hoverTextColorPicker.val() || '#ffffff'; // Fallback to white
            const style = $styleInputs.filter(':checked').val() || 'outline';
            const radius = $radiusInputs.filter(':checked').val() || 'rounded';
            const iconEnabled = $iconToggle.is(':checked');
            const iconType = $iconInputs.filter(':checked').val() || 'camera';

            // Update button style classes
            $previewBtn
                .removeClass('style-outline style-filled style-ghost')
                .addClass('style-' + style);

            // Update button radius classes
            $previewBtn
                .removeClass('radius-sharp radius-rounded radius-pill')
                .addClass('radius-' + radius);

            // Update icon visibility and type
            if (iconEnabled && icons[iconType]) {
                const svgHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + icons[iconType] + '</svg>';
                $previewIcon.html(svgHtml).show();
            } else {
                $previewIcon.hide();
            }

            // Calculate normal colors based on style
            if (style === 'filled') {
                currentColors.normal = {
                    background: accentColor,
                    border: accentColor,
                    text: '#fff'
                };
            } else if (style === 'ghost') {
                currentColors.normal = {
                    background: 'transparent',
                    border: 'transparent',
                    text: textColor
                };
            } else {
                // Outline style
                currentColors.normal = {
                    background: 'transparent',
                    border: accentColor,
                    text: textColor
                };
            }

            // Hover colors (same for all styles)
            currentColors.hover = {
                background: hoverBgColor,
                border: hoverBgColor,
                text: hoverTextColor
            };

            // Apply normal colors
            applyColors(currentColors.normal);
        }

        // Apply colors to preview button
        function applyColors(colors) {
            $previewBtn.css({
                'background-color': colors.background,
                'border-color': colors.border,
                'color': colors.text
            });
            $previewBtn.find('.mrv-preview-text, .mrv-preview-icon').css('color', colors.text);
        }

        // Hover event handlers for live preview
        $previewBtn.on('mouseenter', function() {
            applyColors(currentColors.hover);
        });

        $previewBtn.on('mouseleave', function() {
            applyColors(currentColors.normal);
        });

        // Initialize: update all card states
        updateCardStates('.mrv-button-style-card', 'mrv_button_style');
        updateCardStates('.mrv-radius-card', 'mrv_button_radius');
        updateCardStates('.mrv-icon-box', 'mrv_icon_type');

        // Initial preview update
        updatePreview();
    });
})(jQuery);

/**
 * General Tab - Order Button Toggle
 */
(function($) {
    'use strict';

    $(function() {
        const $orderToggle = $('#mrv_order_button_enabled');
        const $ctaFields = $('.mrv-cta-text-fields');

        if (!$orderToggle.length || !$ctaFields.length) return;

        $orderToggle.on('change', function() {
            if ($(this).is(':checked')) {
                $ctaFields.slideDown(200);
            } else {
                $ctaFields.slideUp(200);
            }
        });
    });
})(jQuery);

/**
 * General Tab - Examples Gallery Toggle & Media Uploader
 */
(function($) {
    'use strict';

    $(function() {
        const $examplesToggle = $('#mrv_examples_enabled');
        const $examplesFields = $('.mrv-examples-fields');
        const $textToggle = $('#mrv_examples_text_enabled');
        const $textFields = $('.mrv-examples-text-fields');

        if (!$examplesToggle.length) return;

        // Toggle examples fields visibility
        $examplesToggle.on('change', function() {
            if ($(this).is(':checked')) {
                $examplesFields.slideDown(200);
            } else {
                $examplesFields.slideUp(200);
            }
        });

        // Toggle text fields visibility
        $textToggle.on('change', function() {
            if ($(this).is(':checked')) {
                $textFields.slideDown(200);
            } else {
                $textFields.slideUp(200);
            }
        });

        // Media uploader for example images
        let mediaFrame;

        $('.mrv-example-preview').on('click', function(e) {
            // Don't open media frame if clicking remove button
            if ($(e.target).closest('.mrv-example-remove').length) return;

            const $preview = $(this);
            const $uploader = $preview.closest('.mrv-example-uploader');
            const index = $uploader.data('index');
            const $input = $('#mrv_example_image_' + index);

            // Create media frame if doesn't exist
            if (!mediaFrame) {
                mediaFrame = wp.media({
                    title: 'Selecteer voorbeeldafbeelding',
                    button: { text: 'Gebruik deze afbeelding' },
                    multiple: false,
                    library: { type: 'image' }
                });
            }

            // Store current uploader context
            mediaFrame.currentIndex = index;
            mediaFrame.currentPreview = $preview;
            mediaFrame.currentInput = $input;

            // Handle selection
            mediaFrame.off('select').on('select', function() {
                const attachment = mediaFrame.state().get('selection').first().toJSON();
                const imageUrl = attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

                mediaFrame.currentInput.val(attachment.id);
                mediaFrame.currentPreview
                    .css('background-image', 'url(' + imageUrl + ')')
                    .addClass('has-image');
            });

            mediaFrame.open();
        });

        // Remove image
        $('.mrv-example-remove').on('click', function(e) {
            e.stopPropagation();

            const $uploader = $(this).closest('.mrv-example-uploader');
            const index = $uploader.data('index');
            const $input = $('#mrv_example_image_' + index);
            const $preview = $uploader.find('.mrv-example-preview');

            $input.val('0');
            $preview
                .css('background-image', '')
                .removeClass('has-image');
        });
    });
})(jQuery);

/**
 * General Tab - Multi-Product Toggle & Range Slider
 */
(function($) {
    'use strict';

    $(function() {
        const $multiProductToggle = $('#mrv_multi_product_enabled');
        const $multiProductFields = $('.mrv-multi-product-fields');
        const $maxItemsRange = $('#mrv_multi_product_max_items');
        const $maxItemsValue = $('#mrv-max-items-value');

        if ($multiProductToggle.length) {
            // Toggle multi-product fields visibility
            $multiProductToggle.on('change', function() {
                if ($(this).is(':checked')) {
                    $multiProductFields.slideDown(200);
                } else {
                    $multiProductFields.slideUp(200);
                }
            });
        }

        // WhatsApp toggle
        const $whatsappToggle = $('#mrv_whatsapp_enabled');
        const $whatsappFields = $('.mrv-whatsapp-fields');

        if ($whatsappToggle.length) {
            $whatsappToggle.on('change', function() {
                if ($(this).is(':checked')) {
                    $whatsappFields.slideDown(200);
                } else {
                    $whatsappFields.slideUp(200);
                }
            });
        }

        // Button position dropdown - show/hide shortcode info
        const $buttonPosition = $('#mrv_button_position');
        const $shortcodeInfo = $('.mrv-shortcode-info');

        if ($buttonPosition.length && $shortcodeInfo.length) {
            $buttonPosition.on('change', function() {
                if ($(this).val() === 'shortcode') {
                    $shortcodeInfo.slideDown(200);
                } else {
                    $shortcodeInfo.slideUp(200);
                }
            });
        }

        // Toggle API Key visibility
        const $toggleApiKey = $('#mrv-toggle-api-key');
        const $eyeIcon = $('#mrv-eye-icon');
        const $eyeOffIcon = $('#mrv-eye-off-icon');

        if ($toggleApiKey.length) {
            $toggleApiKey.on('click', function() {
                const $input = $('#mrv_api_key');
                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $eyeIcon.hide();
                    $eyeOffIcon.show();
                } else {
                    $input.attr('type', 'password');
                    $eyeIcon.show();
                    $eyeOffIcon.hide();
                }
            });
        }

        // Test API Key button
        const $testApiBtn = $('#mrv-test-api-key');
        const $apiKeyInput = $('#mrv_api_key');
        const $testResult = $('#mrv-api-test-result');

        if ($testApiBtn.length && $apiKeyInput.length) {
            $testApiBtn.on('click', function() {
                const apiKey = $apiKeyInput.val().trim();

                if (!apiKey) {
                    showTestResult('error', 'Geen API key', 'Vul eerst een API key in.');
                    return;
                }

                // Show loading state
                $testApiBtn.prop('disabled', true).text('Testen...');
                $testResult.hide();

                $.ajax({
                    url: mrvAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mrv_test_api_key',
                        security: mrvAdmin.nonce,
                        api_key: apiKey
                    },
                    success: function(response) {
                        if (response.success) {
                            showTestResult('success', response.data.message, response.data.details);
                        } else {
                            showTestResult('error', response.data.message, response.data.details);
                        }
                    },
                    error: function() {
                        showTestResult('error', 'Verbindingsfout', 'Kon geen verbinding maken met de server.');
                    },
                    complete: function() {
                        $testApiBtn.prop('disabled', false).text('Test API Key');
                    }
                });
            });
        }

        function showTestResult(type, title, details) {
            const bgColor = type === 'success' ? '#ecfdf5' : '#fef2f2';
            const borderColor = type === 'success' ? '#a7f3d0' : '#fecaca';
            const textColor = type === 'success' ? '#065f46' : '#991b1b';
            const icon = type === 'success'
                ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;color:#10b981;flex-shrink:0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;color:#ef4444;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

            $testResult
                .css({
                    background: bgColor,
                    border: '1px solid ' + borderColor,
                    color: textColor
                })
                .html(`
                    <div style="display:flex;gap:10px;align-items:flex-start;">
                        ${icon}
                        <div>
                            <strong style="display:block;margin-bottom:2px;">${title}</strong>
                            <span style="font-size:12px;opacity:0.9;">${details}</span>
                        </div>
                    </div>
                `)
                .slideDown(200);
        }

        // Update range value display
        if ($maxItemsRange.length && $maxItemsValue.length) {
            $maxItemsRange.on('input', function() {
                $maxItemsValue.text($(this).val());
            });
        }
    });
})(jQuery);

/**
 * General Tab - Preset Selector & Custom Prompt Toggle
 */
(function($) {
    'use strict';

    $(function() {
        const $presetOptions = $('.mrv-preset-option');
        const $customPromptField = $('.mrv-custom-prompt-field');

        if (!$presetOptions.length) return;

        // Handle preset selection
        $presetOptions.on('click', function() {
            const $option = $(this);
            const $radio = $option.find('input[type="radio"]');
            const value = $radio.val();

            // Update active state
            $presetOptions.removeClass('mrv-preset-active');
            $option.addClass('mrv-preset-active');

            // Show/hide custom prompt field
            if (value === 'custom') {
                $customPromptField.slideDown(200);
            } else {
                $customPromptField.slideUp(200);
            }
        });
    });
})(jQuery);

/**
 * Generic Color Picker Initializer
 * Initializes all .mrv-color-picker inputs that aren't already initialized
 */
(function($) {
    'use strict';

    $(function() {
        // Initialize all color pickers with mrv-color-picker class
        // that haven't been initialized yet (no wp-picker-container parent)
        $('.mrv-color-picker').each(function() {
            const $input = $(this);

            // Skip if already initialized
            if ($input.closest('.wp-picker-container').length) {
                return;
            }

            // Initialize with default options
            $input.wpColorPicker({
                defaultColor: $input.data('default-color') || false,
                change: function(event, ui) {
                    // Trigger change event for any listeners
                    $(this).trigger('colorchange', [ui.color.toString()]);
                },
                clear: function() {
                    $(this).trigger('colorchange', ['']);
                }
            });
        });
    });
})(jQuery);
