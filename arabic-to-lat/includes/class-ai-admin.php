<?php
/**
 * Settings → Arabic to Latin → AI slug suggestions.
 *
 * @package ArabicToLat
 */

namespace ArabicToLat;

defined( 'ABSPATH' ) || exit;

/**
 * The AI section of the settings page: provider, model, key, style, daily
 * limit, and a "Test connection" button. Kept apart from the transliteration
 * settings so turning AI on or off never touches them.
 */
final class AI_Admin {

	const GROUP = 'arabic_to_lat_ai_group';

	public function init() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'arabic_to_lat_settings_after', array( $this, 'render' ) );
		add_action( 'admin_post_arabic_to_lat_ai_test', array( $this, 'handle_test' ) );
	}

	public function register_setting() {
		register_setting(
			self::GROUP,
			AI::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Keys: a new value is encrypted; an empty field keeps the saved key; the
	 * "remove" box deletes it. The key is never sent back to the browser.
	 *
	 * @param mixed $input Form input, or an already stored array.
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		// update_option() from code (tests, WP-CLI) passes the stored shape.
		if ( empty( $input['arabic_to_lat_ai_form'] ) ) {
			return $input;
		}

		$old    = get_option( AI::OPTION, array() );
		$old    = is_array( $old ) ? $old : array();
		$models = AI::models();
		$clean  = array(
			'enabled'     => ! empty( $input['enabled'] ),
			'provider'    => self::clean_provider( isset( $input['provider'] ) ? (string) $input['provider'] : '', $models ),
			'style'       => isset( $input['style'] ) && 'translate' === $input['style'] ? 'translate' : 'describe',
			'daily_limit' => isset( $input['daily_limit'] ) ? min( 10000, max( 0, (int) $input['daily_limit'] ) ) : 100,
		);
		foreach ( array_keys( $models ) as $provider ) {
			$model                        = isset( $input[ 'model_' . $provider ] ) ? (string) $input[ 'model_' . $provider ] : '';
			$clean[ 'model_' . $provider ] = isset( $models[ $provider ][ $model ] ) ? $model : (string) key( $models[ $provider ] );

			$entered = isset( $input[ 'key_' . $provider ] ) ? trim( sanitize_text_field( wp_unslash( (string) $input[ 'key_' . $provider ] ) ) ) : '';
			if ( ! empty( $input[ 'remove_key_' . $provider ] ) ) {
				$clean[ 'key_' . $provider ] = '';
			} elseif ( '' !== $entered ) {
				$clean[ 'key_' . $provider ] = AI::encrypt( $entered );
			} else {
				$clean[ 'key_' . $provider ] = isset( $old[ 'key_' . $provider ] ) ? (string) $old[ 'key_' . $provider ] : '';
			}
		}
		return $clean;
	}

	/**
	 * A known provider; "wordpress" only where core has the AI Client.
	 *
	 * @param string $provider Submitted value.
	 * @param array  $models   AI::models().
	 */
	private static function clean_provider( $provider, array $models ) {
		if ( isset( $models[ $provider ] ) ) {
			return $provider;
		}
		if ( AI::WORDPRESS === $provider && AI::wordpress_client_exists() ) {
			return $provider;
		}
		return AI::wordpress_client_exists() ? AI::WORDPRESS : 'anthropic';
	}

	/**
	 * "Test connection": one real request with a fixed Arabic title, no cache.
	 */
	public function handle_test() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'arabic-to-lat' ), 403 );
		}
		check_admin_referer( 'arabic_to_lat_ai_test' );

		$result = AI::suggest(
			array(
				'title'    => 'طبخة ملوخية',
				'content'  => 'طريقة تحضير الملوخية المصرية بالثوم والكزبرة خطوة بخطوة.',
				'no_cache' => true,
			)
		);
		set_transient(
			'arabic_to_lat_ai_test_' . get_current_user_id(),
			is_wp_error( $result ) ? array( 'error' => $result->get_error_message() ) : array( 'slug' => $result['slug'] ),
			HOUR_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'arabic_to_lat_ai_tested', 1, admin_url( 'options-general.php?page=' . Admin::PAGE ) ) . '#atl-ai' );
		exit;
	}

	public function render() {
		$settings = AI::settings();
		$models   = AI::models();
		$option   = AI::OPTION;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag.
		$test = isset( $_GET['arabic_to_lat_ai_tested'] ) ? get_transient( 'arabic_to_lat_ai_test_' . get_current_user_id() ) : false;
		?>
		<h2 id="atl-ai"><?php esc_html_e( 'AI slug suggestions (optional)', 'arabic-to-lat' ); ?></h2>
		<p><?php esc_html_e( 'With an AI service, authors can ask for an English slug that describes the post, for example طبخة ملوخية → molokhia-recipe. The suggestion appears in the block editor and is only used when the author picks it. Nothing runs automatically, and transliteration keeps working without AI.', 'arabic-to-lat' ); ?></p>
		<p class="description"><?php esc_html_e( 'Privacy: when an author asks for a suggestion, the post title and the first 300 words of the post are sent to the AI service you choose below. Each request uses your own AI account and costs a fraction of a cent.', 'arabic-to-lat' ); ?></p>

		<?php if ( is_array( $test ) ) : ?>
			<?php if ( isset( $test['error'] ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $test['error'] ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-success inline"><p>
					<?php
					/* translators: %s: slug suggested by the AI */
					echo esc_html( sprintf( __( 'Connection works. Suggested slug for "طبخة ملوخية": %s', 'arabic-to-lat' ), $test['slug'] ) );
					?>
				</p></div>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( ! AI::can_encrypt() ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'The PHP openssl extension is missing, so a key saved here is stored without encryption. Define it in wp-config.php instead (see below).', 'arabic-to-lat' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( self::GROUP ); ?>
			<input type="hidden" name="<?php echo esc_attr( $option ); ?>[arabic_to_lat_ai_form]" value="1">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'AI suggestions', 'arabic-to-lat' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>> <?php esc_html_e( 'Show the "Suggest an English slug" panel in the block editor', 'arabic-to-lat' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Provider', 'arabic-to-lat' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Provider', 'arabic-to-lat' ); ?></legend>
							<?php if ( AI::wordpress_client_exists() ) : ?>
								<div style="margin-bottom:12px">
									<label><input type="radio" name="<?php echo esc_attr( $option ); ?>[provider]" value="<?php echo esc_attr( AI::WORDPRESS ); ?>" <?php checked( $settings['provider'], AI::WORDPRESS ); ?>> <strong><?php esc_html_e( 'WordPress AI connector', 'arabic-to-lat' ); ?></strong></label>
									<p style="margin:6px 0 0 24px">
										<?php
										if ( ! AI::wordpress_client_available() ) {
											esc_html_e( 'AI features are switched off on this site.', 'arabic-to-lat' );
										} elseif ( AI::WORDPRESS === $settings['provider'] && AI::wordpress_connector_ready() ) {
											esc_html_e( 'Ready: uses the AI service and key set up in Settings → Connectors. No key needed here.', 'arabic-to-lat' );
										} elseif ( AI::WORDPRESS === $settings['provider'] ) {
											printf(
												/* translators: %s: link to Settings → Connectors */
												esc_html__( 'No AI connector is set up yet. Add one in %s (for example Anthropic or OpenAI), then come back.', 'arabic-to-lat' ),
												'<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">' . esc_html__( 'Settings → Connectors', 'arabic-to-lat' ) . '</a>'
											);
										} else {
											esc_html_e( 'Uses the AI service and key set up in Settings → Connectors. No key needed here.', 'arabic-to-lat' );
										}
										?>
									</p>
								</div>
							<?php endif; ?>
							<?php foreach ( array( 'anthropic' => 'Claude (Anthropic)', 'openai' => 'ChatGPT (OpenAI)' ) as $provider => $label ) : ?>
								<div style="margin-bottom:12px">
									<label><input type="radio" name="<?php echo esc_attr( $option ); ?>[provider]" value="<?php echo esc_attr( $provider ); ?>" <?php checked( $settings['provider'], $provider ); ?>> <strong><?php echo esc_html( $label ); ?></strong></label>
									<p style="margin:6px 0 0 24px">
										<label><?php esc_html_e( 'Model', 'arabic-to-lat' ); ?>
											<select name="<?php echo esc_attr( $option . '[model_' . $provider . ']' ); ?>">
												<?php foreach ( $models[ $provider ] as $id => $name ) : ?>
													<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings[ 'model_' . $provider ], $id ); ?>><?php echo esc_html( $name ); ?></option>
												<?php endforeach; ?>
											</select>
										</label>
									</p>
									<p style="margin:6px 0 0 24px">
										<?php if ( 'constant' === AI::key_source( $provider ) ) : ?>
											<?php
											/* translators: %s: PHP constant name */
											echo esc_html( sprintf( __( 'API key: set in wp-config.php (%s).', 'arabic-to-lat' ), AI::KEY_CONSTANTS[ $provider ] ) );
											?>
										<?php else : ?>
											<label><?php esc_html_e( 'API key', 'arabic-to-lat' ); ?>
												<input type="password" class="regular-text" autocomplete="off" name="<?php echo esc_attr( $option . '[key_' . $provider . ']' ); ?>" value="" placeholder="<?php echo esc_attr( 'option' === AI::key_source( $provider ) ? sprintf( /* translators: %s: last characters of the key */ __( 'saved (%s) — leave empty to keep it', 'arabic-to-lat' ), AI::key_hint( $provider ) ) : __( 'paste your API key', 'arabic-to-lat' ) ); ?>">
											</label>
											<?php if ( 'option' === AI::key_source( $provider ) ) : ?>
												<label style="margin-left:8px"><input type="checkbox" name="<?php echo esc_attr( $option . '[remove_key_' . $provider . ']' ); ?>" value="1"> <?php esc_html_e( 'Remove saved key', 'arabic-to-lat' ); ?></label>
											<?php endif; ?>
										<?php endif; ?>
									</p>
								</div>
							<?php endforeach; ?>
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: constant for the Claude key, 2: constant for the ChatGPT key */
										__( 'Keys saved here are stored encrypted. To keep them out of the database entirely, add define( \'%1$s\', \'…\' ); or define( \'%2$s\', \'…\' ); to wp-config.php.', 'arabic-to-lat' ),
										AI::KEY_CONSTANTS['anthropic'],
										AI::KEY_CONSTANTS['openai']
									)
								);
								?>
							</p>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Suggestion style', 'arabic-to-lat' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:6px"><input type="radio" name="<?php echo esc_attr( $option ); ?>[style]" value="describe" <?php checked( $settings['style'], 'describe' ); ?>> <?php esc_html_e( 'Describe the post, the way people search for it (طبخة ملوخية → molokhia-recipe)', 'arabic-to-lat' ); ?></label>
						<label style="display:block"><input type="radio" name="<?php echo esc_attr( $option ); ?>[style]" value="translate" <?php checked( $settings['style'], 'translate' ); ?>> <?php esc_html_e( 'Translate the title (طبخة ملوخية → molokhia-dish)', 'arabic-to-lat' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="atl-ai-limit"><?php esc_html_e( 'Daily limit', 'arabic-to-lat' ); ?></label></th>
					<td>
						<input type="number" id="atl-ai-limit" min="0" max="10000" class="small-text" name="<?php echo esc_attr( $option ); ?>[daily_limit]" value="<?php echo (int) $settings['daily_limit']; ?>">
						<?php
						/* translators: %d: requests used today */
						echo esc_html( sprintf( __( 'requests per day (0 = no limit). Used today: %d. Repeated requests for the same post are answered from a cache and are free.', 'arabic-to-lat' ), AI::used_today() ) );
						?>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save AI settings', 'arabic-to-lat' ) ); ?>
		</form>

		<?php if ( AI::is_ready() ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="arabic_to_lat_ai_test">
				<?php wp_nonce_field( 'arabic_to_lat_ai_test' ); ?>
				<?php submit_button( __( 'Test connection', 'arabic-to-lat' ), 'secondary', 'arabic_to_lat_ai_test', false ); ?>
				<span class="description"><?php esc_html_e( 'Sends one request with the title "طبخة ملوخية".', 'arabic-to-lat' ); ?></span>
			</form>
		<?php endif; ?>
		<?php
	}
}
