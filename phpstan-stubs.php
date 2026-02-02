<?php
/**
 * PHPStan stubs for Gravity Forms classes.
 * These are not loaded at runtime, only for static analysis.
 */

if (false) {
    class GFForms {
        public static function include_feed_addon_framework(): void {}
    }

    class GFAddOn {
        public static function register(string $class): void {}
    }

    class GFFeedAddOn extends GFAddOn {
        /** @return array<string, mixed> */
        public function get_plugin_settings(): array { return []; }
        /** @return array<string, mixed> */
        public function get_current_form(): array { return []; }
        /** @return mixed */
        public function get_field_value(array $form, string $field_id, array $entry) { return null; }
        /** @return array<int, array<string, mixed>> */
        public function get_generic_map_fields(array $form): array { return []; }
        public function add_feed_error(string $message, array $feed, array $entry, array $form): void {}
        public function add_note(int $entry_id, string $note, string $note_type = ''): void {}
        public function log_debug(string $message): void {}
    }

    class GFFormsModel {
        /** @return GF_Field|null */
        public static function get_field(array $form, int $field_id) { return null; }
    }

    class GFCommon {
        public static function get_label(GF_Field $field): string { return ''; }
        public static function replace_variables(string $text, array $form, array $entry): string { return ''; }
    }

    class GF_Field {
        public int $id;
        public string $label;
    }
}
