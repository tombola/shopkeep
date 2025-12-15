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
		$admin_url = \admin_url( "post.php?post={$order_id}&action=edit" );
		$order_data = array(
			'Status'         => $order->get_status(),
			'Date Created'   => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
			'Customer'       => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'Email'          => $order->get_billing_email(),
			'Phone'          => $order->get_billing_phone(),
			'Payment Method' => $order->get_payment_method_title(),
			'Total'          => \html_entity_decode( \wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
			'Admin URL'      => $admin_url,
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
			$admin_url = \admin_url( "post.php?post={$order_id}&action=edit" );
			WP_CLI::line( sprintf( 'Status: %s', WP_CLI::colorize( "%Y{$order->get_status()}%n" ) ) );
			WP_CLI::line( sprintf( 'Date: %s', $order->get_date_created()->date( 'Y-m-d H:i:s' ) ) );
			WP_CLI::line( sprintf( 'Customer: %s', $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) );
			WP_CLI::line( sprintf( 'Total: %s', \html_entity_decode( \wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ) ) );
			WP_CLI::line( sprintf( 'Admin URL: %s', $admin_url ) );

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

	/**
	 * Finds duplicate orders (same items) for a customer by email address.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : The customer email address.
	 *
	 * [--status=<status>]
	 * : Filter by order status (e.g., completed, processing, pending).
	 *
	 * [--match-quantity]
	 * : Require exact quantity matches (default: only match products).
	 *
	 * ## EXAMPLES
	 *
	 *     # Find duplicate orders for a customer
	 *     $ wp order find_duplicates customer@example.com
	 *
	 *     # Find duplicates with exact quantity matches
	 *     $ wp order find_duplicates customer@example.com --match-quantity
	 *
	 *     # Find duplicates in completed orders only
	 *     $ wp order find_duplicates customer@example.com --status=completed
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function find_duplicates( $args, $assoc_args ) {
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
		$status         = isset( $assoc_args['status'] ) ? $assoc_args['status'] : 'any';
		$match_quantity = isset( $assoc_args['match-quantity'] );

		// Build query arguments
		$query_args = array(
			'billing_email' => $email,
			'limit'         => -1, // Get all orders
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

		if ( count( $orders ) < 2 ) {
			WP_CLI::success( "Only one order found. No duplicates possible." );
			return;
		}

		// Build order signatures
		// Note: A signature is based on ALL items in an order. Two orders are only
		// considered duplicates if they have exactly the same items (no more, no less).
		$order_signatures = array();

		foreach ( $orders as $order ) {
			$order_id = $order->get_id();
			$items    = $order->get_items();

			if ( empty( $items ) ) {
				continue;
			}

			// Create signature for this order based on ALL items
			$signature_parts = array();

			foreach ( $items as $item ) {
				$product = $item->get_product();
				if ( ! $product ) {
					continue;
				}

				$product_id = $product->get_id();
				$quantity   = $item->get_quantity();

				if ( $match_quantity ) {
					$signature_parts[] = $product_id . ':' . $quantity;
				} else {
					$signature_parts[] = $product_id;
				}
			}

			// Sort to ensure consistent signature
			\sort( $signature_parts );
			$signature = \md5( \implode( '|', $signature_parts ) );

			// Store order with its signature
			if ( ! isset( $order_signatures[ $signature ] ) ) {
				$order_signatures[ $signature ] = array();
			}

			$order_signatures[ $signature ][] = array(
				'order'     => $order,
				'order_id'  => $order_id,
				'signature' => $signature_parts,
			);
		}

		// Find duplicates (signatures with more than one order)
		$duplicates = array();
		foreach ( $order_signatures as $signature => $orders_group ) {
			if ( count( $orders_group ) > 1 ) {
				$duplicates[ $signature ] = $orders_group;
			}
		}

		if ( empty( $duplicates ) ) {
			WP_CLI::success( "No duplicate orders found for {$email}" );
			return;
		}

		// Display results
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( "%B=== Duplicate Orders for {$email} ===%n" ) );
		WP_CLI::line( WP_CLI::colorize( "%RFound " . count( $duplicates ) . " set(s) of duplicate orders%n" ) );
		WP_CLI::line( '' );

		$set_number = 1;
		foreach ( $duplicates as $signature => $orders_group ) {
			WP_CLI::line( \str_repeat( '=', 80 ) );
			WP_CLI::line( WP_CLI::colorize( "%BDuplicate Set #{$set_number}%n - " . count( $orders_group ) . " orders with identical items" ) );
			WP_CLI::line( \str_repeat( '=', 80 ) );

			// Display the common items
			$first_order = $orders_group[0]['order'];
			$items       = $first_order->get_items();

			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( "%Y--- Common Items ---%n" ) );

			$item_data = array();
			foreach ( $items as $item ) {
				$product      = $item->get_product();
				$product_name = $item->get_name();
				$sku          = $product ? $product->get_sku() : '';
				$quantity     = $item->get_quantity();

				$item_data[] = array(
					'Product' => $product_name,
					'SKU'     => $sku ?: 'N/A',
					'Qty'     => $quantity,
				);
			}

			WP_CLI\Utils\format_items( 'table', $item_data, array( 'Product', 'SKU', 'Qty' ) );

			// Display the duplicate orders
			WP_CLI::line( '' );
			WP_CLI::line( WP_CLI::colorize( "%Y--- Duplicate Orders ---%n" ) );

			$orders_table = array();
			foreach ( $orders_group as $order_info ) {
				$order    = $order_info['order'];
				$order_id = $order_info['order_id'];
				$admin_url = \admin_url( "post.php?post={$order_id}&action=edit" );

				$orders_table[] = array(
					'Order ID'  => $order_id,
					'Date'      => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
					'Status'    => $order->get_status(),
					'Total'     => \html_entity_decode( \wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
					'Admin URL' => $admin_url,
				);
			}

			WP_CLI\Utils\format_items( 'table', $orders_table, array( 'Order ID', 'Date', 'Status', 'Total', 'Admin URL' ) );

			WP_CLI::line( '' );
			$set_number++;
		}

		WP_CLI::success( 'Duplicate order search completed.' );
	}

	/**
	 * Scans all orders in a date range to find duplicates grouped by customer email.
	 *
	 * ## OPTIONS
	 *
	 * --start=<date>
	 * : Start date for the scan (YYYY-MM-DD format).
	 *
	 * [--end=<date>]
	 * : End date for the scan (YYYY-MM-DD format). Default: today
	 *
	 * [--status=<status>]
	 * : Filter by order status (e.g., completed, processing, pending).
	 *
	 * [--match-quantity]
	 * : Require exact quantity matches (default: only match products).
	 *
	 * ## EXAMPLES
	 *
	 *     # Scan all orders from January 2024
	 *     $ wp order scan_duplicates --start=2024-01-01 --end=2024-01-31
	 *
	 *     # Scan completed orders from last month to today
	 *     $ wp order scan_duplicates --start=2024-11-01 --status=completed
	 *
	 *     # Scan with exact quantity matching
	 *     $ wp order scan_duplicates --start=2024-01-01 --match-quantity
	 *
	 * @param array $args       Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function scan_duplicates( $args, $assoc_args ) {
		// Check if WooCommerce is active
		if ( ! \function_exists( 'wc_get_orders' ) ) {
			WP_CLI::error( 'WooCommerce is not active.' );
			return;
		}

		// Validate and get start date
		if ( ! isset( $assoc_args['start'] ) ) {
			WP_CLI::error( 'Start date is required. Use --start=YYYY-MM-DD' );
			return;
		}

		$start_date = $assoc_args['start'];
		$end_date   = isset( $assoc_args['end'] ) ? $assoc_args['end'] : \date( 'Y-m-d' );
		$status     = isset( $assoc_args['status'] ) ? $assoc_args['status'] : 'any';
		$match_quantity = isset( $assoc_args['match-quantity'] );

		// Build query arguments
		$query_args = array(
			'limit'        => -1, // Get all orders
			'date_created' => $start_date . '...' . $end_date,
			'orderby'      => 'date',
			'order'        => 'DESC',
		);

		if ( $status !== 'any' ) {
			$query_args['status'] = $status;
		}

		// Fetch all orders in the date range
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( "%B=== Scanning Orders for Duplicates ===%n" ) );
		WP_CLI::line( "Date Range: {$start_date} to {$end_date}" );
		WP_CLI::line( '' );
		WP_CLI::line( 'Fetching orders...' );

		$orders = \wc_get_orders( $query_args );

		if ( empty( $orders ) ) {
			WP_CLI::warning( "No orders found in date range: {$start_date} to {$end_date}" );
			return;
		}

		WP_CLI::line( WP_CLI::colorize( "%GFound " . count( $orders ) . " order(s) to analyze%n" ) );
		WP_CLI::line( '' );

		// Group orders by email address
		$orders_by_email = array();

		foreach ( $orders as $order ) {
			// Skip refunds - they don't have billing info and aren't regular orders
			if ( $order->get_type() === 'shop_order_refund' ) {
				continue;
			}

			$email = $order->get_billing_email();
			if ( empty( $email ) ) {
				continue;
			}

			if ( ! isset( $orders_by_email[ $email ] ) ) {
				$orders_by_email[ $email ] = array();
			}
			$orders_by_email[ $email ][] = $order;
		}

		WP_CLI::line( 'Analyzing ' . count( $orders_by_email ) . ' unique email addresses...' );
		WP_CLI::line( '' );

		// Find duplicates for each email
		$emails_with_duplicates = array();

		foreach ( $orders_by_email as $email => $customer_orders ) {
			// Skip if only one order for this email
			if ( count( $customer_orders ) < 2 ) {
				continue;
			}

			// Build order signatures for this email
			$order_signatures = array();

			foreach ( $customer_orders as $order ) {
				$order_id = $order->get_id();
				$items    = $order->get_items();

				if ( empty( $items ) ) {
					continue;
				}

				// Create signature for this order
				$signature_parts = array();

				foreach ( $items as $item ) {
					$product = $item->get_product();
					if ( ! $product ) {
						continue;
					}

					$product_id = $product->get_id();
					$quantity   = $item->get_quantity();

					if ( $match_quantity ) {
						$signature_parts[] = $product_id . ':' . $quantity;
					} else {
						$signature_parts[] = $product_id;
					}
				}

				// Sort to ensure consistent signature
				\sort( $signature_parts );
				$signature = \md5( \implode( '|', $signature_parts ) );

				// Store order with its signature
				if ( ! isset( $order_signatures[ $signature ] ) ) {
					$order_signatures[ $signature ] = array();
				}

				$order_signatures[ $signature ][] = array(
					'order'     => $order,
					'order_id'  => $order_id,
					'signature' => $signature_parts,
				);
			}

			// Find duplicates for this email
			$duplicates = array();
			foreach ( $order_signatures as $signature => $orders_group ) {
				if ( count( $orders_group ) > 1 ) {
					$duplicates[ $signature ] = $orders_group;
				}
			}

			// Store if duplicates found
			if ( ! empty( $duplicates ) ) {
				$emails_with_duplicates[ $email ] = $duplicates;
			}
		}

		// Display results
		if ( empty( $emails_with_duplicates ) ) {
			WP_CLI::success( 'No duplicate orders found in the specified date range.' );
			return;
		}

		WP_CLI::line( \str_repeat( '=', 80 ) );
		WP_CLI::line( WP_CLI::colorize( "%R=== SCAN RESULTS ===%n" ) );
		WP_CLI::line( WP_CLI::colorize( "%RFound duplicates for " . count( $emails_with_duplicates ) . " email address(es)%n" ) );
		WP_CLI::line( \str_repeat( '=', 80 ) );
		WP_CLI::line( '' );

		// Display duplicates for each email
		foreach ( $emails_with_duplicates as $email => $duplicates ) {
			WP_CLI::line( \str_repeat( '-', 80 ) );
			WP_CLI::line( WP_CLI::colorize( "%B{$email}%n - " . count( $duplicates ) . " duplicate set(s)" ) );
			WP_CLI::line( \str_repeat( '-', 80 ) );

			$set_number = 1;
			foreach ( $duplicates as $signature => $orders_group ) {
				WP_CLI::line( '' );
				WP_CLI::line( WP_CLI::colorize( "%YDuplicate Set #{$set_number}:%n " . count( $orders_group ) . " orders with identical items" ) );

				// Display the common items (compact format)
				$first_order = $orders_group[0]['order'];
				$items       = $first_order->get_items();
				$item_names  = array();

				foreach ( $items as $item ) {
					$product_name = $item->get_name();
					$quantity     = $item->get_quantity();
					$item_names[] = $product_name . ' (×' . $quantity . ')';
				}

				WP_CLI::line( 'Items: ' . \implode( ', ', $item_names ) );

				// Display the duplicate orders in a compact table
				$orders_table = array();
				foreach ( $orders_group as $order_info ) {
					$order     = $order_info['order'];
					$order_id  = $order_info['order_id'];
					$admin_url = \admin_url( "post.php?post={$order_id}&action=edit" );

					$orders_table[] = array(
						'Order ID'  => $order_id,
						'Date'      => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
						'Status'    => $order->get_status(),
						'Total'     => \html_entity_decode( \wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
						'Admin URL' => $admin_url,
					);
				}

				WP_CLI\Utils\format_items( 'table', $orders_table, array( 'Order ID', 'Date', 'Status', 'Total', 'Admin URL' ) );

				$set_number++;
			}

			WP_CLI::line( '' );
		}

		WP_CLI::success( 'Duplicate scan completed. Found duplicates for ' . count( $emails_with_duplicates ) . ' email address(es).' );
	}
}
