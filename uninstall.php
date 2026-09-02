<?php
/**
 * Remove plugin data when Wedding Invitation Maker - BRILLI is deleted.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('brilli_wedding_invitation_maker_options');
delete_option('brilli_wim_db_version');

global $wpdb;

$brilli_wim_history_table = $wpdb->prefix . 'brilli_wim_history';
$wpdb->query("DROP TABLE IF EXISTS {$brilli_wim_history_table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from the trusted WordPress prefix.
