<?php
/**
 * Settings page and existing-slug converter UI.
 *
 * @package ArabicToLat
 */

namespace ArabicToLat;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → Arabic to Latin.
 */
final class Admin {

	const PAGE = 'arabic-to-lat';

	/**
	 * @var string[]|null Types ticked in the last converter request; null = all.
	 */
	private $scope = null;

	/**
	 * Phrase used to compare the spelling presets: one letter that differs in each.
	 */
	const PRESET_EXAMPLE = 'شارع جمال عبد الناصر الجديد';

	/**
	 * Markup allowed in help texts: mixed Arabic/Latin examples need their own
	 * direction, or the browser shows "ع = 3" as "3 = ع".
	 */
	const EXAMPLE_TAGS = array(
		'bdi'  => array( 'dir' => true ),
		'code' => array(),
	);

	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );
		add_action( 'admin_post_arabic_to_lat_convert', array( $this, 'handle_convert' ) );
		add_action( 'wp_ajax_arabic_to_lat_convert_batch', array( $this, 'ajax_convert_batch' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'acf/field_group/admin_enqueue_scripts', array( $this, 'enqueue_acf_script' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ARABIC_TO_LAT_FILE ), array( $this, 'action_links' ) );
	}

	public function menu() {
		add_options_page(
			__( 'Arabic to Latin', 'arabic-to-lat' ),
			__( 'Arabic to Latin', 'arabic-to-lat' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $this->page_url() ), esc_html__( 'Settings', 'arabic-to-lat' ) )
		);
		return $links;
	}

	public function register_setting() {
		register_setting(
			'arabic_to_lat_settings_group',
			Plugin::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * @param mixed $input Raw form input.
	 */
	public function sanitize_settings( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$preset = isset( $input['preset'] ) && isset( Transliterator::presets()[ $input['preset'] ] ) ? (string) $input['preset'] : 'standard';

		// Already sanitized (update_option() called directly with the stored shape).
		if ( isset( $input['custom'] ) && is_array( $input['custom'] ) && ! isset( $input['custom_raw'] ) ) {
			return array(
				'files'  => ! empty( $input['files'] ),
				'preset' => $preset,
				'custom' => $input['custom'],
			);
		}

		return array(
			'files'  => ! empty( $input['files'] ),
			'preset' => $preset,
			'custom' => self::parse_custom_table( isset( $input['custom_raw'] ) ? (string) $input['custom_raw'] : '' ),
		);
	}

	/**
	 * Parses "عربي = arabi" lines. Keys must be Arabic script (spaces allowed for
	 * phrases); values are reduced to what can live in a URL.
	 *
	 * @param string $raw Textarea content.
	 * @return array<string,string>
	 */
	public static function parse_custom_table( $raw ) {
		$table = array();
		foreach ( preg_split( '/\R/u', $raw ) as $line ) {
			$parts = preg_split( '/\s*(?:=>|=|\t)\s*/u', trim( $line ), 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$from = trim( $parts[0] );
			$to   = strtolower( preg_replace( '/[^A-Za-z0-9\- ]/', '', trim( $parts[1] ) ) );
			$only_arabic_and_spaces = preg_match( '/^[\s\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]+$/u', $from );
			if ( '' === $from || ! $only_arabic_and_spaces ) {
				continue;
			}
			$table[ $from ] = $to;
		}
		return $table;
	}

	public function activation_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'arabic_to_lat_activated' ) ) {
			return;
		}
		delete_transient( 'arabic_to_lat_activated' );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Arabic to Latin is active: new Arabic slugs and file names are now transliterated to Latin. Existing slugs were not changed.', 'arabic-to-lat' ),
			esc_url( $this->page_url() ),
			esc_html__( 'Review existing Arabic slugs', 'arabic-to-lat' )
		);
	}

	public function handle_convert() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'arabic-to-lat' ), 403 );
		}
		check_admin_referer( 'arabic_to_lat_convert' );

		$dry_run = empty( $_POST['arabic_to_lat_apply'] );
		if ( ! $dry_run && empty( $_POST['arabic_to_lat_backup'] ) ) {
			wp_safe_redirect( add_query_arg( 'arabic_to_lat_error', 'backup', $this->page_url() ) );
			exit;
		}
		// Re-conversion rewrites URLs that currently work, so it takes its own
		// confirmation on top of the backup one.
		if ( ! $dry_run && ! empty( $_POST['arabic_to_lat_reconvert'] ) && empty( $_POST['arabic_to_lat_reconvert_confirm'] ) ) {
			wp_safe_redirect( add_query_arg( 'arabic_to_lat_error', 'reconvert', $this->page_url() ) . '#atl-reconvert' );
			exit;
		}

		$result = $this->scoped_converter()->run(
			array(
				'dry_run' => $dry_run,
				'limit'   => $dry_run ? 200 : 0,
				'seconds' => $dry_run ? 0 : 20,
			)
		);
		$result['dry_run'] = $dry_run;
		$result['scope']   = $this->scope;

		set_transient( 'arabic_to_lat_result_' . get_current_user_id(), $result, HOUR_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'arabic_to_lat_result', 1, $this->page_url() ) . '#atl-existing' );
		exit;
	}

	/**
	 * One batch of the JavaScript-driven conversion. The cursor comes back
	 * with each response so the next batch continues where this one stopped;
	 * changes are collected in the same per-user result the page displays.
	 */
	public function ajax_convert_batch() {
		check_ajax_referer( 'arabic_to_lat_convert' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$cursor = array(
			'posts' => isset( $_POST['cursor_posts'] ) ? (int) $_POST['cursor_posts'] : 0,
			'terms' => isset( $_POST['cursor_terms'] ) ? (int) $_POST['cursor_terms'] : 0,
		);

		$batch = $this->scoped_converter()->run(
			array(
				'dry_run' => false,
				/**
				 * Filters how long one conversion batch may run, in seconds.
				 *
				 * @param float $seconds Default 10; lower it on hosts with short request limits.
				 */
				'seconds' => (float) apply_filters( 'arabic_to_lat_batch_seconds', 10 ),
				'cursor'  => $cursor,
			)
		);

		$key    = 'arabic_to_lat_result_' . get_current_user_id();
		$stored = get_transient( $key );
		if ( ! is_array( $stored ) || ( 0 === $cursor['posts'] && 0 === $cursor['terms'] ) ) {
			$stored = array(
				'changes' => array(),
				'count'   => 0,
				'dry_run' => false,
			);
		}
		// The table on the page lists at most 1,000 changes; the count covers all of them.
		$stored['changes'] = array_slice( array_merge( $stored['changes'], $batch['changes'] ), 0, 1000 );
		$stored['count']  += count( $batch['changes'] );
		$stored['done']    = $batch['done'];
		$stored['scope']   = $this->scope;
		set_transient( $key, $stored, HOUR_IN_SECONDS );

		wp_send_json_success(
			array(
				'converted' => count( $batch['changes'] ),
				'done'      => $batch['done'],
				'cursor'    => $batch['cursor'],
			)
		);
	}

	/**
	 * Converter limited to the post types and taxonomies ticked in the form.
	 * Converter::scope() only accepts names it would convert anyway.
	 */
	private function scoped_converter() {
		$converter = new Converter( Plugin::instance()->transliterator() );
		$this->scope = null;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Callers verify the nonce first.
		if ( ! empty( $_POST['arabic_to_lat_scope'] ) ) {
			$post_types = isset( $_POST['arabic_to_lat_post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['arabic_to_lat_post_types'] ) ) : array();
			$taxonomies = isset( $_POST['arabic_to_lat_taxonomies'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['arabic_to_lat_taxonomies'] ) ) : array();
			$converter->scope( $post_types, $taxonomies );
			$this->scope = array_merge( $post_types, $taxonomies );
		}
		if ( ! empty( $_POST['arabic_to_lat_reconvert'] ) ) {
			$converter->reconvert();
		}
		// phpcs:enable
		return $converter;
	}

	/**
	 * ACF field group editor only (the hook fires nowhere else).
	 */
	public function enqueue_acf_script() {
		wp_enqueue_script(
			'arabic-to-lat-acf',
			plugins_url( 'assets/js/acf-field-names.js', ARABIC_TO_LAT_FILE ),
			array( 'acf-field-group', 'wp-api-fetch', 'wp-url' ),
			ARABIC_TO_LAT_VERSION,
			true
		);
	}

	/**
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script(
			'arabic-to-lat-convert',
			plugins_url( 'assets/js/admin-convert.js', ARABIC_TO_LAT_FILE ),
			array(),
			ARABIC_TO_LAT_VERSION,
			true
		);
		wp_localize_script(
			'arabic-to-lat-convert',
			'arabicToLatConvert',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'resultUrl' => add_query_arg( 'arabic_to_lat_result', 1, $this->page_url() ) . '#atl-existing',
				/* translators: 1: slugs converted so far, 2: slugs found */
				'progress'  => __( 'Converting… %1$d of %2$d', 'arabic-to-lat' ),
				/* translators: %1$d: slugs changed so far. Used when the total is not known in advance. */
				'scanning'  => __( 'Checking slugs… %1$d changed so far', 'arabic-to-lat' ),
				'error'     => __( 'The conversion stopped because of an error. Slugs converted so far are saved; run it again to continue.', 'arabic-to-lat' ),
			)
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Plugin::settings();
		$plugin   = Plugin::instance();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display parameters.
		$test     = isset( $_GET['arabic_to_lat_test'] ) ? sanitize_text_field( wp_unslash( $_GET['arabic_to_lat_test'] ) ) : '';
		$error    = isset( $_GET['arabic_to_lat_error'] ) ? sanitize_key( $_GET['arabic_to_lat_error'] ) : '';
		$result   = isset( $_GET['arabic_to_lat_result'] ) ? get_transient( 'arabic_to_lat_result_' . get_current_user_id() ) : false;
		// phpcs:enable
		$converter = new Converter( $plugin->transliterator() );
		$counts    = $converter->count();
		$by_type   = $converter->count_by_type();
		$custom   = '';
		foreach ( $settings['custom'] as $from => $to ) {
			$custom .= $from . ' = ' . $to . "\n";
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Arabic to Latin', 'arabic-to-lat' ); ?></h1>
			<p><?php esc_html_e( 'New post, page, product and term slugs, and uploaded file names, are transliterated from Arabic, Persian and Urdu script to Latin letters. It works the same in every language of a WPML, Polylang or TranslatePress site, because it looks at the script, not the language setting.', 'arabic-to-lat' ); ?></p>

			<h2><?php esc_html_e( 'Try it', 'arabic-to-lat' ); ?></h2>
			<form method="get" action="">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
				<label for="atl-test" class="screen-reader-text"><?php esc_html_e( 'Arabic title', 'arabic-to-lat' ); ?></label>
				<input type="text" id="atl-test" name="arabic_to_lat_test" class="regular-text" dir="auto" value="<?php echo esc_attr( $test ); ?>" placeholder="<?php echo esc_attr( 'مرحبا بالعالم' ); ?>">
				<?php submit_button( __( 'Show slug', 'arabic-to-lat' ), 'secondary', '', false ); ?>
			</form>
			<?php if ( '' !== $test ) : ?>
				<p><?php esc_html_e( 'Slug:', 'arabic-to-lat' ); ?> <code><?php echo esc_html( rawurldecode( sanitize_title( $test ) ) ); ?></code></p>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Settings', 'arabic-to-lat' ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( 'arabic_to_lat_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Spelling style', 'arabic-to-lat' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Spelling style', 'arabic-to-lat' ); ?></legend>
								<?php foreach ( Transliterator::preset_labels() as $key => $label ) : ?>
									<?php $example = ( new Transliterator( array(), $key ) )->transliterate( self::PRESET_EXAMPLE ); ?>
									<label style="display:block;margin-bottom:6px">
										<input type="radio" name="<?php echo esc_attr( Plugin::OPTION ); ?>[preset]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $settings['preset'], $key ); ?>>
										<?php echo esc_html( $label ); ?>
										<code><?php echo esc_html( sanitize_title_with_dashes( $example, '', 'save' ) ); ?></code>
									</label>
								<?php endforeach; ?>
								<p class="description">
									<?php
									echo wp_kses(
										sprintf(
											/* translators: %s: the Arabic example phrase */
											esc_html__( 'Each example shows how %s is written. Custom replacements below still win. Applies to new slugs only.', 'arabic-to-lat' ),
											'<bdi>' . self::PRESET_EXAMPLE . '</bdi>'
										),
										self::EXAMPLE_TAGS
									);
									?>
								</p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'File names', 'arabic-to-lat' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Plugin::OPTION ); ?>[files]" value="1" <?php checked( $settings['files'] ); ?>>
								<?php
								echo wp_kses(
									sprintf(
										/* translators: %s: example, e.g. "صورة.jpg → sura.jpg" */
										esc_html__( 'Transliterate the names of newly uploaded files (%s)', 'arabic-to-lat' ),
										'<bdi dir="ltr"><bdi>صورة</bdi>.jpg → sura.jpg</bdi>'
									),
									self::EXAMPLE_TAGS
								);
								?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="atl-custom"><?php esc_html_e( 'Custom replacements', 'arabic-to-lat' ); ?></label></th>
						<td>
							<textarea id="atl-custom" name="<?php echo esc_attr( Plugin::OPTION ); ?>[custom_raw]" rows="8" cols="50" class="large-text code" dir="ltr"><?php echo esc_textarea( $custom ); ?></textarea>
							<p class="description">
								<?php
								echo wp_kses(
									sprintf(
										/* translators: 1: letter example "ع = 3", 2: word example "محمد = mohammed" */
										esc_html__( 'One per line: Arabic = latin. Single letters override the default table (%1$s); words and phrases replace the whole word (%2$s). Applies to new slugs only.', 'arabic-to-lat' ),
										'<bdi dir="ltr"><code><bdi>ع</bdi> = 3</code></bdi>',
										'<bdi dir="ltr"><code><bdi>محمد</bdi> = mohammed</code></bdi>'
									),
									self::EXAMPLE_TAGS
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2 id="atl-existing"><?php esc_html_e( 'Existing Arabic slugs', 'arabic-to-lat' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: "3 posts", 2: "1 term" */
						__( 'Slugs that still contain Arabic script: %1$s and %2$s.', 'arabic-to-lat' ),
						/* translators: %d: number of posts */
						sprintf( _n( '%d post', '%d posts', $counts['posts'], 'arabic-to-lat' ), $counts['posts'] ),
						/* translators: %d: number of terms */
						sprintf( _n( '%d term', '%d terms', $counts['terms'], 'arabic-to-lat' ), $counts['terms'] )
					)
				);
				?>
			</p>
			<p><?php esc_html_e( 'Converting is optional and changes URLs. Old URLs keep working: they are redirected (301) to the new ones, for posts, pages and terms. WooCommerce attribute terms are never converted, because existing variations depend on them.', 'arabic-to-lat' ); ?></p>

			<?php if ( 'backup' === $error ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'Please confirm that you have a database backup.', 'arabic-to-lat' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'reconvert' === $error ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'Please confirm that you understand these URLs change.', 'arabic-to-lat' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $result ) : ?>
				<?php $this->render_result( $result ); ?>
			<?php endif; ?>

			<?php if ( $counts['posts'] || $counts['terms'] ) : ?>
				<form id="atl-convert-form" class="atl-convert-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="arabic_to_lat_convert">
					<input type="hidden" name="arabic_to_lat_scope" value="1">
					<?php wp_nonce_field( 'arabic_to_lat_convert' ); ?>
					<fieldset>
						<legend><strong><?php esc_html_e( 'Convert slugs of', 'arabic-to-lat' ); ?></strong></legend>
						<?php foreach ( array( 'posts' => 'arabic_to_lat_post_types', 'terms' => 'arabic_to_lat_taxonomies' ) as $kind => $field ) : ?>
							<?php foreach ( $by_type[ $kind ] as $name => $n ) : ?>
								<?php
								$object = 'posts' === $kind ? get_post_type_object( $name ) : get_taxonomy( $name );
								$label  = $object ? $object->labels->name : $name;
								?>
								<label style="display:inline-block;margin:4px 16px 4px 0">
									<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[]" value="<?php echo esc_attr( $name ); ?>" data-count="<?php echo (int) $n; ?>" <?php checked( empty( $result['scope'] ) || in_array( $name, $result['scope'], true ) ); ?>>
									<?php echo esc_html( sprintf( '%s (%d)', $label, $n ) ); ?>
								</label>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</fieldset>
					<p>
						<button type="submit" name="arabic_to_lat_preview" value="1" class="button" formnovalidate><?php esc_html_e( 'Preview changes', 'arabic-to-lat' ); ?></button>
					</p>
					<p>
						<label>
							<input type="checkbox" name="arabic_to_lat_backup" value="1" required>
							<?php esc_html_e( 'I have a current database backup.', 'arabic-to-lat' ); ?>
						</label>
					</p>
					<button type="submit" name="arabic_to_lat_apply" value="1" class="button button-primary"><?php esc_html_e( 'Convert existing slugs', 'arabic-to-lat' ); ?></button>
					<p class="atl-progress" hidden>
						<progress max="1" value="0" style="width:100%;max-width:30em"></progress><br>
						<span class="atl-progress-status" role="status" aria-live="polite"></span>
					</p>
				</form>
			<?php endif; ?>

			<h2 id="atl-reconvert"><?php esc_html_e( 'Slugs this plugin already converted', 'arabic-to-lat' ); ?></h2>
			<p><?php esc_html_e( 'If you change the spelling style, slugs created earlier keep the spelling they already have. That is deliberate: they are working URLs. This is the separate, optional step that brings them in line with the style you use now.', 'arabic-to-lat' ); ?></p>
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Read this before using it.', 'arabic-to-lat' ); ?></strong></p>
				<ul style="list-style:disc;margin-left:2em">
					<li><?php esc_html_e( 'Every URL it changes currently works. Old URLs are redirected (301) to the new ones, but a redirect is not free: search engines take time to follow it, and links outside your site are not updated.', 'arabic-to-lat' ); ?></li>
					<li><?php esc_html_e( 'Only slugs this plugin produced are touched. A slug you typed yourself is recognised and left alone.', 'arabic-to-lat' ); ?></li>
					<li><?php esc_html_e( 'Uploaded files are never renamed: there is no redirect for a file, so every embed and hotlink to it would break.', 'arabic-to-lat' ); ?></li>
					<li><?php esc_html_e( 'If you sell through WooCommerce, product URLs may be in feeds and marketplace listings that a redirect does not repair.', 'arabic-to-lat' ); ?></li>
					<li><?php esc_html_e( 'Preview first. The preview lists every URL that would change.', 'arabic-to-lat' ); ?></li>
				</ul>
			</div>
			<form id="atl-reconvert-form" class="atl-convert-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="arabic_to_lat_convert">
				<input type="hidden" name="arabic_to_lat_scope" value="1">
				<input type="hidden" name="arabic_to_lat_reconvert" value="1">
				<?php wp_nonce_field( 'arabic_to_lat_convert' ); ?>
				<fieldset>
					<legend><strong><?php esc_html_e( 'Re-convert slugs of', 'arabic-to-lat' ); ?></strong></legend>
					<?php
					$reconvert_types = array(
						'arabic_to_lat_post_types' => array_diff( Converter::post_types(), array( 'attachment' ) ),
						'arabic_to_lat_taxonomies' => Converter::taxonomies(),
					);
					foreach ( $reconvert_types as $field => $names ) :
						foreach ( $names as $name ) :
							$object = 'arabic_to_lat_post_types' === $field ? get_post_type_object( $name ) : get_taxonomy( $name );
							$label  = $object ? $object->labels->name : $name;
							?>
							<label style="display:inline-block;margin:4px 16px 4px 0">
								<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[]" value="<?php echo esc_attr( $name ); ?>">
								<?php echo esc_html( $label ); ?>
							</label>
							<?php
						endforeach;
					endforeach;
					?>
				</fieldset>
				<p>
					<button type="submit" name="arabic_to_lat_preview" value="1" class="button" formnovalidate><?php esc_html_e( 'Preview changes', 'arabic-to-lat' ); ?></button>
				</p>
				<p>
					<label>
						<input type="checkbox" name="arabic_to_lat_backup" value="1" required>
						<?php esc_html_e( 'I have a current database backup.', 'arabic-to-lat' ); ?>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="arabic_to_lat_reconvert_confirm" value="1" required>
						<?php esc_html_e( 'I understand these URLs work today and will change.', 'arabic-to-lat' ); ?>
					</label>
				</p>
				<button type="submit" name="arabic_to_lat_apply" value="1" class="button"><?php esc_html_e( 'Re-convert these slugs', 'arabic-to-lat' ); ?></button>
				<p class="atl-progress" hidden>
					<progress max="1" value="0" style="width:100%;max-width:30em"></progress><br>
					<span class="atl-progress-status" role="status" aria-live="polite"></span>
				</p>
			</form>

			<?php
			/**
			 * Fires after the converter sections of Settings → Arabic to Latin.
			 * The optional AI section renders here.
			 */
			do_action( 'arabic_to_lat_settings_after' );
			?>

			<h2><?php esc_html_e( 'Default table', 'arabic-to-lat' ); ?></h2>
			<details>
				<summary><?php esc_html_e( 'Show all default replacements', 'arabic-to-lat' ); ?></summary>
				<p>
					<?php
					foreach ( Transliterator::default_table() as $from => $to ) {
						if ( Transliterator::VOWEL_U === $from || Transliterator::VOWEL_I === $from ) {
							continue;
						}
						echo '<bdi dir="ltr"><bdi><code>' . esc_html( $from ) . '</code></bdi>&nbsp;→&nbsp;<code>' . esc_html( '' === $to ? '∅' : $to ) . '</code></bdi> &nbsp; ';
					}
					?>
				</p>
				<p class="description"><?php esc_html_e( 'و and ي are read as the vowels u and i inside a word and as w and y at the start of a word or before a vowel.', 'arabic-to-lat' ); ?></p>
			</details>
		</div>
		<?php
	}

	/**
	 * @param array $result Converter result.
	 */
	private function render_result( array $result ) {
		$count = isset( $result['count'] ) ? (int) $result['count'] : count( $result['changes'] );
		if ( $result['dry_run'] ) {
			/* translators: %d: number of slugs */
			$message = sprintf( _n( 'Preview: %d slug would change.', 'Preview: %d slugs would change.', $count, 'arabic-to-lat' ), $count );
			if ( ! $result['done'] ) {
				$message .= ' ' . __( 'Only the first 200 are shown.', 'arabic-to-lat' );
			}
		} else {
			/* translators: %d: number of slugs */
			$message = sprintf( _n( '%d slug converted.', '%d slugs converted.', $count, 'arabic-to-lat' ), $count );
			if ( ! $result['done'] ) {
				$message .= ' ' . __( 'Time limit reached: run the conversion again to continue.', 'arabic-to-lat' );
			}
		}
		?>
		<div class="notice <?php echo $result['dry_run'] ? 'notice-info' : 'notice-success'; ?> inline"><p><?php echo esc_html( $message ); ?></p></div>
		<?php if ( $result['changes'] ) : ?>
			<table class="widefat striped" style="max-width:60em;margin-bottom:1em">
				<thead><tr>
					<th><?php esc_html_e( 'Type', 'arabic-to-lat' ); ?></th>
					<th><?php esc_html_e( 'Old slug', 'arabic-to-lat' ); ?></th>
					<th><?php esc_html_e( 'New slug', 'arabic-to-lat' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $result['changes'] as $change ) : ?>
					<tr>
						<td><?php echo esc_html( $change['label'] ); ?></td>
						<td dir="rtl"><?php echo esc_html( $change['old'] ); ?></td>
						<td><code><?php echo esc_html( $change['new'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private function page_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}
}
