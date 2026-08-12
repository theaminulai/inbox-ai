<?php
/**
 * Settings page — shared subnav, reused identically by all seven tab
 * templates (`ai-provider.php`, `general.php`, `prompts.php`, `usage.php`,
 * `notifications.php`, `flamingo.php`, `integrations.php`).
 *
 * Every tab template renders this via {@see \InboxAI\Support\Template::render()}
 * rather than hand-copying the `<a data-subnav="...">` list — previously each
 * of the seven templates carried its own literal copy (see this plugin's own
 * commit history), so adding an eighth tab meant editing seven files in
 * lockstep and risking one falling out of sync. Now the tab list — its
 * order, keys, and labels — lives in exactly one place: {@see self} below.
 * Adding a tab is a one-line change here plus the matching
 * `Template::render( 'settings/<new-tab>', ... )` call in `settings.php` and
 * the whitelist entry in `SettingsPage::TABS`.
 *
 * The `.inboxai-settings__tabs` wrapper still lives inside each tab's own
 * `.inboxai-settings__shell` (not hoisted out further) because it doubles as
 * the left column of that tab's two-column CSS grid layout, not just
 * navigation — see `_layout.scss`. Only its inner link list was ever
 * actually duplicated.
 *
 * @var string $active_tab Currently visible tab key.
 *
 * @package InboxAI\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Single source of truth for the subnav's keys, order, and labels — see
// {@see \InboxAI\Admin\Pages\SettingsPage::TABS} for the matching `?tab=`
// whitelist (kept as a separate, deliberately key-only list there; nothing
// server-side needs the label) and `tabs.js`'s own `TABS` array (the
// client-side switch has no template to read this from, so it repeats just
// the keys).
$inboxai_settings_tabs = array(
	'ai-settings'      => __( 'AI Provider', 'inbox-ai' ),
	'general-settings' => __( 'General', 'inbox-ai' ),
	'prompts'          => __( 'Prompts', 'inbox-ai' ),
	'usage'            => __( 'Usage & Billing', 'inbox-ai' ),
	'notifications'    => __( 'Notifications', 'inbox-ai' ),
	'integrations'     => __( 'Integrations', 'inbox-ai' ),
	'flamingo'         => __( 'Import & Migration', 'inbox-ai' ),
);

?>
<div class="inboxai-settings__tabs">
	<?php foreach ( $inboxai_settings_tabs as $inboxai_tab_key => $inboxai_tab_label ) : ?>
		<a href="#" data-subnav="<?php echo esc_attr( $inboxai_tab_key ); ?>" class="<?php echo $inboxai_tab_key === $active_tab ? 'inboxai-is-active' : ''; ?>"><?php echo esc_html( $inboxai_tab_label ); ?></a>
	<?php endforeach; ?>
</div>
