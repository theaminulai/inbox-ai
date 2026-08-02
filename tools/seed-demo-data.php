<?php
/**
 * Dev-only tool: seeds 100 realistic demo submissions into Inbox AI's own
 * tables so the AI Inbox, Contacts, and Usage & Billing screens have
 * something to show while testing. Not part of the shipped plugin — delete
 * this file (or just leave it out of the SVN/zip package) before release.
 *
 * HOW TO RUN (pick one):
 *
 * 1. WP-CLI (if installed):
 *      wp eval-file wp-content/plugins/cf7-ai-inbox/tools/seed-demo-data.php
 *
 * 2. Browser, while logged into wp-admin as an administrator:
 *      Visit this file's URL directly, e.g.
 *      http://localhost/your-site/wp-content/plugins/cf7-ai-inbox/tools/seed-demo-data.php?confirm=yes
 *    The `?confirm=yes` is required on purpose, so this can't fire by accident.
 *
 * Safe to run more than once — it just adds another 100 rows each time.
 *
 * Full developer guide: docs/dev-tools.md
 *
 * @package InboxAI
 */

use InboxAI\Database\MessageRepository;
use InboxAI\Database\ActivityRepository;
use InboxAI\Database\Migrator;

// Bootstrap WordPress only if this wasn't already loaded via `wp eval-file`
// (WP-CLI loads WordPress itself first, so ABSPATH is already defined then).
if ( ! defined( 'ABSPATH' ) ) {
	require dirname( __FILE__, 5 ) . '/wp-load.php';
}

$is_cli = defined( 'WP_CLI' ) && WP_CLI;

if ( ! $is_cli ) {
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		wp_die( 'You must be logged in as an administrator to run this.' );
	}

	if ( ! isset( $_GET['confirm'] ) || 'yes' !== $_GET['confirm'] ) {
		wp_die( 'Add <code>?confirm=yes</code> to the URL to actually seed demo data.' );
	}
}

if ( ! class_exists( MessageRepository::class ) ) {
	die( 'Inbox AI does not appear to be active on this site.' );
}

/**
 * How many demo submissions to create.
 */
$total = 100;

$first_names = array( 'Aisha', 'Ben', 'Carla', 'Daniel', 'Elena', 'Farid', 'Grace', 'Hiro', 'Ines', 'Jamal', 'Kayla', 'Liam', 'Maya', 'Noah', 'Olga', 'Priya', 'Quinn', 'Rosa', 'Sam', 'Tara' );
$last_names  = array( 'Khan', 'Nguyen', 'Silva', 'Brown', 'Popescu', 'Haddad', 'Lee', 'Tanaka', 'Fischer', 'Okafor', 'Martin', 'Reilly', 'Rossi', 'Kim', 'Ivanova', 'Sharma', 'Turner', 'Diaz', 'Cohen', 'Patel' );
$domains     = array( 'gmail.com', 'outlook.com', 'yahoo.com', 'acme-corp.test', 'northwind.test', 'example.com' );

$categories = array( 'Quote Request', 'Support', 'General Inquiry', 'Feedback', 'Bug Report', 'Partnership', 'Job Application' );
$priorities = array( 'Urgent', 'High', 'Normal', 'Low' );

// `source_category` is fixed per form (it's captured once from that form's
// own CategoryTaxonomy assignment — see `SubmissionMapper::find_source_category()`
// — not per submission), so it's assigned per demo form below, not per row.
// Left blank for one form in three so seeded data also demonstrates the
// "form has no category configured yet" case the AI category prompt has to
// handle on its own (see `PromptBuilder::build_analysis_prompt()`).
$source_category_pool = array( 'Sales', 'Support', 'Careers', 'General' );
$providers  = array(
	'openai'    => array( 'gpt-4o-mini', 'gpt-4.1' ),
	'anthropic' => array( 'claude-haiku-4-5', 'claude-sonnet-4-5' ),
	'gemini'    => array( 'gemini-2.5-flash', 'gemini-2.5-pro' ),
);

$subjects_by_category = array(
	'Quote Request'   => array( 'Quote for a new project', 'Pricing for bulk order', 'Can you send a quote?' ),
	'Support'         => array( 'Trouble logging in', 'My order hasn\'t arrived', 'Feature isn\'t working' ),
	'General Inquiry' => array( 'Quick question', 'Do you offer this service?', 'Just checking something' ),
	'Feedback'        => array( 'Loved the new update', 'Some feedback on your site', 'Suggestion for improvement' ),
	'Bug Report'      => array( 'Found a bug on checkout', 'Error when submitting the form', 'Page crashes on mobile' ),
	'Partnership'     => array( 'Partnership opportunity', 'Interested in collaborating', 'Vendor inquiry' ),
	'Job Application' => array( 'Application for open role', 'Interested in joining your team', 'Resume attached' ),
);

$message_bodies = array(
	"Hi there,\n\nI wanted to reach out about this — could someone get back to me when possible?\n\nThanks!",
	"Hello,\n\nWe've been using your service for a while now and had a question come up. Appreciate any help.\n\nBest,",
	"Hey,\n\nJust submitting this through the contact form. Let me know what the next steps are.\n\nThanks!",
	"Hi,\n\nThis has been an issue for a couple of days now. Would love a quick resolution if possible.\n\nRegards,",
);

// Real Contact Form 7 forms if any exist on this site, otherwise a fallback.
$cf7_forms = get_posts(
	array(
		'post_type'      => 'wpcf7_contact_form',
		'post_status'    => 'publish',
		'numberposts'    => -1,
	)
);

$forms = array();
foreach ( $cf7_forms as $i => $form_post ) {
	$forms[] = array(
		'id'              => $form_post->ID,
		'title'           => $form_post->post_title,
		// Every third form seeded with no source category, so the demo data
		// covers both cases (see the `$source_category_pool` note above).
		'source_category' => ( 0 === $i % 3 ) ? '' : $source_category_pool[ $i % count( $source_category_pool ) ],
	);
}
if ( array() === $forms ) {
	$forms[] = array( 'id' => 1, 'title' => 'Contact form 1', 'source_category' => $source_category_pool[0] );
}

// A pool of ~25 recurring senders (rather than 100 unique) so the Contacts
// List has something realistic to group by sender.
$sender_pool = array();
for ( $i = 0; $i < 25; $i++ ) {
	$first = $first_names[ array_rand( $first_names ) ];
	$last  = $last_names[ array_rand( $last_names ) ];
	$sender_pool[] = array(
		'name'  => "{$first} {$last}",
		'email' => strtolower( $first . '.' . $last . $i ) . '@' . $domains[ array_rand( $domains ) ],
	);
}

$created = 0;

for ( $n = 0; $n < $total; $n++ ) {
	$sender   = $sender_pool[ array_rand( $sender_pool ) ];
	$form     = $forms[ array_rand( $forms ) ];
	$category = $categories[ array_rand( $categories ) ];
	$subject  = $subjects_by_category[ $category ][ array_rand( $subjects_by_category[ $category ] ) ];
	$message  = $message_bodies[ array_rand( $message_bodies ) ];

	$id = MessageRepository::insert(
		array(
			'form_id'           => $form['id'],
			'form_title'        => $form['title'],
			'submission_hash'   => md5( $sender['email'] . $subject . $n . wp_rand() ),
			'sender_name'       => $sender['name'],
			'sender_email'      => $sender['email'],
			'subject'           => $subject,
			'message'           => $message,
			'fields'            => array(
				'your-name'    => $sender['name'],
				'your-email'   => $sender['email'],
				'your-subject' => $subject,
				'your-message' => $message,
			),
			'meta'              => array( 'seeded' => true ),
			'channel'           => 'contact-form-7',
			'submission_status' => 'mail_sent',
			'mail_status'       => 'sent',
			'source_category'   => $form['source_category'],
		)
	);

	if ( 0 === $id ) {
		continue;
	}

	// Spread submissions across the last 90 days instead of all "now".
	$days_ago    = wp_rand( 0, 90 );
	$hours_ago   = wp_rand( 0, 23 );
	$created_at  = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days -{$hours_ago} hours" ) );

	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- dev-only seeding tool, not shipped with the plugin; MessageRepository::insert() has no way to backdate created_at, which this tool needs for realistic date-filter testing.
	$wpdb->update(
		$wpdb->prefix . Migrator::MESSAGES_TABLE,
		array( 'created_at' => $created_at ),
		array( 'id' => $id )
	);

	ActivityRepository::log( $id, 'received', array(), 0 );

	// Distribute workflow statuses roughly like a real inbox would look.
	$roll = wp_rand( 1, 100 );

	if ( $roll <= 5 ) {
		MessageRepository::mark_failed( $id, 'The AI provider request timed out.' );
		ActivityRepository::log( $id, 'ai_analysis_failed', array(), 0 );
		++$created;
		continue;
	}

	$provider_key = array_rand( $providers );
	$model        = $providers[ $provider_key ][ array_rand( $providers[ $provider_key ] ) ];
	$confidence   = wp_rand( 55, 98 );
	$priority     = $priorities[ array_rand( $priorities ) ];

	$workflow_status = 'new';
	if ( $roll <= 45 ) {
		$workflow_status = 'new';
	} elseif ( $roll <= 65 ) {
		$workflow_status = 'reviewed';
	} elseif ( $roll <= 90 ) {
		$workflow_status = 'replied';
	} else {
		$workflow_status = 'archived';
	}

	MessageRepository::update_analysis(
		$id,
		array(
			'ai_summary'      => "Summary: {$sender['name']} is asking about \"{$subject}\".",
			'ai_reasoning'    => 'Classified based on the subject line and message content.',
			'category'        => $category,
			'priority'        => $priority,
			'confidence'      => $confidence,
			'ai_provider'     => $provider_key,
			'ai_model'        => $model,
			'reply_subject'   => 'Re: ' . $subject,
			'reply_draft'     => "Hi {$sender['name']},\n\nThanks for reaching out — we'll follow up shortly.\n\nBest regards,",
			'workflow_status' => $workflow_status,
		)
	);

	ActivityRepository::log( $id, 'ai_analysis_completed', array( 'confidence' => $confidence ), 0 );

	if ( 'replied' === $workflow_status ) {
		MessageRepository::set_reply_sent(
			$id,
			'Re: ' . $subject,
			"Hi {$sender['name']},\n\nThanks for reaching out — here's an update on your request.\n\nBest regards,"
		);
		ActivityRepository::log( $id, 'reply_sent', array(), 0 );
	}

	++$created;
}

$summary = "Inbox AI: seeded {$created} demo submissions across " . count( $sender_pool ) . " senders and " . count( $forms ) . " form(s).";

if ( $is_cli ) {
	WP_CLI::success( $summary );
} else {
	echo esc_html( $summary );
}
