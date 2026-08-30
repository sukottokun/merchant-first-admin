<?php
/**
 * Plugin Name:  Merchant-First Admin
 * Description:  Reshapes wp-admin around store operations: store tasks at the top level, everything WordPress behind one door. Built for demo sites.
 * Version:      0.9.0
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

	const VERSION  = '0.9.0';
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
		'subscriptions',
		'products',
		'customers',
		'reports',
		'marketing',
		'payments',
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
		'wordpress' => array( 'index.php' ),
	);

	/**
	 * Items to promote out of the WooCommerce submenu onto the top level.
	 * Matched on submenu slug needle, then title needle as a fallback.
	 */
	private $promote = array(
		'home'      => array(
			'title'  => 'Home',
			'icon'   => 'dashicons-store',
			'slugs'  => array( 'wc-admin' ),
			'names'  => array( 'Home' ),
			// Land on Analytics Overview rather than Woo's stock Home screen.
			// Overview already renders the performance tiles and charts a
			// merchant wants first; stock Home is an onboarding checklist.
			// Permissions still resolve through the original 'wc-admin'
			// registration — 'path' is a client-side route, not a page.
			'target' => 'admin.php?page=wc-admin&path=/analytics/overview',
		),
		'orders'    => array(
			'title' => 'Orders',
			'icon'  => 'dashicons-cart',
			'slugs' => array( 'wc-orders', 'post_type=shop_order' ),
			'names' => array( 'Orders' ),
		),
		'subscriptions' => array(
			'title' => 'Subscriptions',
			'icon'  => 'dashicons-update',
			'slugs' => array( 'wc-orders--shop_subscription', 'post_type=shop_subscription' ),
			'names' => array( 'Subscriptions' ),
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

	/**
	 * The catch-all. Every WooCommerce submenu entry that isn't promoted to
	 * the top level ends up here, so nothing a Woo extension registers can
	 * silently vanish.
	 */
	private $extensions = array(
		'title' => 'Extensions',
		'icon'  => 'dashicons-admin-plugins',
		'slug'  => 'admin.php?page=wc-admin&path=/extensions',
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
			printf( "  %-5s %-28s cap=%-22s slug=%s\n", $pos, $this->clean_label( $item[ self::TITLE ] ), $item[ self::CAP ], $item[ self::SLUG ] );
		}
		echo "\n\nSUBMENUS\n";
		foreach ( (array) $submenu as $parent => $items ) {
			echo "\n  [$parent]\n";
			foreach ( $items as $item ) {
				printf( "      %-28s cap=%-22s slug=%s\n", $this->clean_label( $item[ self::TITLE ] ), $item[ self::CAP ], $item[ self::SLUG ] );
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
				if ( 0 === strcasecmp( trim( $this->clean_label( $item[ self::TITLE ] ) ), $name ) ) {
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
		$wc_parent = isset( $this->found['woo_parent'] ) ? $menu[ $this->found['woo_parent'] ][ self::SLUG ] : 'woocommerce';

		foreach ( $this->promote as $key => $spec ) {
			$k = $this->find_sub( $wc_parent, $spec['slugs'], $spec['names'] );
			if ( false === $k ) {
				continue;
			}
			$item = $submenu[ $wc_parent ][ $k ];

			$slug = empty( $spec['target'] )
				? $this->absolute_slug( $item[ self::SLUG ] )
				: $spec['target'];

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
				$menu[ $this->found[ $key ] ][ self::TITLE ] = $title;
				$menu[ $this->found[ $key ] ][ self::PAGE_TITLE ] = $title;
			}
		}
		foreach ( $this->icons as $key => $icon ) {
			if ( isset( $this->found[ $key ], $menu[ $this->found[ $key ] ] ) ) {
				$menu[ $this->found[ $key ] ][ self::ICON ] = $icon;
			}
		}

		// 5b. Home already lands on Analytics Overview, so a separate Reports
		//     top-level pointed at the same route — and WordPress highlighted
		//     both, through two different code paths. Fold the sub-reports
		//     under Home and drop the duplicate. As with the WooCommerce
		//     parent, the original submenu stays registered so permissions
		//     keep resolving.
		$this->merge_reports_into_home();

		// 6. Everything left under WooCommerce goes to Extensions, so no
		//    extension can register a menu that never appears anywhere.
		$this->build_extensions( $wc_parent );

		// 7. Fold WordPress-land into the drawer.
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
	 * Give Home the analytics sub-reports and remove the Reports top-level.
	 */
	private function merge_reports_into_home() {
		global $menu, $submenu;

		if ( ! isset( $this->found['reports'], $this->found['home'] ) ) {
			return;
		}

		$reports_slug = $menu[ $this->found['reports'] ][ self::SLUG ];
		$home_slug    = $menu[ $this->found['home'] ][ self::SLUG ];

		if ( ! empty( $submenu[ $reports_slug ] ) ) {
			$children = array();
			foreach ( $submenu[ $reports_slug ] as $item ) {
				if ( empty( $item[ self::SLUG ] ) ) {
					continue;
				}
				$children[] = array(
					$this->clean_label( $item[ self::TITLE ] ),
					$item[ self::CAP ],
					$this->absolute_slug( $item[ self::SLUG ] ),
				);
			}
			if ( $children ) {
				$submenu[ $home_slug ] = $children;
			}
		}

		unset( $menu[ $this->found['reports'] ], $this->found['reports'] );
	}

	/**
	 * Collect every WooCommerce submenu entry that wasn't promoted.
	 *
	 * Deduplicated by visible label against what is already on the top level,
	 * which drops the legacy twins WooCommerce keeps registered alongside the
	 * HPOS screens (two Orders, two Subscriptions). Every distinct destination
	 * stays reachable.
	 *
	 * @param string $wc_parent Slug the WooCommerce submenu is keyed under.
	 */
	private function build_extensions( $wc_parent ) {
		global $menu, $submenu;

		if ( empty( $submenu[ $wc_parent ] ) ) {
			return;
		}

		$placed = array();
		foreach ( (array) $menu as $item ) {
			if ( ! empty( $item[ self::TITLE ] ) ) {
				$placed[] = strtolower( $this->clean_label( $item[ self::TITLE ] ) );
			}
		}

		$leftovers = array();
		foreach ( $submenu[ $wc_parent ] as $item ) {
			if ( empty( $item[ self::SLUG ] ) || isset( $this->promoted[ $item[ self::SLUG ] ] ) ) {
				continue;
			}
			$label = strtolower( $this->clean_label( $item[ self::TITLE ] ) );
			if ( '' === $label || in_array( $label, $placed, true ) ) {
				continue;
			}
			$placed[]    = $label;
			$leftovers[] = array(
				$this->clean_label( $item[ self::TITLE ] ),
				$item[ self::CAP ],
				$this->absolute_slug( $item[ self::SLUG ] ),
			);
			$this->promoted[ $item[ self::SLUG ] ] = $this->absolute_slug( $item[ self::SLUG ] );
		}

		if ( ! $leftovers ) {
			return;
		}

		// WordPress links a parent menu to its FIRST child, so the marketplace
		// page has to lead or "Extensions" would open Live Branches.
		usort(
			$leftovers,
			function ( $a, $b ) {
				$rank = function ( $row ) {
					return ( false !== strpos( $row[ self::SLUG ], 'extensions' )
						|| false !== strpos( $row[ self::SLUG ], 'wc-addons' ) ) ? 0 : 1;
				};
				return $rank( $a ) <=> $rank( $b );
			}
		);

		$pos          = $this->free_position();
		$menu[ $pos ] = array(
			$this->label( $this->extensions['title'] ),
			$leftovers[0][ self::CAP ],
			$this->extensions['slug'],
			$this->extensions['title'],
			'menu-top mfa-extensions',
			'mfa-extensions',
			$this->extensions['icon'],
		);
		$this->found['extensions']            = $pos;
		$submenu[ $this->extensions['slug'] ] = $leftovers;
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
		$parent = $menu[ $this->found['wordpress'] ][ self::SLUG ]; // index.php

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
				$this->clean_label( $item[ self::TITLE ] ),
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

	/**
	 * Flatten a core menu title to a plain label.
	 *
	 * Core packs count bubbles into the title as markup, including a
	 * screen-reader-only copy of the text. wp_strip_all_tags() alone merges
	 * both into the visible label — "Comments" becomes "Comments 00 Comments
	 * in moderation" — so drop the spans whole before stripping.
	 *
	 * @param string $title Raw menu title, possibly containing markup.
	 * @return string
	 */
	private function clean_label( $title ) {
		$title = preg_replace( '#<span[^>]*>.*?</span>#is', '', (string) $title );
		return trim( wp_strip_all_tags( $title ) );
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

	/**
	 * Make a submenu slug linkable from the top level.
	 *
	 * Submenu slugs are bare page names; WordPress only resolves those
	 * relative to admin.php when the page hook is registered under that exact
	 * parent. Promote one and that no longer holds, so the link renders as a
	 * file path and 404s. Prefix it explicitly.
	 *
	 * @param string $slug Registered submenu slug.
	 * @return string
	 */
	private function absolute_slug( $slug ) {
		return ( false === strpos( $slug, '.php' ) ) ? 'admin.php?page=' . $slug : $slug;
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

		// The named merchant items, in the order above.
		$named = array();
		foreach ( $this->order as $key ) {
			if ( isset( $this->found[ $key ], $menu[ $this->found[ $key ] ][ self::SLUG ] ) ) {
				$named[] = $menu[ $this->found[ $key ] ][ self::SLUG ];
			}
		}

		// Extensions, then Settings, then the separator and the drawer always
		// close out the menu — Settings stays last no matter what gets
		// installed, rather than drifting above whatever registered itself.
		$tail = array();
		foreach ( array( 'extensions', 'settings', 'separator', 'wordpress' ) as $key ) {
			if ( isset( $this->found[ $key ], $menu[ $this->found[ $key ] ][ self::SLUG ] ) ) {
				$tail[] = $menu[ $this->found[ $key ] ][ self::SLUG ];
			}
		}

		// Anything else — Wholesale, Bookings, whatever gets installed next —
		// keeps the position it registered, in the store group above the
		// drawer. Nothing needs to be named here to be placed sensibly.
		$others = array_values( array_diff( $slugs, $named, $tail ) );

		return array_merge( $named, $others, $tail );
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
				'title' => __( 'Merchant view:', 'merchant-first-admin' ) . ' ' . self::woo_mark(),
				'href'  => wp_nonce_url( add_query_arg( 'mfa', 'off' ), self::NONCE ),
				'meta'  => array( 'title' => __( 'Switch back to the stock WordPress menu', 'merchant-first-admin' ) ),
			) );
		}
	}

	/**
	 * The Woo wordmark, inline.
	 *
	 * Path data is the official asset in Woo Purple 40 (#873EFF). The shipped
	 * SVG colours through .st0 class selectors, which would collide with
	 * anything else in wp-admin, so the fill moves to the group and the
	 * classes are dropped. Proportions and colour are untouched, per the
	 * brand's prohibited-modifications rules.
	 *
	 * @return string
	 */
	private static function woo_mark() {
		return '<svg class="mfa-woo" viewBox="0 0 183.6 47.5" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Woo" focusable="false"><g fill="#873EFF" fill-rule="evenodd" clip-rule="evenodd"><path d="M77.4,0c-4.3,0-7.1,1.4-9.6,6.1L56.4,27.6V8.5c0-5.7-2.7-8.5-7.7-8.5s-7.1,1.7-9.6,6.5L28.3,27.6V8.7 c0-6.1-2.5-8.7-8.6-8.7H7.3C2.6,0,0,2.2,0,6.2s2.5,6.4,7.1,6.4h5.1v24.1c0,6.8,4.6,10.8,11.2,10.8s9.6-2.6,12.9-8.7l7.2-13.5v11.4 c0,6.7,4.4,10.8,11.1,10.8s9.2-2.3,13-8.7l16.6-28C87.8,4.7,85.3,0,77.3,0C77.3,0,77.3,0,77.4,0z"/><path d="M108.6,0C95,0,84.7,10.1,84.7,23.8s10.4,23.7,23.9,23.7s23.8-10.1,23.9-23.7C132.5,10.1,122.1,0,108.6,0z M108.6,32.9c-5.1,0-8.6-3.8-8.6-9.1s3.5-9.2,8.6-9.2s8.6,3.9,8.6,9.2S113.8,32.9,108.6,32.9z"/><path d="M159.7,0c-13.5,0-23.9,10.1-23.9,23.8s10.4,23.7,23.9,23.7s23.9-10.1,23.9-23.7S173.2,0,159.7,0z M159.7,32.9 c-5.2,0-8.5-3.8-8.5-9.1s3.4-9.2,8.5-9.2s8.6,3.9,8.6,9.2S164.9,32.9,159.7,32.9z"/></g></svg>';
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
			#wp-admin-bar-mfa-toggle .mfa-woo { height:12px; width:auto; vertical-align:-1px; margin-left:3px; }
			#wp-admin-bar-mfa-toggle .ab-item { display:flex; align-items:center; gap:1px; }
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

		// Every Analytics screen shares one page slug — 'wc-admin' — and is
		// told apart only by its client-side 'path'. Matching on the page
		// alone would highlight Home while sitting on Customers, so try the
		// fuller page+path key first and fall back to the bare page.
		$path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';
		if ( $path ) {
			$keyed = $plugin_page . '&path=' . $path;
			if ( isset( $this->promoted[ $keyed ] ) ) {
				return $this->promoted[ $keyed ];
			}
		}
		return isset( $this->promoted[ $plugin_page ] ) ? $this->promoted[ $plugin_page ] : $parent_file;
	}

}

Merchant_First_Admin::boot();
