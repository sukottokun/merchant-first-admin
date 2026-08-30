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
function __( $t, $d = null ) { return $t; }
function _e( $t, $d = null ) { echo $t; }
function esc_html__( $t, $d = null ) { return $t; }
function wp_verify_nonce( $n, $a ) { return 'valid' === $n ? 1 : false; }
function wp_create_nonce( $a ) { return 'valid'; }
function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=valid'; }
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

// ---- Mirrors a live WooCommerce 11.x admin menu (WP 6.x + Jetpack + a B2B extension) ----
$menu = array(
	'2'       => array( 'Dashboard', 'read', 'index.php', '', 'menu-top', 'menu-dashboard', 'dashicons-dashboard' ),
	'2.09281' => array( 'Stats', 'view_stats', 'stats', '', 'menu-top', 'toplevel_page_stats', 'dashicons-chart-area' ),
	'3'       => array( 'Jetpack', 'jetpack_admin_page', 'jetpack', '', 'menu-top', 'toplevel_page_jetpack', 'dashicons-jetpack' ),
	'4'       => array( '', 'read', 'separator1', '', 'wp-menu-separator' ),
	'5'       => array( 'Posts', 'edit_posts', 'edit.php', '', 'menu-top', 'menu-posts', 'dashicons-admin-post' ),
	'10'      => array( 'Media', 'upload_files', 'upload.php', '', 'menu-top', 'menu-media', 'dashicons-admin-media' ),
	'15'      => array( 'Links', 'manage_links', 'edit-tags.php?taxonomy=link_category', '', 'menu-top', 'menu-links', 'dashicons-admin-links' ),
	'20'      => array( 'Pages', 'edit_pages', 'edit.php?post_type=page', '', 'menu-top', 'menu-pages', 'dashicons-admin-page' ),
	'25'      => array( 'Comments <span class="awaiting-mod count-0"><span class="pending-count" aria-hidden="true">0</span><span class="comments-in-moderation-text screen-reader-text">0 Comments in moderation</span></span>', 'edit_posts', 'edit-comments.php', '', 'menu-top', 'menu-comments', 'dashicons-admin-comments' ),
	'26'      => array( 'Products', 'edit_products', 'edit.php?post_type=product', '', 'menu-top', 'menu-products', 'dashicons-archive' ),
	'51'      => array( 'Wholesale', 'manage_wholesale', 'wwp_wholesale', '', 'menu-top', 'toplevel_page_wwp', 'dashicons-tag' ),
	'55.5'    => array( 'WooCommerce', 'edit_others_shop_orders', 'woocommerce', '', 'menu-top', 'toplevel_page_woocommerce', 'dashicons-cart' ),
	'56'      => array( 'Payments', 'manage_woocommerce', 'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM', '', 'menu-top', 'toplevel_page_payments', 'dashicons-money-alt' ),
	'57'      => array( 'Analytics', 'view_woocommerce_reports', 'wc-admin&path=/analytics/overview', '', 'menu-top', 'toplevel_page_wc-analytics', 'dashicons-chart-bar' ),
	'58'      => array( 'Marketing', 'manage_woocommerce', 'woocommerce-marketing', '', 'menu-top', 'toplevel_page_woocommerce-marketing', 'dashicons-megaphone' ),
	'59'      => array( '', 'read', 'separator2', '', 'wp-menu-separator' ),
	'60'      => array( 'Appearance', 'switch_themes', 'themes.php', '', 'menu-top', 'menu-appearance', 'dashicons-admin-appearance' ),
	'65'      => array( 'Plugins <span class="update-plugins count-2"><span class="update-count">2</span></span>', 'activate_plugins', 'plugins.php', '', 'menu-top', 'menu-plugins', 'dashicons-admin-plugins' ),
	'70'      => array( 'Users', 'list_users', 'users.php', '', 'menu-top', 'menu-users', 'dashicons-admin-users' ),
	'75'      => array( 'Tools', 'edit_posts', 'tools.php', '', 'menu-top', 'menu-tools', 'dashicons-admin-tools' ),
	'80'      => array( 'Settings', 'manage_options', 'options-general.php', '', 'menu-top', 'menu-settings', 'dashicons-admin-settings' ),
	'99'      => array( '', 'read', 'separator3', '', 'wp-menu-separator' ),
	'100'     => array( '', 'read', 'separator4', '', 'wp-menu-separator' ),
);

$submenu = array(
	'index.php'   => array(
		array( 'Home', 'read', 'index.php' ),
		array( 'Updates', 'update_plugins', 'update-core.php' ),
	),
	'edit.php'    => array(
		array( 'All Posts', 'edit_posts', 'edit.php' ),
		array( 'Categories', 'manage_categories', 'edit-tags.php?taxonomy=category' ),
		array( 'Tags', 'manage_post_tags', 'edit-tags.php?taxonomy=post_tag' ),
	),
	'woocommerce' => array(
		array( 'Home', 'read', 'wc-admin' ),
		array( 'Orders', 'edit_shop_orders', ( 'legacy' === $scenario ) ? 'edit.php?post_type=shop_order' : 'wc-orders' ),
		array( 'Subscriptions', 'edit_shop_orders', 'wc-orders--shop_subscription' ),
		array( 'Orders', 'edit_shop_orders', 'edit.php?post_type=shop_order' ),
		array( 'Live Branches', 'read', 'wc-admin&path=/live-branches' ),
		array( 'Customers', 'view_woocommerce_reports', 'wc-admin&path=/customers' ),
		array( 'Coupons', 'manage_options', 'coupons-moved' ),
		array( 'Reports', 'view_woocommerce_reports', 'wc-reports' ),
		array( 'Settings', 'manage_woocommerce', 'wc-settings' ),
		array( 'Status', 'manage_woocommerce', 'wc-status' ),
		array( 'Extensions', 'view_woocommerce_reports', 'wc-admin&path=/extensions' ),
	),
	'wc-admin&path=/analytics/overview' => array(
		array( 'Overview', 'view_woocommerce_reports', 'wc-admin&path=/analytics/overview' ),
		array( 'Products', 'view_woocommerce_reports', 'wc-admin&path=/analytics/products' ),
		array( 'Revenue', 'view_woocommerce_reports', 'wc-admin&path=/analytics/revenue' ),
		array( 'Orders', 'view_woocommerce_reports', 'wc-admin&path=/analytics/orders' ),
		array( 'Stock', 'view_woocommerce_reports', 'wc-admin&path=/analytics/stock' ),
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

echo "\nREGISTRATION CHECK\n";
echo isset( $submenu['woocommerce'] )
	? "  PASS  \$submenu['woocommerce'] still registered (" . count( $submenu['woocommerce'] ) . " entries), promoted pages keep their permissions\n"
	: "  FAIL  \$submenu['woocommerce'] was destroyed, promoted pages will 403\n";
$tops = array();
foreach ( $menu as $m ) { if ( '' !== $m[0] ) { $tops[] = wp_strip_all_tags( $m[0] ); } }
$dupes = array_keys( array_filter( array_count_values( $tops ), function ( $n ) { return $n > 1; } ) );
echo $dupes ? "  FAIL  duplicate top-level items: " . implode( ', ', $dupes ) . "\n" : "  PASS  no duplicate top-level items\n";

$drawer_labels = array();
foreach ( (array) $submenu['index.php'] as $d ) { $drawer_labels[] = $d[0]; }
$dirty = array_filter( $drawer_labels, function ( $l ) { return (bool) preg_match( '/\d|moderation|<span/', $l ); } );
echo $dirty
	? "  FAIL  drawer labels still carry count markup: " . implode( ' | ', $dirty ) . "\n"
	: "  PASS  drawer labels are clean\n";

$bad = array();
foreach ( (array) $submenu as $parent => $items ) {
	if ( false === strpos( (string) $parent, 'admin.php' ) ) { continue; }
	foreach ( $items as $i ) {
		if ( false === strpos( $i[2], '.php' ) ) { $bad[] = $parent . ' > ' . $i[2]; }
	}
}
echo $bad
	? "  FAIL  promoted submenu slugs would render as file paths: " . implode( ' | ', $bad ) . "\n"
	: "  PASS  promoted submenu slugs are admin.php-resolvable\n";

$tops = array();
foreach ( $menu as $m ) { if ( '' !== $m[0] ) { $tops[] = wp_strip_all_tags( $m[0] ); } }
echo in_array( 'Reports', $tops, true )
	? "  FAIL  Reports still has its own top-level, so it will double-highlight with Home\n"
	: "  PASS  no duplicate Reports top-level\n";
$home = null;
foreach ( $menu as $m ) { if ( 'Home' === $m[0] ) { $home = $m[2]; } }
echo ( $home && ! empty( $submenu[ $home ] ) )
	? "  PASS  Home carries the analytics sub-reports (" . count( $submenu[ $home ] ) . ")\n"
	: "  FAIL  Home has no sub-reports\n";

$drawer_slugs = array();
foreach ( (array) $submenu['index.php'] as $d ) { $drawer_slugs[] = $d[2]; }
echo in_array( 'update-core.php', $drawer_slugs, true )
	? "  PASS  Updates is reachable from the WordPress drawer\n"
	: "  FAIL  Updates was dropped, so update-core.php has no menu route\n";
