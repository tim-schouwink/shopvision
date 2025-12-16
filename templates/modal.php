<?php
/**
 * Modal template - rendered via JavaScript to avoid theme conflicts
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current preset for UI text variations
$preset = get_option('mrv_preset', 'interior');

// Preset-specific texts
$preset_texts = [
    'interior' => [
        'dropHere'    => __('Drag your room photo here', 'shopvision'),
        'orBrowse'    => __('or click to browse — your visualization starts immediately', 'shopvision'),
        'processing'  => __('AI is creating your visualization...', 'shopvision'),
        'defaultTitle' => __('See it for yourself', 'shopvision'),
        'defaultSubtitle' => __('Upload a photo of your room', 'shopvision'),
    ],
    'fashion' => [
        'dropHere'    => __('Upload a photo of yourself', 'shopvision'),
        'orBrowse'    => __('or click to browse — your visualization starts immediately', 'shopvision'),
        'processing'  => __('AI is fitting the clothing to your photo...', 'shopvision'),
        'defaultTitle' => __('Try it on virtually', 'shopvision'),
        'defaultSubtitle' => __('Upload a photo of yourself', 'shopvision'),
    ],
    'custom' => [
        'dropHere'    => __('Drag your photo here', 'shopvision'),
        'orBrowse'    => __('or click to browse — your visualization starts immediately', 'shopvision'),
        'processing'  => __('AI is creating your visualization...', 'shopvision'),
        'defaultTitle' => __('See it for yourself', 'shopvision'),
        'defaultSubtitle' => __('Upload a photo', 'shopvision'),
    ],
];
$texts = $preset_texts[$preset] ?? $preset_texts['interior'];

// Get example images if enabled
$examples_enabled = get_option('mrv_examples_enabled', false);
$example_images = [];
if ($examples_enabled) {
    for ($i = 1; $i <= 3; $i++) {
        $image_id = get_option('mrv_example_image_' . $i, 0);
        if ($image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'medium');
            if ($image_url) {
                $example_images[] = $image_url;
            }
        }
    }
}
$has_examples = !empty($example_images) && count($example_images) >= 1;

// Get title and subtitle (use preset defaults if not customized)
$examples_text_enabled = get_option('mrv_examples_text_enabled', true);
$examples_title = get_option('mrv_examples_title', '');
$examples_subtitle = get_option('mrv_examples_subtitle', '');
// Fall back to preset defaults if empty
if (empty($examples_title)) {
    $examples_title = $texts['defaultTitle'];
}
if (empty($examples_subtitle)) {
    $examples_subtitle = $texts['defaultSubtitle'];
}
$show_text = $has_examples && $examples_text_enabled;
?>
<script>
(function() {
    // Create modal HTML and inject directly into body
    const modalHTML = `
    <div id="mrv-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:999999;align-items:center;justify-content:center;padding:20px;margin:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
        <div id="mrv-overlay" style="position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.6);"></div>
        <div style="position:relative;width:100%;max-width:500px;max-height:90vh;background:#fff;border-radius:12px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);overflow:auto;z-index:1;">
            <button type="button" id="mrv-close" style="position:absolute;top:12px;right:12px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:#f3f4f6;border:none;border-radius:50%;cursor:pointer;z-index:10;padding:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" style="width:16px;height:16px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div style="padding:32px;">
                <!-- Upload -->
                <div id="mrv-upload" style="display:flex;flex-direction:column;align-items:center;">
                    <?php if ($has_examples): ?>
                    <!-- Examples Deck -->
                    <div class="mrv-examples-deck" style="width:100%;">
                        <?php foreach ($example_images as $index => $image_url): ?>
                        <div class="mrv-deck-card" style="background-image:url('<?php echo esc_url($image_url); ?>');"></div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($show_text): ?>
                    <!-- Title & Subtitle -->
                    <div style="text-align:center;margin-bottom:20px;width:100%;">
                        <h3 style="font-size:22px;font-weight:600;color:#1f2937;margin:0 0 4px;line-height:1.3;"><?php echo esc_html($examples_title); ?></h3>
                        <p style="font-size:15px;color:#6b7280;margin:0;line-height:1.4;"><?php echo esc_html($examples_subtitle); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <!-- Upload Zone -->
                    <div id="mrv-upload-zone" style="display:flex;flex-direction:column;align-items:center;width:100%;padding:24px 20px;border:2px dashed #d1d5db;border-radius:12px;background:#f9fafb;cursor:pointer;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" style="width:24px;height:24px;margin-bottom:10px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <p style="font-size:14px;font-weight:500;color:#374151;margin:0 0 2px;"><?php echo esc_html($texts['dropHere']); ?></p>
                    <p style="font-size:12px;color:#6b7280;margin:0 0 6px;text-align:center;"><?php echo esc_html($texts['orBrowse']); ?></p>
                    <p style="font-size:11px;color:#9ca3af;margin:0;text-align:center;">Maximaal 10MB - JPG, PNG, WebP, HEIC of HEIF</p>
                    <input type="file" id="mrv-file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" style="display:none;">
                    </div>
                    <?php if (get_option('mrv_multi_product_enabled', false)): ?>
                    <!-- Product Slots (Multi-product mode) -->
                    <div id="mrv-product-slots-section" style="display:none;padding:16px 0 0;border-top:1px solid #e5e7eb;margin-top:20px;width:100%;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                            <span style="font-size:13px;font-weight:500;color:#374151;"><?php echo esc_html__('Products in visualization', 'shopvision'); ?></span>
                            <span style="font-size:12px;color:#6b7280;">
                                <span id="mrv-slots-count">0</span>/<span id="mrv-slots-max"><?php echo esc_html(get_option('mrv_multi_product_max_items', 5)); ?></span>
                            </span>
                        </div>
                        <div id="mrv-product-slots" style="display:flex;gap:10px;flex-wrap:wrap;">
                            <!-- Dynamically rendered by JS -->
                        </div>
                        <p style="margin:10px 0 0;font-size:11px;color:#9ca3af;"><?php echo esc_html__('Close the modal to add more products', 'shopvision'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Processing -->
                <div id="mrv-processing" style="display:none;flex-direction:column;align-items:center;padding:40px 24px;text-align:center;">
                    <div id="mrv-spinner" style="width:40px;height:40px;border:3px solid #e5e7eb;border-top-color:var(--mrv-accent,#2563eb);border-radius:50%;animation:mrv-spin 1s linear infinite;margin-bottom:16px;"></div>
                    <p style="font-size:15px;color:#374151;margin:0 0 20px;"><?php echo esc_html($texts['processing']); ?></p>
                    <div style="width:100%;max-width:240px;height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden;">
                        <div id="mrv-progress" style="height:100%;background:var(--mrv-accent,#2563eb);border-radius:2px;width:0%;transition:width 300ms;"></div>
                    </div>
                </div>
                <!-- Result -->
                <div id="mrv-result" style="display:none;flex-direction:column;align-items:center;">
                    <div style="width:100%;margin-bottom:16px;border-radius:8px;overflow:hidden;background:#f3f4f6;">
                        <img id="mrv-image" src="" alt="" style="display:block;width:100%;height:auto;">
                    </div>
                    <!-- Multi-product list (rendered by JS) -->
                    <div id="mrv-result-products" style="display:none;width:100%;margin-bottom:16px;"></div>
                    <!-- Consent Checkbox -->
                    <label id="mrv-consent-label" style="display:flex;align-items:flex-start;gap:10px;width:100%;padding:12px 14px;margin-bottom:16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;cursor:pointer;font-size:13px;line-height:1.4;color:#0c4a6e;">
                        <input type="checkbox" id="mrv-consent" style="width:18px;height:18px;margin-top:1px;accent-color:var(--mrv-accent,#2563eb);cursor:pointer;flex-shrink:0;">
                        <span style="flex:1;"><?php echo esc_html__('I consent to this image being used for marketing and as an example on the website', 'shopvision'); ?></span>
                    </label>
                    <!-- Single product: order + download buttons -->
                    <div id="mrv-result-actions-single" style="display:flex;gap:10px;width:100%;">
                        <a id="mrv-download" href="" download style="display:flex;align-items:center;justify-content:center;gap:6px;flex:1;padding:10px 16px;font-size:13px;font-weight:500;text-decoration:none;border-radius:8px;background:var(--mrv-accent,#2563eb);color:#fff;border:none;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span class="mrv-download-text"><?php echo esc_html(get_option('mrv_download_button_text', __('Download', 'shopvision'))); ?></span>
                        </a>
                        <button type="button" id="mrv-retry" style="display:flex;align-items:center;justify-content:center;gap:6px;flex:1;padding:10px 16px;font-size:13px;font-weight:500;border-radius:8px;background:#f3f4f6;color:#374151;border:1px solid #d1d5db;cursor:pointer;">
                            <?php echo esc_html__('Try again', 'shopvision'); ?>
                        </button>
                    </div>
                    <!-- Multi-product: only download button (full width) -->
                    <div id="mrv-result-actions-multi" style="display:none;width:100%;">
                        <a id="mrv-download-multi" href="" download style="display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:12px 16px;font-size:13px;font-weight:500;text-decoration:none;border-radius:8px;background:var(--mrv-accent,#2563eb);color:#fff;border:none;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span><?php echo esc_html(get_option('mrv_download_button_text', __('Download', 'shopvision'))); ?></span>
                        </a>
                    </div>
                    <?php if (get_option('mrv_whatsapp_enabled', false)): ?>
                    <!-- WhatsApp Button -->
                    <button type="button" id="mrv-whatsapp-btn" style="display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px 16px;margin-top:10px;font-size:13px;font-weight:500;border-radius:8px;background:<?php echo esc_attr(get_option('mrv_whatsapp_bg_color', '#25D366')); ?>;color:<?php echo esc_attr(get_option('mrv_whatsapp_text_color', '#ffffff')); ?>;border:none;cursor:pointer;transition:background 0.15s;" onmouseover="this.style.background='<?php echo esc_attr(get_option('mrv_whatsapp_hover_bg_color', '#128C7E')); ?>'" onmouseout="this.style.background='<?php echo esc_attr(get_option('mrv_whatsapp_bg_color', '#25D366')); ?>'">
                        <svg viewBox="0 0 24 24" fill="currentColor" style="width:18px;height:18px;">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        <span><?php echo esc_html(get_option('mrv_whatsapp_button_text', __('Request quote via WhatsApp', 'shopvision'))); ?></span>
                    </button>
                    <?php endif; ?>
                </div>
                <!-- Error -->
                <div id="mrv-error" style="display:none;flex-direction:column;align-items:center;padding:40px 24px;text-align:center;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="width:48px;height:48px;margin-bottom:16px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <p id="mrv-error-msg" style="font-size:15px;color:#374151;margin:0 0 20px;">Er ging iets mis. Probeer het opnieuw.</p>
                    <button type="button" id="mrv-error-retry" style="padding:10px 20px;font-size:13px;font-weight:500;border-radius:8px;background:var(--mrv-accent,#2563eb);color:#fff;border:none;cursor:pointer;">Opnieuw proberen</button>
                </div>
            </div>
        </div>
    </div>
    <style>@keyframes mrv-spin{to{transform:rotate(360deg)}}</style>`;

    // Insert at end of body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
})();
</script>
