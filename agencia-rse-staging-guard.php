<?php
/**
 * Plugin Name:       Agencia RSE Staging Guard
 * Plugin URI:        https://github.com/agenciaRSE/agencia-rse-staging-guard
 * Description:       For staging and development copies of a site. Keeps the whole site out of search engines and stops the real analytics and ad tags (GTM, GA4, Google Ads, Meta…), WooCommerce webhooks and pings. Optionally blocks or redirects outgoing email. It never changes saved settings and does nothing on a domain that looks like production.
 * Version:           1.0.0
 * Requires at least: 5.7
 * Requires PHP:      7.2
 * Author:            AGENCIA RSE
 * Author URI:        https://agenciarse.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       agencia-rse-staging-guard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGRSE_SG_VERSION', '1.0.0' );
define( 'AGRSE_SG_FILE', __FILE__ );

final class AGRSE_Staging_Guard {

	const OPTION   = 'agrse_sg_settings';
	const MAIL_LOG = 'agrse_sg_mail_log';
	const SLUG     = 'agencia-rse-staging-guard';

	/** @var AGRSE_Staging_Guard|null */
	private static $instance = null;

	/** @var array|null Cached detection result: array( bool active, string reason ). */
	private $state = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->noindex_hooks();
		$this->tracking_hooks();
		$this->outgoing_hooks();
		$this->mail_hooks();

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar' ), 5 );
		add_filter( 'plugin_action_links_' . plugin_basename( AGRSE_SG_FILE ), array( $this, 'action_links' ) );
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	public static function defaults() {
		return array(
			'mode'          => 'auto', // auto | on | off.
			'forced_host'   => '',     // Host where "on" was chosen: "on" only applies there.
			'noindex'       => 1,
			'tracking'      => 1,
			'webhooks'      => 1,
			'pings'         => 1,
			'mail'          => 0,      // Email is left alone unless the user turns this on.
			'mail_redirect' => '',
			'badge'         => 1,
		);
	}

	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	private function feature( $key ) {
		$s = self::settings();
		return $this->is_active() && ! empty( $s[ $key ] );
	}

	public function register_settings() {
		register_setting(
			'agrse_sg_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array();

		$out['mode']        = ( isset( $input['mode'] ) && in_array( $input['mode'], array( 'auto', 'on', 'off' ), true ) ) ? $input['mode'] : 'auto';
		$out['forced_host'] = 'on' === $out['mode'] ? self::host() : '';
		foreach ( array( 'noindex', 'tracking', 'webhooks', 'pings', 'mail', 'badge' ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}
		$redirect             = isset( $input['mail_redirect'] ) ? sanitize_email( wp_unslash( $input['mail_redirect'] ) ) : '';
		$out['mail_redirect'] = is_email( $redirect ) ? $redirect : '';

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Detection
	 * ------------------------------------------------------------------- */

	/** Site host without port, lower case. */
	public static function host() {
		return strtolower( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ) );
	}

	/** Does the host look like a staging, development or local copy? */
	public static function host_looks_staging( $host ) {
		if ( '' === $host || 'localhost' === $host || preg_match( '~^\d{1,3}(\.\d{1,3}){3}$~', $host ) ) {
			return true;
		}
		if ( preg_match( '~\.(local|test|localhost|invalid|example|ddev\.site|lndo\.site)$~', $host ) ) {
			return true;
		}
		// Default domains that hosts give to staging copies and temporary sites.
		if ( preg_match( '~\.(wpengine\.com|wpenginepowered\.com|kinsta\.cloud|pantheonsite\.io|flywheelstaging\.com|flywheelsites\.com|cloudwaysapps\.com|wpcomstaging\.com|instawp\.xyz|instawp\.co|tastewp\.com|sg-host\.com|myftpupload\.com)$~', $host ) ) {
			return true;
		}
		return (bool) preg_match( '~(^|[.-])(staging|stage|stg|dev|develop|test|testing|demo|preprod|pre)([.-]|\d|$)~', $host );
	}

	/**
	 * Is the guard active on this site, and why.
	 *
	 * @return array( bool active, string reason )
	 */
	public function state() {
		if ( null !== $this->state ) {
			return $this->state;
		}
		if ( defined( 'AGRSE_STAGING' ) ) {
			return $this->state = array( (bool) AGRSE_STAGING, 'constant' );
		}
		$s    = self::settings();
		$host = self::host();
		if ( 'off' === $s['mode'] ) {
			return $this->state = array( false, 'mode_off' );
		}
		// "Always on" is tied to the host where it was chosen, so a copy of the database on the live domain falls back to detection.
		if ( 'on' === $s['mode'] && $s['forced_host'] === $host ) {
			return $this->state = array( true, 'mode_on' );
		}
		if ( function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'staging', 'development', 'local' ), true ) ) {
			return $this->state = array( true, 'environment' );
		}
		if ( self::host_looks_staging( $host ) ) {
			return $this->state = array( true, 'host' );
		}
		// Saved value, read directly so our own blog_public filter does not count.
		global $wpdb;
		$saved = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'blog_public' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( '0' === (string) $saved ) {
			return $this->state = array( true, 'blog_public' );
		}
		return $this->state = array( false, 'production' );
	}

	public function is_active() {
		$state = $this->state();
		return $state[0];
	}

	/** Reset the cached state after the settings change in the same request. */
	public function reset_state() {
		$this->state = null;
	}

	private function reason_text( $reason ) {
		$texts = array(
			'constant'    => __( 'the AGRSE_STAGING constant in wp-config.php', 'agencia-rse-staging-guard' ),
			'mode_off'    => __( 'the mode is set to "Off"', 'agencia-rse-staging-guard' ),
			'mode_on'     => __( 'the mode is set to "Always on" for this domain', 'agencia-rse-staging-guard' ),
			'environment' => __( 'WP_ENVIRONMENT_TYPE is not "production"', 'agencia-rse-staging-guard' ),
			'host'        => __( 'the domain looks like a staging or development copy', 'agencia-rse-staging-guard' ),
			'blog_public' => __( '"Discourage search engines" is checked in Settings > Reading', 'agencia-rse-staging-guard' ),
			'production'  => __( 'the domain looks like a live site', 'agencia-rse-staging-guard' ),
		);
		return isset( $texts[ $reason ] ) ? $texts[ $reason ] : $reason;
	}

	/* ---------------------------------------------------------------------
	 * 1. Keep the site out of search engines
	 * ------------------------------------------------------------------- */

	private function noindex_hooks() {
		// blog_public = 0 in memory only: WordPress and SEO plugins print noindex, robots.txt disallows.
		add_filter(
			'pre_option_blog_public',
			function ( $pre ) {
				// Settings > Reading shows the real value, so a fake one is never saved.
				if ( is_admin() && isset( $GLOBALS['pagenow'] ) && 'options-reading.php' === $GLOBALS['pagenow'] ) {
					return $pre;
				}
				return $this->feature( 'noindex' ) ? '0' : $pre;
			}
		);

		add_filter(
			'wp_robots',
			function ( $robots ) {
				if ( $this->feature( 'noindex' ) ) {
					unset( $robots['index'], $robots['follow'], $robots['max-image-preview'] );
					$robots['noindex']  = true;
					$robots['nofollow'] = true;
				}
				return $robots;
			},
			999
		);

		// Yoast SEO.
		add_filter(
			'wpseo_robots',
			function ( $r ) {
				return $this->feature( 'noindex' ) ? 'noindex, nofollow' : $r;
			},
			999
		);
		add_filter(
			'wpseo_robots_array',
			function ( $r ) {
				return $this->feature( 'noindex' ) ? array( 'index' => 'noindex', 'follow' => 'nofollow' ) : $r;
			},
			999
		);

		// Rank Math.
		add_filter(
			'rank_math/frontend/robots',
			function ( $r ) {
				if ( $this->feature( 'noindex' ) ) {
					$r           = is_array( $r ) ? $r : array();
					$r['index']  = 'noindex';
					$r['follow'] = 'nofollow';
				}
				return $r;
			},
			999
		);

		// SEOPress.
		add_filter(
			'seopress_titles_robots',
			function ( $r ) {
				return $this->feature( 'noindex' ) ? '<meta name="robots" content="noindex, nofollow">' : $r;
			},
			999
		);

		// HTTP header on every PHP response, including feeds, sitemaps, AJAX and REST.
		add_action(
			'send_headers',
			function () {
				if ( $this->feature( 'noindex' ) && ! headers_sent() ) {
					header( 'X-Robots-Tag: noindex, nofollow', true );
				}
			},
			0
		);
		add_filter(
			'rest_post_dispatch',
			function ( $result ) {
				if ( $result instanceof WP_HTTP_Response && $this->feature( 'noindex' ) ) {
					$result->header( 'X-Robots-Tag', 'noindex, nofollow' );
				}
				return $result;
			}
		);
	}

	/* ---------------------------------------------------------------------
	 * 2. Stop the real analytics and ad tags
	 * ------------------------------------------------------------------- */

	/** Pattern of analytics and ad tags, for snippets and for the final HTML. */
	public static function tracking_pattern() {
		return '~googletagmanager\.com|google-analytics\.com|gtag\s*\(|\bfbq\s*\(|connect\.facebook\.net|googleadservices\.com|clarity\.ms|static\.hotjar\.com|snap\.licdn\.com|analytics\.tiktok\.com|bat\.bing\.com|GTM-[A-Z0-9]{5,}~i';
	}

	private function tracking_hooks() {
		// PixelYourSite: its own filter that turns every pixel off.
		add_filter(
			'pys_disable_by_gdpr',
			function ( $disabled ) {
				return $this->feature( 'tracking' ) ? true : $disabled;
			},
			99
		);

		// Elementor Pro custom code: leave out the snippets that load tags.
		add_action(
			'pre_get_posts',
			function ( $q ) {
				if ( is_admin() || 'elementor_snippet' !== $q->get( 'post_type' ) || ! $this->feature( 'tracking' ) ) {
					return;
				}
				static $ids = null;
				if ( null === $ids ) {
					global $wpdb;
					$ids  = array();
					$rows = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_code'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					foreach ( $rows as $row ) {
						if ( preg_match( self::tracking_pattern(), (string) $row->meta_value ) ) {
							$ids[] = (int) $row->post_id;
						}
					}
				}
				if ( $ids ) {
					$q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), $ids ) );
				}
			}
		);

		// GTM4WP.
		add_filter(
			'option_gtm4wp-options',
			function ( $o ) {
				if ( is_array( $o ) && ! is_admin() && $this->feature( 'tracking' ) ) {
					$o['gtm-code'] = '';
				}
				return $o;
			}
		);

		// Site Kit by Google.
		$block = function ( $blocked ) {
			return $this->feature( 'tracking' ) ? true : $blocked;
		};
		add_filter( 'googlesitekit_analytics-4_tag_blocked', $block );
		add_filter( 'googlesitekit_tagmanager_tag_blocked', $block );
		add_filter( 'googlesitekit_ads_tag_blocked', $block );

		// MonsterInsights.
		add_filter(
			'monsterinsights_track_user',
			function ( $track ) {
				return $this->feature( 'tracking' ) ? false : $track;
			}
		);

		// Safety net on the final HTML (theme, header and footer code, HTML widgets…).
		add_action(
			'template_redirect',
			function () {
				if ( ! $this->feature( 'tracking' ) || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
					return;
				}
				ob_start( array( __CLASS__, 'strip_tracking' ) );
			},
			0
		);
	}

	/** Removes analytics and ad <script>/<noscript> tags from a full HTML page. */
	public static function strip_tracking( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '<html' ) ) {
			return $html;
		}
		$removed = 0;
		$clean   = preg_replace_callback(
			'~<(script|noscript)\b[^>]*>.*?</\1>~is',
			function ( $m ) use ( &$removed ) {
				if ( preg_match( AGRSE_Staging_Guard::tracking_pattern(), $m[0] ) && false === stripos( $m[0], 'application/ld+json' ) ) {
					$removed++;
					return '';
				}
				return $m[0];
			},
			$html
		);
		if ( null === $clean ) {
			return $html; // The regex failed (backtrack limit): leave the page as it was.
		}
		$note = '<!-- agencia-rse-staging-guard ' . AGRSE_SG_VERSION . ': staging' . ( $removed ? ', ' . $removed . ' tracking tags removed' : '' ) . " -->\n";
		return preg_replace( '~</head>~i', $note . '</head>', $clean, 1 );
	}

	/* ---------------------------------------------------------------------
	 * 3. WooCommerce webhooks and pings
	 * ------------------------------------------------------------------- */

	private function outgoing_hooks() {
		// WooCommerce webhooks would send test orders to the real ERP, CRM or shipping service.
		add_filter(
			'woocommerce_webhook_should_deliver',
			function ( $deliver ) {
				return $this->feature( 'webhooks' ) ? false : $deliver;
			},
			999
		);

		// Pings to update services and pingbacks to linked sites.
		add_filter(
			'pre_option_ping_sites',
			function ( $pre ) {
				return $this->feature( 'pings' ) ? '' : $pre;
			}
		);
		add_action(
			'pre_ping',
			function ( &$links ) {
				if ( $this->feature( 'pings' ) ) {
					$links = array();
				}
			}
		);
	}

	/* ---------------------------------------------------------------------
	 * 4. Email (off unless the user turns it on)
	 * ------------------------------------------------------------------- */

	private function mail_hooks() {
		// Redirect: every message goes to one address, Cc and Bcc removed.
		add_filter(
			'wp_mail',
			function ( $atts ) {
				$s = self::settings();
				if ( ! $this->feature( 'mail' ) || '' === $s['mail_redirect'] ) {
					return $atts;
				}
				$to              = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : (string) $atts['to'];
				$atts['subject'] = '[STAGING → ' . $to . '] ' . $atts['subject'];
				$atts['to']      = $s['mail_redirect'];
				$atts['headers'] = self::strip_copy_headers( isset( $atts['headers'] ) ? $atts['headers'] : '' );
				self::log_mail( $to, $atts['subject'], 'redirected' );
				return $atts;
			},
			999
		);

		// Block: the message is dropped and wp_mail() reports success, so forms do not show errors.
		// shortcut: SMTP plugins that replace wp_mail() without the pre_wp_mail filter are not blocked; then use their own "disable email" option.
		add_filter(
			'pre_wp_mail',
			function ( $pre, $atts ) {
				$s = self::settings();
				if ( null !== $pre || ! $this->feature( 'mail' ) || '' !== $s['mail_redirect'] ) {
					return $pre;
				}
				$to = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : (string) $atts['to'];
				self::log_mail( $to, (string) $atts['subject'], 'blocked' );
				return true;
			},
			999,
			2
		);
	}

	/** Removes Cc and Bcc lines from wp_mail() headers (string or array). */
	public static function strip_copy_headers( $headers ) {
		$lines = is_array( $headers ) ? $headers : preg_split( "~\r\n|\r|\n~", (string) $headers );
		$lines = array_values(
			array_filter(
				(array) $lines,
				function ( $line ) {
					return '' !== trim( (string) $line ) && ! preg_match( '~^\s*b?cc\s*:~i', (string) $line );
				}
			)
		);
		return is_array( $headers ) ? $lines : implode( "\n", $lines );
	}

	private static function log_mail( $to, $subject, $action ) {
		$log = get_option( self::MAIL_LOG, array() );
		$log = is_array( $log ) ? $log : array();
		array_unshift(
			$log,
			array(
				'time'    => time(),
				'to'      => substr( $to, 0, 200 ),
				'subject' => substr( $subject, 0, 200 ),
				'action'  => $action,
			)
		);
		update_option( self::MAIL_LOG, array_slice( $log, 0, 20 ), false );
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------- */

	public function action_links( $links ) {
		$url = admin_url( 'tools.php?page=' . self::SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'agencia-rse-staging-guard' ) . '</a>' );
		return $links;
	}

	public function add_settings_page() {
		add_management_page(
			__( 'Staging Guard', 'agencia-rse-staging-guard' ),
			__( 'Staging Guard', 'agencia-rse-staging-guard' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	public function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'tools_page_' . self::SLUG === $screen->id ) {
			return; // The settings page already shows the status.
		}
		list( $active, $reason ) = $this->state();
		$link                    = '<a href="' . esc_url( admin_url( 'tools.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Staging Guard settings', 'agencia-rse-staging-guard' ) . '</a>';
		if ( $active ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Staging Guard is active: this copy is treated as staging.', 'agencia-rse-staging-guard' ) . ' ' . $link . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( 'production' === $reason ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Staging Guard is active but doing nothing:', 'agencia-rse-staging-guard' ) . '</strong> ' . esc_html(
				sprintf(
					/* translators: %s: domain name */
					__( '%s looks like a live site. If it is, deactivate the plugin. If it is a staging copy, choose "Always on" in the settings.', 'agencia-rse-staging-guard' ),
					self::host()
				)
			) . ' ' . $link . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public function admin_bar( $bar ) {
		$s = self::settings();
		if ( ! $this->is_active() || empty( $s['badge'] ) || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'agrse-staging-guard',
				'title' => esc_html__( 'STAGING', 'agencia-rse-staging-guard' ),
				'href'  => current_user_can( 'manage_options' ) ? admin_url( 'tools.php?page=' . self::SLUG ) : false,
				'meta'  => array( 'html' => '<style>#wp-admin-bar-agrse-staging-guard>.ab-item{background:#b32d2e!important;color:#fff!important;font-weight:600}</style>' ),
			)
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$this->reset_state();
		$s                       = self::settings();
		list( $active, $reason ) = $this->state();
		$name                    = self::OPTION;
		$features                = array(
			'noindex'  => array( __( 'Hide from search engines', 'agencia-rse-staging-guard' ), __( 'noindex on every page (WordPress, Yoast, Rank Math, SEOPress), X-Robots-Tag header and robots.txt that disallows everything.', 'agencia-rse-staging-guard' ) ),
			'tracking' => array( __( 'Block analytics and ad tags', 'agencia-rse-staging-guard' ), __( 'GTM, GA4, Google Ads, Meta, Clarity, Hotjar, LinkedIn, TikTok and Bing: PixelYourSite, GTM4WP, Site Kit, MonsterInsights, Elementor custom code and any of those tags left in the HTML.', 'agencia-rse-staging-guard' ) ),
			'webhooks' => array( __( 'Pause WooCommerce webhooks', 'agencia-rse-staging-guard' ), __( 'Test orders are not sent to your ERP, CRM, shipping or invoicing service.', 'agencia-rse-staging-guard' ) ),
			'pings'    => array( __( 'Stop pings and pingbacks', 'agencia-rse-staging-guard' ), __( 'New posts do not notify update services or the sites they link to.', 'agencia-rse-staging-guard' ) ),
			'badge'    => array( __( 'STAGING label in the toolbar', 'agencia-rse-staging-guard' ), __( 'So nobody confuses this copy with the live site.', 'agencia-rse-staging-guard' ) ),
		);
		$log                     = get_option( self::MAIL_LOG, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Staging Guard', 'agencia-rse-staging-guard' ); ?></h1>

			<div class="notice inline <?php echo $active ? 'notice-success' : 'notice-warning'; ?>" style="margin:16px 0">
				<p>
					<strong><?php echo $active ? esc_html__( 'Active on this site.', 'agencia-rse-staging-guard' ) : esc_html__( 'Not active on this site.', 'agencia-rse-staging-guard' ); ?></strong>
					<?php
					/* translators: %s: why the guard is active or not */
					echo esc_html( sprintf( __( 'Reason: %s.', 'agencia-rse-staging-guard' ), $this->reason_text( $reason ) ) );
					?>
				</p>
			</div>

			<p><?php esc_html_e( 'Saved settings of other plugins are never changed: if this database is copied back to the live site, its analytics, indexing and email keep working as before.', 'agencia-rse-staging-guard' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'agrse_sg_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'When to act', 'agencia-rse-staging-guard' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'When to act', 'agencia-rse-staging-guard' ); ?></legend>
								<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="auto" <?php checked( $s['mode'], 'auto' ); ?> /> <?php esc_html_e( 'Automatic (recommended)', 'agencia-rse-staging-guard' ); ?></label>
								<p class="description" style="margin:0 0 8px 24px"><?php esc_html_e( 'Acts when the domain looks like staging (staging., dev., test., .local, localhost, host staging domains…), when WP_ENVIRONMENT_TYPE is not production, or when "Discourage search engines" is checked.', 'agencia-rse-staging-guard' ); ?></p>
								<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="on" <?php checked( $s['mode'], 'on' ); ?> />
									<?php
									/* translators: %s: domain name */
									echo esc_html( sprintf( __( 'Always on for %s', 'agencia-rse-staging-guard' ), self::host() ) );
									?>
								</label>
								<p class="description" style="margin:0 0 8px 24px"><?php esc_html_e( 'Only on this domain: if the database is copied to another domain, it goes back to automatic.', 'agencia-rse-staging-guard' ); ?></p>
								<label><input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="off" <?php checked( $s['mode'], 'off' ); ?> /> <?php esc_html_e( 'Off', 'agencia-rse-staging-guard' ); ?></label>
								<p class="description" style="margin:8px 0 0"><?php echo wp_kses( __( 'The <code>AGRSE_STAGING</code> constant (true or false) in wp-config.php overrides this choice.', 'agencia-rse-staging-guard' ), array( 'code' => array() ) ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'What to turn off', 'agencia-rse-staging-guard' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'What to turn off', 'agencia-rse-staging-guard' ); ?></legend>
								<?php foreach ( $features as $key => $f ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="1" <?php checked( $s[ $key ], 1 ); ?> /> <?php echo esc_html( $f[0] ); ?></label>
									<p class="description" style="margin:0 0 10px 24px"><?php echo esc_html( $f[1] ); ?></p>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Email', 'agencia-rse-staging-guard' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Email', 'agencia-rse-staging-guard' ); ?></legend>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[mail]" value="1" <?php checked( $s['mail'], 1 ); ?> /> <?php esc_html_e( 'Stop outgoing email', 'agencia-rse-staging-guard' ); ?></label>
								<p class="description" style="margin:0 0 10px 24px"><?php esc_html_e( 'Off by default, because many staging copies need to test forms and orders. Turn it on so customers never get emails from this copy.', 'agencia-rse-staging-guard' ); ?></p>
								<label for="agrse-sg-redirect"><?php esc_html_e( 'Send everything to this address instead (optional)', 'agencia-rse-staging-guard' ); ?></label><br />
								<input type="email" id="agrse-sg-redirect" class="regular-text" name="<?php echo esc_attr( $name ); ?>[mail_redirect]" value="<?php echo esc_attr( $s['mail_redirect'] ); ?>" placeholder="you@example.com" />
								<p class="description"><?php esc_html_e( 'Empty: messages are dropped. With an address: they all go there, with the original recipient in the subject and no Cc or Bcc.', 'agencia-rse-staging-guard' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php if ( is_array( $log ) && $log ) : ?>
				<h2><?php esc_html_e( 'Last stopped emails', 'agencia-rse-staging-guard' ); ?></h2>
				<table class="widefat striped" style="max-width:960px">
					<thead><tr><th><?php esc_html_e( 'Date', 'agencia-rse-staging-guard' ); ?></th><th><?php esc_html_e( 'To', 'agencia-rse-staging-guard' ); ?></th><th><?php esc_html_e( 'Subject', 'agencia-rse-staging-guard' ); ?></th><th><?php esc_html_e( 'Action', 'agencia-rse-staging-guard' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $log as $row ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $row['time'] ) ); ?></td>
								<td><?php echo esc_html( $row['to'] ); ?></td>
								<td><?php echo esc_html( $row['subject'] ); ?></td>
								<td><?php echo 'redirected' === $row['action'] ? esc_html__( 'Redirected', 'agencia-rse-staging-guard' ) : esc_html__( 'Blocked', 'agencia-rse-staging-guard' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}

AGRSE_Staging_Guard::instance();
