tombola/shopkeep
================



[![Build Status](https://travis-ci.org/tombola/shopkeep.svg?branch=master)](https://travis-ci.org/tombola/shopkeep)

Quick links: [Using](#using) | [Installing](#installing) | [Contributing](#contributing) | [Support](#support)

## Using

This package provides WP-CLI commands for managing and analyzing WooCommerce orders.

### Order Commands

#### Show Order Details

Display detailed information for a single order including all items, addresses, and totals:

```bash
wp order show <order_id>
```

**Example:**
```bash
wp order show 123
```

**Output includes:**
- Order status, date, customer information
- Payment method and order total
- Billing and shipping addresses
- Order items table (Product, SKU, Quantity, Total)
- Order totals breakdown (Subtotal, Shipping, Tax, Discount, Total)

---

#### List Orders by Customer Email

List all orders for a specific customer by email address:

```bash
wp order by_email <email> [--limit=<number>] [--status=<status>]
```

**Parameters:**
- `<email>` - Customer email address (required)
- `--limit=<number>` - Maximum number of orders to display (default: 10)
- `--status=<status>` - Filter by order status (e.g., completed, processing, pending)

**Examples:**
```bash
# List all orders for a customer (default: 10 most recent)
wp order by_email customer@example.com

# List only completed orders
wp order by_email customer@example.com --status=completed

# Show 20 most recent orders
wp order by_email customer@example.com --limit=20

# Combine filters
wp order by_email customer@example.com --status=processing --limit=5
```

**Output includes:**
- Order count summary
- For each order: Order ID, Status, Date, Customer, Total
- Order items table for each order

---

#### Find Duplicate Orders

Find orders with identical items for a customer (useful for detecting duplicate purchases):

```bash
wp order find_duplicates <email> [--status=<status>] [--match-quantity]
```

**Parameters:**
- `<email>` - Customer email address (required)
- `--status=<status>` - Filter by order status (e.g., completed, processing, pending)
- `--match-quantity` - Require exact quantity matches (default: only match products)

**Examples:**
```bash
# Find duplicate orders (same products, any quantity)
wp order find_duplicates customer@example.com

# Find duplicates with exact quantity matches
wp order find_duplicates customer@example.com --match-quantity

# Find duplicates in completed orders only
wp order find_duplicates customer@example.com --status=completed
```

**How it works:**
- Orders are considered duplicates if they contain exactly the same items (no more, no less)
- By default, only product IDs are matched (quantities can differ)
- With `--match-quantity`, both products AND quantities must match exactly
- Results are grouped into "duplicate sets" showing which orders have identical items

**Output includes:**
- Number of duplicate sets found
- For each duplicate set:
  - Common items table (Product, SKU, Quantity)
  - Duplicate orders table (Order ID, Date, Status, Total)

---

### Example: Hello World

The package also includes a simple example command:

```bash
wp hello-world
```

## Installing

Installing this package requires WP-CLI v2.5 or greater. Update to the latest stable release with `wp cli update`.

Once you've done so, you can install the latest stable version of this package with:

```bash
wp package install tombola/shopkeep:@stable
```

To install the latest development version of this package, use the following command instead:

```bash
wp package install tombola/shopkeep:dev-master
```
