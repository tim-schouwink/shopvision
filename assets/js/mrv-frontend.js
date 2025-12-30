/**
 * Shopvision - Frontend JavaScript
 * Modular, extensible architecture with inline styles for theme compatibility
 */
(function() {
    'use strict';

    /**
     * MRVModal - Main modal controller
     * Extensible class with event hooks for future functionality
     */
    class MRVModal {
        constructor(config) {
            this.config = config;
            this.state = 'idle'; // idle, upload, processing, result, error
            this.productId = null;
            this.generationId = null; // Track current generation
            this.elements = {};
            this.eventHandlers = {};
            this.initialized = false;
            this.isAddingToCart = false;

            // Multi-product cart
            this.cart = null;
            this.multiProductEnabled = config.features?.multiProduct || false;

            if (this.multiProductEnabled && typeof MRVCart !== 'undefined') {
                this.cart = new MRVCart({
                    maxItems: config.features?.maxProducts || 5,
                    enabled: true
                });

                // Listen for cart changes to update UI
                this.cart.onChange(() => this.updateButtonBadges());
            }

            this.init();
        }

        /**
         * Initialize the modal
         */
        init() {
            this.bindButtons();
            document.addEventListener('DOMContentLoaded', () => this.bindButtons());
            window.addEventListener('load', () => {
                this.bindButtons();
                this.initModal();
            });
        }

        /**
         * Bind visualizer buttons
         */
        bindButtons() {
            document.querySelectorAll('.mrv-visualizer-button').forEach(btn => {
                if (btn.dataset.mrvBound) return;
                btn.dataset.mrvBound = 'true';

                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    const productId = parseInt(btn.dataset.productId, 10);
                    this.productId = productId;

                    if (this.multiProductEnabled && this.cart) {
                        // Multi-product mode: add to cart and show toast
                        const productData = this.config.currentProduct;
                        if (productData && productData.product_id === productId) {
                            const added = this.cart.add(productData);
                            if (added) {
                                this.showToast({
                                    message: productData.name,
                                    subtitle: `${this.cart.count()} ${this.cart.count() !== 1 ? (this.config.i18n?.products || 'products') : (this.config.i18n?.product || 'product')} ${this.config.i18n?.inVisualization || 'in visualization'}`,
                                    actions: [
                                        { label: this.config.i18n?.view || 'View', onClick: () => this.open() },
                                        { label: this.config.i18n?.close || 'Close', dismiss: true }
                                    ]
                                });
                            }
                        }
                        // Open modal regardless
                        this.open();
                    } else {
                        // Single product mode
                        this.open();
                    }
                });
            });

            // Update badges on initial load
            this.updateButtonBadges();
        }

        /**
         * Update all visualizer buttons with cart count badge
         */
        updateButtonBadges() {
            if (!this.multiProductEnabled || !this.cart) return;

            const count = this.cart.count();

            document.querySelectorAll('.mrv-visualizer-button').forEach(btn => {
                const productId = parseInt(btn.dataset.productId, 10);
                const isInCart = this.cart.has(productId);

                // Update or create badge
                let badge = btn.querySelector('.mrv-btn-badge');

                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'mrv-btn-badge';
                        btn.appendChild(badge);
                    }
                    badge.textContent = count;
                    badge.style.display = '';
                } else if (badge) {
                    badge.style.display = 'none';
                }

                // Mark if product is already in cart
                btn.classList.toggle('mrv-in-cart', isInCart);
            });
        }

        /**
         * Show toast notification
         */
        showToast({ message, subtitle, actions = [], duration = 4000 }) {
            // Remove existing toast
            const existing = document.querySelector('.mrv-toast');
            if (existing) existing.remove();

            const accentColor = this.config.styling?.accentColor || '#2563eb';

            const toast = document.createElement('div');
            toast.className = 'mrv-toast';
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 1000000;
                transform: translateY(100px);
                opacity: 0;
                transition: transform 0.3s ease, opacity 0.3s ease;
            `;

            toast.innerHTML = `
                <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:#1f2937;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,0.3);color:#fff;">
                    <div style="width:24px;height:24px;border-radius:50%;background:#10b981;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" style="width:14px;height:14px;">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:14px;font-weight:500;">${message}</div>
                        ${subtitle ? `<div style="font-size:12px;color:#9ca3af;margin-top:1px;">${subtitle}</div>` : ''}
                    </div>
                    <div style="display:flex;gap:8px;margin-left:8px;">
                        ${actions.map((a, i) => `
                            <button type="button" class="mrv-toast-btn" data-index="${i}" style="
                                padding:6px 12px;
                                font-size:12px;
                                font-weight:500;
                                border:none;
                                border-radius:6px;
                                cursor:pointer;
                                background:${i === 0 ? accentColor : 'rgba(255,255,255,0.1)'};
                                color:#fff;
                                transition:background 0.15s;
                            ">${a.label}</button>
                        `).join('')}
                    </div>
                </div>
            `;

            document.body.appendChild(toast);

            // Trigger animation
            requestAnimationFrame(() => {
                toast.style.transform = 'translateY(0)';
                toast.style.opacity = '1';
            });

            // Bind actions
            toast.querySelectorAll('.mrv-toast-btn').forEach((btn, i) => {
                btn.addEventListener('click', () => {
                    if (actions[i].onClick) actions[i].onClick();
                    this.hideToast(toast);
                });
            });

            // Auto-hide
            setTimeout(() => this.hideToast(toast), duration);
        }

        /**
         * Hide toast notification
         */
        hideToast(toast) {
            if (!toast || !toast.parentNode) return;
            toast.style.transform = 'translateY(100px)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }

        /**
         * Initialize modal elements
         */
        initModal() {
            if (this.initialized) return true;

            const modal = document.getElementById('mrv-modal');
            if (!modal) return false;

            this.elements = {
                modal: modal,
                overlay: document.getElementById('mrv-overlay'),
                close: document.getElementById('mrv-close'),
                upload: document.getElementById('mrv-upload'),
                uploadZone: document.getElementById('mrv-upload-zone'),
                fileInput: document.getElementById('mrv-file'),
                processing: document.getElementById('mrv-processing'),
                progress: document.getElementById('mrv-progress'),
                result: document.getElementById('mrv-result'),
                image: document.getElementById('mrv-image'),
                download: document.getElementById('mrv-download'),
                downloadMulti: document.getElementById('mrv-download-multi'),
                retry: document.getElementById('mrv-retry'),
                error: document.getElementById('mrv-error'),
                errorMsg: document.getElementById('mrv-error-msg'),
                errorRetry: document.getElementById('mrv-error-retry'),
                consent: document.getElementById('mrv-consent'),
                productSlots: document.getElementById('mrv-product-slots'),
                slotsCount: document.getElementById('mrv-slots-count'),
                resultProducts: document.getElementById('mrv-result-products'),
                actionsSingle: document.getElementById('mrv-result-actions-single'),
                actionsMulti: document.getElementById('mrv-result-actions-multi'),
                whatsappBtn: document.getElementById('mrv-whatsapp-btn'),
            };

            this.bindModalEvents();
            this.setupOrderButton();
            this.setupWhatsAppButton();
            this.initialized = true;
            this.emit('init');
            return true;
        }

        /**
         * Setup order button if enabled
         */
        setupOrderButton() {
            if (!this.config.features?.orderButton) return;

            const { retry, download } = this.elements;
            if (!retry || !download) return;

            const accentColor = this.config.styling?.accentColor || '#2563eb';
            const orderText = this.config.i18n?.orderThis || 'Order This';
            const downloadText = this.config.i18n?.download || 'Download';

            // Transform retry button into order button
            retry.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                ${orderText}
            `;

            // Update download button text
            const downloadTextEl = download.querySelector('.mrv-download-text');
            if (downloadTextEl) {
                downloadTextEl.textContent = downloadText;
            }

            // Swap colors: order button gets accent color, download gets secondary
            retry.style.background = accentColor;
            retry.style.color = '#fff';
            retry.style.border = `1px solid ${accentColor}`;

            download.style.background = '#f3f4f6';
            download.style.color = '#374151';
            download.style.border = '1px solid #d1d5db';

            // Remove old click handler and add new one
            const newRetry = retry.cloneNode(true);
            retry.parentNode.replaceChild(newRetry, retry);
            this.elements.retry = newRetry;

            newRetry.addEventListener('click', (e) => {
                e.preventDefault();
                this.addToCart();
            });
        }

        /**
         * Setup WhatsApp button if enabled
         */
        setupWhatsAppButton() {
            const { whatsappBtn } = this.elements;
            if (!whatsappBtn || !this.config.whatsapp?.enabled) return;

            whatsappBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.openWhatsApp();
            });
        }

        /**
         * Open WhatsApp with product information
         */
        openWhatsApp() {
            const whatsappConfig = this.config.whatsapp;
            if (!whatsappConfig?.phone) {
                console.error('MRV: WhatsApp phone number not configured');
                return;
            }

            // Get product names
            let productNames = [];

            if (this.multiProductEnabled && this.cart && this.cart.count() > 0) {
                // Multi-product mode: get all products from cart
                const items = this.cart.getItems();
                productNames = items.map(item => item.name);
            } else if (this.config.currentProduct) {
                // Single product mode
                productNames = [this.config.currentProduct.name];
            }

            // Build message
            let message = whatsappConfig.message || 'Hallo! Ik zou graag een prijsopgave willen voor de volgende items:';

            if (productNames.length > 0) {
                message += '\n\n';
                productNames.forEach((name, index) => {
                    message += `${index + 1}. ${name}\n`;
                });
            }

            // Build WhatsApp URL
            const phone = whatsappConfig.phone.replace(/[^0-9]/g, '');
            const encodedMessage = encodeURIComponent(message);
            const waUrl = `https://wa.me/${phone}?text=${encodedMessage}`;

            // Open in new tab
            window.open(waUrl, '_blank');

            // Track WhatsApp click
            this.trackWhatsAppClick();

            this.emit('whatsappClick', { products: productNames, message });
        }

        /**
         * Track WhatsApp button click via AJAX
         */
        trackWhatsAppClick() {
            const formData = new FormData();
            formData.append('action', 'mrv_track_whatsapp');
            formData.append('nonce', this.config.nonce);

            fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData,
            }).catch(() => {
                // Silently fail - tracking is not critical
            });
        }

        /**
         * Get variation ID from the product form if available
         * Returns variation_id for variable products, or null for simple products
         */
        getSelectedVariation() {
            // Check for variation input (set when user selects variation options)
            const variationInput = document.querySelector('input[name="variation_id"]');
            if (variationInput && variationInput.value && variationInput.value !== '0') {
                return {
                    variation_id: parseInt(variationInput.value, 10),
                    attributes: this.getVariationAttributes()
                };
            }

            // Check if this is a variable product without selection
            const variationsForm = document.querySelector('form.variations_form');
            if (variationsForm) {
                return { needsSelection: true };
            }

            return null;
        }

        /**
         * Get selected variation attributes
         */
        getVariationAttributes() {
            const attributes = {};
            document.querySelectorAll('form.variations_form select[name^="attribute_"]').forEach(select => {
                if (select.value) {
                    attributes[select.name] = select.value;
                }
            });
            return attributes;
        }

        /**
         * Add product to cart via WooCommerce native AJAX
         * Uses multiple fallback methods to ensure it works
         */
        async addToCart() {
            if (this.isAddingToCart || !this.productId) return;

            // Check for variable product
            const variation = this.getSelectedVariation();

            if (variation?.needsSelection) {
                alert(this.config.i18n?.selectVariation || 'Please select a variation (e.g. color or size) before ordering.');
                this.close();
                return;
            }

            this.isAddingToCart = true;
            const { retry } = this.elements;
            const originalHTML = retry.innerHTML;

            // Show loading state
            retry.innerHTML = `
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;animation:mrv-spin 1s linear infinite;">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                </svg>
                ${this.config.i18n?.addingToCart || 'Adding...'}
            `;
            retry.disabled = true;

            // Determine which product ID to use
            const productIdToAdd = variation?.variation_id || this.productId;
            const isVariation = !!variation?.variation_id;

            try {
                let success = false;

                // Method 1: Try WooCommerce native wc-ajax endpoint (most reliable)
                if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.wc_ajax_url) {
                    const wcAjaxUrl = wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart');

                    const formData = new FormData();
                    formData.append('quantity', 1);

                    if (isVariation) {
                        formData.append('product_id', this.productId);
                        formData.append('variation_id', variation.variation_id);
                        if (variation.attributes) {
                            for (const [key, value] of Object.entries(variation.attributes)) {
                                formData.append(key, value);
                            }
                        }
                    } else {
                        formData.append('product_id', this.productId);
                    }

                    const response = await fetch(wcAjaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData,
                    });

                    const data = await response.json();

                    if (data && !data.error) {
                        success = true;
                        if (data.fragments) {
                            this.updateCartFragments(data.fragments);
                        }
                        if (typeof jQuery !== 'undefined') {
                            jQuery(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash]);
                        }
                    }
                }

                // Method 2: Fallback to our custom AJAX handler
                if (!success) {
                    const formData = new FormData();
                    formData.append('action', 'mrv_add_to_cart');
                    formData.append('product_id', this.productId);
                    formData.append('nonce', this.config.nonce);

                    if (isVariation) {
                        formData.append('variation_id', variation.variation_id);
                        if (variation.attributes) {
                            formData.append('variation_attributes', JSON.stringify(variation.attributes));
                        }
                    }

                    const response = await fetch(this.config.ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData,
                    });

                    const data = await response.json();

                    if (data.success) {
                        success = true;
                        if (data.data?.fragments) {
                            this.updateCartFragments(data.data.fragments);
                        }
                        if (typeof jQuery !== 'undefined') {
                            jQuery(document.body).trigger('wc_fragment_refresh');
                        }
                    }
                }

                // Method 3: Ultimate fallback - redirect to add-to-cart URL
                if (!success) {
                    let redirectUrl = window.location.origin + window.location.pathname + '?add-to-cart=' + productIdToAdd;
                    if (isVariation) {
                        redirectUrl += '&product_id=' + this.productId;
                    }
                    window.location.href = redirectUrl;
                    return;
                }

                // Show success state
                retry.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    ${this.config.i18n?.addedToCart || 'Added!'}
                `;

                this.emit('addedToCart', { productId: this.productId });

                // Reset after delay
                setTimeout(() => {
                    retry.innerHTML = originalHTML;
                    retry.disabled = false;
                    this.isAddingToCart = false;
                }, 2000);

            } catch (error) {
                // On any error, use the reliable redirect method
                window.location.href = window.location.pathname + '?add-to-cart=' + productIdToAdd;
            }
        }

        /**
         * Update WooCommerce cart fragments
         */
        updateCartFragments(fragments) {
            if (!fragments) return;

            for (const [selector, html] of Object.entries(fragments)) {
                const elements = document.querySelectorAll(selector);
                elements.forEach(el => {
                    el.outerHTML = html;
                });
            }
        }

        /**
         * Bind modal event listeners
         */
        bindModalEvents() {
            const { modal, overlay, close, uploadZone, fileInput, retry, errorRetry } = this.elements;

            // Close handlers
            close?.addEventListener('click', () => this.close());
            overlay?.addEventListener('click', () => this.close());
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen()) this.close();
            });

            // Upload zone - use uploadZone for click/drag events
            if (uploadZone && fileInput) {
                uploadZone.addEventListener('click', () => fileInput.click());

                uploadZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    this.setUploadHighlight(true);
                });

                uploadZone.addEventListener('dragleave', () => {
                    this.setUploadHighlight(false);
                });

                uploadZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    this.setUploadHighlight(false);
                    if (e.dataTransfer.files.length) {
                        this.handleFile(e.dataTransfer.files[0]);
                    }
                });

                fileInput.addEventListener('change', (e) => {
                    if (e.target.files.length) {
                        this.handleFile(e.target.files[0]);
                    }
                });
            }

            // Retry button - only bind if not order button mode
            if (!this.config.features?.orderButton) {
                retry?.addEventListener('click', () => this.reset());
            }
            errorRetry?.addEventListener('click', () => this.reset());

            // Consent checkbox
            const { consent } = this.elements;
            consent?.addEventListener('change', () => this.updateConsent());
        }

        /**
         * Update consent status via AJAX
         */
        async updateConsent() {
            const { consent } = this.elements;
            if (!consent || !this.generationId) return;

            const isChecked = consent.checked;

            try {
                const formData = new FormData();
                formData.append('action', 'mrv_update_consent');
                formData.append('nonce', this.config.nonce);
                formData.append('generation_id', this.generationId);
                formData.append('consent', isChecked ? '1' : '0');

                await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                this.emit('consentChanged', { generationId: this.generationId, consent: isChecked });
            } catch (error) {
                console.error('MRV: Failed to update consent', error);
            }
        }

        /**
         * Set upload zone highlight state
         */
        setUploadHighlight(active) {
            const { uploadZone } = this.elements;
            if (!uploadZone) return;

            const accentColor = this.config.styling?.accentColor || '#2563eb';
            uploadZone.style.borderColor = active ? accentColor : '#d1d5db';
        }

        /**
         * Open the modal
         */
        open() {
            if (!this.initialized && !this.initModal()) {
                setTimeout(() => {
                    if (this.initModal()) {
                        this.doOpen();
                    } else {
                        console.error('MRV: Could not initialize modal');
                        alert(this.config.i18n?.error || 'Er ging iets mis. Probeer de pagina te verversen.');
                    }
                }, 100);
                return;
            }
            this.doOpen();
        }

        /**
         * Actually open the modal
         */
        doOpen() {
            this.reset();
            this.renderProductSlots();
            this.elements.modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            this.emit('open', { productId: this.productId });
        }

        /**
         * Render product slots in modal (multi-product mode)
         */
        renderProductSlots() {
            const { productSlots, slotsCount } = this.elements;
            if (!productSlots || !this.multiProductEnabled || !this.cart) return;

            const items = this.cart.getItems();
            const maxItems = this.cart.maxItems;

            // Update count
            if (slotsCount) {
                slotsCount.textContent = items.length;
            }

            let html = '';

            // Render filled slots
            items.forEach((item) => {
                html += `
                    <div class="mrv-product-slot mrv-product-slot--filled" data-product-id="${item.product_id}" style="
                        position: relative;
                        width: 64px;
                        height: 64px;
                        border-radius: 8px;
                        overflow: hidden;
                        flex-shrink: 0;
                        border: 2px solid #e5e7eb;
                    ">
                        <img src="${item.image_thumb}" alt="${item.name}" style="width:100%;height:100%;object-fit:cover;">
                        <button type="button" class="mrv-slot-remove" data-product-id="${item.product_id}" style="
                            position: absolute;
                            top: 2px;
                            right: 2px;
                            width: 20px;
                            height: 20px;
                            padding: 0;
                            border: none;
                            border-radius: 50%;
                            background: rgba(0,0,0,0.6);
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        ">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" style="width:12px;height:12px;">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                        <span style="
                            position: absolute;
                            bottom: 0;
                            left: 0;
                            right: 0;
                            padding: 2px 4px;
                            font-size: 9px;
                            color: #fff;
                            background: linear-gradient(transparent, rgba(0,0,0,0.7));
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        ">${item.name}</span>
                    </div>
                `;
            });

            // Render empty slots
            for (let i = items.length; i < maxItems; i++) {
                html += `
                    <div class="mrv-product-slot mrv-product-slot--empty" style="
                        width: 64px;
                        height: 64px;
                        border: 2px dashed #d1d5db;
                        border-radius: 8px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        background: #f9fafb;
                        flex-shrink: 0;
                    ">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" style="width:20px;height:20px;">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </div>
                `;
            }

            productSlots.innerHTML = html;

            // Bind remove buttons
            productSlots.querySelectorAll('.mrv-slot-remove').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const productId = parseInt(btn.dataset.productId, 10);
                    this.cart.remove(productId);
                    this.renderProductSlots();
                    this.updateButtonBadges();
                });
            });

            // Show/hide product slots section based on items
            const slotsSection = document.getElementById('mrv-product-slots-section');
            if (slotsSection) {
                slotsSection.style.display = items.length > 0 || maxItems > 1 ? 'block' : 'none';
            }
        }

        /**
         * Close the modal
         */
        close() {
            if (!this.elements.modal) return;
            this.elements.modal.style.display = 'none';
            document.body.style.overflow = '';
            this.emit('close');
        }

        /**
         * Check if modal is open
         */
        isOpen() {
            return this.elements.modal?.style.display === 'flex';
        }

        /**
         * Reset to upload state
         */
        reset() {
            if (this.elements.fileInput) {
                this.elements.fileInput.value = '';
            }
            if (this.elements.consent) {
                this.elements.consent.checked = false;
            }
            this.isAddingToCart = false;
            this.generationId = null;
            this.setState('upload');
            this.emit('reset');
        }

        /**
         * Set modal state
         */
        setState(state) {
            const previousState = this.state;
            this.state = state;

            const states = ['upload', 'processing', 'result', 'error'];
            states.forEach(s => {
                const el = this.elements[s];
                if (el) el.style.display = (s === state) ? 'flex' : 'none';
            });

            this.emit('stateChange', { from: previousState, to: state });
        }

        /**
         * Handle file selection
         */
        handleFile(file) {
            // Use config values or defaults
            const validTypes = this.config.upload?.validTypes || ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
            const maxSize = this.config.upload?.maxSize || 5 * 1024 * 1024; // 5MB

            if (!validTypes.includes(file.type)) {
                this.showError(this.config.i18n?.invalidType || 'Invalid file type. Use JPG, PNG, WebP, HEIC or HEIF.');
                this.emit('error', { type: 'invalid_type', file });
                return;
            }

            if (file.size > maxSize) {
                this.showError(this.config.i18n?.fileTooLarge || 'File is too large. Maximum 5MB.');
                this.emit('error', { type: 'file_too_large', file });
                return;
            }

            this.emit('fileSelected', { file });
            this.process(file);
        }

        /**
         * Process the file - upload and generate visualization
         */
        async process(file) {
            this.setState('processing');
            this.startProgress();
            this.emit('processingStart', { file });

            const formData = new FormData();
            formData.append('action', 'mrv_process');
            formData.append('nonce', this.config.nonce);
            formData.append('room_image', file);

            // Multi-product mode: send all product IDs
            if (this.multiProductEnabled && this.cart && this.cart.count() > 0) {
                const productIds = this.cart.getProductIds();
                productIds.forEach(id => {
                    formData.append('product_ids[]', id);
                });
            } else {
                // Single product mode
                formData.append('product_id', this.productId);
            }

            try {
                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    this.showResult(data.data.image_url, data.data.generation_id);
                    this.emit('success', { imageUrl: data.data.image_url, generationId: data.data.generation_id });
                } else {
                    const message = data.data?.message || this.config.i18n?.error;
                    this.showError(message);
                    this.emit('error', { type: 'api_error', message });
                }
            } catch (error) {
                console.error('MRV Error:', error);
                this.showError(this.config.i18n?.error);
                this.emit('error', { type: 'network_error', error });
            }
        }

        /**
         * Start progress animation
         */
        startProgress() {
            const { progress } = this.elements;
            if (!progress) return;

            progress.style.width = '0%';
            let pct = 0;

            const interval = setInterval(() => {
                if (this.state !== 'processing') {
                    clearInterval(interval);
                    return;
                }
                pct = Math.min(90, pct + Math.max(0.5, (90 - pct) / 50));
                progress.style.width = pct + '%';
                if (pct >= 90) clearInterval(interval);
            }, 500);
        }

        /**
         * Show result state
         */
        showResult(imageUrl, generationId) {
            const { image, download, downloadMulti, progress, consent, resultProducts, actionsSingle, actionsMulti } = this.elements;

            if (image) image.src = imageUrl;
            if (download) download.href = imageUrl;
            if (downloadMulti) downloadMulti.href = imageUrl;
            if (progress) progress.style.width = '100%';

            // Store generation ID and reset consent checkbox
            this.generationId = generationId;
            if (consent) consent.checked = false;

            // Check if multi-product mode with multiple items
            const isMultiResult = this.multiProductEnabled && this.cart && this.cart.count() > 1;

            if (isMultiResult) {
                // Render product list
                this.renderResultProducts();
                if (resultProducts) resultProducts.style.display = 'block';
                if (actionsSingle) actionsSingle.style.display = 'none';
                if (actionsMulti) actionsMulti.style.display = 'block';
            } else {
                // Single product mode
                if (resultProducts) resultProducts.style.display = 'none';
                if (actionsSingle) actionsSingle.style.display = 'flex';
                if (actionsMulti) actionsMulti.style.display = 'none';
            }

            setTimeout(() => this.setState('result'), 200);
        }

        /**
         * Render product list in result state (multi-product mode)
         */
        renderResultProducts() {
            const { resultProducts } = this.elements;
            if (!resultProducts || !this.cart) return;

            const items = this.cart.getItems();
            const accentColor = this.config.styling?.accentColor || '#2563eb';

            let html = `
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
                    <div style="padding:12px 14px;border-bottom:1px solid #e5e7eb;background:#fff;">
                        <span style="font-size:13px;font-weight:600;color:#374151;">${this.config.i18n?.productsInVisualization || 'Products in this visualization'}</span>
                    </div>
                    <div style="max-height:180px;overflow-y:auto;">
            `;

            items.forEach((item, index) => {
                html += `
                    <a href="${item.permalink}" target="_blank" style="
                        display:flex;
                        align-items:center;
                        gap:12px;
                        padding:10px 14px;
                        text-decoration:none;
                        border-bottom:${index < items.length - 1 ? '1px solid #f3f4f6' : 'none'};
                        transition:background 0.15s;
                    " onmouseover="this.style.background='#fff'" onmouseout="this.style.background='transparent'">
                        <img src="${item.image_thumb}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;flex-shrink:0;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:500;color:#1f2937;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.name}</div>
                            <div style="font-size:12px;color:#6b7280;">${item.price}</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:4px;padding:6px 10px;background:${accentColor};color:#fff;font-size:11px;font-weight:500;border-radius:6px;flex-shrink:0;">
                            <span>${this.config.i18n?.view || 'View'}</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px;">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </div>
                    </a>
                `;
            });

            html += `
                    </div>
                </div>
            `;

            resultProducts.innerHTML = html;
        }

        /**
         * Show error state
         */
        showError(message) {
            const { errorMsg } = this.elements;
            if (errorMsg) errorMsg.textContent = message;
            this.setState('error');
        }

        /**
         * Event system - register handler
         */
        on(event, handler) {
            if (!this.eventHandlers[event]) {
                this.eventHandlers[event] = [];
            }
            this.eventHandlers[event].push(handler);
            return this; // Allow chaining
        }

        /**
         * Event system - remove handler
         */
        off(event, handler) {
            if (!this.eventHandlers[event]) return this;

            if (handler) {
                this.eventHandlers[event] = this.eventHandlers[event].filter(h => h !== handler);
            } else {
                delete this.eventHandlers[event];
            }
            return this;
        }

        /**
         * Event system - emit event
         */
        emit(event, data = {}) {
            const handlers = this.eventHandlers[event] || [];
            handlers.forEach(handler => {
                try {
                    handler({ ...data, modal: this });
                } catch (error) {
                    console.error(`MRV: Error in ${event} handler:`, error);
                }
            });
        }

        /**
         * Get current state
         */
        getState() {
            return this.state;
        }

        /**
         * Get product ID
         */
        getProductId() {
            return this.productId;
        }

        /**
         * Destroy instance
         */
        destroy() {
            this.close();
            this.eventHandlers = {};
            this.initialized = false;
            this.emit('destroy');
        }
    }

    /**
     * Initialize when config is available
     */
    function initMRV() {
        if (typeof mrvConfig === 'undefined') return;

        // Create global instance
        window.MRVModal = MRVModal;
        window.mrvModalInstance = new MRVModal(mrvConfig);

        // Example of how to extend:
        // window.mrvModalInstance.on('success', (data) => {
        //     console.log('Visualization complete:', data.imageUrl);
        // });
    }

    // Initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMRV);
    } else {
        initMRV();
    }

})();
