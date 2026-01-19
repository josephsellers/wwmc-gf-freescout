<?php
/**
 * FreeScout integration using the Gravity Forms Add-On Framework.
 *
 * @package GF_FreeScout
 */

defined( 'ABSPATH' ) || die();

// Load the Feed Add-On Framework.
GFForms::include_feed_addon_framework();

/**
 * FreeScout integration using the Add-On Framework.
 *
 * @see GFFeedAddOn
 */
class GF_FreeScout extends GFFeedAddOn {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $_version = GF_FREESCOUT_VERSION;

	/**
	 * Minimum Gravity Forms version required.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '2.5';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $_slug = 'wwmc-gf-freescout';

	/**
	 * Relative path to the main plugin file.
	 *
	 * @var string
	 */
	protected $_path = 'wwmc-gf-freescout/freescout.php';

	/**
	 * Full path to this class file.
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * Add-On URL.
	 *
	 * @var string
	 */
	protected $_url = 'https://rokesmith.com';

	/**
	 * Add-On title.
	 *
	 * @var string
	 */
	protected $_title = 'Gravity Forms FreeScout Add-On';

	/**
	 * Short title for menus.
	 *
	 * @var string
	 */
	protected $_short_title = 'FreeScout';

	/**
	 * Singleton instance.
	 *
	 * @var GF_FreeScout|null
	 */
	private static $_instance = null;

	/**
	 * Capability for settings page access.
	 *
	 * @var string
	 */
	protected $_capabilities_settings_page = 'gravityforms_freescout';

	/**
	 * Capability for form settings page access.
	 *
	 * @var string
	 */
	protected $_capabilities_form_settings = 'gravityforms_freescout';

	/**
	 * Capability for uninstalling the add-on.
	 *
	 * @var string
	 */
	protected $_capabilities_uninstall = 'gravityforms_freescout_uninstall';

	/**
	 * Capabilities for roles.
	 *
	 * @var array
	 */
	protected $_capabilities = array( 'gravityforms_freescout', 'gravityforms_freescout_uninstall' );

	/**
	 * Enable async feed processing.
	 *
	 * @var bool
	 */
	protected $_async_feed_processing = true;

	/**
	 * Get singleton instance.
	 *
	 * @return GF_FreeScout
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @return string
	 */
	public function get_menu_icon() {
		return 'dashicons-email-alt';
	}

	// -------------------------------------------------------------------------
	// PLUGIN SETTINGS (Global Configuration)
	// -------------------------------------------------------------------------

	/**
	 * Define global plugin settings fields.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'FreeScout API Settings', 'wwmc-gf-freescout' ),
				'description' => esc_html__( 'Configure your FreeScout instance connection settings. You can find your API key in FreeScout under Manage -> API Keys.', 'wwmc-gf-freescout' ),
				'fields'      => array(
					array(
						'name'              => 'freescout_url',
						'label'             => esc_html__( 'FreeScout URL', 'wwmc-gf-freescout' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'tooltip'           => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'FreeScout URL', 'wwmc-gf-freescout' ),
							esc_html__( 'Enter the base URL of your FreeScout instance (e.g., https://support.example.com). Do not include a trailing slash.', 'wwmc-gf-freescout' )
						),
						'feedback_callback' => array( $this, 'validate_freescout_url' ),
					),
					array(
						'name'              => 'api_key',
						'label'             => esc_html__( 'API Key', 'wwmc-gf-freescout' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'input_type'        => 'password',
						'tooltip'           => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'API Key', 'wwmc-gf-freescout' ),
							esc_html__( 'Enter your FreeScout API key. Generate one in FreeScout under Manage -> API Keys.', 'wwmc-gf-freescout' )
						),
						'feedback_callback' => array( $this, 'validate_api_credentials' ),
					),
					array(
						'name'          => 'default_mailbox_id',
						'label'         => esc_html__( 'Default Mailbox ID', 'wwmc-gf-freescout' ),
						'type'          => 'text',
						'class'         => 'small',
						'default_value' => '1',
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Default Mailbox ID', 'wwmc-gf-freescout' ),
							esc_html__( 'Enter the default FreeScout mailbox ID. This can be overridden per form feed. Find the ID in FreeScout mailbox settings.', 'wwmc-gf-freescout' )
						),
					),
				),
			),
		);
	}

	/**
	 * Validate the FreeScout URL format.
	 *
	 * @param string $value The URL value.
	 * @return bool
	 */
	public function validate_freescout_url( $value ) {
		if ( empty( $value ) ) {
			return false;
		}
		return filter_var( $value, FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Validate API credentials by making a test request.
	 *
	 * @param string $value The API key value.
	 * @return bool|null
	 */
	public function validate_api_credentials( $value ) {
		if ( empty( $value ) ) {
			return false;
		}

		$settings = $this->get_plugin_settings();
		$url      = rgar( $settings, 'freescout_url' );

		if ( empty( $url ) ) {
			return null; // Can't validate without URL.
		}

		// Test the API connection by fetching mailboxes.
		$response = wp_remote_get(
			rtrim( $url, '/' ) . '/api/mailboxes',
			array(
				'headers' => array(
					'X-FreeScout-API-Key' => $value,
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	// -------------------------------------------------------------------------
	// FEED SETTINGS (Per-Form Configuration)
	// -------------------------------------------------------------------------

	/**
	 * Define feed settings fields.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		return array(
			// Feed Name Section.
			array(
				'title'  => esc_html__( 'Feed Settings', 'wwmc-gf-freescout' ),
				'fields' => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'wwmc-gf-freescout' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Name', 'wwmc-gf-freescout' ),
							esc_html__( 'Enter a feed name to uniquely identify this setup.', 'wwmc-gf-freescout' )
						),
					),
				),
			),
			// Mailbox Configuration.
			array(
				'title'  => esc_html__( 'Mailbox', 'wwmc-gf-freescout' ),
				'fields' => array(
					array(
						'name'    => 'mailboxId',
						'label'   => esc_html__( 'Mailbox ID', 'wwmc-gf-freescout' ),
						'type'    => 'text',
						'class'   => 'small',
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Mailbox ID', 'wwmc-gf-freescout' ),
							esc_html__( 'Enter the FreeScout mailbox ID for this form. Leave empty to use the default from plugin settings.', 'wwmc-gf-freescout' )
						),
					),
				),
			),
			// Customer Field Mappings.
			array(
				'title'  => esc_html__( 'Customer Information', 'wwmc-gf-freescout' ),
				'fields' => array(
					array(
						'name'     => 'customerEmail',
						'label'    => esc_html__( 'Email', 'wwmc-gf-freescout' ),
						'type'     => 'field_select',
						'required' => true,
						'args'     => array(
							'field_types' => array( 'email' ),
						),
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Customer Email', 'wwmc-gf-freescout' ),
							esc_html__( 'Select the form field containing the customer email address.', 'wwmc-gf-freescout' )
						),
					),
					array(
						'name'    => 'customerName',
						'label'   => esc_html__( 'Name', 'wwmc-gf-freescout' ),
						'type'    => 'field_select',
						'args'    => array(
							'field_types' => array( 'name', 'text' ),
						),
						'tooltip' => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Customer Name', 'wwmc-gf-freescout' ),
							esc_html__( 'Select the form field containing the customer name (optional).', 'wwmc-gf-freescout' )
						),
					),
				),
			),
			// Conversation Details.
			array(
				'title'  => esc_html__( 'Conversation Details', 'wwmc-gf-freescout' ),
				'fields' => array(
					array(
						'name'          => 'subject',
						'label'         => esc_html__( 'Subject', 'wwmc-gf-freescout' ),
						'type'          => 'text',
						'class'         => 'large merge-tag-support mt-position-right mt-hide_all_fields',
						'default_value' => 'Contact Form: {form_title}',
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Subject', 'wwmc-gf-freescout' ),
							esc_html__( 'Enter the conversation subject. You can use merge tags like {form_title} or field merge tags.', 'wwmc-gf-freescout' )
						),
					),
					array(
						'name'     => 'messageField',
						'label'    => esc_html__( 'Message Field', 'wwmc-gf-freescout' ),
						'type'     => 'field_select',
						'required' => true,
						'args'     => array(
							'field_types' => array( 'textarea', 'text', 'post_content' ),
						),
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Message Field', 'wwmc-gf-freescout' ),
							esc_html__( 'Select the form field containing the main message content.', 'wwmc-gf-freescout' )
						),
					),
				),
			),
			// Extra Fields Mapping.
			array(
				'title'  => esc_html__( 'Additional Information', 'wwmc-gf-freescout' ),
				'fields' => array(
					array(
						'name'        => 'extraFields',
						'label'       => esc_html__( 'Extra Fields', 'wwmc-gf-freescout' ),
						'type'        => 'generic_map',
						'tooltip'     => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Extra Fields', 'wwmc-gf-freescout' ),
							esc_html__( 'Map additional form fields to include in the conversation message. The key will be used as a label.', 'wwmc-gf-freescout' )
						),
						'key_field'   => array(
							'placeholder'  => esc_html__( 'Label', 'wwmc-gf-freescout' ),
							'custom_value' => true,
							'title'        => esc_html__( 'Label', 'wwmc-gf-freescout' ),
						),
						'value_field' => array(
							'choices'      => 'form_fields',
							'custom_value' => true,
						),
					),
				),
			),
			// Conditional Logic.
			array(
				'title'  => esc_html__( 'Conditional Logic', 'wwmc-gf-freescout' ),
				'fields' => array(
					array(
						'name'           => 'feedCondition',
						'type'           => 'feed_condition',
						'label'          => esc_html__( 'Condition', 'wwmc-gf-freescout' ),
						'checkbox_label' => esc_html__( 'Enable Condition', 'wwmc-gf-freescout' ),
						'instructions'   => esc_html__( 'Create FreeScout conversation if', 'wwmc-gf-freescout' ),
						'tooltip'        => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'wwmc-gf-freescout' ),
							esc_html__( 'When conditions are enabled, the conversation will only be created when the conditions are met.', 'wwmc-gf-freescout' )
						),
					),
				),
			),
		);
	}

	/**
	 * Define feed list columns.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'      => esc_html__( 'Name', 'wwmc-gf-freescout' ),
			'customerEmail' => esc_html__( 'Email Field', 'wwmc-gf-freescout' ),
		);
	}

	/**
	 * Format the customerEmail column value.
	 *
	 * @param array $feed The current feed.
	 * @return string
	 */
	public function get_column_value_customerEmail( $feed ) {
		$field_id = rgars( $feed, 'meta/customerEmail' );
		if ( empty( $field_id ) ) {
			return esc_html__( 'Not configured', 'wwmc-gf-freescout' );
		}

		$form  = $this->get_current_form();
		$field = GFFormsModel::get_field( $form, $field_id );

		if ( $field ) {
			return esc_html( GFCommon::get_label( $field ) );
		}

		return esc_html( 'Field ID: ' . $field_id );
	}

	/**
	 * Enable feed duplication.
	 *
	 * @param int|array $id The ID of the feed.
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {
		return true;
	}

	// -------------------------------------------------------------------------
	// FEED PROCESSING
	// -------------------------------------------------------------------------

	/**
	 * Process the feed - create FreeScout conversation.
	 *
	 * @param array $feed  The current feed object.
	 * @param array $entry The current entry object.
	 * @param array $form  The current form object.
	 *
	 * @return array|WP_Error The entry array on success, WP_Error on failure.
	 */
	public function process_feed( $feed, $entry, $form ) {
		// Get plugin settings.
		$settings = $this->get_plugin_settings();

		// Get API configuration.
		$api_url = rtrim( rgar( $settings, 'freescout_url' ), '/' );
		$api_key = rgar( $settings, 'api_key' );

		// Validate API configuration.
		if ( empty( $api_url ) || empty( $api_key ) ) {
			$this->add_feed_error(
				esc_html__( 'FreeScout API is not configured. Please check plugin settings.', 'wwmc-gf-freescout' ),
				$feed,
				$entry,
				$form
			);
			return new WP_Error( 'api_not_configured', 'FreeScout API is not configured.' );
		}

		// Get mailbox ID (feed setting or plugin default).
		$mailbox_id = rgars( $feed, 'meta/mailboxId' );
		if ( empty( $mailbox_id ) ) {
			$mailbox_id = rgar( $settings, 'default_mailbox_id', 1 );
		}
		$mailbox_id = (int) $mailbox_id;

		// Get customer email.
		$customer_email = $this->get_field_value( $form, $entry, rgars( $feed, 'meta/customerEmail' ) );

		// Validate email.
		if ( empty( $customer_email ) || ! is_email( $customer_email ) ) {
			$this->add_feed_error(
				esc_html__( 'Invalid or missing customer email address.', 'wwmc-gf-freescout' ),
				$feed,
				$entry,
				$form
			);
			return new WP_Error( 'invalid_email', 'Invalid or missing customer email.' );
		}

		// Get customer name.
		$customer_name = $this->get_field_value( $form, $entry, rgars( $feed, 'meta/customerName' ) );
		$name_parts    = $this->parse_customer_name( $customer_name );

		// Get subject (with merge tag replacement).
		$subject = rgars( $feed, 'meta/subject' );
		if ( empty( $subject ) ) {
			$subject = sprintf( 'Contact Form: %s', $form['title'] );
		}
		$subject = GFCommon::replace_variables( $subject, $form, $entry, false, true, false, 'text' );

		// Get message.
		$message = $this->get_field_value( $form, $entry, rgars( $feed, 'meta/messageField' ) );

		if ( empty( $message ) ) {
			$this->add_feed_error(
				esc_html__( 'Message field is empty.', 'wwmc-gf-freescout' ),
				$feed,
				$entry,
				$form
			);
			return new WP_Error( 'empty_message', 'Message field is empty.' );
		}

		// Build full message body with extra fields.
		$full_message = $this->build_message_body( $message, $feed, $entry, $form );

		// Build API payload.
		$payload = array(
			'type'      => 'email',
			'mailboxId' => $mailbox_id,
			'subject'   => $subject,
			'customer'  => array(
				'email' => $customer_email,
			),
			'threads'   => array(
				array(
					'type'      => 'customer',
					'text'      => $full_message,
					'createdAt' => gmdate( 'c', strtotime( $entry['date_created'] ) ),
				),
			),
			'imported'  => true,
			'status'    => 'active',
		);

		// Add customer name if available.
		if ( ! empty( $name_parts['first'] ) ) {
			$payload['customer']['firstName'] = $name_parts['first'];
		}
		if ( ! empty( $name_parts['last'] ) ) {
			$payload['customer']['lastName'] = $name_parts['last'];
		}

		// Log the request.
		$this->log_debug( __METHOD__ . '(): Sending request to FreeScout: ' . print_r( $payload, true ) );

		// Send API request.
		$response = wp_remote_post(
			$api_url . '/api/conversations',
			array(
				'headers' => array(
					'X-FreeScout-API-Key' => $api_key,
					'Content-Type'        => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		// Handle response.
		if ( is_wp_error( $response ) ) {
			$this->add_feed_error(
				sprintf(
					/* translators: %s: error message */
					esc_html__( 'API request failed: %s', 'wwmc-gf-freescout' ),
					$response->get_error_message()
				),
				$feed,
				$entry,
				$form
			);
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		$this->log_debug( __METHOD__ . '(): Response code: ' . $response_code . '; Body: ' . $response_body );

		// Check for success.
		if ( $response_code >= 200 && $response_code < 300 ) {
			$data = json_decode( $response_body, true );

			// Store conversation ID in entry meta.
			if ( isset( $data['id'] ) ) {
				gform_update_meta( $entry['id'], 'freescout_conversation_id', $data['id'] );
				gform_update_meta( $entry['id'], 'freescout_conversation_number', rgar( $data, 'number', '' ) );
			}

			// Add success note.
			$this->add_note(
				$entry['id'],
				sprintf(
					/* translators: %d: conversation ID */
					esc_html__( 'FreeScout conversation created successfully. Conversation ID: %d', 'wwmc-gf-freescout' ),
					rgar( $data, 'id', 0 )
				),
				'success'
			);

			return $entry;
		}

		// Handle error response.
		$this->add_feed_error(
			sprintf(
				/* translators: 1: HTTP response code, 2: response body */
				esc_html__( 'API error: HTTP %1$d - %2$s', 'wwmc-gf-freescout' ),
				$response_code,
				$response_body
			),
			$feed,
			$entry,
			$form
		);

		return new WP_Error( 'api_error', 'API returned error: ' . $response_code );
	}

	// -------------------------------------------------------------------------
	// HELPER METHODS
	// -------------------------------------------------------------------------

	/**
	 * Parse a customer name into first and last name parts.
	 *
	 * @param string $name The full name.
	 * @return array Array with 'first' and 'last' keys.
	 */
	private function parse_customer_name( $name ) {
		$name = trim( $name );

		if ( empty( $name ) ) {
			return array(
				'first' => '',
				'last'  => '',
			);
		}

		// Split on whitespace, max 2 parts.
		$parts = preg_split( '/\s+/', $name, 2 );

		return array(
			'first' => $parts[0],
			'last'  => isset( $parts[1] ) ? $parts[1] : '',
		);
	}

	/**
	 * Build the full message body including extra fields and metadata.
	 *
	 * @param string $message The main message content.
	 * @param array  $feed    The current feed.
	 * @param array  $entry   The current entry.
	 * @param array  $form    The current form.
	 *
	 * @return string The formatted message body.
	 */
	private function build_message_body( $message, $feed, $entry, $form ) {
		$body = $message;

		// Add extra fields if configured.
		$extra_fields = $this->get_generic_map_fields( $feed, 'extraFields', $form, $entry );

		if ( ! empty( $extra_fields ) ) {
			$extras = array();

			foreach ( $extra_fields as $label => $value ) {
				if ( ! empty( $value ) && ! empty( $label ) ) {
					$extras[] = sprintf( '%s: %s', $label, $value );
				}
			}

			if ( ! empty( $extras ) ) {
				$body .= "\n\n---\n" . implode( "\n", $extras );
			}
		}

		// Add source metadata.
		$body .= sprintf(
			"\n\n---\nSubmitted via: %s\nForm: %s\nSource URL: %s\nEntry ID: %d",
			wp_parse_url( home_url(), PHP_URL_HOST ),
			$form['title'],
			rgar( $entry, 'source_url' ),
			$entry['id']
		);

		return $body;
	}

	/**
	 * Define entry meta for FreeScout data.
	 *
	 * @param array $entry_meta Existing entry meta.
	 * @param int   $form_id    The form ID.
	 *
	 * @return array Modified entry meta.
	 */
	public function get_entry_meta( $entry_meta, $form_id ) {
		$entry_meta['freescout_conversation_id'] = array(
			'label'             => esc_html__( 'FreeScout Conversation ID', 'wwmc-gf-freescout' ),
			'is_numeric'        => true,
			'is_default_column' => false,
		);

		$entry_meta['freescout_conversation_number'] = array(
			'label'             => esc_html__( 'FreeScout Conversation #', 'wwmc-gf-freescout' ),
			'is_numeric'        => true,
			'is_default_column' => false,
		);

		return $entry_meta;
	}
}
