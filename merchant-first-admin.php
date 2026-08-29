<?php
/**
 * Plugin Name:  Merchant-First Admin
 * Description:  Reshapes wp-admin around store operations: store tasks at the top level, everything WordPress behind one door. Built for demo sites.
 * Version:      1.0.0
 * Author:       Scott Massey
 * License:      GPL-2.0-or-later
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

	const VERSION  = '1.0.0';
	const OPT_USER = 'mfa_disabled';

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
		'home'      => array( 'page=woocommerce', 'woocommerce' ),
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
		'home'      => 'Home',
		'reports'   => 'Reports',
		'wordpress' => 'WordPress',
	);

	/** Icon overrides. */
	private $icons = array(
		'home'      => 'dashicons-store',
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

	public static function boot() {
		$self = new self();
		add_action( 'admin_menu', array( $self, 'restructure' ), 9999 );
		add_action( 'admin_menu', array( $self, 'dump' ), 100000 );
		add_action( 'admin_init', array( $self, 'handle_switches' ) );
		add_filter( 'custom_menu_order', array( $self, 'enable_custom_order' ) );
		add_filter( 'menu_order', array( $self, 'apply_order' ) );
		add_filter( 'all_plugins', array( $self, 'hide_self' ) );
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
		// would not take effect until the following pageload.
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
		$mode = sanitize_key( wp_unslash( $_GET['mfa'] ) );

		if ( 'off' === $mode ) {
			update_user_meta( get_current_user_id(), self::OPT_USER, 1 );
		} elseif ( 'on' === $mode ) {
			delete_user_meta( get_current_user_id(), self::OPT_USER );
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
		echo str_repeat( '=', 72 ) . "\n\nTOP LEVEL\n\n";
		foreach ( (array) $menu as $pos => $item ) {
			if ( empty( $item[0] ) ) {
				printf( "  %-5s ---- separator ----\n", $pos );
				continue;
			}
			printf( "  %-5s %-28s cap=%-22s slug=%s\n", $pos, wp_strip_all_tags( $item[0] ), $item[1], $item[2] );
		}
		echo "\n\nSUBMENUS\n";
		foreach ( (array) $submenu as $parent => $items ) {
			echo "\n  [$parent]\n";
			foreach ( $items as $item ) {
				printf( "      %-28s cap=%-22s slug=%s\n", wp_strip_all_tags( $item[0] ), $item[1], $item[2] );
			}
		}
		echo "\n\nRESOLVED BY THIS PLUGIN\n\n";
		foreach ( $this->found as $key => $idx ) {
			printf( "  %-12s -> menu[%s] = %s\n", $key, $idx, isset( $menu[ $idx ][2] ) ? $menu[ $idx ][2] : '?' );
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
				if ( ! empty( $item[2] ) && $item[2] === $needle ) {
					return $i;
				}
			}
		}
		foreach ( (array) $needles as $needle ) {
			foreach ( (array) $menu as $i => $item ) {
				if ( ! empty( $item[2] ) && false !== strpos( $item[2], $needle ) ) {
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
				if ( ! empty( $item[2] ) && false !== strpos( $item[2], $needle ) ) {
					return $k;
				}
			}
		}
		foreach ( (array) $names as $name ) {
			foreach ( $submenu[ $parent ] as $k => $item ) {
				if ( 0 === strcasecmp( trim( wp_strip_all_tags( $item[0] ) ), $name ) ) {
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

		// 2. Promote Orders / Customers / Settings out of the Woo submenu.
		$wc_parent = isset( $this->found['home'] ) ? $menu[ $this->found['home'] ][2] : 'woocommerce';

		foreach ( $this->promote as $key => $spec ) {
			$k = $this->find_sub( $wc_parent, $spec['slugs'], $spec['names'] );
			if ( false === $k ) {
				continue;
			}
			$item = $submenu[ $wc_parent ][ $k ];
			unset( $submenu[ $wc_parent ][ $k ] );

			$slug = $item[2];
			// Submenu slugs are relative to admin.php unless they name their own file.
			if ( false === strpos( $slug, '.php' ) ) {
				$slug = 'admin.php?page=' . $slug;
			}

			$pos = $this->free_position();
			$menu[ $pos ] = array(
				$spec['title'],
				$item[1],
				$slug,
				isset( $item[3] ) ? $item[3] : $spec['title'],
				'menu-top mfa-promoted',
				'mfa-' . $key,
				$spec['icon'],
			);
			$this->found[ $key ] = $pos;
		}

		// 3. Invent the top-level items Woo doesn't have (Payments).
		foreach ( $this->invent as $key => $spec ) {
			if ( ! current_user_can( $spec['cap'] ) ) {
				continue;
			}
			$pos = $this->free_position();
			$menu[ $pos ] = array(
				$spec['title'],
				$spec['cap'],
				$spec['slug'],
				$spec['title'],
				'menu-top mfa-invented',
				'mfa-' . $key,
				$spec['icon'],
			);
			$this->found[ $key ] = $pos;
		}

		// 4. Point "Home" straight at the Woo dashboard and drop its flyout,
		//    now that its useful children live on the top level.
		if ( isset( $this->found['home'] ) ) {
			$i = $this->found['home'];
			if ( ! empty( $submenu[ $wc_parent ] ) ) {
				$first     = reset( $submenu[ $wc_parent ] );
				$home_slug = ! empty( $first[2] ) ? $first[2] : '';

				// Whatever is left over after promotion still needs a home:
				// coupons belong with Marketing, Status/Extensions with
				// Settings. The Home entry itself is dropped — it has become
				// this top-level item.
				$settings_target  = isset( $this->found['settings'] ) ? $menu[ $this->found['settings'] ][2] : '';
				$marketing_target = isset( $this->found['marketing'] ) ? $menu[ $this->found['marketing'] ][2] : '';

				foreach ( $submenu[ $wc_parent ] as $leftover ) {
					if ( empty( $leftover[2] ) || $leftover[2] === $home_slug ) {
						continue;
					}
					$is_marketing = ( false !== strpos( $leftover[2], 'marketing' ) )
						|| ( false !== strpos( $leftover[2], 'coupon' ) );

					$target = ( $is_marketing && $marketing_target ) ? $marketing_target : $settings_target;
					if ( $target ) {
						$submenu[ $target ][] = $leftover;
					}
				}

				unset( $submenu[ $wc_parent ] );
				if ( $home_slug ) {
					$menu[ $i ][2] = ( false === strpos( $home_slug, '.php' ) )
						? 'admin.php?page=' . $home_slug
						: $home_slug;
				}
			}
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
			$classes = isset( $item[4] ) ? $item[4] : '';
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
			if ( ! empty( $item[2] ) && 'index.php' === $item[2] ) {
				$item[0] = 'Dashboard';
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

			if ( $this->is_current( $item[2] ) ) {
				continue; // leave it expanded on the top level
			}

			$new[] = array(
				wp_strip_all_tags( $item[0] ),
				$item[1],
				$item[2],
				isset( $item[3] ) ? $item[3] : $item[0],
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
				'title' => 'Merchant view: on',
				'href'  => add_query_arg( 'mfa', 'off' ),
				'meta'  => array( 'title' => 'Switch back to the stock WordPress menu' ),
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

	/** Keep the plugin out of the Plugins list so demos stay clean. */
	public function hide_self( $plugins ) {
		if ( ! $this->active() ) {
			return $plugins;
		}
		unset( $plugins[ plugin_basename( __FILE__ ) ] );
		return $plugins;
	}
}

Merchant_First_Admin::boot();
