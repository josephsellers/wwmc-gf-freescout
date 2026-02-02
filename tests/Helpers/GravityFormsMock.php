<?php
/**
 * Gravity Forms mock classes for testing.
 *
 * @package WWMC_GF_FreeScout
 */

/**
 * Mock GFForms class.
 */
class GFForms {
	/**
	 * Mock include_feed_addon_framework.
	 *
	 * @return void
	 */
	public static function include_feed_addon_framework() {
		// No-op for testing.
	}
}

/**
 * Mock GFFeedAddOn base class.
 */
class GFFeedAddOn {
	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $_version = '1.0.0';

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $_slug = '';

	/**
	 * Feed errors for testing.
	 *
	 * @var array
	 */
	public $feed_errors = array();

	/**
	 * Notes added during processing.
	 *
	 * @var array
	 */
	public $notes = array();

	/**
	 * Log messages.
	 *
	 * @var array
	 */
	public $log_messages = array();

	/**
	 * Mock plugin settings.
	 *
	 * @var array
	 */
	protected $plugin_settings = array();

	/**
	 * Get plugin settings.
	 *
	 * @return array
	 */
	public function get_plugin_settings() {
		return $this->plugin_settings;
	}

	/**
	 * Set plugin settings for testing.
	 *
	 * @param array $settings Settings array.
	 * @return void
	 */
	public function set_plugin_settings( $settings ) {
		$this->plugin_settings = $settings;
	}

	/**
	 * Get field value from entry.
	 *
	 * @param array      $form     Form array.
	 * @param array      $entry    Entry array.
	 * @param string|int $field_id Field ID.
	 * @return mixed
	 */
	public function get_field_value( $form, $entry, $field_id ) {
		return isset( $entry[ $field_id ] ) ? $entry[ $field_id ] : '';
	}

	/**
	 * Add feed error.
	 *
	 * @param string $message Error message.
	 * @param array  $feed    Feed array.
	 * @param array  $entry   Entry array.
	 * @param array  $form    Form array.
	 * @return void
	 */
	public function add_feed_error( $message, $feed, $entry, $form ) {
		$this->feed_errors[] = array(
			'message' => $message,
			'feed'    => $feed,
			'entry'   => $entry,
			'form'    => $form,
		);
	}

	/**
	 * Add note to entry.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param string $note     Note text.
	 * @param string $type     Note type.
	 * @return void
	 */
	public function add_note( $entry_id, $note, $type = 'note' ) {
		$this->notes[] = array(
			'entry_id' => $entry_id,
			'note'     => $note,
			'type'     => $type,
		);
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	public function log_debug( $message ) {
		$this->log_messages[] = $message;
	}

	/**
	 * Get generic map fields.
	 *
	 * @param array $feed   Feed array.
	 * @param string $name  Map name.
	 * @param array $form   Form array.
	 * @param array $entry  Entry array.
	 * @return array
	 */
	public function get_generic_map_fields( $feed, $name, $form, $entry ) {
		$map_data = isset( $feed['meta'][ $name ] ) ? $feed['meta'][ $name ] : array();
		$result   = array();

		if ( is_array( $map_data ) ) {
			foreach ( $map_data as $item ) {
				$key   = isset( $item['key'] ) ? $item['key'] : '';
				$value = isset( $item['value'] ) ? $item['value'] : '';

				// If value is a field ID, get from entry.
				if ( is_numeric( $value ) && isset( $entry[ $value ] ) ) {
					$value = $entry[ $value ];
				}

				if ( ! empty( $key ) ) {
					$result[ $key ] = $value;
				}
			}
		}

		return $result;
	}
}

/**
 * Mock GFAddOn class.
 */
class GFAddOn {
	/**
	 * Registered add-ons.
	 *
	 * @var array
	 */
	private static $registered = array();

	/**
	 * Register an add-on.
	 *
	 * @param string $class Class name.
	 * @return void
	 */
	public static function register( $class ) {
		self::$registered[] = $class;
	}

	/**
	 * Get registered add-ons.
	 *
	 * @return array
	 */
	public static function get_registered() {
		return self::$registered;
	}
}

/**
 * Mock GFCommon class.
 */
class GFCommon {
	/**
	 * Replace merge tags in text.
	 *
	 * @param string $text        Text with merge tags.
	 * @param array  $form        Form array.
	 * @param array  $entry       Entry array.
	 * @param bool   $url_encode  URL encode values.
	 * @param bool   $esc_html    Escape HTML.
	 * @param bool   $nl2br       Convert newlines to breaks.
	 * @param string $format      Output format.
	 * @return string
	 */
	public static function replace_variables( $text, $form, $entry, $url_encode = false, $esc_html = false, $nl2br = false, $format = 'text' ) {
		// Simple replacement for {form_title}.
		$text = str_replace( '{form_title}', $form['title'], $text );
		return $text;
	}

	/**
	 * Get field label.
	 *
	 * @param object $field Field object.
	 * @return string
	 */
	public static function get_label( $field ) {
		return isset( $field->label ) ? $field->label : '';
	}
}

/**
 * Mock GFFormsModel class.
 */
class GFFormsModel {
	/**
	 * Get field by ID.
	 *
	 * @param array $form     Form array.
	 * @param int   $field_id Field ID.
	 * @return object|null
	 */
	public static function get_field( $form, $field_id ) {
		if ( isset( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				if ( isset( $field['id'] ) && $field['id'] == $field_id ) {
					return (object) $field;
				}
			}
		}
		return null;
	}
}

/**
 * Mock gform_update_meta function.
 *
 * @param int    $entry_id Entry ID.
 * @param string $key      Meta key.
 * @param mixed  $value    Meta value.
 * @return void
 */
function gform_update_meta( $entry_id, $key, $value ) {
	global $gform_entry_meta;
	if ( ! isset( $gform_entry_meta ) ) {
		$gform_entry_meta = array();
	}
	if ( ! isset( $gform_entry_meta[ $entry_id ] ) ) {
		$gform_entry_meta[ $entry_id ] = array();
	}
	$gform_entry_meta[ $entry_id ][ $key ] = $value;
}

/**
 * Get stored entry meta.
 *
 * @param int    $entry_id Entry ID.
 * @param string $key      Meta key.
 * @return mixed
 */
function gform_get_meta( $entry_id, $key ) {
	global $gform_entry_meta;
	if ( isset( $gform_entry_meta[ $entry_id ][ $key ] ) ) {
		return $gform_entry_meta[ $entry_id ][ $key ];
	}
	return null;
}
