<?php

namespace WP_CLI\HelloWorld;

use WP_CLI;
use WP_CLI_Command;

class OrderCommand extends WP_CLI_Command {

	/**
	 * Displays order details with all order items.
	 *
	 * ## OPTIONS
	 *
	 * <order_id>
	 * : The WooCommerce order ID to display.
	 *
	 * ## EXAMPLES
	 *
	 *     # Display order details for order ID 123
	 *     $ wp order show 123
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function show( $args, $assoc_args ) {
		// Check if WooCommerce is active
		if ( ! \function_exists( 'wc_get_order' ) ) {
			WP_CLI::error( 'WooCommerce is not active.' );
			return;
		}

		// Get order ID from arguments
		$order_id = $args[0];

		// Fetch the order
		$order = \wc_get_order( $order_id );

		if ( ! $order ) {
			WP_CLI::error( "Order #{$order_id} not found." );
			return;
		}

		// Display order header
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( "%B=== Order #{$order_id} ===%n" ) );
		WP_CLI::line( '' );

		// Display order details
		$order_data = array(
			'Status'         => $order->get_status(),
			'Date Created'   => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'Customer'       => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'Email'          => $order->get_billing_email(),
			'Phone'          => $order->get_billing_phone(),
			'Payment Method' => $order->get_payment_method_title(),
			'Total'          => \html_entity_decode( \wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
		);

		foreach ( $order_data as $label => $value ) {
			WP_CLI::line( sprintf( '%s: %s', WP_CLI::colorize( "%Y{$label}%n" ), $value ) );
		}

		// Display billing address
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( "%Y--- Billing Address ---%n" ) );
		$billing_address = $order->get_formatted_billing_address();
		$billing_address = \str_replace( array( '<br/>', '<br>', '<br />' ), "\n", $billing_address );
		$billing_address = \html_entity_decode( \wp_strip_all_tags( $billing_address ), ENT_QUOTES, 'UTF-8' );
		WP_CLI::line( $billing_address );

		// Display shipping address if different
		if ( $order->has_shipping_address() ) {
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( "%Y--- Shipping Address ---%n" ) );
			$shipping_address = $order->get_formatted_shipping_address();
		$shipping_address = \str_replace( array( '<br/>', '<br>', '<br />' ), "\n", $shipping_address );
		$shipping_address = \html_entity_decode( \wp_strip_all_tags( $shipping_address ), ENT_QUOTES, 'UTF-8' );
		WP_CLI::line( $shipping_address );
		}

		// Display order items
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( "%Y--- Order Items ---%n" ) );
		WP_CLI::line( '' );

		$items = $order->get_items();

		if ( empty( $items ) ) {
			WP_CLI::line( 'No items in this order.' );
		} else {
			$item_data = array();

			foreach ( $items as $item_id => $item ) {
				$product      = $item->get_product();
				$product_name = $item->get_name();
				$sku          = $product ? $product->get_sku() : '';
				$quantity     = $item->get_quantity();
				$subtotal     = $order->get_formatted_line_subtotal( $item );
				$total        = \wc_price( $item->get_total() );

				$item_data[] = array(
					'ID'       => $item_id,
					'Product'  => $product_name,
					'SKU'      => $sku ?: 'N/A',
					'Qty'      => $quantity,
					'Subtotal' => \html_entity_decode( \wp_strip_all_tags( $subtotal ), ENT_QUOTES, 'UTF-8' ),
					'Total'    => \html_entity_decode( \wp_strip_all_tags( $total ), ENT_QUOTES, 'UTF-8' ),
				);
			}

			WP_CLI\Utils\format_items( 'table', $item_data, array( 'ID', 'Product', 'SKU', 'Qty', 'Subtotal', 'Total' ) );
		}

		// Display order totals
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( "%Y--- Order Totals ---%n" ) );
		WP_CLI::line( sprintf( 'Subtotal: %s', \html_entity_decode( \wp_strip_all_tags( \wc_price( $order->get_subtotal() ) ), ENT_QUOTES, 'UTF-8' ) ) );
		WP_CLI::line( sprintf( 'Shipping: %s', \html_entity_decode( \wp_strip_all_tags( \wc_price( $order->get_shipping_total() ) ), ENT_QUOTES, 'UTF-8' ) ) );
		WP_CLI::line( sprintf( 'Tax: %s', \html_entity_decode( \wp_strip_all_tags( \wc_price( $order->get_total_tax() ) ), ENT_QUOTES, 'UTF-8' ) ) );
		WP_CLI::line( sprintf( 'Discount: %s', \html_entity_decode( \wp_strip_all_tags( \wc_price( $order->get_discount_total() ) ), ENT_QUOTES, 'UTF-8' ) ) );
		WP_CLI::line( sprintf( '%s: %s', WP_CLI::colorize( "%GTotal%n" ), \html_entity_decode( \wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ) ) );
		WP_CLI::line( '' );

		WP_CLI::success( "Order #{$order_id} displayed successfully." );
	}

	/**
	 * Lists all orders for a customer by email address.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : The customer email address.
	 *
	 * [--limit=<number>]
	 * : Maximum number of orders to display. Default: 10
	 *
	 * [--status=<status>]
	 * : Filter by order status (e.g., completed, processing, pending).
	 *
	 * ## EXAMPLES
	 *
	 *     # List all orders for a customer
	 *     $ wp order by_email customer@example.com
	 *
	 *     # List only completed orders
	 *     $ wp order by_email customer@example.com --status=completed
	 *
	 *     # Limit to 5 orders
	 *     $ wp order by_email customer@example.com --limit=5
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function by_email( $args, $assoc_args ) {
		// Check if WooCommerce is active
		if ( ! \function_exists( 'wc_get_orders' ) ) {
			WP_CLI::error( 'WooCommerce is not active.' );
			return;
		}

		// Get email from arguments
		$email = $args[0];

		// Validate email
		if ( ! \is_email( $email ) ) {
			WP_CLI::error( "Invalid email address: {$email}" );
			return;
		}

		// Get optional parameters
		$limit  = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 10;
		$status = isset( $assoc_args['status'] ) ? $assoc_args['status'] : 'any';

		// Build query arguments
		$query_args = array(
			'billing_email' => $email,
			'limit'         => $limit,
			'orderby'       => 'date',
			'order'         => 'DESC',
		);

		if ( $status !== 'any' ) {
			$query_args['status'] = $status;
		}

		// Fetch orders
		$orders = \wc_get_orders( $query_args );

		if ( empty( $orders ) ) {
			WP_CLI::warning( "No orders found for email: {$email}" );
			return;
		}

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( "%B=== Orders for {$email} ===%n" ) );
		WP_CLI::line( WP_CLI::colorize( "%GFound " . count( $orders ) . " order(s)%n" ) );
		WP_CLI::line( '' );

		foreach ( $orders as $order ) {
			$order_id = $order->get_id();

			// Display order header
			WP_CLI::line( \str_repeat( '=', 80 ) );
			WP_CLI::line( WP_CLI::colorize( "%BOrder #{$order_id}%n" ) );
			WP_CLI::line( \str_repeat( '=', 80 ) );

			// Display basic order info
			WP_CLI::line( sprintf( 'Status: %s', WP_CLI::colorize( "%Y{$order->get_status()}%n" ) ) );
			WP_CLI::line( sprintf( 'Date: %s', $order->get_date_created()->date( 'Y-m-d H:i:s' ) ) );
			WP_CLI::line( sprintf( 'Customer: %s', $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) );
			WP_CLI::line( sprintf( 'Total: %s', \html_entity_decode( \wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ) ) );

			// Display order items
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( "%Y--- Order Items ---%n" ) );

			$items = $order->get_items();

			if ( empty( $items ) ) {
				WP_CLI::line( 'No items in this order.' );
			} else {
				$item_data = array();

				foreach ( $items as $item ) {
					$product      = $item->get_product();
					$product_name = $item->get_name();
					$sku          = $product ? $product->get_sku() : '';
					$quantity     = $item->get_quantity();
					$total        = \wc_price( $item->get_total() );

					$item_data[] = array(
						'Product'  => $product_name,
						'SKU'      => $sku ?: 'N/A',
						'Qty'      => $quantity,
						'Total'    => \html_entity_decode( \wp_strip_all_tags( $total ), ENT_QUOTES, 'UTF-8' ),
					);
				}

				WP_CLI\Utils\format_items( 'table', $item_data, array( 'Product', 'SKU', 'Qty', 'Total' ) );
			}

			WP_CLI::line( '' );
		}

		WP_CLI::success( 'Orders listed successfully.' );
	}
}
