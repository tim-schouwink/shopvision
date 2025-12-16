<?php
/**
 * Google Gemini API client for Nano Banana Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class MRV_Gemini_Client {

    /**
     * API endpoint - Using Gemini 3 Pro Image Preview (Nano Banana Pro)
     */
    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-image-preview:generateContent';

    /**
     * API key
     */
    private string $api_key;

    /**
     * Current preset
     */
    private string $preset;

    /**
     * Custom prompt (for custom preset)
     */
    private string $custom_prompt;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = get_option('mrv_api_key', '');
        $this->preset = get_option('mrv_preset', 'interior');
        $this->custom_prompt = get_option('mrv_custom_prompt', '');
    }

    /**
     * Check if API is configured
     */
    public function is_configured(): bool {
        return !empty($this->api_key);
    }

    /**
     * Generate room visualization
     *
     * @param string    $room_image_path Path to the uploaded room image
     * @param int|array $product_ids     WooCommerce product ID(s) - single int or array
     * @return array{success: bool, image_data?: string, error?: string}
     */
    public function generate_visualization(string $room_image_path, int|array $product_ids): array {
        if (!$this->is_configured()) {
            return ['success' => false, 'error' => __('API key not configured.', 'shopvision')];
        }

        // Normalize to array
        if (!is_array($product_ids)) {
            $product_ids = [$product_ids];
        }

        // Get product data for all products
        $products_data = $this->get_products_data($product_ids);

        if (empty($products_data)) {
            return ['success' => false, 'error' => __('No product images found.', 'shopvision')];
        }

        // Read room image
        $room_image_data = $this->encode_image($room_image_path);

        if (!$room_image_data) {
            return ['success' => false, 'error' => __('Could not read room image.', 'shopvision')];
        }

        // Build the prompt based on preset and single/multi product
        $is_multi = count($products_data) > 1;
        $prompt = $this->get_prompt_for_preset($products_data, $is_multi);

        // Get all product images
        $product_images = [];
        foreach ($products_data as $pd) {
            if (!empty($pd['image'])) {
                $product_images[] = $pd['image'];
            }
        }

        // Build request body with images
        $request_body = $this->build_request_body($prompt, $room_image_data, $product_images, $is_multi);

        // Make API request
        $response = $this->make_request($request_body);

        return $response;
    }

    /**
     * Get product data for multiple products
     */
    private function get_products_data(array $product_ids): array {
        $products_data = [];

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            $image_id = $product->get_image_id();
            if (!$image_id) continue;

            $image_path = get_attached_file($image_id);
            if (!$image_path || !file_exists($image_path)) continue;

            $encoded = $this->encode_image($image_path);
            if (!$encoded) continue;

            // Get product categories for context
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']);

            $products_data[] = [
                'id'         => $product_id,
                'name'       => $product->get_name(),
                'categories' => is_array($categories) ? $categories : [],
                'image'      => $encoded,
            ];
        }

        return $products_data;
    }

    /**
     * Get product images as base64
     * Only uses the main product image to avoid confusion from gallery/lifestyle images
     */
    private function get_product_images(int $product_id): array {
        $product = wc_get_product($product_id);

        if (!$product) {
            return [];
        }

        $images = [];

        // Get main image ONLY - gallery images often show different angles
        // or lifestyle shots that confuse the AI about the product's true form
        $main_image_id = $product->get_image_id();
        if ($main_image_id) {
            $image_path = get_attached_file($main_image_id);
            if ($image_path && file_exists($image_path)) {
                $encoded = $this->encode_image($image_path);
                if ($encoded) {
                    $images[] = $encoded;
                }
            }
        }

        return $images;
    }

    /**
     * Encode image to base64 with mime type
     * Converts HEIC/HEIF to JPEG for better API compatibility
     */
    private function encode_image(string $path): ?array {
        if (!file_exists($path)) {
            return null;
        }

        $mime_type = mime_content_type($path);

        if (!in_array($mime_type, ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'])) {
            return null;
        }

        // Convert HEIC/HEIF to JPEG for faster processing
        if (in_array($mime_type, ['image/heic', 'image/heif'])) {
            $converted = $this->convert_heic_to_jpeg($path);
            if ($converted) {
                return [
                    'mime_type' => 'image/jpeg',
                    'data'      => base64_encode($converted),
                ];
            }
            // If conversion fails, try sending as-is
        }

        $data = file_get_contents($path);

        if ($data === false) {
            return null;
        }

        return [
            'mime_type' => $mime_type,
            'data'      => base64_encode($data),
        ];
    }

    /**
     * Convert HEIC/HEIF to JPEG using ImageMagick or GD
     */
    private function convert_heic_to_jpeg(string $path): ?string {
        // Try ImageMagick first (most reliable for HEIC)
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick($path);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality(85);
                $data = $imagick->getImageBlob();
                $imagick->destroy();
                return $data;
            } catch (\Exception $e) {
                // Fall through to other methods
            }
        }

        // Try WordPress image editor (uses GD or ImageMagick)
        $editor = wp_get_image_editor($path);
        if (!is_wp_error($editor)) {
            $temp_file = wp_tempnam('mrv_heic_') . '.jpg';
            $saved = $editor->save($temp_file, 'image/jpeg');
            if (!is_wp_error($saved) && file_exists($saved['path'])) {
                $data = file_get_contents($saved['path']);
                @unlink($saved['path']);
                return $data ?: null;
            }
        }

        return null;
    }

    /**
     * Get prompt based on current preset
     */
    private function get_prompt_for_preset(array $products_data, bool $is_multi): string {
        switch ($this->preset) {
            case 'fashion':
                return $is_multi
                    ? $this->build_fashion_multi_prompt($products_data)
                    : $this->build_fashion_prompt();

            case 'custom':
                return !empty($this->custom_prompt)
                    ? $this->custom_prompt
                    : $this->build_interior_prompt(); // Fallback to interior

            case 'interior':
            default:
                return $is_multi
                    ? $this->build_interior_multi_prompt($products_data)
                    : $this->build_interior_prompt();
        }
    }

    /**
     * Build the interior prompt for single product
     * Uses edit-style prompting to preserve the product exactly as shown
     */
    private function build_interior_prompt(): string {
        return <<<PROMPT
You are a photo editor. Your task is to composite the furniture product into the room photo.

STRICT RULES - YOU MUST FOLLOW EXACTLY:

1. PRODUCT DIMENSIONS ARE SACRED
   - A chair MUST remain a chair (single seat width)
   - A sofa MUST remain a sofa (same number of seats)
   - NEVER stretch, widen, or resize the product
   - The aspect ratio of the furniture must be IDENTICAL to the reference
   - If the reference shows a narrow chair, the result shows a narrow chair
   - If the reference shows a 3-seater sofa, the result shows a 3-seater sofa

2. COPY THE PRODUCT EXACTLY
   - Same color (do not change hue, saturation, or brightness of the product itself)
   - Same material texture
   - Same design details (stitching, buttons, legs, armrests)
   - Same number of cushions, pillows, or sections
   - Think of it as cutting out the product and pasting it into the room

3. ROOM PLACEMENT
   - Find a logical spot in the room for this type of furniture
   - If similar furniture exists, replace it
   - If the room is empty, place against a suitable wall or area
   - Scale the product realistically for the room's perspective

4. ONLY ADJUST
   - Lighting/shadows to match room ambiance
   - Perspective to match room angle
   - Nothing else about the product

FORBIDDEN:
- Making a chair wider (turning it into a loveseat or sofa)
- Making a sofa narrower (turning it into a chair)
- Changing the color
- Adding or removing legs, cushions, or design elements
- "Improving" or "reimagining" the design

Output the composited room photo.
PROMPT;
    }

    /**
     * Build interior prompt for multiple products
     * AI determines appropriate quantities (e.g., chairs around a table)
     */
    private function build_interior_multi_prompt(array $products_data): string {
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

        return <<<PROMPT
You are a photo editor. Your task is to composite MULTIPLE furniture products into the room photo.

PRODUCTS TO PLACE:{$product_list}

STRICT RULES - YOU MUST FOLLOW EXACTLY:

1. PLACE ALL PRODUCTS
   - Each product reference image shows ONE item to place
   - Place all items in the room in a logical, realistic arrangement
   - Use appropriate quantities based on furniture type:
     * Dining chairs around a table: use 4-6 chairs depending on table size
     * Decorative items: place as a set if it makes sense
     * Individual pieces (sofas, tables): place exactly one

2. PRODUCT DIMENSIONS ARE SACRED
   - A chair MUST remain a chair (single seat width)
   - A sofa MUST remain a sofa (same number of seats)
   - NEVER stretch, widen, or resize any product
   - Maintain correct proportions between all items

3. COPY EACH PRODUCT EXACTLY
   - Same color (do not change hue, saturation, or brightness)
   - Same material texture
   - Same design details
   - Think of it as cutting out each product and pasting it into the room

4. ROOM PLACEMENT
   - Arrange items in a natural, aesthetically pleasing composition
   - Items should relate to each other logically (chairs around table, etc.)
   - Scale all products realistically for the room's perspective
   - Adjust lighting/shadows to match room ambiance

FORBIDDEN:
- Changing product colors or materials
- Stretching or resizing products
- Omitting any of the provided products
- Adding furniture not in the reference images

Output the composited room photo with all products naturally arranged.
PROMPT;
    }

    /**
     * Build fashion prompt for single product
     * Virtual try-on style - show product on model
     */
    private function build_fashion_prompt(): string {
        return <<<PROMPT
Create a product visualization showing how the garment from the reference image would look styled in this setting.

RULES:
1. Maintain the original scene and lighting
2. Show the garment exactly as it appears in the reference - same color, pattern, fabric
3. Ensure natural and realistic presentation
4. Keep the composition balanced

Output the visualization.
PROMPT;
    }

    /**
     * Build fashion prompt for multiple products (complete outfit)
     */
    private function build_fashion_multi_prompt(array $products_data): string {
        $count = count($products_data);

        return <<<PROMPT
Create a product visualization showing how all {$count} garments from the reference images would look styled together in this setting.

RULES:
1. Maintain the original scene and lighting
2. Show each garment exactly as it appears - same colors, patterns, fabrics
3. Ensure natural and realistic presentation
4. Keep the composition balanced

Output the visualization.
PROMPT;
    }

    /**
     * Build the API request body
     * Structure: User image first (to be edited), then product reference images
     */
    private function build_request_body(string $prompt, array $room_image, array $product_images, bool $is_multi = false): array {
        $parts = [];

        // Get preset-specific labels
        $labels = $this->get_request_labels();

        // First: The user image that needs to be edited
        $parts[] = ['text' => $labels['user_image']];
        $parts[] = [
            'inline_data' => [
                'mime_type' => $room_image['mime_type'],
                'data'      => $room_image['data'],
            ],
        ];

        // Second: Product reference images
        if ($is_multi) {
            // Multi-product: label each product image
            foreach ($product_images as $index => $image) {
                $parts[] = ['text' => sprintf($labels['product_multi'], $index + 1)];
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $image['mime_type'],
                        'data'      => $image['data'],
                    ],
                ];
            }
        } else {
            // Single product
            $parts[] = ['text' => $labels['product_single']];
            foreach ($product_images as $image) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => $image['mime_type'],
                        'data'      => $image['data'],
                    ],
                ];
            }
        }

        // Third: The editing instruction
        $parts[] = ['text' => $prompt];

        return [
            'contents' => [
                [
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['image', 'text'],
                'temperature' => 0.0, // Minimize creativity, maximize accuracy
            ],
        ];
    }

    /**
     * Get preset-specific labels for API request
     */
    private function get_request_labels(): array {
        switch ($this->preset) {
            case 'fashion':
                return [
                    'user_image'     => 'Scene image:',
                    'product_single' => 'Garment reference:',
                    'product_multi'  => 'Garment %d reference:',
                ];
            case 'interior':
            default:
                return [
                    'user_image'     => 'Room photo to edit:',
                    'product_single' => 'Furniture product reference (copy this EXACTLY):',
                    'product_multi'  => 'Product %d reference:',
                ];
        }
    }

    /**
     * Make the API request
     */
    private function make_request(array $body): array {
        $url = self::API_ENDPOINT . '?key=' . $this->api_key;

        $response = wp_remote_post($url, [
            'timeout' => 180,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();

            // User-friendly timeout message
            if (strpos($error_message, 'cURL error 28') !== false || strpos($error_message, 'timed out') !== false) {
                return [
                    'success' => false,
                    'error'   => __('The AI service is currently busy. Please try again in a few minutes.', 'shopvision'),
                ];
            }

            // User-friendly connection error
            if (strpos($error_message, 'cURL error') !== false) {
                return [
                    'success' => false,
                    'error'   => __('Could not connect to the AI service. Check your internet connection and try again.', 'shopvision'),
                ];
            }

            return [
                'success' => false,
                'error'   => $error_message,
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = $data['error']['message'] ?? __('API request failed.', 'shopvision');

            // Check for overloaded/rate limit errors
            if ($status_code === 503 || $status_code === 429) {
                return [
                    'success' => false,
                    'error'   => __('The AI service is currently busy. Please try again in a few minutes.', 'shopvision'),
                ];
            }

            return [
                'success' => false,
                'error'   => $error_message,
            ];
        }

        // Check for empty response (API overloaded, silent failure)
        if (empty($data['candidates'][0]['content']['parts'])) {
            return [
                'success' => false,
                'error'   => __('The AI service is currently busy. Please try again in a few minutes.', 'shopvision'),
            ];
        }

        // Extract image from response
        $image_data = $this->extract_image_from_response($data);

        if (!$image_data) {
            // Log the actual response for debugging
            $text_response = $this->extract_text_from_response($data);
            $block_reason = $data['candidates'][0]['finishReason'] ?? '';

            // Check for content filtering
            if ($block_reason === 'SAFETY') {
                return [
                    'success' => false,
                    'error'   => __('The image could not be generated due to content filters. Please try a different photo.', 'shopvision'),
                ];
            }

            // Check for image generation not supported message
            if ($block_reason === 'IMAGE_GENERATION_FAILED' || str_contains($text_response, 'image generation')) {
                return [
                    'success' => false,
                    'error'   => __('Image generation failed. Please try again.', 'shopvision'),
                ];
            }

            // Log for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MRV Gemini Response - No image. Finish reason: ' . $block_reason);
                error_log('MRV Gemini Response - Text: ' . substr($text_response, 0, 500));
            }

            return [
                'success' => false,
                'error'   => __('No image in API response.', 'shopvision') . ($text_response ? ' AI: ' . substr($text_response, 0, 150) : ''),
            ];
        }

        return [
            'success'    => true,
            'image_data' => $image_data,
        ];
    }

    /**
     * Extract generated image from API response
     */
    private function extract_image_from_response(array $response): ?string {
        if (empty($response['candidates'][0]['content']['parts'])) {
            return null;
        }

        foreach ($response['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['inlineData']['data'])) {
                return $part['inlineData']['data'];
            }
        }

        return null;
    }

    /**
     * Extract text response from API (for error messages)
     */
    private function extract_text_from_response(array $response): string {
        if (empty($response['candidates'][0]['content']['parts'])) {
            return '';
        }

        $texts = [];
        foreach ($response['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $texts[] = $part['text'];
            }
        }

        return implode(' ', $texts);
    }
}
