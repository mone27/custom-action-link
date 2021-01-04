<?php

function cacl_receive_setup_hooks(){
    add_action('init', 'cacl_check_request');
}


function cacl_check_request() {
    if ( isset( $_REQUEST['action_link_key'] ) and isset( $_REQUEST['action_link_id'] )) {
        $key = $_REQUEST['action_link_key'];
        $id =  absint($_REQUEST['action_link_id']);
        [$key_valid, $data]  = cacl_check_key($key, $id);

        if ($key_valid == true) {
            $data = maybe_unserialize($data);

            $success = cacl_set_pmpro_level($data);

            if ($success){
                wp_safe_redirect(SUCCESS_PAGE);
                exit;
            }
            else {
                echo "An error occurred in submitting the action link";
            }

        }

    }
}

/**
 * Check that the given key is valid and present in the db.
 * Returns an array where the first element is a bool if the key is valid or not
 * the second element is the associated data from the db.
 *
 * @since    1.0.0
 * @param      string    $key  The action unique key.
 *
 * @return array $key_valid, $data
 */
function cacl_check_key($key, $id){
    global $wp_hasher;
    global $wpdb;

    if ( empty( $wp_hasher ) ) {
        require_once ABSPATH . WPINC . '/class-phpass.php';
        $wp_hasher = new PasswordHash( 8, true );
    }

    // Get the key from the db
    $table_name = CACL_TABLE_NAME;
    $action = $wpdb->get_row($wpdb->prepare ( "SELECT * FROM $table_name WHERE id=%d", $id) , ARRAY_A);

    if (null !== $action){
        if ( $wp_hasher->CheckPassword( $key, $action["action_link_key"]) ) {
            return [true, $action["data"]];
        }
    }

    return [false, NULL];

}



/**
 * Add user to a pmpro membership level
 */
function cacl_set_pmpro_level($data){

    if (! isset($data['member-email'])) return false;

    $user_email = $data['member-email'];

    $user_id = email_exists($user_email);

    if ($user_id == false){
        return false;
    }

    $current_level    = pmpro_getMembershipLevelForUser( $user_id );

    if ( !empty( $current_level ) && absint( $current_level->ID ) == MEMBERSHIP_LEVEL ) {
        // Membership level is already active
        return false;
    }

    $new_level = pmpro_changeMembershipLevel(MEMBERSHIP_LEVEL, $user_id);

    if (!$new_level){
        error_log("ACL: Problem in changing membership level for". $user_id. " to level ". MEMBERSHIP_LEVEL );
        return false;
    }

    return true;

}
