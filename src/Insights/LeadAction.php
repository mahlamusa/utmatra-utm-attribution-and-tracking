<?php
/**
 * LeadAction — the public `utmatra_record_lead` action for custom forms.
 *
 * Lets a theme or plugin whose form has no bundled adapter report a
 * submission as a lead without calling Utmatra's internal classes:
 *
 *     do_action(
 *         'utmatra_record_lead',
 *         array(
 *             'source'  => 'my-theme',
 *             'form_id' => 'contact',
 *             'lead_id' => $entry_id,
 *         )
 *     );
 *
 * The lead is recorded exactly like an adapter's: a `link_id = 'lead'`
 * conversion row from the visitor's last touch, a recent-leads widget entry
 * and the `utmatra_lead_recorded` action (and so the lead webhook).
 *
 * @since 1.1.0
 *
 * @package Utmatra\Insights
 */

namespace Utmatra\Insights;

use Utmatra\Tracking\Attribution;

defined( 'ABSPATH' ) || exit;

/**
 * LeadAction
 *
 * @since 1.1.0
 */
class LeadAction {

	/**
	 * Name of the public action.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public const HOOK = 'utmatra_record_lead';

	/**
	 * Maximum stored length of a caller's lead id, in characters.
	 *
	 * @since 1.1.0
	 * @var int
	 */
	public const MAX_LEAD_ID_LENGTH = 64;

	/**
	 * Register the action handler.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function register(): void {
		add_action( self::HOOK, array( self::class, 'record' ) );
	}

	/**
	 * Record a lead reported through the public action.
	 *
	 * Does nothing when the lead is not an array, has no usable `source`, or
	 * the visitor carries no attribution data (the same rule the bundled
	 * adapters follow).
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $lead {
	 *     The lead. Unknown keys are ignored.
	 *
	 *     @type string     $source     Required. Slug of the plugin or theme reporting the lead, e.g. `my-theme`
	 *                                  (passed through sanitize_key()). Stored as the event's integration and
	 *                                  used to group leads in the recent-leads widget. Do not reuse a bundled
	 *                                  adapter's slug (`contact-form-7`, `woocommerce`, ...).
	 *     @type int|string $form_id    Optional. Identifier of the form.
	 *     @type string     $form_title Optional. Human-readable form name.
	 *     @type int|string $lead_id    Optional. The caller's own id for the submission (a post or entry id),
	 *                                  stored (up to 64 characters) in the event's `meta` so the row can be
	 *                                  matched to it.
	 *     @type string     $url        Optional. URL of the page the form was submitted on. Defaults to the
	 *                                  request referer, then the current URL.
	 *     @type array      $fields     Optional. Submitted values keyed by label. Never stored; passed to
	 *                                  `utmatra_lead_recorded` listeners and so to the lead webhook.
	 * }
	 *
	 * @return void
	 */
	public static function record( $lead ): void {
		if ( ! is_array( $lead ) || ! Attribution::has_data() ) {
			return;
		}

		$record = self::to_record( $lead, Attribution::get_last() );

		if ( null === $record ) {
			return;
		}

		Recorder::record( $record['integration'], $record['data'] );
	}

	/**
	 * Map a public lead onto the integration slug and Recorder record.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $lead   The lead, shaped as documented on record().
	 * @param array<string, mixed> $posted The visitor's last-touch attribution.
	 *
	 * @return array{integration: string, data: array<string, mixed>}|null Null when `source` is missing or empty after sanitising.
	 */
	public static function to_record( array $lead, array $posted ): ?array {
		$source = is_string( $lead['source'] ?? null ) ? sanitize_key( $lead['source'] ) : '';

		if ( '' === $source ) {
			return null;
		}

		$data = array(
			'form_id'    => self::text( $lead['form_id'] ?? '' ),
			'form_title' => self::text( $lead['form_title'] ?? '' ),
			'posted'     => $posted,
			'created_at' => time(),
			'lead_id'    => mb_substr( self::text( $lead['lead_id'] ?? '' ), 0, self::MAX_LEAD_ID_LENGTH ),
		);

		if ( is_string( $lead['url'] ?? null ) ) {
			$data['page_url'] = $lead['url'];
		}

		if ( isset( $lead['fields'] ) && is_array( $lead['fields'] ) ) {
			$data['fields'] = $lead['fields'];
		}

		return array(
			'integration' => $source,
			'data'        => $data,
		);
	}

	/**
	 * A scalar as sanitised text; anything else as an empty string.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $value Value from the lead.
	 *
	 * @return string
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}
}
