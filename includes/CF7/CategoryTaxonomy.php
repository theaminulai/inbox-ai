<?php
/**
 * Per-form, admin-editable AI categories.
 *
 * @package CF7AIInbox\CF7
 */

namespace CF7AIInbox\CF7;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CategoryTaxonomy
 *
 * Replaces a fixed, hardcoded category list with a real, growing
 * vocabulary: each Contact Form 7 form gets its own set of categories,
 * checked/added from a WooCommerce-"Product categories"-style checklist box
 * on that form's own edit screen sidebar. There is no seeded/default
 * category of any kind — a brand-new site's checklist is genuinely empty
 * until an admin clicks "+ Add new category" on some form, and only
 * categories that have actually been added anywhere ever appear as
 * checkboxes (on every form, not just the one they were first added on) or
 * in the AI Inbox List's category filter.
 *
 * A real WordPress taxonomy (rather than a bespoke options table) is used
 * for this so term storage and "create on the fly" behavior come for free
 * from `wp_set_object_terms()` — nothing here hand-rolls term management.
 *
 * Contact Form 7's `wpcf7_contact_form` post type is registered with
 * `'public' => false` and no `add_meta_box()`/`do_meta_boxes()` call
 * anywhere in CF7 itself (confirmed by reading `contact-form-7/includes/contact-form.php`
 * and `contact-form-7/admin/edit-contact-form.php`), so WordPress's native
 * taxonomy meta box never has anywhere to attach — this box is entirely
 * hand-rendered and injected via CF7's own `wpcf7_admin_misc_pub_section`
 * hook (fired inside the sidebar "Status" box's `#misc-publishing-actions`
 * div), the closest thing CF7 offers to a sidebar extension point. Saving
 * happens on `wpcf7_after_save`, which CF7 fires (with the fully-saved
 * `WPCF7_ContactForm` object) whether the form was just created or updated.
 */
final class CategoryTaxonomy {

	/**
	 * Taxonomy key.
	 *
	 * @var string
	 */
	public const TAXONOMY = 'cf7ai_category';

	/**
	 * Nonce action for the checklist box's hidden field.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'cf7ai_save_categories';

	/**
	 * Registers WordPress/CF7 hooks. Must run unconditionally (not just
	 * `is_admin()`) — {@see \CF7AIInbox\AI\AnalysisQueue::process()} reads
	 * this taxonomy's terms from a WP-Cron request.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'wpcf7_admin_misc_pub_section', array( $this, 'render_metabox' ) );
		add_action( 'wpcf7_after_save', array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_script' ) );
	}

	/**
	 * Enqueues the checklist box's behavior script, only on CF7's own "Edit
	 * Contact Form" / "Add Contact Form" screens.
	 *
	 * Deliberately a plain, unbundled static file rather than something
	 * routed through this plugin's own webpack entry point
	 * (`src/admin/index.js`) — that bundle is only ever enqueued on this
	 * plugin's own `cf7ai-*` pages (see
	 * {@see \CF7AIInbox\Admin\Menu::enqueue_assets()}), never on CF7's own
	 * `admin.php?page=wpcf7` screens.
	 *
	 * @return void
	 */
	public function maybe_enqueue_script(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page-identity check, not a state-changing request.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'wpcf7-new' !== $page ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page-identity check.
			$is_edit_screen = 'wpcf7' === $page && isset( $_GET['post'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['action'] ?? '' ) );

			if ( ! $is_edit_screen ) {
				return;
			}
		}
		$cf7_assets_file = CF7AI_INBOX_PATH . 'build/cf7/category.asset.php';
		$cf7_assets      = file_exists( $cf7_assets_file )
			? require $cf7_assets_file
			: array(
				'dependencies' => array(),
				'version'      => CF7AI_INBOX_VERSION,
			);
		wp_enqueue_script(
			'cf7ai-category-metabox',
			CF7AI_INBOX_URL . 'build/cf7/category.js',
			$cf7_assets['dependencies'],
			$cf7_assets['version'],
			true
		);
	}

	/**
	 * Registers the taxonomy. No default terms are ever created — a fresh
	 * site's checklist starts genuinely empty.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			'wpcf7_contact_form',
			array(
				'labels'            => array(
					'name'          => __( 'AI Categories', 'cf7-ai-inbox' ),
					'singular_name' => __( 'AI Category', 'cf7-ai-inbox' ),
				),
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => false,
				'show_in_rest'      => false,
				'show_admin_column' => false,
				'query_var'         => false,
				'rewrite'           => false,
			)
		);
	}

	/**
	 * Renders the "AI Categories" checklist box inside the CF7 edit-form
	 * screen's sidebar "Status" box — a checkbox per existing category
	 * (checked if assigned to this form) plus a WooCommerce-"Product
	 * categories"-style "+ Add new category" toggle. Only markup here; all
	 * behavior is wired up by `assets/js/category-metabox.js` (see the
	 * comment at the end of this method for why nothing is inlined).
	 *
	 * @param int|string $post_id The form's post id, or a non-numeric/`-1`
	 *                            placeholder for a not-yet-saved new form.
	 *
	 * @return void
	 */
	public function render_metabox( $post_id ): void {
		$post_id  = (int) $post_id;
		$assigned = $post_id > 0 ? wp_get_post_terms( $post_id, self::TAXONOMY, array( 'fields' => 'names' ) ) : array();
		$assigned = is_array( $assigned ) ? $assigned : array();

		$all_terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'names',
			)
		);
		$all_terms = is_array( $all_terms ) ? $all_terms : array();
		sort( $all_terms );

		?>
		<div class="misc-pub-section cf7ai-categories" style="border-top:1px solid #eee;padding:10px 10px 12px;">
			<strong style="display:block;margin-bottom:6px;"><?php esc_html_e( 'AI Categories', 'cf7-ai-inbox' ); ?></strong>
			<p style="margin:0 0 8px;font-size:12px;color:#646970;">
				<?php esc_html_e( 'The categories the AI can assign to this form\'s submissions.', 'cf7-ai-inbox' ); ?>
			</p>
			<div id="cf7ai-category-list" style="max-height:200px;overflow-y:auto;border:1px solid #dcdcde;border-radius:3px;padding:8px 10px;background:#fff;margin-bottom:8px;">
				<?php if ( array() === $all_terms ) : ?>
					<p id="cf7ai-category-empty" style="margin:0;font-size:12px;color:#646970;font-style:italic;">
						<?php esc_html_e( 'No categories yet.', 'cf7-ai-inbox' ); ?>
					</p>
				<?php else : ?>
					<?php foreach ( $all_terms as $term_name ) : ?>
						<label style="display:block;font-size:13px;margin-bottom:4px;">
							<input type="checkbox" name="cf7ai_categories[]" value="<?php echo esc_attr( $term_name ); ?>" <?php checked( in_array( $term_name, $assigned, true ) ); ?> />
							<?php echo esc_html( $term_name ); ?>
						</label>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<a href="#" id="cf7ai-add-toggle"><?php esc_html_e( '+ Add new category', 'cf7-ai-inbox' ); ?></a>
			<div id="cf7ai-add-new" style="display:none;margin-top:8px;">
				<input type="text" id="cf7ai-add-input" style="width:100%;box-sizing:border-box;margin-bottom:6px;" placeholder="<?php esc_attr_e( 'New category name', 'cf7-ai-inbox' ); ?>" />
				<button type="button" class="button" id="cf7ai-add-submit"><?php esc_html_e( 'Add new category', 'cf7-ai-inbox' ); ?></button>
			</div>
			<?php wp_nonce_field( self::NONCE_ACTION, 'cf7ai_categories_nonce' ); ?>
		</div>
		<?php
		/*
		 * No inline <style>/<script> here on purpose: CF7's edit-form screen
		 * builds its entire page through WPCF7_HTMLFormatter, which runs
		 * everything through wp_kses() before printing (see
		 * WPCF7_HTMLFormatter::print(), which calls
		 * `echo wp_kses( $this->output(), $this->options['allowed_html'] )`).
		 * `script`/`style` are never in that allow-list, so wp_kses() strips
		 * the tags but leaves their raw text content behind. All behavior
		 * instead lives in the conditionally-enqueued
		 * assets/js/category-metabox.js (a real <script src>, untouched by
		 * kses).
		 */
	}

	/**
	 * Persists the checked/added category list against the just-saved form.
	 *
	 * Hooked to `wpcf7_after_save`, which fires for both a brand-new form
	 * (after `wpcf7_after_create`) and an updated one (after
	 * `wpcf7_after_update`) — in both cases the object's id is already the
	 * real, saved post id.
	 *
	 * @param \WPCF7_ContactForm $contact_form The just-saved form.
	 *
	 * @return void
	 */
	public function save( $contact_form ): void {
		if ( ! $contact_form instanceof \WPCF7_ContactForm ) {
			return;
		}

		// Note: capability is deliberately not re-checked here. By the time
		// `wpcf7_after_save` fires, Contact Form 7's own admin handler
		// (`admin/admin.php`) has already verified both its nonce and the
		// `wpcf7_edit_contact_form` capability for this exact save request
		// — this only re-checks the one field this class itself added.
		if (
			! isset( $_POST['cf7ai_categories_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cf7ai_categories_nonce'] ) ), self::NONCE_ACTION )
		) {
			return;
		}

		// Absent entirely (rather than an empty array) when every checkbox
		// is unchecked and nothing new was added — an empty list is a
		// legitimate, intentional state (unassign every category from this
		// form), not "the field wasn't submitted".
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_unslash() only; every element is run through sanitize_text_field() in the array_map() immediately below before it's used for anything.
		$posted = isset( $_POST['cf7ai_categories'] ) && is_array( $_POST['cf7ai_categories'] )
			? wp_unslash( $_POST['cf7ai_categories'] )
			: array();

		$names = array_filter(
			array_map(
				static function ( $name ) {
					return sanitize_text_field( trim( (string) $name ) );
				},
				$posted
			),
			static function ( $name ) {
				return '' !== $name;
			}
		);

		$names = array_values( array_unique( $names ) );

		// wp_set_object_terms() creates any of these as new terms on the
		// fly if they don't already exist — the same behavior WordPress's
		// own Categories/Tags box relies on. An empty array intentionally
		// removes every existing association.
		wp_set_object_terms( $contact_form->id(), $names, self::TAXONOMY, false );
	}
}
