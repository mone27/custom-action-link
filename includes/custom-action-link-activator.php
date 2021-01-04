<?php

/**
 * Activate the plugin by creating a table to hold the keys
 */


function cacl_activate()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . "action_link_db";
    $key_length = 30;
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            action_link_key longtext NOT NULL,
            data longtext,
            UNIQUE KEY id (id)
        ) $charset_collate;";

    if (!function_exists('dbDelta')) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    }

    $wpdb->query($sql);

}
