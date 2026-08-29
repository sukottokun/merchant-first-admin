<?php
define( 'ABSPATH', '/fake/' );
$GLOBALS['_filters'] = array();

function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['_filters'][$h][] = $cb; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['_filters'][$h][] = $cb; }
function remove_all_actions( $h ) {}
function is_admin() { return true; }
function is_user_logged_in() { return true; }
function get_current_user_id() { return 1; }
function get_user_meta( $u, $k, $s = false ) { return ''; }
function update_user_meta( $u, $k, $v ) {}
function delete_user_meta( $u, $k ) {}
function current_user_can( $c ) { return true; }
function wp_strip_all_tags( $t ) { return trim( strip_tags( (string) $t ) ); }
function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $k ) ); }
function sanitize_text_field( $t ) { return trim( (string) $t ); }
function wp_unslash( $v ) { return $v; }
function esc_html( $t ) { return $t; }
function add_query_arg() { return '#'; }
function plugin_basename( $f ) { return 'merchant-first-admin/merchant-first-admin.php'; }
function get_current_screen() { return null; }
function __return_empty_string() { return ''; }
function __return_true() { return true; }
function wp_get_current_user() { $u = new stdClass; $u->roles = array( $GLOBALS['_role'] ); return $u; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }

$scenario = isset( $argv[1] ) ? $argv[1] : 'store';
$GLOBALS['_role'] = ( 'shopmgr' === $scenario ) ? 'shop_manager' : 'administrator';

// ---- A realistic WP 6.x + WooCommerce 9.x + Jetpack + Wholesale menu ----
$menu = array(
	'2'    => array( 'Dashboard', 'read', 'index.php', '', 'menu-top', 'menu-dashboard', 'dashicons-dashboard' ),
	'3'    => array( 'Jetpack', 'manage_options', 'jetpack', '', 'menu-top', 'toplevel_page_jetpack', 'dashicons-jetpack' ),
	'4'    => array( '', 'read', 'separator1', '', 'wp-menu-separator' ),
	'5'    => array( 'Posts', 'edit_posts', 'edit.php', '', 'menu-top', 'menu-posts', 'dashicons-admin-post' ),
	'10'   => array( 'Media', 'upload_files', 'upload.php', '', 'menu-top', 'menu-media', 'dashicons-admin-media' ),
	'20'   => array( 'Pages', 'edit_pages', 'edit.php?post_type=page', '', 'menu-top', 'menu-pages', 'dashicons-admin-page' ),
	'25'   => array( 'Comments', 'edit_posts', 'edit-comments.php', '', 'menu-top', 'menu-comments', 'dashicons-admin-comments' ),
	'55.5' => array( 'WooCommerce', 'manage_woocommerce', 'woocommerce', '', 'menu-top', 'toplevel_page_woocommerce', 'dashicons-cart' ),
	'55.6' => array( 'Products', 'edit_products', 'edit.php?post_type=product', '', 'menu-top', 'menu-products', 'dashicons-archive' ),
	'55.7' => array( 'Analytics', 'view_woocommerce_reports', 'wc-admin&path=/analytics/overview', '', 'menu-top', 'toplevel_page_wc-admin-path--analytics-overview', 'dashicons-chart-bar' ),
	'58'   => array( 'Marketing', 'manage_woocommerce', 'woocommerce-marketing', '', 'menu-top', 'toplevel_page_woocommerce-marketing', 'dashicons-megaphone' ),
	'58.5' => array( 'Wholesale', 'manage_woocommerce', 'wholesale-for-woocommerce', '', 'menu-top', 'toplevel_page_wholesale', 'dashicons-tag' ),
	'59'   => array( '', 'read', 'separator2', '', 'wp-menu-separator' ),
	'60'   => array( 'Appearance', 'switch_themes', 'themes.php', '', 'menu-top', 'menu-appearance', 'dashicons-admin-appearance' ),
	'65'   => array( 'Plugins', 'activate_plugins', 'plugins.php', '', 'menu-top', 'menu-plugins', 'dashicons-admin-plugins' ),
	'70'   => array( 'Users', 'list_users', 'users.php', '', 'menu-top', 'menu-users', 'dashicons-admin-users' ),
	'75'   => array( 'Tools', 'edit_posts', 'tools.php', '', 'menu-top', 'menu-tools', 'dashicons-admin-tools' ),
	'80'   => array( 'Settings', 'manage_options', 'options-general.php', '', 'menu-top', 'menu-settings', 'dashicons-admin-settings' ),
);

$submenu = array(
	'index.php'   => array(
		array( 'Home', 'read', 'index.php' ),
		array( 'Updates', 'update_core', 'update-core.php' ),
	),
	'edit.php'    => array(
		array( 'All Posts', 'edit_posts', 'edit.php' ),
		array( 'Categories', 'manage_categories', 'edit-tags.php?taxonomy=category' ),
		array( 'Tags', 'manage_categories', 'edit-tags.php?taxonomy=post_tag' ),
	),
	'woocommerce' => array(
		array( 'Home', 'manage_woocommerce', 'wc-admin' ),
		array( 'Orders', 'edit_shop_orders', ( 'legacy' === $scenario ) ? 'edit.php?post_type=shop_order' : 'wc-orders' ),
		array( 'Customers', 'manage_woocommerce', 'wc-admin&path=/customers' ),
		array( 'Coupons', 'manage_woocommerce', 'wc-admin&path=/marketing/coupons' ),
		array( 'Settings', 'manage_woocommerce', 'wc-settings' ),
		array( 'Status', 'manage_woocommerce', 'wc-status' ),
		array( 'Extensions', 'manage_woocommerce', 'wc-addons' ),
	),
	'themes.php'  => array(
		array( 'Themes', 'switch_themes', 'themes.php' ),
		array( 'Editor', 'edit_theme_options', 'site-editor.php' ),
	),
);

// Which screen are we pretending to be on?
switch ( $scenario ) {
	case 'posts':
		$pagenow = 'edit.php';  $typenow = 'post';  break;
	case 'pages':
		$pagenow = 'edit.php';  $typenow = 'page';  $GLOBALS['_qs'] = 'post_type=page'; break;
	default:
		$pagenow = 'admin.php'; $typenow = '';      $_GET['page'] = 'wc-admin';
}
echo "SCENARIO: $scenario  (role={$GLOBALS['_role']}, pagenow=$pagenow, typenow=$typenow)\n";

require dirname( __DIR__ ) . '/merchant-first-admin.php';

// Fire admin_menu callbacks (the plugin registers restructure at 9999).
foreach ( $GLOBALS['_filters']['admin_menu'] as $cb ) { call_user_func( $cb ); }

// Emulate wp-admin/menu.php's custom ordering block.
$order = array();
foreach ( $menu as $item ) { $order[] = $item[2]; }
foreach ( $GLOBALS['_filters']['menu_order'] as $cb ) { $order = call_user_func( $cb, $order ); }

$by_slug = array();
foreach ( $menu as $item ) { $by_slug[ $item[2] ] = $item; }

echo "RESULTING TOP-LEVEL MENU\n" . str_repeat( '=', 64 ) . "\n";
$n = 0;
foreach ( $order as $slug ) {
	if ( ! isset( $by_slug[ $slug ] ) ) { continue; }
	$item = $by_slug[ $slug ];
	if ( '' === $item[0] ) { echo "     ----------------\n"; continue; }
	printf( "%2d.  %-14s %s\n", ++$n, wp_strip_all_tags( $item[0] ), $slug );
	if ( ! empty( $submenu[ $slug ] ) ) {
		foreach ( $submenu[ $slug ] as $sub ) {
			printf( "         - %-14s %s\n", wp_strip_all_tags( $sub[0] ), $sub[2] );
		}
	}
}
