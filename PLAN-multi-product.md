# Multi-Product Visualisatie - Implementatieplan

## Overzicht

Deze feature maakt het mogelijk om meerdere producten (max 5) te verzamelen en samen in één AI-visualisatie te plaatsen. De AI bepaalt automatisch logische hoeveelheden (bijv. 4-6 stoelen rond een eettafel).

---

## Architectuur

### Data Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                           FRONTEND                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Productpagina A        Productpagina B        Productpagina C      │
│       │                      │                      │                │
│       ▼                      ▼                      ▼                │
│  [Visualiseer]          [Visualiseer]          [Visualiseer]        │
│       │                      │                      │                │
│       └──────────────────────┼──────────────────────┘                │
│                              ▼                                       │
│                    ┌─────────────────┐                               │
│                    │  MRVCart class  │ ◄── localStorage              │
│                    │  (JavaScript)   │     'mrv_visualization_cart'  │
│                    └────────┬────────┘                               │
│                             │                                        │
│                             ▼                                        │
│           ┌─────────────────────────────────┐                        │
│           │         Modal UI                 │                        │
│           │  ┌─────┐┌─────┐┌─────┐┌─────┐   │                        │
│           │  │prod1││prod2││ + ──││ + ──│   │ ◄── Product slots      │
│           │  └─────┘└─────┘└─────┘└─────┘   │                        │
│           └─────────────────────────────────┘                        │
│                             │                                        │
└─────────────────────────────┼────────────────────────────────────────┘
                              │
                              ▼ AJAX (mrv_process)
┌─────────────────────────────────────────────────────────────────────┐
│                           BACKEND                                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────────┐    ┌──────────────────┐                       │
│  │ MRV_Ajax_Handler │───►│ MRV_Gemini_Client│                       │
│  │                  │    │                  │                       │
│  │ - Validate items │    │ - Build prompt   │                       │
│  │ - Get product    │    │ - Include all    │                       │
│  │   images         │    │   product images │                       │
│  │ - Build context  │    │ - AI generates   │                       │
│  └──────────────────┘    └──────────────────┘                       │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## localStorage Structure

```javascript
// Key: 'mrv_visualization_cart'
{
  "items": [
    {
      "product_id": 123,
      "name": "Moderne Loungestoel",
      "image_url": "https://example.com/chair.jpg",
      "image_thumb": "https://example.com/chair-150x150.jpg",
      "price": "€ 299,00",
      "permalink": "https://example.com/product/loungestoel/",
      "added_at": 1702654321000
    },
    {
      "product_id": 456,
      "name": "Eiken Eettafel",
      "image_url": "https://example.com/table.jpg",
      "image_thumb": "https://example.com/table-150x150.jpg",
      "price": "€ 899,00",
      "permalink": "https://example.com/product/eettafel/",
      "added_at": 1702654400000
    }
  ],
  "max_items": 5,
  "expires_at": 1703259121000,  // 7 dagen vanaf laatste update
  "updated_at": 1702654400000
}
```

---

## Admin Instellingen

### Nieuwe opties in "Algemeen" tab

```php
// Nieuwe settings te registreren
'mrv_multi_product_enabled'     => boolean (default: false)
'mrv_multi_product_max_items'   => integer (default: 5, range: 2-5)
'mrv_multi_product_prompt'      => textarea (optioneel, voor geavanceerde users)
```

### UI in Admin

```
┌─────────────────────────────────────────────────────────────────┐
│  Multi-product visualisatie                                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  [Toggle] Multi-product modus inschakelen                       │
│                                                                  │
│  Wanneer ingeschakeld kunnen klanten meerdere producten         │
│  verzamelen en samen visualiseren in één afbeelding.            │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  Maximum aantal producten                                │    │
│  │  [====●=====] 5                                          │    │
│  │                                                          │    │
│  │  ℹ️ De AI bepaalt automatisch logische hoeveelheden.    │    │
│  │     Bijvoorbeeld: meerdere eetkamerstoelen rond een     │    │
│  │     tafel, of een set kussens op een bank.              │    │
│  └─────────────────────────────────────────────────────────┘    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Frontend: JavaScript Classes

### MRVCart Class (nieuw bestand: `assets/js/mrv-cart.js`)

```javascript
/**
 * MRVCart - Manages the visualization product cart
 * Persists to localStorage with 7-day expiration
 */
class MRVCart {
    static STORAGE_KEY = 'mrv_visualization_cart';
    static EXPIRY_DAYS = 7;

    constructor(config) {
        this.maxItems = config.maxItems || 5;
        this.enabled = config.multiProductEnabled || false;
        this.items = [];
        this.listeners = [];

        this.load();
    }

    /**
     * Load cart from localStorage
     */
    load() {
        try {
            const stored = localStorage.getItem(MRVCart.STORAGE_KEY);
            if (!stored) return;

            const data = JSON.parse(stored);

            // Check expiration
            if (data.expires_at && data.expires_at < Date.now()) {
                this.clear();
                return;
            }

            this.items = data.items || [];
        } catch (e) {
            console.error('MRVCart: Failed to load', e);
            this.items = [];
        }
    }

    /**
     * Save cart to localStorage
     */
    save() {
        const data = {
            items: this.items,
            max_items: this.maxItems,
            expires_at: Date.now() + (MRVCart.EXPIRY_DAYS * 24 * 60 * 60 * 1000),
            updated_at: Date.now()
        };

        try {
            localStorage.setItem(MRVCart.STORAGE_KEY, JSON.stringify(data));
            this.notify();
        } catch (e) {
            console.error('MRVCart: Failed to save', e);
        }
    }

    /**
     * Add product to cart
     * @returns {boolean} success
     */
    add(product) {
        // Check if already exists
        if (this.has(product.product_id)) {
            return false;
        }

        // Check max items
        if (this.items.length >= this.maxItems) {
            return false;
        }

        this.items.push({
            ...product,
            added_at: Date.now()
        });

        this.save();
        return true;
    }

    /**
     * Remove product from cart
     */
    remove(productId) {
        this.items = this.items.filter(item => item.product_id !== productId);
        this.save();
    }

    /**
     * Check if product is in cart
     */
    has(productId) {
        return this.items.some(item => item.product_id === productId);
    }

    /**
     * Get all items
     */
    getItems() {
        return [...this.items];
    }

    /**
     * Get item count
     */
    count() {
        return this.items.length;
    }

    /**
     * Check if cart is full
     */
    isFull() {
        return this.items.length >= this.maxItems;
    }

    /**
     * Clear all items
     */
    clear() {
        this.items = [];
        localStorage.removeItem(MRVCart.STORAGE_KEY);
        this.notify();
    }

    /**
     * Subscribe to changes
     */
    onChange(callback) {
        this.listeners.push(callback);
        return () => {
            this.listeners = this.listeners.filter(l => l !== callback);
        };
    }

    /**
     * Notify listeners
     */
    notify() {
        this.listeners.forEach(cb => cb(this.items, this.count()));
    }
}
```

### MRVModal Updates

```javascript
// In MRVModal class - nieuwe/aangepaste methods

class MRVModal {
    constructor(config) {
        // ... bestaande code ...

        // Multi-product support
        this.multiProductEnabled = config.features?.multiProduct || false;
        this.cart = null;

        if (this.multiProductEnabled) {
            this.cart = new MRVCart({
                maxItems: config.features?.maxProducts || 5,
                multiProductEnabled: true
            });
        }
    }

    /**
     * Handle visualizer button click
     */
    handleButtonClick(productId, productData) {
        if (this.multiProductEnabled) {
            // Add to cart (if not already in)
            const added = this.cart.add(productData);

            // Show toast notification
            if (added) {
                this.showToast({
                    message: `${productData.name} toegevoegd`,
                    subtitle: `${this.cart.count()} product${this.cart.count() !== 1 ? 'en' : ''} in visualisatie`,
                    actions: [
                        { label: 'Bekijk', onClick: () => this.open() },
                        { label: 'Sluiten', dismiss: true }
                    ]
                });
            }

            // Open modal
            this.open();
        } else {
            // Single product mode (bestaand gedrag)
            this.productId = productId;
            this.open();
        }
    }

    /**
     * Render product slots in modal
     */
    renderProductSlots() {
        const container = this.elements.productSlots;
        if (!container) return;

        const items = this.cart.getItems();
        const maxItems = this.cart.maxItems;

        let html = '';

        // Render filled slots
        items.forEach((item, index) => {
            html += `
                <div class="mrv-product-slot mrv-product-slot--filled" data-product-id="${item.product_id}">
                    <img src="${item.image_thumb}" alt="${item.name}">
                    <button type="button" class="mrv-product-slot-remove" data-product-id="${item.product_id}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                    <span class="mrv-product-slot-name">${item.name}</span>
                </div>
            `;
        });

        // Render empty slots
        for (let i = items.length; i < maxItems; i++) {
            html += `
                <div class="mrv-product-slot mrv-product-slot--empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                </div>
            `;
        }

        container.innerHTML = html;

        // Bind remove buttons
        container.querySelectorAll('.mrv-product-slot-remove').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const productId = parseInt(btn.dataset.productId);
                this.cart.remove(productId);
                this.renderProductSlots();
                this.updateButtonStates();
            });
        });
    }

    /**
     * Show toast notification
     */
    showToast({ message, subtitle, actions, duration = 4000 }) {
        // Remove existing toast
        const existing = document.querySelector('.mrv-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'mrv-toast';
        toast.innerHTML = `
            <div class="mrv-toast-content">
                <div class="mrv-toast-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <div class="mrv-toast-text">
                    <div class="mrv-toast-message">${message}</div>
                    ${subtitle ? `<div class="mrv-toast-subtitle">${subtitle}</div>` : ''}
                </div>
                <div class="mrv-toast-actions">
                    ${actions.map(a => `
                        <button type="button" class="mrv-toast-btn" data-action="${a.dismiss ? 'dismiss' : 'custom'}">
                            ${a.label}
                        </button>
                    `).join('')}
                </div>
            </div>
        `;

        document.body.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => toast.classList.add('mrv-toast--visible'));

        // Bind actions
        const buttons = toast.querySelectorAll('.mrv-toast-btn');
        buttons.forEach((btn, i) => {
            btn.addEventListener('click', () => {
                if (actions[i].onClick) actions[i].onClick();
                this.hideToast(toast);
            });
        });

        // Auto-hide
        setTimeout(() => this.hideToast(toast), duration);
    }

    hideToast(toast) {
        toast.classList.remove('mrv-toast--visible');
        setTimeout(() => toast.remove(), 300);
    }
}
```

---

## Frontend: Button State Updates

### Button met counter badge

```javascript
/**
 * Update all visualizer buttons on page
 */
updateVisualizerButtons() {
    if (!this.multiProductEnabled || !this.cart) return;

    const count = this.cart.count();

    document.querySelectorAll('.mrv-visualizer-button').forEach(btn => {
        const productId = parseInt(btn.dataset.productId);
        const isInCart = this.cart.has(productId);

        // Update badge
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
```

### CSS voor badge

```css
.mrv-visualizer-button {
    position: relative;
}

.mrv-btn-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    font-size: 11px;
    font-weight: 600;
    line-height: 18px;
    text-align: center;
    color: #fff;
    background: var(--mrv-accent, #2563eb);
    border-radius: 9px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.mrv-visualizer-button.mrv-in-cart .mrv-btn-badge {
    background: #10b981; /* green - already added */
}
```

---

## Frontend: Modal UI Updates

### Nieuwe modal HTML structure

```html
<!-- In templates/modal.php - product slots section -->
<?php if (get_option('mrv_multi_product_enabled', false)): ?>
<div id="mrv-product-slots-section" class="mrv-product-slots-section">
    <div class="mrv-product-slots-header">
        <span class="mrv-product-slots-title">Producten in visualisatie</span>
        <span class="mrv-product-slots-count">
            <span id="mrv-slots-count">0</span>/<span id="mrv-slots-max"><?php echo esc_html(get_option('mrv_multi_product_max_items', 5)); ?></span>
        </span>
    </div>
    <div id="mrv-product-slots" class="mrv-product-slots">
        <!-- Dynamically rendered by JS -->
    </div>
    <p class="mrv-product-slots-hint">
        Sluit de modal om meer producten toe te voegen via de productpagina's.
    </p>
</div>
<?php endif; ?>
```

### CSS voor product slots

```css
/* Product Slots Section */
.mrv-product-slots-section {
    padding: 16px 0 0;
    border-top: 1px solid #e5e7eb;
    margin-top: 20px;
}

.mrv-product-slots-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.mrv-product-slots-title {
    font-size: 13px;
    font-weight: 500;
    color: #374151;
}

.mrv-product-slots-count {
    font-size: 12px;
    color: #6b7280;
}

.mrv-product-slots {
    display: flex;
    gap: 10px;
}

.mrv-product-slot {
    position: relative;
    width: 64px;
    height: 64px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.mrv-product-slot--empty {
    border: 2px dashed #d1d5db;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9fafb;
}

.mrv-product-slot--empty svg {
    width: 20px;
    height: 20px;
    stroke: #9ca3af;
}

.mrv-product-slot--filled {
    border: 2px solid #e5e7eb;
}

.mrv-product-slot--filled img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.mrv-product-slot-remove {
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
    opacity: 0;
    transition: opacity 0.15s;
}

.mrv-product-slot--filled:hover .mrv-product-slot-remove {
    opacity: 1;
}

.mrv-product-slot-remove svg {
    width: 12px;
    height: 12px;
    stroke: #fff;
}

.mrv-product-slot-name {
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
}

.mrv-product-slots-hint {
    margin: 10px 0 0;
    font-size: 11px;
    color: #9ca3af;
}

/* Toast Notification */
.mrv-toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1000000;
    transform: translateY(100px);
    opacity: 0;
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.mrv-toast--visible {
    transform: translateY(0);
    opacity: 1;
}

.mrv-toast-content {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: #1f2937;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    color: #fff;
}

.mrv-toast-icon {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #10b981;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.mrv-toast-icon svg {
    width: 14px;
    height: 14px;
    stroke: #fff;
}

.mrv-toast-message {
    font-size: 14px;
    font-weight: 500;
}

.mrv-toast-subtitle {
    font-size: 12px;
    color: #9ca3af;
    margin-top: 1px;
}

.mrv-toast-actions {
    display: flex;
    gap: 8px;
    margin-left: 8px;
}

.mrv-toast-btn {
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 500;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    background: rgba(255,255,255,0.1);
    color: #fff;
    transition: background 0.15s;
}

.mrv-toast-btn:hover {
    background: rgba(255,255,255,0.2);
}

.mrv-toast-btn:first-child {
    background: var(--mrv-accent, #2563eb);
}

.mrv-toast-btn:first-child:hover {
    filter: brightness(1.1);
}
```

---

## Backend: AJAX Handler Updates

### Aangepaste process_visualization method

```php
/**
 * Process visualization with multiple products
 */
public function process_visualization(): void {
    check_ajax_referer('mrv_nonce', 'nonce');

    // Validate room image
    if (empty($_FILES['room_image'])) {
        wp_send_json_error(['message' => __('Geen afbeelding geüpload.', 'mokana-room-visualizer')]);
    }

    // Get product IDs - single or multiple
    $product_ids = [];

    if (!empty($_POST['product_ids'])) {
        // Multi-product mode
        $product_ids = array_map('absint', (array) $_POST['product_ids']);
        $product_ids = array_filter($product_ids);

        // Limit to max items
        $max_items = (int) get_option('mrv_multi_product_max_items', 5);
        $product_ids = array_slice($product_ids, 0, $max_items);
    } elseif (!empty($_POST['product_id'])) {
        // Single product mode (backwards compatible)
        $product_ids = [absint($_POST['product_id'])];
    }

    if (empty($product_ids)) {
        wp_send_json_error(['message' => __('Geen product geselecteerd.', 'mokana-room-visualizer')]);
    }

    // Validate products and gather data
    $products_data = [];
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) continue;

        $image_id = $product->get_image_id();
        if (!$image_id) continue;

        $products_data[] = [
            'id' => $product_id,
            'name' => $product->get_name(),
            'description' => $product->get_short_description() ?: $product->get_description(),
            'image_url' => wp_get_attachment_url($image_id),
            'image_path' => get_attached_file($image_id),
            'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']),
        ];
    }

    if (empty($products_data)) {
        wp_send_json_error(['message' => __('Geen geldige producten gevonden.', 'mokana-room-visualizer')]);
    }

    // Process room image
    $room_image = $this->process_uploaded_image($_FILES['room_image']);
    if (is_wp_error($room_image)) {
        wp_send_json_error(['message' => $room_image->get_error_message()]);
    }

    // Call Gemini API with all products
    $gemini = new MRV_Gemini_Client();
    $result = $gemini->generate_visualization($room_image, $products_data);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    // Save generation with all product IDs
    $generation_id = MRV_Post_Types::create_generation([
        'image_data' => $result['image_data'],
        'product_ids' => $product_ids,  // Now supports array
        'user_id' => get_current_user_id(),
    ]);

    wp_send_json_success([
        'image_url' => $result['image_url'],
        'generation_id' => $generation_id,
    ]);
}
```

---

## Backend: Gemini Client Updates

### Multi-product prompt building

```php
/**
 * Build prompt for visualization
 */
private function build_prompt(array $products_data): string {
    $is_multi = count($products_data) > 1;

    if ($is_multi) {
        $product_list = '';
        foreach ($products_data as $index => $product) {
            $categories = implode(', ', $product['categories']);
            $product_list .= sprintf(
                "\n%d. %s (%s)",
                $index + 1,
                $product['name'],
                $categories ?: 'furniture'
            );
        }

        return sprintf(
            "Place these furniture items naturally in the room photograph:%s

Instructions:
- Arrange items in a realistic, aesthetically pleasing composition
- Use appropriate quantities based on furniture type (e.g., 4-6 dining chairs around a table)
- Maintain proper scale and perspective relative to the room
- Ensure items complement each other and the existing room decor
- Items should look naturally placed, not floating or overlapping incorrectly",
            $product_list
        );
    }

    // Single product prompt (existing logic)
    $product = $products_data[0];
    return sprintf(
        "Place this %s (%s) naturally in the room photograph.
         Maintain realistic scale, lighting, and perspective.",
        $product['name'],
        implode(', ', $product['categories']) ?: 'furniture item'
    );
}

/**
 * Generate visualization with multiple product images
 */
public function generate_visualization(string $room_image_path, array $products_data): array|WP_Error {
    $api_key = get_option('mrv_api_key');

    if (empty($api_key)) {
        return new WP_Error('no_api_key', __('API key niet geconfigureerd.', 'mokana-room-visualizer'));
    }

    // Build image parts array
    $image_parts = [];

    // Room image first
    $room_base64 = base64_encode(file_get_contents($room_image_path));
    $room_mime = mime_content_type($room_image_path);
    $image_parts[] = [
        'inline_data' => [
            'mime_type' => $room_mime,
            'data' => $room_base64,
        ]
    ];

    // Add all product images
    foreach ($products_data as $product) {
        if (!file_exists($product['image_path'])) continue;

        $product_base64 = base64_encode(file_get_contents($product['image_path']));
        $product_mime = mime_content_type($product['image_path']);
        $image_parts[] = [
            'inline_data' => [
                'mime_type' => $product_mime,
                'data' => $product_base64,
            ]
        ];
    }

    // Build request
    $prompt = $this->build_prompt($products_data);

    $request_body = [
        'contents' => [
            [
                'parts' => array_merge(
                    [['text' => $prompt]],
                    $image_parts
                )
            ]
        ],
        'generationConfig' => [
            'responseModalities' => ['image', 'text'],
            'imageMimeType' => 'image/jpeg',
        ]
    ];

    // ... rest of API call logic ...
}
```

---

## Database: Post Type Updates

### Multiple product IDs support

```php
/**
 * Create generation - updated for multi-product
 */
public static function create_generation(array $args): int {
    // ... existing code ...

    // Support both single and multiple product IDs
    $product_ids = $args['product_ids'] ?? [];
    if (!empty($args['product_id']) && empty($product_ids)) {
        $product_ids = [$args['product_id']];
    }

    // Store as serialized array
    update_post_meta($post_id, '_mrv_product_ids', $product_ids);

    // Keep backwards compatible single ID for primary product
    if (!empty($product_ids[0])) {
        update_post_meta($post_id, '_mrv_product_id', $product_ids[0]);
    }

    // ... rest of existing code ...
}
```

---

## Implementatie Volgorde

### Fase 1: Admin Settings (1-2 uur)
- [ ] Nieuwe settings registreren in `class-mrv-settings.php`
- [ ] UI toevoegen in Algemeen tab
- [ ] Settings doorgeven aan frontend via `wp_localize_script`

### Fase 2: Cart Management (2-3 uur)
- [ ] `MRVCart` class maken in nieuw bestand
- [ ] localStorage structure implementeren
- [ ] Expiration logic (7 dagen)
- [ ] Change listeners voor UI updates

### Fase 3: Button Updates (1-2 uur)
- [ ] Button click handler aanpassen voor multi-product mode
- [ ] Badge/counter toevoegen aan buttons
- [ ] CSS styling voor badges

### Fase 4: Toast Notifications (1 uur)
- [ ] Toast component maken
- [ ] Animaties
- [ ] Actions (Bekijk / Sluiten)

### Fase 5: Modal UI (2-3 uur)
- [ ] Product slots HTML structure
- [ ] Dynamische rendering van filled/empty slots
- [ ] Remove product functionality
- [ ] Responsive styling

### Fase 6: Backend Updates (2-3 uur)
- [ ] AJAX handler voor multiple products
- [ ] Gemini client prompt updates
- [ ] Multiple images in API request
- [ ] Post type meta updates

### Fase 7: Testing & Polish (2-3 uur)
- [ ] Single product mode blijft werken
- [ ] Multi-product mode volledig testen
- [ ] Edge cases (cart vol, product verwijderd, etc.)
- [ ] Mobile responsive
- [ ] Backwards compatibility

---

## Totale Geschatte Tijd

**12-17 uur** ontwikkeltijd

---

## Belangrijke Overwegingen

### Backwards Compatibility
- Single product mode moet blijven werken als multi-product uit staat
- Bestaande `product_id` parameter in AJAX blijft ondersteund
- Generaties met enkel `_mrv_product_id` meta blijven werken

### Performance
- localStorage is snel en geen server load
- Cart data is klein (max 5 items, ~2KB)
- Geen extra database queries voor cart

### UX Best Practices
- Duidelijke feedback bij elke actie
- Makkelijk om producten te verwijderen
- Geen verwarring met WooCommerce cart
- Toast verdwijnt automatisch maar heeft actieknoppen

---

## Open Items / Toekomstige Verbeteringen

1. **Quick-add vanuit modal** - Dropdown om direct producten te zoeken/toevoegen
2. **Cart delen** - Shareable link met voorgeselecteerde producten
3. **Suggesties** - "Klanten combineren dit vaak met..."
4. **Templates** - Voorgedefinieerde combinaties (woonkamer set, slaapkamer set)
