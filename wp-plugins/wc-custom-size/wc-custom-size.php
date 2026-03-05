<?php
/**
 * Plugin Name: WC Custom Size Pricing
 * Plugin URI:  https://example.com
 * Description: Adds width × height (meters) inputs on product pages for products with the "custom-size" attribute set to "yes". Price = W × H × Rate (replaces the regular price entirely). Rate is configured per-product in the product admin.
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: wc-custom-size
 * Requires at least: 5.9
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Custom_Size_Pricing {

    public function __construct() {
        // Admin ─ add rate field to product General tab
        add_action( 'woocommerce_product_options_pricing', [ $this, 'add_rate_field' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_rate_field' ] );

        // Frontend ─ render size inputs above the Add-to-Cart button
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'display_size_inputs' ] );

        // Enqueue real-time price preview script
        add_action( 'wp_footer', [ $this, 'enqueue_preview_script' ] );

        // Cart ─ store dimensions + override calculated price
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 2 );
        add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'restore_cart_item_from_session' ], 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'override_cart_price' ], 20 );

        // Cart / checkout display
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_data' ], 10, 2 );

        // Order ─ persist dimensions in order item meta
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_order_item_meta' ], 10, 3 );

        // Validation before add-to-cart
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_size_inputs' ], 10, 2 );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns true when the product has the "custom-size" attribute with value "yes".
     */
    private function is_custom_size_product( int $product_id ): bool {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return false;
        }

        $attributes = $product->get_attributes();
        $attr_key   = 'pa_custom-size'; // taxonomy slug WC creates from attribute "custom-size"

        if ( ! isset( $attributes[ $attr_key ] ) ) {
            return false;
        }

        /** @var WC_Product_Attribute $attribute */
        $attribute = $attributes[ $attr_key ];

        foreach ( $attribute->get_options() as $term_id ) {
            $term = get_term( $term_id, $attr_key );
            if ( $term && ! is_wp_error( $term ) && strtolower( $term->slug ) === 'yes' ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the per-m² rate stored on the product, or 0.
     */
    private function get_rate( int $product_id ): float {
        return (float) get_post_meta( $product_id, '_custom_size_rate', true );
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  ADMIN – rate meta field
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Adds a "Custom Size Rate" field to the product Pricing section.
     */
    public function add_rate_field(): void {
        global $post;

        if ( ! $this->is_custom_size_product( (int) $post->ID ) ) {
            return;
        }

        echo '<div class="options_group">';

        woocommerce_wp_text_input( [
            'id'                => '_custom_size_rate',
            'label'             => sprintf(
                /* translators: %s: currency symbol */
                __( 'Custom Size Rate (%s / m²)', 'wc-custom-size' ),
                get_woocommerce_currency_symbol()
            ),
            'desc_tip'          => true,
            'description'       => __( 'Final price = Width (m) × Height (m) × this rate. Replaces the regular price.', 'wc-custom-size' ),
            'type'              => 'number',
            'custom_attributes' => [ 'step' => '0.01', 'min' => '0' ],
        ] );

        echo '</div>';
    }

    /**
     * Saves the rate field on product save.
     */
    public function save_rate_field( int $post_id ): void {
        if ( isset( $_POST['_custom_size_rate'] ) ) {
            update_post_meta(
                $post_id,
                '_custom_size_rate',
                wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_custom_size_rate'] ) ) )
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  FRONTEND – size input fields
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Renders width/height inputs and inlines the data the JS preview needs.
     */
    public function display_size_inputs(): void {
        global $product;

        if ( ! $this->is_custom_size_product( $product->get_id() ) ) {
            return;
        }

        $rate = $this->get_rate( $product->get_id() );
        ?>
        <div class="custom-size-fields" id="custom-size-fields">
            <h4 class="custom-size-title">
                <?php esc_html_e( 'Enter Your Custom Size', 'wc-custom-size' ); ?>
            </h4>

            <p class="form-row form-row-first">
                <label for="custom_width">
                    <?php esc_html_e( 'Width (m)', 'wc-custom-size' ); ?>
                    <abbr class="required" title="<?php esc_attr_e( 'required', 'wc-custom-size' ); ?>">*</abbr>
                </label>
                <input
                    type="number"
                    id="custom_width"
                    name="custom_width"
                    class="input-text"
                    placeholder="<?php esc_attr_e( 'e.g. 1.50', 'wc-custom-size' ); ?>"
                    min="0.01"
                    step="0.01"
                    required
                >
            </p>

            <p class="form-row form-row-last">
                <label for="custom_height">
                    <?php esc_html_e( 'Height (m)', 'wc-custom-size' ); ?>
                    <abbr class="required" title="<?php esc_attr_e( 'required', 'wc-custom-size' ); ?>">*</abbr>
                </label>
                <input
                    type="number"
                    id="custom_height"
                    name="custom_height"
                    class="input-text"
                    placeholder="<?php esc_attr_e( 'e.g. 2.00', 'wc-custom-size' ); ?>"
                    min="0.01"
                    step="0.01"
                    required
                >
            </p>

            <p class="form-row form-row-wide custom-size-preview" id="custom-size-preview" aria-live="polite"></p>
        </div>

        <style>
            .custom-size-fields { margin-bottom: 1.5em; padding: 1em; border: 1px solid #e0e0e0; border-radius: 4px; }
            .custom-size-title  { margin: 0 0 .75em; font-size: 1em; font-weight: 600; }
            .custom-size-fields .form-row { display: inline-block; width: 48%; vertical-align: top; }
            .custom-size-fields label { display: block; margin-bottom: .3em; font-size: .9em; }
            .custom-size-fields input { width: 100%; }
            .custom-size-preview { font-weight: 700; font-size: 1.1em; color: #333; margin-top: .5em; }
        </style>

        <script>
            window.wcCustomSize = {
                rate:     <?php echo json_encode( $rate ); ?>,
                decimals: <?php echo json_encode( wc_get_price_decimals() ); ?>,
                decSep:   <?php echo json_encode( wc_get_price_decimal_separator() ); ?>,
                thouSep:  <?php echo json_encode( wc_get_price_thousand_separator() ); ?>,
                symbol:   <?php echo json_encode( get_woocommerce_currency_symbol() ); ?>,
                format:   <?php echo json_encode( get_woocommerce_price_format() ); ?>
            };
        </script>
        <?php
    }

    /**
     * Outputs the small inline JS that handles the live price preview.
     * Hooked to wp_footer so we are sure jQuery is already loaded.
     */
    public function enqueue_preview_script(): void {
        if ( ! is_product() ) {
            return;
        }

        global $product;
        if ( ! $product || ! $this->is_custom_size_product( $product->get_id() ) ) {
            return;
        }
        ?>
        <script>
        (function ($) {
            'use strict';

            /**
             * Format a raw numeric price using WooCommerce's configured
             * currency symbol, separators and display format.
             *
             * @param  {number} price
             * @return {string}
             */
            function formatPrice(price) {
                var cfg = window.wcCustomSize;
                if (!cfg) { return price.toFixed(2); }

                // Build decimal string
                var fixed  = price.toFixed(cfg.decimals);
                var parts  = fixed.split('.');
                // Thousands separator
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thouSep);
                // Decimal separator
                var number = cfg.decimals > 0
                    ? parts[0] + cfg.decSep + (parts[1] || '')
                    : parts[0];

                // Apply WC price format (e.g. '%1$s%2$s' → '$12.00')
                return cfg.format
                    .replace('%1$s', cfg.symbol)
                    .replace('%2$s', number);
            }

            function updatePreview() {
                var cfg = window.wcCustomSize;
                if (!cfg || !cfg.rate) { return; }

                var w     = parseFloat($('#custom_width').val())  || 0;
                var h     = parseFloat($('#custom_height').val()) || 0;
                var price = w * h * cfg.rate;
                var $prev = $('#custom-size-preview');

                if (w > 0 && h > 0) {
                    $prev.html(
                        '<?php echo esc_js( __( 'Your price:', 'wc-custom-size' ) ); ?> '
                        + '<span class="woocommerce-Price-amount amount">' + formatPrice(price) + '</span>'
                    );
                } else {
                    $prev.html('');
                }
            }

            $(document).on('input change', '#custom_width, #custom_height', updatePreview);

        }(jQuery));
        </script>
        <?php
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  VALIDATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Prevents add-to-cart if dimensions are missing or ≤ 0.
     */
    public function validate_size_inputs( bool $passed, int $product_id ): bool {
        if ( ! $this->is_custom_size_product( $product_id ) ) {
            return $passed;
        }

        $width  = isset( $_POST['custom_width'] )  ? (float) $_POST['custom_width']  : 0;
        $height = isset( $_POST['custom_height'] ) ? (float) $_POST['custom_height'] : 0;

        if ( $width <= 0 || $height <= 0 ) {
            wc_add_notice(
                __( 'Please enter a valid width and height (greater than 0) for your custom size.', 'wc-custom-size' ),
                'error'
            );
            return false;
        }

        return $passed;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  CART – store dimensions & override price
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Attaches custom size data to the cart item so WC stores it in the session.
     */
    public function add_cart_item_data( array $cart_item_data, int $product_id ): array {
        if ( ! $this->is_custom_size_product( $product_id ) ) {
            return $cart_item_data;
        }

        $width  = isset( $_POST['custom_width'] )  ? (float) $_POST['custom_width']  : 0;
        $height = isset( $_POST['custom_height'] ) ? (float) $_POST['custom_height'] : 0;
        $rate   = $this->get_rate( $product_id );

        if ( $width > 0 && $height > 0 && $rate > 0 ) {
            $cart_item_data['custom_size'] = [
                'width'  => $width,
                'height' => $height,
                'rate'   => $rate,
                'price'  => round( $width * $height * $rate, wc_get_price_decimals() ),
            ];
            // Unique key so duplicate custom-size items are kept separate in the cart
            $cart_item_data['custom_size_key'] = md5( microtime() . wp_rand() );
        }

        return $cart_item_data;
    }

    /**
     * Restores custom size data when cart is loaded from the session.
     */
    public function restore_cart_item_from_session( array $cart_item, array $values ): array {
        if ( isset( $values['custom_size'] ) ) {
            $cart_item['custom_size'] = $values['custom_size'];
        }
        return $cart_item;
    }

    /**
     * Overrides the cart item price with the calculated W × H × Rate value.
     *
     * @param WC_Cart $cart
     */
    public function override_cart_price( \WC_Cart $cart ): void {
        // Skip during admin non-AJAX requests to avoid interfering with admin WC queries
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! empty( $cart_item['custom_size'] ) ) {
                $cart_item['data']->set_price( $cart_item['custom_size']['price'] );
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  CART / CHECKOUT / ORDER – display & persistence
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Shows the custom size in cart and checkout item tables.
     */
    public function display_cart_item_data( array $item_data, array $cart_item ): array {
        if ( ! empty( $cart_item['custom_size'] ) ) {
            $s = $cart_item['custom_size'];
            $item_data[] = [
                'key'   => __( 'Custom Size', 'wc-custom-size' ),
                'value' => sprintf( '%.2f m &times; %.2f m', $s['width'], $s['height'] ),
            ];
        }
        return $item_data;
    }

    /**
     * Saves dimensions and rate to the order line item (visible in WC admin orders).
     *
     * @param WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $values
     */
    public function save_order_item_meta( \WC_Order_Item_Product $item, string $cart_item_key, array $values ): void {
        if ( ! empty( $values['custom_size'] ) ) {
            $s = $values['custom_size'];
            $item->add_meta_data(
                __( 'Custom Size', 'wc-custom-size' ),
                sprintf( '%.2f m × %.2f m', $s['width'], $s['height'] )
            );
            $item->add_meta_data(
                __( 'Rate (per m²)', 'wc-custom-size' ),
                wc_price( $s['rate'] )
            );
        }
    }
}

// Boot the plugin only after WooCommerce has loaded
add_action( 'woocommerce_loaded', function () {
    new WC_Custom_Size_Pricing();
} );
