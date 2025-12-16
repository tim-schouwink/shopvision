/**
 * MRVCart - Manages the visualization product cart
 * Persists to localStorage with 7-day expiration
 */
class MRVCart {
    static STORAGE_KEY = 'mrv_visualization_cart';
    static EXPIRY_DAYS = 7;

    constructor(config = {}) {
        this.maxItems = config.maxItems || 5;
        this.enabled = config.enabled || false;
        this.items = [];
        this.listeners = [];

        if (this.enabled) {
            this.load();
        }
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
     * @param {Object} product - Product data
     * @returns {boolean} success
     */
    add(product) {
        if (!this.enabled) return false;

        // Check if already exists
        if (this.has(product.product_id)) {
            return false;
        }

        // Check max items
        if (this.items.length >= this.maxItems) {
            return false;
        }

        this.items.push({
            product_id: product.product_id,
            name: product.name,
            price: product.price || '',
            image_url: product.image_url,
            image_thumb: product.image_thumb,
            permalink: product.permalink || '',
            added_at: Date.now()
        });

        this.save();
        return true;
    }

    /**
     * Remove product from cart
     * @param {number} productId
     */
    remove(productId) {
        const initialLength = this.items.length;
        this.items = this.items.filter(item => item.product_id !== productId);

        if (this.items.length !== initialLength) {
            this.save();
        }
    }

    /**
     * Check if product is in cart
     * @param {number} productId
     * @returns {boolean}
     */
    has(productId) {
        return this.items.some(item => item.product_id === productId);
    }

    /**
     * Get all items
     * @returns {Array}
     */
    getItems() {
        return [...this.items];
    }

    /**
     * Get item count
     * @returns {number}
     */
    count() {
        return this.items.length;
    }

    /**
     * Check if cart is empty
     * @returns {boolean}
     */
    isEmpty() {
        return this.items.length === 0;
    }

    /**
     * Check if cart is full
     * @returns {boolean}
     */
    isFull() {
        return this.items.length >= this.maxItems;
    }

    /**
     * Get product IDs as array
     * @returns {Array<number>}
     */
    getProductIds() {
        return this.items.map(item => item.product_id);
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
     * @param {Function} callback
     * @returns {Function} unsubscribe function
     */
    onChange(callback) {
        this.listeners.push(callback);
        return () => {
            this.listeners = this.listeners.filter(l => l !== callback);
        };
    }

    /**
     * Notify all listeners
     */
    notify() {
        const items = this.getItems();
        const count = this.count();
        this.listeners.forEach(cb => cb(items, count));
    }

    /**
     * Get remaining slots
     * @returns {number}
     */
    getRemainingSlots() {
        return Math.max(0, this.maxItems - this.items.length);
    }
}

// Export for use in other files
window.MRVCart = MRVCart;
