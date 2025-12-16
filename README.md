# Shopvision

AI-powered product visualization for WooCommerce. Let customers see your products in their own space using Google Gemini AI.

## Features

- **Interior Mode** - Visualize furniture, decoration, and lighting in customer's rooms
- **Fashion Mode** - Virtual try-on for clothing, shoes, and accessories
- **Multi-product Visualization** - Combine multiple products in one visualization
- **WhatsApp Integration** - Let customers request quotes directly via WhatsApp
- **Customizable Styling** - Match the button to your store's design
- **Full i18n Support** - English source language with Dutch translation included

## Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.0+
- Google Gemini API key

## Installation

1. Download the latest release
2. Upload to `/wp-content/plugins/shopvision`
3. Activate the plugin
4. Go to WooCommerce → Shopvision
5. Enter your Google Gemini API key
6. Select which products should show the visualizer

## Configuration

### API Setup

1. Go to [Google AI Studio](https://aistudio.google.com/apikey)
2. Create a new API key
3. Enable the Generative Language API
4. Paste the key in Shopvision settings

### Industry Presets

- **Interior** - Optimized for furniture, home decor, lighting
- **Fashion** - Optimized for clothing, virtual try-on
- **Custom** - Write your own AI prompt

### Button Placement

- Automatic (after add to cart button)
- Automatic (below product form)
- Manual via shortcode: `[shopvision]`

## Translations

Shopvision uses English as the source language. Translations can be added via:

- [Loco Translate](https://wordpress.org/plugins/loco-translate/) plugin
- POEdit
- Custom `.po` files in `/languages/`

Dutch translation (`nl_NL`) is included.

## License

GPL-2.0+

## Author

[Tim Schouwink](https://timschouwink.com)
