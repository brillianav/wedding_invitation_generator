<?php
/**
 * Remove plugin data when Wedding Invitation Maker - BRILLI is deleted.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('brilli_wedding_invitation_maker_options');
