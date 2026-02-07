<?php
/**
 * Admin UI: meta box, audience view, analytics.
 *
 * @package Personalization_API
 */

namespace Personalization_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 */
class Admin {

	const MENU_SLUG = 'personalization-api';
	const CAPABILITY = 'manage_options';

	/**
	 * @var self
	 */
	private static $instance;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_post', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_api_key_generate' ) );
		add_action( 'admin_init', array( $this, 'handle_log_clear' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add meta box to post edit screen.
	 */
	public function add_meta_box() {
		add_meta_box(
			'personalization_api_targeting',
			__( 'Personalization targeting', 'personalization-api' ),
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render meta box content.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( 'personalization_api_meta', 'personalization_api_meta_nonce' );
		$industry     = get_post_meta( $post->ID, Post_Meta::META_INDUSTRY, true );
		$company_size = get_post_meta( $post->ID, Post_Meta::META_COMPANY_SIZE, true );
		$role         = get_post_meta( $post->ID, Post_Meta::META_ROLE, true );
		?>
		<p>
			<label for="personalization_industry"><strong><?php esc_html_e( 'Industry', 'personalization-api' ); ?></strong></label><br>
			<input type="text" id="personalization_industry" name="personalization_industry" value="<?php echo esc_attr( $industry ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. technology, finance', 'personalization-api' ); ?>">
		</p>
		<p>
			<label for="personalization_company_size"><strong><?php esc_html_e( 'Company size', 'personalization-api' ); ?></strong></label><br>
			<input type="text" id="personalization_company_size" name="personalization_company_size" value="<?php echo esc_attr( $company_size ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. enterprise, smb', 'personalization-api' ); ?>">
		</p>
		<p>
			<label for="personalization_role"><strong><?php esc_html_e( 'Role', 'personalization-api' ); ?></strong></label><br>
			<input type="text" id="personalization_role" name="personalization_role" value="<?php echo esc_attr( $role ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'e.g. developer, manager', 'personalization-api' ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'Comma-separated values match any of the listed options. Leave empty to not target by this attribute.', 'personalization-api' ); ?></p>
		<?php
	}

	/**
	 * Save meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param \WP_Post $post   Post.
	 */
	public function save_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['personalization_api_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['personalization_api_meta_nonce'] ) ), 'personalization_api_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$post_meta = Post_Meta::instance();
		$fields = array(
			'personalization_industry'     => Post_Meta::META_INDUSTRY,
			'personalization_company_size' => Post_Meta::META_COMPANY_SIZE,
			'personalization_role'         => Post_Meta::META_ROLE,
		);
		foreach ( $fields as $input => $meta_key ) {
			if ( isset( $_POST[ $input ] ) ) {
				$value = $post_meta->sanitize_attribute( wp_unslash( $_POST[ $input ] ) );
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_menu() {
		add_options_page(
			__( 'Personalization API', 'personalization-api' ),
			__( 'Personalization API', 'personalization-api' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts/styles.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'settings_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'personalization-api-admin', PERSONALIZATION_API_PLUGIN_URL . 'assets/admin.css', array(), PERSONALIZATION_API_VERSION );
	}

	/**
	 * Render main admin page (tabs: Audience, Analytics, API Key, Logs).
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'audience';
		$tabs = array(
			'audience' => __( 'Audience targeting', 'personalization-api' ),
			'analytics' => __( 'Analytics', 'personalization-api' ),
			'api-key'  => __( 'API key', 'personalization-api' ),
			'logs'     => __( 'Logs', 'personalization-api' ),
		);
		?>
		<div class="wrap personalization-api-admin">
			<h1><?php esc_html_e( 'Personalization API', 'personalization-api' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::MENU_SLUG . '&tab=' . $key ) ); ?>" class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="personalization-api-tab-content">
				<?php
				switch ( $tab ) {
					case 'audience':
						$this->render_audience_tab();
						break;
					case 'analytics':
						$this->render_analytics_tab();
						break;
					case 'api-key':
						$this->render_api_key_tab();
						break;
					case 'logs':
						$this->render_logs_tab();
						break;
					default:
						$this->render_audience_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * List posts with their target attributes.
	 */
	private function render_audience_tab() {
		$query = new \WP_Query( array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => Post_Meta::META_INDUSTRY, 'compare' => 'EXISTS' ),
				array( 'key' => Post_Meta::META_COMPANY_SIZE, 'compare' => 'EXISTS' ),
				array( 'key' => Post_Meta::META_ROLE, 'compare' => 'EXISTS' ),
			),
		) );
		?>
		<h2><?php esc_html_e( 'Posts by target audience', 'personalization-api' ); ?></h2>
		<p><?php esc_html_e( 'Posts that have at least one personalization attribute set.', 'personalization-api' ); ?></p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post', 'personalization-api' ); ?></th>
					<th><?php esc_html_e( 'Industry', 'personalization-api' ); ?></th>
					<th><?php esc_html_e( 'Company size', 'personalization-api' ); ?></th>
					<th><?php esc_html_e( 'Role', 'personalization-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( $query->have_posts() ) : ?>
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( get_the_ID() ) ); ?>"><?php the_title(); ?></a>
								<?php if ( get_post_status() !== 'publish' ) : ?>
									— <span class="post-state"><?php echo esc_html( get_post_status() ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( get_post_meta( get_the_ID(), Post_Meta::META_INDUSTRY, true ) ); ?></td>
							<td><?php echo esc_html( get_post_meta( get_the_ID(), Post_Meta::META_COMPANY_SIZE, true ) ); ?></td>
							<td><?php echo esc_html( get_post_meta( get_the_ID(), Post_Meta::META_ROLE, true ) ); ?></td>
						</tr>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No posts with targeting yet. Edit a post and set attributes in the "Personalization targeting" meta box.', 'personalization-api' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Basic analytics: impressions and CTR per post.
	 */
	private function render_analytics_tab() {
		$analytics = Analytics::instance();
		$impressions = $analytics->get_all_impressions();
		$clicks = $analytics->get_all_clicks();
		$all_ids = array_unique( array_merge( array_keys( $impressions ), array_keys( $clicks ) ) );
		rsort( $all_ids );
		$all_ids = array_slice( $all_ids, 0, 100 );
		?>
		<h2><?php esc_html_e( 'Personalization effectiveness', 'personalization-api' ); ?></h2>
		<p><?php esc_html_e( 'Impressions are recorded when the recommendations API returns a post. Clicks can be recorded by your front-end (optional).', 'personalization-api' ); ?></p>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post', 'personalization-api' ); ?></th>
					<th><?php esc_html_e( 'Impressions', 'personalization-api' ); ?></th>
					<th><?php esc_html_e( 'Clicks', 'personalization-api' ); ?></th>
					<th><?php esc_html_e( 'CTR', 'personalization-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $all_ids ) ) : ?>
					<?php foreach ( $all_ids as $post_id ) : ?>
						<?php
						$post = get_post( $post_id );
						$imp = $analytics->get_impressions( $post_id );
						$clk = $analytics->get_clicks( $post_id );
						$ctr = $analytics->get_ctr( $post_id );
						?>
						<tr>
							<td><?php echo $post ? esc_html( $post->post_title ) : '#' . $post_id; ?></td>
							<td><?php echo (int) $imp; ?></td>
							<td><?php echo (int) $clk; ?></td>
							<td><?php echo $ctr !== null ? esc_html( round( $ctr * 100, 1 ) . '%' ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No data yet. Use the recommendations API to start recording impressions.', 'personalization-api' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * API key generation and display.
	 */
	private function render_api_key_tab() {
		$stored_key = get_option( REST_API::OPTION_API_KEY, '' );
		$generated = get_transient( 'personalization_api_key_just_generated' );
		$show_full_key = false;
		$api_key_display = '';
		if ( $generated ) {
			delete_transient( 'personalization_api_key_just_generated' );
			$api_key_display = $generated;
			$show_full_key = true;
		} elseif ( $stored_key ) {
			$api_key_display = substr( $stored_key, 0, 8 ) . '…' . substr( $stored_key, -4 );
		}
		?>
		<h2><?php esc_html_e( 'API authentication', 'personalization-api' ); ?></h2>
		<p><?php esc_html_e( 'Use an API key in the header <code>X-API-Key</code> or as query parameter <code>api_key</code> for unauthenticated requests. Logged-in users with Application Passwords can also use the API.', 'personalization-api' ); ?></p>
		<form method="post" action="">
			<?php wp_nonce_field( 'personalization_api_generate_key', 'personalization_api_key_nonce' ); ?>
			<p>
				<button type="submit" name="personalization_api_generate_key" class="button button-primary"><?php esc_html_e( 'Generate new API key', 'personalization-api' ); ?></button>
			</p>
		</form>
		<?php if ( $api_key_display ) : ?>
			<p><label><strong><?php esc_html_e( 'Current API key:', 'personalization-api' ); ?></strong></label><br>
			<code class="personalization-api-key"><?php echo esc_html( $api_key_display ); ?></code></p>
			<?php if ( $show_full_key ) : ?>
				<p class="description"><?php esc_html_e( 'Store this key securely; it will only be shown in full once.', 'personalization-api' ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No API key set. Generate one to allow external clients to call the recommendations endpoint.', 'personalization-api' ); ?></p>
		<?php endif; ?>
		<p><strong><?php esc_html_e( 'Endpoint:', 'personalization-api' ); ?></strong><br>
		<code><?php echo esc_url( rest_url( REST_API::NAMESPACE . '/recommendations' ) ); ?></code></p>
		<?php
	}

	/**
	 * Handle API key generation.
	 */
	public function handle_api_key_generate() {
		if ( ! isset( $_POST['personalization_api_generate_key'] ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		if ( ! isset( $_POST['personalization_api_key_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['personalization_api_key_nonce'] ) ), 'personalization_api_generate_key' ) ) {
			return;
		}
		$key = REST_API::generate_api_key();
		update_option( REST_API::OPTION_API_KEY, $key );
		set_transient( 'personalization_api_key_just_generated', $key, 60 );
		Cache::instance()->invalidate_all();
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::MENU_SLUG . '&tab=api-key' ) );
		exit;
	}

	/**
	 * Show recent logs.
	 */
	private function render_logs_tab() {
		$logs = Logger::get_recent( 100 );
		?>
		<h2><?php esc_html_e( 'Recent logs', 'personalization-api' ); ?></h2>
		<p><?php esc_html_e( 'Logs are written when WP_DEBUG is enabled.', 'personalization-api' ); ?></p>
		<form method="post" action="">
			<?php wp_nonce_field( 'personalization_api_clear_logs', 'personalization_api_logs_nonce' ); ?>
			<p><button type="submit" name="personalization_api_clear_logs" class="button"><?php esc_html_e( 'Clear logs', 'personalization-api' ); ?></button></p>
		</form>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'personalization-api' ); ?></th>
					<th><?php esc_html_e( 'Level', 'personalization-api' ); ?></th>
					<th><?php esc_html_e( 'Message', 'personalization-api' ); ?></th>
					<th><?php esc_html_e( 'Context', 'personalization-api' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $logs ) ) : ?>
					<?php foreach ( $logs as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['time'] ); ?></td>
							<td><?php echo esc_html( $entry['level'] ); ?></td>
							<td><?php echo esc_html( $entry['message'] ); ?></td>
							<td><pre><?php echo esc_html( ! empty( $entry['context'] ) ? wp_json_encode( $entry['context'], JSON_PRETTY_PRINT ) : '' ); ?></pre></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No log entries.', 'personalization-api' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Handle clear logs.
	 */
	public function handle_log_clear() {
		if ( ! isset( $_POST['personalization_api_clear_logs'] ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		if ( ! isset( $_POST['personalization_api_logs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['personalization_api_logs_nonce'] ) ), 'personalization_api_clear_logs' ) ) {
			return;
		}
		Logger::clear();
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::MENU_SLUG . '&tab=logs' ) );
		exit;
	}
}
