<?php
/**
 * Per-form, admin-editable AI categories.
 *
 * @package InboxAI\CF7
 */

namespace InboxAI\CF7;

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
	public const TAXONOMY = 'inboxai_category';

	/**
	 * Nonce action for the checklist box's hidden field.
	 *
	 * @var string
	 */
	private const NONCE_ACTION = 'inboxai_save_categories';

	/**
	 * Registers WordPress/CF7 hooks. Must run unconditionally (not just
	 * `is_admin()`) — {@see \InboxAI\AI\AnalysisQueue::process()} reads
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
	 * plugin's own `inboxai-*` pages (see
	 * {@see \InboxAI\Admin\Menu::enqueue_assets()}), never on CF7's own
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
		$cf7_assets_file = INBOXAI_PATH . 'build/cf7/category.asset.php';
		$cf7_assets      = file_exists( $cf7_assets_file )
			? require $cf7_assets_file
			: array(
				'dependencies' => array(),
				'version'      => INBOXAI_VERSION,
			);
		wp_enqueue_script(
			'inboxai-category-metabox',
			INBOXAI_URL . 'build/cf7/category.js',
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
					'name'          => __( 'AI Categories', 'inbox-ai' ),
					'singular_name' => __( 'AI Category', 'inbox-ai' ),
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
	 * Renders the "AI Categories" box as its own, visually separate postbox
	 * in the CF7 edit-form screen's sidebar — a checkbox per existing
	 * category (checked if assigned to this form) plus a
	 * WooCommerce-"Product categories"-style "+ Add new category" toggle.
	 * This box is deliberately add/assign-only: there is no rename or
	 * delete control here on purpose, so a category can't be renamed or
	 * removed (affecting every form that uses it) from a screen that's
	 * scoped to just one form. Renaming/deleting categories globally is a
	 * separate, deliberate action — see the "Manage Categories" card on the
	 * Settings page's General tab
	 * ({@see \InboxAI\Admin\Ajax\SettingsAjaxController::rename_category()}/
	 * {@see \InboxAI\Admin\Ajax\SettingsAjaxController::delete_category()}).
	 *
	 * Contact Form 7 has no hook that renders a genuinely separate,
	 * sibling postbox in this screen's sidebar — {@see self::init()}'s own
	 * `wpcf7_admin_misc_pub_section` hook is CF7's only sidebar extension
	 * point, and it fires *inside* the "Status" box's own
	 * `#misc-publishing-actions` div (confirmed by reading CF7's own
	 * `admin/edit-contact-form.php`: `#postbox-container-1` hardcodes
	 * exactly two `<section class="postbox">`s — "Status" and "Do you need
	 * help?" — with no action hook around or between them). So this method
	 * still renders here (there's nowhere else to hook into), but as a
	 * complete, hidden `<section class="postbox">` of its own; the
	 * conditionally-enqueued `category-metabox.js` moves that whole section
	 * out of the Status box and into `#postbox-container-1` as its own
	 * sibling, then reveals it — a plain DOM move, not a second render.
	 * Only markup here; all behavior (including that move) is wired up by
	 * that script (see the comment at the end of this method for why
	 * nothing is inlined).
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

		$manage_url = add_query_arg( 'tab', 'general-settings', \InboxAI\Admin\Menu::url( 'inboxai-settings' ) );

		?>
		<section class="postbox inboxai-categories-postbox" id="inboxai-category-postbox" style="display:none;">
			<h2><?php esc_html_e( 'AI Categories', 'inbox-ai' ); ?></h2>
			<div class="inside inboxai-categories">
				<p style="margin:0 0 8px;font-size:12px;color:#646970;">
					<?php esc_html_e( 'The categories the AI can assign to this form\'s submissions. Check the ones that apply, or add a new one — renaming or deleting a category (for every form that uses it) is done from Settings, not here.', 'inbox-ai' ); ?>
				</p>
				<div id="inboxai-category-list" style="max-height:200px;overflow-y:auto;border:1px solid #dcdcde;border-radius:3px;padding:8px 10px;background:#fff;margin-bottom:8px;">
					<?php if ( array() === $all_terms ) : ?>
						<p id="inboxai-category-empty" style="margin:0;font-size:12px;color:#646970;font-style:italic;">
							<?php esc_html_e( 'No categories yet.', 'inbox-ai' ); ?>
						</p>
					<?php else : ?>
						<?php foreach ( $all_terms as $term_name ) : ?>
							<label style="display:block;font-size:13px;margin-bottom:4px;">
								<input type="checkbox" name="inboxai_categories[]" value="<?php echo esc_attr( $term_name ); ?>" <?php checked( in_array( $term_name, $assigned, true ) ); ?> />
								<?php echo esc_html( $term_name ); ?>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<a href="#" id="inboxai-add-toggle"><?php esc_html_e( '+ Add new category', 'inbox-ai' ); ?></a>
				<div id="inboxai-add-new" style="display:none;margin-top:8px;">
					<input type="text" id="inboxai-add-input" style="width:100%;box-sizing:border-box;margin-bottom:6px;" placeholder="<?php esc_attr_e( 'New category name', 'inbox-ai' ); ?>" />
					<button type="button" class="button" id="inboxai-add-submit"><?php esc_html_e( 'Add new category', 'inbox-ai' ); ?></button>
				</div>
				<p style="margin:10px 0 0;font-size:11.5px;">
					<a href="<?php echo esc_url( $manage_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Rename or delete categories →', 'inbox-ai' ); ?></a>
				</p>
				<?php wp_nonce_field( self::NONCE_ACTION, 'inboxai_categories_nonce' ); ?>
			</div>
		</section>
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
			! isset( $_POST['inboxai_categories_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['inboxai_categories_nonce'] ) ), self::NONCE_ACTION )
		) {
			return;
		}

		// Absent entirely (rather than an empty array) when every checkbox
		// is unchecked and nothing new was added — an empty list is a
		// legitimate, intentional state (unassign every category from this
		// form), not "the field wasn't submitted".
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_unslash() only; every element is run through sanitize_text_field() in the array_map() immediately below before it's used for anything.
		$posted = isset( $_POST['inboxai_categories'] ) && is_array( $_POST['inboxai_categories'] )
			? wp_unslash( $_POST['inboxai_categories'] )
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
