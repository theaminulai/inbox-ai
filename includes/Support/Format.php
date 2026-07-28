<?php
/**
 * Server-side rendering helpers, originally built for the AI Inbox
 * List/Detail screens and reused as-is by the Contacts List screen (see
 * `includes/Templates/contacts-list.php`) — a contact row needs the same
 * priority badge, avatar, and relative timestamp rendering a message row
 * does, since it's built from that same sender's most recent message.
 *
 * @package InboxAI\Support
 */

namespace InboxAI\Support;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Format
 *
 * PHP ports of what used to be the AI Inbox List's client-side rendering
 * helpers (`src/admin/componets/shared/badges.js`, `avatar.js`, `time.js`).
 * Now that the list and detail screens are rendered server-side (see
 * {@see \InboxAI\Admin\Pages\InboxListPage}), the actual markup for a
 * priority/status badge, a confidence bar, an avatar, and a relative
 * timestamp needs to exist in PHP too — this is the one place for that,
 * matching the original JS output's CSS classes exactly so no SCSS changes
 * were needed alongside this move.
 */
final class Format {

	/**
	 * Avatar background color palette. Order matches
	 * `src/admin/componets/shared/avatar.js`'s `PALETTE`, though the actual
	 * hash function differs (PHP's `crc32()` vs. the JS file's hand-rolled
	 * one) — this only needs to be stable within itself (the same seed always
	 * picks the same color), not identical across the two implementations.
	 *
	 * @var string[]
	 */
	private const AVATAR_PALETTE = array( '#3A5CF6', '#D93B3B', '#1F9254', '#DA8A2E', '#6B4CE6', '#9AA1AC' );

	/**
	 * @var array<string, array{0:string,1:string,2:string}>
	 */
	private const PRIORITY_MAP = array(
		'urgent' => array( 'Urgent', 'var(--urgent)', 'inboxai-badge--urgent' ),
		'high'   => array( 'High', 'var(--high)', 'inboxai-badge--high' ),
		'normal' => array( 'Normal', 'var(--normal)', 'inboxai-badge--normal' ),
		'low'    => array( 'Low', 'var(--low)', 'inboxai-badge--low' ),
	);

	/**
	 * @var array<string, array{0:string,1:string}>
	 */
	private const STATUS_MAP = array(
		'new'      => array( 'New', 'inboxai-status--new' ),
		'review'   => array( 'Needs Review', 'inboxai-status--review' ),
		'reviewed' => array( 'Reviewed', 'inboxai-status--reviewed' ),
		'drafted'  => array( 'Drafted', 'inboxai-status--drafted' ),
		'replied'  => array( 'Replied', 'inboxai-status--replied' ),
		'failed'   => array( 'Failed', 'inboxai-status--failed' ),
		'archived' => array( 'Archived', 'inboxai-status--archived' ),
	);

	/**
	 * @param string $priority `urgent`|`high`|`normal`|`low`, or `''` for a
	 *                         message AI analysis hasn't run for yet — a
	 *                         real, common state (a fresh submission is
	 *                         `new`/pending until WP-Cron picks it up), not
	 *                         an error, so it renders as a plain "—" rather
	 *                         than silently falling back to "Normal" and
	 *                         making an unanalyzed row look already scored.
	 *
	 * @return string HTML for a `<span class="inboxai-badge">`, or a
	 *                bare "—" for the not-yet-analyzed case.
	 */
	public static function priority_badge_html( string $priority ): string {
		if ( '' === $priority ) {
			return '<span style="color:var(--text-tertiary);">—</span>';
		}

		// A genuinely unrecognized (but non-empty) value — e.g. old data
		// from before a priority label changed — still falls back to
		// "Normal" defensively, rather than showing nothing at all.
		[ $label, $color, $css_class ] = self::PRIORITY_MAP[ $priority ] ?? self::PRIORITY_MAP['normal'];

		return sprintf(
			'<span class="inboxai-badge %1$s"><span class="inboxai-badge__dot" style="background:%2$s;"></span>%3$s</span>',
			esc_attr( $css_class ),
			esc_attr( $color ),
			esc_html( $label )
		);
	}

	/**
	 * @param string $status One of `Migrator`'s `workflow_status` values.
	 *
	 * @return string HTML for a `<span class="inboxai-status">`.
	 */
	public static function status_badge_html( string $status ): string {
		[ $label, $css_class ] = self::STATUS_MAP[ $status ] ?? self::STATUS_MAP['new'];

		return sprintf(
			'<span class="inboxai-status %1$s">%2$s</span>',
			esc_attr( $css_class ),
			esc_html( $label )
		);
	}

	/**
	 * @param int|null $confidence 0-100, or null when a message has no AI
	 *                             analysis yet (failed/pending).
	 *
	 * @return string HTML for the confidence bar cell.
	 */
	public static function confidence_cell_html( ?int $confidence ): string {
		if ( null === $confidence ) {
			return '<div class="inboxai-confidence"><div class="inboxai-confidence__value" style="color:var(--text-tertiary);">—</div>' .
				'<div class="inboxai-confidence__track"><div class="inboxai-confidence__fill" style="width:0%;"></div></div></div>';
		}

		$color = $confidence >= 70 ? 'var(--conf-good)' : ( $confidence >= 40 ? 'var(--conf-mid)' : 'var(--conf-low)' );
		$warn  = $confidence < 70
			? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>'
			: '';

		return sprintf(
			'<div class="inboxai-confidence"><div class="inboxai-confidence__value" style="color:%1$s;">%2$s%3$d%%</div><div class="inboxai-confidence__track"><div class="inboxai-confidence__fill" style="width:%3$d%%;background:%1$s;"></div></div></div>',
			esc_attr( $color ),
			$warn,
			$confidence
		);
	}

	/**
	 * @param string $name
	 *
	 * @return string Up to two uppercase initials, or `?` if `$name` is empty.
	 */
	public static function avatar_initials( string $name ): string {
		$trimmed = trim( $name );

		if ( '' === $trimmed ) {
			return '?';
		}

		$parts = preg_split( '/\s+/', $trimmed );

		if ( false === $parts || array() === $parts ) {
			return '?';
		}

		$initials = count( $parts ) > 1
			? mb_substr( $parts[0], 0, 1 ) . mb_substr( $parts[ count( $parts ) - 1 ], 0, 1 )
			: mb_substr( $parts[0], 0, 2 );

		return mb_strtoupper( $initials );
	}

	/**
	 * A stable (same seed always returns the same color) but arbitrary color
	 * pick from a small fixed palette, so the same customer's avatar looks
	 * the same across renders without needing to store a color anywhere.
	 *
	 * @param string $seed Typically the sender's email.
	 *
	 * @return string CSS color.
	 */
	public static function avatar_color( string $seed ): string {
		return self::AVATAR_PALETTE[ crc32( $seed ) % count( self::AVATAR_PALETTE ) ];
	}

	/**
	 * @param string $mysql_datetime e.g. `2026-07-20 14:32:00`, already in the
	 *                               site's local time (see every
	 *                               `Database\*Repository` write, via
	 *                               `current_time( 'mysql' )`) — no timezone
	 *                               conversion needed here for the same reason.
	 *
	 * @return string e.g. `2h ago`, `18m ago`, `3d ago`, or `—` if empty/unparseable.
	 */
	public static function time_ago( string $mysql_datetime ): string {
		if ( '' === $mysql_datetime ) {
			return '—';
		}

		$timestamp = strtotime( $mysql_datetime );

		if ( false === $timestamp ) {
			return $mysql_datetime;
		}

		$seconds = max( 0, current_time( 'timestamp' ) - $timestamp ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- matches the local-time write side (current_time('mysql')); comparing two local times, not doing anything timezone-sensitive.

		if ( $seconds < 60 ) {
			return __( 'just now', 'inbox-ai' );
		}

		$minutes = (int) floor( $seconds / 60 );

		if ( $minutes < 60 ) {
			/* translators: %d: minutes */
			return sprintf( __( '%dm ago', 'inbox-ai' ), $minutes );
		}

		$hours = (int) floor( $minutes / 60 );

		if ( $hours < 24 ) {
			/* translators: %d: hours */
			return sprintf( __( '%dh ago', 'inbox-ai' ), $hours );
		}

		$days = (int) floor( $hours / 24 );

		/* translators: %d: days */
		return sprintf( __( '%dd ago', 'inbox-ai' ), $days );
	}

	/**
	 * @param string $mysql_datetime
	 *
	 * @return string A longer, absolute format for detail screens, e.g.
	 *                 `Jul 20, 2026 2:14 pm`.
	 */
	public static function format_datetime( string $mysql_datetime ): string {
		if ( '' === $mysql_datetime ) {
			return '—';
		}

		$timestamp = strtotime( $mysql_datetime );

		if ( false === $timestamp ) {
			return $mysql_datetime;
		}

		return date_i18n( 'M j, Y g:i a', $timestamp );
	}

	/**
	 * Real, bookmarkable `<a href>` pagination links (`add_query_arg()`
	 * against the current URL, only ever changing `paged`) — replaces the old
	 * client-side pager that fetched each page over AJAX, so a large
	 * submission table paginates the same way any other WordPress admin list
	 * table does.
	 *
	 * @param int $total        Total matching rows across every page.
	 * @param int $current_page 1-indexed current page.
	 * @param int $per_page     Rows per page.
	 *
	 * @return string HTML for a `.inboxai-pager` element, or '' if
	 *                everything fits on one page.
	 */
	public static function pagination_links( int $total, int $current_page, int $per_page ): string {
		$total_pages = max( 1, (int) ceil( $total / max( 1, $per_page ) ) );

		if ( $total_pages <= 1 ) {
			return '';
		}

		$current_page = min( $current_page, $total_pages );

		$link = static function ( int $page, string $label, bool $disabled = false, bool $active = false ) {
			if ( $disabled ) {
				// A plain `<span>`, not a `<button disabled>` — this is a link-based
				// pager now (real URLs, no client-side pager button), so there's no
				// `:disabled` pseudo-class to dim it; the same visual effect is
				// applied inline instead.
				return '<span class="inboxai-pager__btn" aria-disabled="true" style="opacity:.4;cursor:default;">' . $label . '</span>';
			}

			$url = add_query_arg( array( 'paged' => $page ) );

			return sprintf(
				'<a class="inboxai-pager__btn%1$s" href="%2$s">%3$s</a>',
				$active ? ' inboxai-is-active' : '',
				esc_url( $url ),
				$label
			);
		};

		$prev_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6"/></svg>';
		$next_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 18l6-6-6-6"/></svg>';

		$pages = array();

		if ( $total_pages <= 7 ) {
			$pages = range( 1, $total_pages );
		} else {
			$pages[] = 1;

			if ( $current_page > 3 ) {
				$pages[] = '…';
			}

			for ( $i = max( 2, $current_page - 1 ); $i <= min( $total_pages - 1, $current_page + 1 ); $i++ ) {
				$pages[] = $i;
			}

			if ( $current_page < $total_pages - 2 ) {
				$pages[] = '…';
			}

			$pages[] = $total_pages;
		}

		$html  = '<div class="inboxai-pager">';
		$html .= $link( $current_page - 1, $prev_icon, $current_page <= 1 );

		foreach ( $pages as $p ) {
			$html .= '…' === $p
				? '<span class="inboxai-pager__ellipsis">…</span>'
				: $link( (int) $p, (string) $p, false, (int) $p === $current_page );
		}

		$html .= $link( $current_page + 1, $next_icon, $current_page >= $total_pages );
		$html .= '</div>';

		return $html;
	}
}
