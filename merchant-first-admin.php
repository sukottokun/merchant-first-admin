<?php
/**
 * Plugin Name:  Merchant-First Admin
 * Description:  Reshapes wp-admin around store operations: store tasks at the top level, everything WordPress behind one door. Built for demo sites.
 * Version:      1.1.2
 * Author:       Scott Massey
 * License:      GPL-2.0-or-later
 * Text Domain:  merchant-first-admin
 * Requires at least: 6.4
 * Requires PHP: 7.4
 *
 * Escape hatches (admins only):
 *   ?mfa=off      turn the restructure off for your user (persists)
 *   ?mfa=on       turn it back on
 *   ?mfa=notices  show admin notices for this pageload
 *   ?mfa=debug    dump the live $menu / $submenu arrays and stop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Merchant_First_Admin {

	const VERSION  = '1.1.2';
	const OPT_USER = 'mfa_disabled';
	const NONCE    = 'mfa-switch';
	const TEXTDOMAIN = 'merchant-first-admin';

	/**
	 * Offsets into a $menu / $submenu row. Core never names these, so every
	 * plugin that touches the admin menu ends up reading $item[ self::SLUG ] and hoping.
	 */
	const TITLE      = 0;
	const CAP        = 1;
	const SLUG       = 2;
	const PAGE_TITLE = 3;
	const CLASSES    = 4;
	const HOOKNAME   = 5;
	const ICON       = 6;

	/**
	 * Top-level order, by our internal keys. Anything not listed keeps its
	 * relative position at the end.
	 */
	private $order = array(
		'home',
		'orders',
		'products',
		'customers',
		'reports',
		'marketing',
		'payments',
		'wholesale',
		'settings',
		'separator',
		'wordpress',
	);

	/**
	 * How to find each top-level menu on this install. We match the registered
	 * slug against these needles (substring, first match wins) rather than
	 * hardcoding, because Woo changes slugs between versions and HPOS moves
	 * Orders from edit.php to admin.php.
	 */
	private $find_top = array(
		'woo_parent' => array( 'woocommerce' ),
		'payments'   => array( 'tab=checkout' ),
		'products'  => array( 'post_type=product' ),
		'reports'   => array( 'path=/analytics', 'wc-admin&path=%2Fanalytics' ),
		'marketing' => array( 'woocommerce-marketing', 'path=/marketing' ),
		'wholesale' => array( 'wholesale' ),
		'wordpress' => array( 'index.php' ),
	);

	/**
	 * Items to promote out of the WooCommerce submenu onto the top level.
	 * Matched on submenu slug needle, then title needle as a fallback.
	 */
	private $promote = array(
		'home'      => array(
			'title' => 'Home',
			'icon'  => 'dashicons-store',
			'slugs' => array( 'wc-admin' ),
			'names' => array( 'Home' ),
		),
		'orders'    => array(
			'title' => 'Orders',
			'icon'  => 'dashicons-cart',
			'slugs' => array( 'wc-orders', 'post_type=shop_order' ),
			'names' => array( 'Orders' ),
		),
		'customers' => array(
			'title' => 'Customers',
			'icon'  => 'dashicons-groups',
			'slugs' => array( 'path=/customers', 'path=%2Fcustomers' ),
			'names' => array( 'Customers' ),
		),
		'settings'  => array(
			'title' => 'Settings',
			'icon'  => 'dashicons-admin-generic',
			'slugs' => array( 'wc-settings' ),
			'names' => array( 'Settings' ),
		),
	);

	/** Brand-new top-level items that point at an existing screen. */
	private $invent = array(
		'payments' => array(
			'title' => 'Payments',
			'icon'  => 'dashicons-money-alt',
			'slug'  => 'admin.php?page=wc-settings&tab=checkout',
			'cap'   => 'manage_woocommerce',
		),
	);

	/** Renames applied to discovered top-level items. */
	private $rename = array(
		'reports'   => 'Reports',
		'wordpress' => 'WordPress',
	);

	/** Icon overrides. */
	private $icons = array(
		'reports'   => 'dashicons-chart-bar',
		'wordpress' => 'dashicons-wordpress',
	);

	/**
	 * Top-level menus folded into the WordPress drawer, in drawer order.
	 * Slug needles again — these are core so they're stable.
	 */
	private $drawer = array(
		'edit.php',
		'edit.php?post_type=page',
		'upload.php',
		'edit-comments.php',
		'edit-tags.php?taxonomy=link_category',
		'stats',
		'themes.php',
		'plugins.php',
		'users.php',
		'tools.php',
		'options-general.php',
		'jetpack',
	);

	/** Roles that never see the WordPress drawer at all. */
	private $hide_drawer_for = array( 'shop_manager' );

	/** Resolved: internal key => index into $GLOBALS['menu']. */
	private $found = array();

	/** Original submenu slug => the top-level slug it was promoted to. */
	private $promoted = array();

	public static function boot() {
		$self = new self();
		add_action( 'admin_menu', array( $self, 'restructure' ), 9999 );
		add_action( 'admin_menu', array( $self, 'dump' ), 100000 );
		add_action( 'admin_init', array( $self, 'handle_switches' ) );
		add_filter( 'custom_menu_order', array( $self, 'enable_custom_order' ) );
		add_filter( 'menu_order', array( $self, 'apply_order' ) );
		add_filter( 'parent_file', array( $self, 'fix_highlight' ) );
		add_action( 'admin_bar_menu', array( $self, 'clean_admin_bar' ), 999 );
		add_action( 'admin_head', array( $self, 'styles' ) );
		add_action( 'admin_print_scripts', array( $self, 'suppress_notices' ), 1 );
		add_filter( 'admin_footer_text', array( $self, 'footer' ) );
		add_filter( 'update_footer', '__return_empty_string', 11 );
	}

	/* ---------------------------------------------------------------------
	 * State
	 * ------------------------------------------------------------------ */

	public function active() {
		if ( ! is_admin() || ! is_user_logged_in() ) {
			return false;
		}
		if ( defined( 'MFA_DISABLE' ) && MFA_DISABLE ) {
			return false;
		}
		// admin_menu fires before admin_init, so read the switch straight from
		// the request as well as from the stored preference — otherwise ?mfa=off
		// would not take effect until the following pageload. This path only
		// reads; the write lives in handle_switches() behind a nonce.
		if ( isset( $_GET['mfa'] ) && current_user_can( 'manage_options' ) ) {
			$mode = sanitize_key( wp_unslash( $_GET['mfa'] ) );
			if ( 'off' === $mode ) {
				return false;
			}
			if ( 'on' === $mode ) {
				return true;
			}
		}
		return ! get_user_meta( get_current_user_id(), self::OPT_USER, true );
	}

	public function handle_switches() {
		if ( ! current_user_can( 'manage_options' ) || empty( $_GET['mfa'] ) ) {
			return;
		}

		$mode  = sanitize_key( wp_unslash( $_GET['mfa'] ) );
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( 'on' === $mode ) {
			// Restoring the default state. Deliberately nonce-free: this is the
			// recovery path, and requiring a nonce here strands anyone whose
			// preference is already off, because the admin bar toggle that
			// would carry the nonce only renders while the plugin is active.
			delete_user_meta( get_current_user_id(), self::OPT_USER );
			return;
		}

		if ( 'off' === $mode && wp_verify_nonce( $nonce, self::NONCE ) ) {
			update_user_meta( get_current_user_id(), self::OPT_USER, 1 );
		}
	}

	/**
	 * Prints what actually got registered on this install, so the config
	 * above can be tuned against reality instead of guesswork.
	 */
	public function dump() {
		global $menu, $submenu;

		if ( empty( $_GET['mfa'] ) || 'debug' !== sanitize_key( wp_unslash( $_GET['mfa'] ) ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo "MERCHANT-FIRST ADMIN " . self::VERSION . " — live menu dump\n";
		echo "WooCommerce: " . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'not detected' ) . "\n";
		echo 'active():    ' . ( $this->active() ? 'YES' : 'NO — restructure was skipped' ) . "\n";
		echo 'user meta ' . self::OPT_USER . ': ' . var_export( get_user_meta( get_current_user_id(), self::OPT_USER, true ), true ) . "\n";
		echo 'MFA_DISABLE: ' . ( defined( 'MFA_DISABLE' ) ? var_export( MFA_DISABLE, true ) : 'not defined' ) . "\n";
		printf(
			"menu rows: %d   submenu parents: %d   resolved: %d\n",
			count( (array) $menu ),
			count( (array) $submenu ),
			count( $this->found )
		);
		echo str_repeat( '=', 72 ) . "\n\nTOP LEVEL\n\n";
		foreach ( (array) $menu as $pos => $item ) {
			if ( ! is_array( $item ) ) {
				printf( "  %-5s ?? not an array\n", $pos );
				continue;
			}
			if ( empty( $item[ self::TITLE ] ) ) {
				printf( "  %-5s ---- separator ----\n", $pos );
				continue;
			}
			printf( "  %-5s %-28s cap=%-22s slug=%s\n", $pos, wp_strip_all_tags( $item[ self::TITLE ] ), $item[ self::CAP ], $item[ self::SLUG ] );
		}
		echo "\n\nSUBMENUS\n";
		foreach ( (array) $submenu as $parent => $items ) {
			echo "\n  [$parent]\n";
			foreach ( $items as $item ) {
				printf( "      %-28s cap=%-22s slug=%s\n", wp_strip_all_tags( $item[ self::TITLE ] ), $item[ self::CAP ], $item[ self::SLUG ] );
			}
		}
		echo "\n\nRESOLVED BY THIS PLUGIN\n\n";
		foreach ( $this->found as $key => $idx ) {
			printf( "  %-12s -> menu[%s] = %s\n", $key, $idx, isset( $menu[ $idx ][ self::SLUG ] ) ? $menu[ $idx ][ self::SLUG ] : '?' );
		}
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Discovery helpers
	 * ------------------------------------------------------------------ */

	private function find_top_index( $needles ) {
		global $menu;
		// Exact matches across the whole menu first: 'woocommerce' must not
		// resolve to 'woocommerce-marketing', and 'edit.php' must not resolve
		// to 'edit.php?post_type=page'.
		foreach ( (array) $needles as $needle ) {
			foreach ( (array) $menu as $i => $item ) {
				if ( ! empty( $item[ self::SLUG ] ) && $item[ self::SLUG ] === $needle ) {
					return $i;
				}
			}
		}
		foreach ( (array) $needles as $needle ) {
			foreach ( (array) $menu as $i => $item ) {
				if ( ! empty( $item[ self::SLUG ] ) && false !== strpos( $item[ self::SLUG ], $needle ) ) {
					return $i;
				}
			}
		}
		return false;
	}

	private function find_sub( $parent, $slugs, $names ) {
		global $submenu;
		if ( empty( $submenu[ $parent ] ) ) {
			return false;
		}
		foreach ( (array) $slugs as $needle ) {
			foreach ( $submenu[ $parent ] as $k => $item ) {
				if ( ! empty( $item[ self::SLUG ] ) && $item[ self::SLUG ] === $needle ) {
					return $k;
				}
			}
		}
		foreach ( (array) $slugs as $needle ) {
			foreach ( $submenu[ $parent ] as $k => $item ) {
				if ( ! empty( $item[ self::SLUG ] ) && false !== strpos( $item[ self::SLUG ], $needle ) ) {
					return $k;
				}
			}
		}
		foreach ( (array) $names as $name ) {
			foreach ( $submenu[ $parent ] as $k => $item ) {
				if ( 0 === strcasecmp( trim( wp_strip_all_tags( $item[ self::TITLE ] ) ), $name ) ) {
					return $k;
				}
			}
		}
		return false;
	}

	/** The post type a bare core file implies when none is in the query string. */
	private $implied_type = array(
		'edit.php'     => 'post',
		'post-new.php' => 'post',
		'upload.php'   => 'attachment',
	);

	/** Does the current screen belong to this menu slug? */
	private function is_current( $slug ) {
		global $pagenow, $typenow;

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page ) {
			return ( false !== strpos( $slug, 'page=' . $page ) ) || ( $slug === $page );
		}

		$file = strtok( $slug, '?' );
		if ( $file !== $pagenow ) {
			return false;
		}

		$query = (string) wp_parse_url( $slug, PHP_URL_QUERY );
		parse_str( $query, $args );
		$slug_type = isset( $args['post_type'] ) ? $args['post_type'] : '';
		$now_type  = $typenow ? $typenow : '';

		// 'edit.php' owns the Posts screen even though $typenow is 'post'
		// there, while 'edit.php?post_type=page' owns Pages.
		if ( '' === $slug_type ) {
			$implied = isset( $this->implied_type[ $file ] ) ? $this->implied_type[ $file ] : '';
			return ( '' === $now_type || $now_type === $implied );
		}
		return $slug_type === $now_type;
	}

	/* ---------------------------------------------------------------------
	 * The restructure
	 * ------------------------------------------------------------------ */

	public function restructure() {
		global $menu, $submenu;

		if ( ! $this->active() ) {
			return;
		}

		// 1. Locate the menus we know about.
		foreach ( $this->find_top as $key => $needles ) {
			$idx = $this->find_top_index( $needles );
			if ( false !== $idx ) {
				$this->found[ $key ] = $idx;
			}
		}

		// 2. Promote Orders / Customers / Settings onto the top level.
		//
		// The originals are deliberately LEFT REGISTERED in $submenu. WordPress
		// resolves a plugin page's permissions through its original parent —
		// 'wc-admin' is keyed as woocommerce_page_wc-admin — so removing the
		// entry makes every promoted page die with "Sorry, you are not allowed
		// to access this page." We hide the old parent from $menu instead,
		// which stops it rendering while leaving the registration intact.
		$wc_parent = isset( $this->found['woo_parent'] ) ? $menu[ $this->found['woo_parent'] ][2] : 'woocommerce';

		foreach ( $this->promote as $key => $spec ) {
			$k = $this->find_sub( $wc_parent, $spec['slugs'], $spec['names'] );
			if ( false === $k ) {
				continue;
			}
			$item = $submenu[ $wc_parent ][ $k ];

			$slug = $item[ self::SLUG ];
			// Submenu slugs are relative to admin.php unless they name their own file.
			if ( false === strpos( $slug, '.php' ) ) {
				$slug = 'admin.php?page=' . $slug;
			}

			$pos          = $this->free_position();
			$menu[ $pos ] = array(
				$this->label( $spec['title'] ),
				$item[ self::CAP ],
				$slug,
				isset( $item[ self::PAGE_TITLE ] ) ? $item[ self::PAGE_TITLE ] : $spec['title'],
				'menu-top mfa-promoted',
				'mfa-' . $key,
				$spec['icon'],
			);
			$this->found[ $key ]        = $pos;
			$this->promoted[ $item[ self::SLUG ] ] = $slug;
		}

		// 3. Invent Payments only if this Woo version doesn't already ship it.
		foreach ( $this->invent as $key => $spec ) {
			if ( isset( $this->found[ $key ] ) || ! current_user_can( $spec['cap'] ) ) {
				continue;
			}
			$pos          = $this->free_position();
			$menu[ $pos ] = array(
				$this->label( $spec['title'] ),
				$spec['cap'],
				$spec['slug'],
				$spec['title'],
				'menu-top mfa-invented',
				'mfa-' . $key,
				$spec['icon'],
			);
			$this->found[ $key ] = $pos;
		}

		// 4. Hide the old WooCommerce top-level. Its submenu stays registered.
		if ( isset( $this->found['woo_parent'] ) ) {
			unset( $menu[ $this->found['woo_parent'] ], $this->found['woo_parent'] );
		}

		// 5. Rename and re-icon.
		foreach ( $this->rename as $key => $title ) {
			if ( isset( $this->found[ $key ], $menu[ $this->found[ $key ] ] ) ) {
				$menu[ $this->found[ $key ] ][0] = $title;
				$menu[ $this->found[ $key ] ][3] = $title;
			}
		}
		foreach ( $this->icons as $key => $icon ) {
			if ( isset( $this->found[ $key ], $menu[ $this->found[ $key ] ] ) ) {
				$menu[ $this->found[ $key ] ][6] = $icon;
			}
		}

		// 6. Fold WordPress-land into the drawer.
		$this->build_drawer();

		// 7. Drop every stock separator and place exactly one, immediately
		//    above the WordPress drawer.
		foreach ( (array) $menu as $i => $item ) {
			$classes = isset( $item[ self::CLASSES ] ) ? $item[ self::CLASSES ] : '';
			if ( false !== strpos( $classes, 'wp-menu-separator' ) ) {
				unset( $menu[ $i ] );
			}
		}
		if ( isset( $this->found['wordpress'] ) ) {
			$pos                        = $this->free_position();
			$menu[ $pos ]               = array( '', 'read', 'mfa-separator', '', 'wp-menu-separator' );
			$this->found['separator']   = $pos;
		}
	}

	/**
	 * Move core menus under the renamed Dashboard ("WordPress").
	 *
	 * The item that owns the current screen is deliberately left on the top
	 * level, so its own submenu (Categories, Tags, Themes, ...) stays
	 * reachable once you've stepped into WordPress-land.
	 */
	private function build_drawer() {
		global $menu, $submenu;

		if ( ! isset( $this->found['wordpress'] ) ) {
			return;
		}
		$parent = $menu[ $this->found['wordpress'] ][2]; // index.php

		if ( $this->user_has_role( $this->hide_drawer_for ) ) {
			unset( $menu[ $this->found['wordpress'] ], $this->found['wordpress'] );
			foreach ( $this->drawer as $needle ) {
				$i = $this->find_exact_top( $needle );
				if ( false !== $i ) {
					unset( $menu[ $i ] );
				}
			}
			return;
		}

		$dash = isset( $submenu[ $parent ] ) ? $submenu[ $parent ] : array();
		$new  = array();

		// Keep the real Dashboard as the drawer's first entry.
		foreach ( $dash as $item ) {
			if ( ! empty( $item[ self::SLUG ] ) && 'index.php' === $item[ self::SLUG ] ) {
				$item[ self::TITLE ] = __( 'Dashboard', 'merchant-first-admin' );
				$new[]   = $item;
				break;
			}
		}

		foreach ( $this->drawer as $needle ) {
			$i = $this->find_exact_top( $needle );
			if ( false === $i || ! isset( $menu[ $i ] ) ) {
				continue;
			}
			$item = $menu[ $i ];

			if ( $this->is_current( $item[ self::SLUG ] ) ) {
				continue; // leave it expanded on the top level
			}

			$new[] = array(
				wp_strip_all_tags( $item[ self::TITLE ] ),
				$item[ self::CAP ],
				$item[ self::SLUG ],
				isset( $item[ self::PAGE_TITLE ] ) ? $item[ self::PAGE_TITLE ] : $item[ self::TITLE ],
			);
			unset( $menu[ $i ] );
		}

		if ( $new ) {
			$submenu[ $parent ] = $new;
		}
	}

	private function find_exact_top( $needle ) {
		return $this->find_top_index( array( $needle ) );
	}

	/**
	 * Translate a menu label.
	 *
	 * The labels live in property defaults, which PHP will not let us wrap in
	 * __() at declaration, so translation happens here instead. Every string
	 * passed in is a literal from the config arrays above.
	 *
	 * @param string $text Untranslated label.
	 * @return string
	 */
	private function label( $text ) {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
		return __( $text, self::TEXTDOMAIN );
	}

	private function free_position() {
		global $menu;
		$pos = 90;
		while ( isset( $menu[ (string) $pos ] ) || isset( $menu[ $pos ] ) ) {
			$pos++;
		}
		return $pos;
	}

	private function user_has_role( $roles ) {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->roles ) {
			return false;
		}
		return (bool) array_intersect( (array) $roles, (array) $user->roles );
	}

	/* ---------------------------------------------------------------------
	 * Ordering
	 * ------------------------------------------------------------------ */

	public function enable_custom_order( $enabled ) {
		return $this->active() ? true : $enabled;
	}

	public function apply_order( $slugs ) {
		global $menu;

		if ( ! $this->active() || ! $this->found ) {
			return $slugs;
		}

		$wanted = array();
		foreach ( $this->order as $key ) {
			if ( isset( $this->found[ $key ], $menu[ $this->found[ $key ] ][2] ) ) {
				$wanted[] = $menu[ $this->found[ $key ] ][2];
			}
		}

		$rest = array_diff( $slugs, $wanted );
		return array_merge( $wanted, $rest );
	}

	/* ---------------------------------------------------------------------
	 * Chrome around the menu
	 * ------------------------------------------------------------------ */

	public function clean_admin_bar( $bar ) {
		if ( ! $this->active() ) {
			// Still offer the way back in.
			if ( current_user_can( 'manage_options' ) ) {
				$bar->add_node( array(
					'id'    => 'mfa-toggle',
					'title' => __( 'Merchant view: off', 'merchant-first-admin' ),
					'href'  => add_query_arg( 'mfa', 'on' ),
				) );
			}
			return;
		}

		foreach ( array( 'wp-logo', 'comments', 'customize', 'updates', 'search' ) as $node ) {
			$bar->remove_node( $node );
		}

		// "Howdy, Scott" -> just the name.
		$account = $bar->get_node( 'my-account' );
		if ( $account ) {
			$bar->add_node( array(
				'id'    => 'my-account',
				'title' => str_replace( 'Howdy, ', '', $account->title ),
			) );
		}

		// Live toggle back to stock WordPress — handy mid-demo.
		if ( current_user_can( 'manage_options' ) ) {
			$bar->add_node( array(
				'id'    => 'mfa-toggle',
				'title' => __( 'Merchant view: on', 'merchant-first-admin' ),
				'href'  => wp_nonce_url( add_query_arg( 'mfa', 'off' ), self::NONCE ),
				'meta'  => array( 'title' => __( 'Switch back to the stock WordPress menu', 'merchant-first-admin' ) ),
			) );
		}
	}

	public function suppress_notices() {
		if ( ! $this->active() || isset( $_GET['mfa'] ) ) {
			return;
		}
		// Only on store screens — leave core update notices alone in WP-land.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		$store = ( false !== strpos( $screen->id, 'woocommerce' ) )
			|| ( false !== strpos( $screen->id, 'wc-' ) )
			|| ( isset( $screen->post_type ) && 'product' === $screen->post_type );

		if ( $store ) {
			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
		}
	}

	public function styles() {
		if ( ! $this->active() ) {
			return;
		}
		echo '<style id="mfa-styles">
			#adminmenu li.mfa-sep { height:1px; margin:8px 12px; background:rgba(255,255,255,.13); padding:0; }
			#adminmenu .wp-menu-image img { opacity:.9; }
			#adminmenu a.menu-top { font-size:13px; }
			#wp-admin-bar-mfa-toggle .ab-item { opacity:.75; }
			#wpfooter { display:none; }
		</style>';
	}

	public function footer( $text ) {
		return $this->active() ? '' : $text;
	}

	/**
	 * A promoted page still reports its original parent, which is no longer in
	 * the menu — so nothing highlights. Point it at the new top-level instead.
	 */
	public function fix_highlight( $parent_file ) {
		global $plugin_page;

		if ( ! $this->active() || empty( $plugin_page ) ) {
			return $parent_file;
		}
		return isset( $this->promoted[ $plugin_page ] ) ? $this->promoted[ $plugin_page ] : $parent_file;
	}

}

Merchant_First_Admin::boot();
