<?php

function cacl_receive_setup_hooks(){
    add_action('init', 'cacl_check_request');
}


function cacl_check_request() {
    if ( isset( $_REQUEST['action_link_key'] ) and isset( $_REQUEST['action_link_id'] )) {
        $key = $_REQUEST['action_link_key'];
        $id =  absint($_REQUEST['action_link_id']);

        $success = cacl_check_key($key, $id);
        if (is_wp_error($success)){
            cacl_abort_on_error($success);
            return;
        }

        $data = maybe_unserialize($success);

        $success = cacl_set_pmpro_level($data);
        if (is_wp_error($success)){
            cacl_abort_on_error($success);
            return;
        }

        $success = calc_receive_email_admin($data);
        if (is_wp_error($success)){
            cacl_abort_on_error($success);
            return;
        }

        $success = calc_receive_email_user($data);
        if (is_wp_error($success)){
            cacl_abort_on_error($success);
            return;
        }

        // The key has been successfully used. Delete it from the db
        $success = cacl_delete_key($id);
        if (is_wp_error($success)){
            cacl_abort_on_error($success);
            return;
        }

        // if we reached this point everything must have succeed
       cacl_post_alert("You successfully submitted the link");

    }
}

/**
 * Check that the given key is valid and present in the db.
 * Returns an array where the first element is a bool if the key is valid or not
 * the second element is the associated data from the db.
 *
 * @param string $key The action unique key.
 *
 * @param $id
 * @return array|WP_Error $key_valid, $data
 * @since    1.0.0
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

    if ($action == null) {
        return new WP_Error("calc_no_key", "The key id does not exists. The link has already been used or is invalid");
    }
    else {
        if ( $wp_hasher->CheckPassword( $key, $action["action_link_key"]) ) {
            return $action["data"];
        }
        else{
            return new WP_Error("calc_invalid_key", "The submitted key is not valid");
        }
    }

}

function cacl_delete_key($id){
    global $wpdb;
    $success = $wpdb->delete(CACL_TABLE_NAME, ['id' => $id]);
    if (!$success) {
        return new WP_Error("calc_del_key", "Error in key deletion. Contact the admin of the website.");
    }
    return true;

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

    $current_level  = pmpro_getMembershipLevelForUser( $user_id );

    if ( !empty( $current_level ) && absint( $current_level->ID ) == MEMBERSHIP_LEVEL ) {
        // Membership level is already active
        return false;
    }

    $new_level = pmpro_changeMembershipLevel(MEMBERSHIP_LEVEL, $user_id);

    if (!$new_level){
        error_log("CACL: Problem in changing membership level for". $user_id. " to level ". MEMBERSHIP_LEVEL );
        return false;
    }

    return true;

}

function calc_receive_email_admin($data){
    $to = ADMIN_EMAIL;
    $subject = "User membership confirmed";
    $content = "Dear website admin,
    the user {$data['member-first-name']} {$data['member-first-name']} with email {$data['member-email']} 
    has just been approved by {$data['lc-email']}";

    $header = "Content-Type: text/html; charset=UTF-8 \n Reply-To: web@ifsa.net";

    $res = wp_mail($to, $subject, $content, $header);

    if(!$res){
        error_log("CACL: Problem in sending email to ".$to);
        return new WP_Error("cacl_err_mail_send", "Problem in sending email.
         Try again later of contact website administrator if the problem persists");
    }
    return true;

}

function calc_receive_email_user($data){
    $to = $data['member-email'];
    $subject = "Your membership has been approved";
    $content = "Dear {$data['member-first-name']},<br>
    your IFSA membership has been successfully approved.<br>
    Now you can start using <a href='https://ifsa.net/treehouse'>Tree House </a> and setup your profile. 
    ";

    $header = "Content-Type: text/html; charset=UTF-8 \n Reply-To: web@ifsa.net";

    $res =  wp_mail($to, $subject, $content, $header);
    if(!$res){
        error_log("CACL: Problem in sending email to ".$to);
        return new WP_Error("cacl_err_mail_send", "Problem in sending email.
         Try again later of contact website administrator if the problem persists");
    }
    return true;
}


/**
 * Add to post content error message
 * @param WP_Error $error
 */
function cacl_abort_on_error($error){
    $error_msg = $error->get_error_message();
    error_log("CACL: An error occurred in submitting the action link $error_msg");
    cacl_post_alert("Error!".$error_msg, $color="red");
}

/**
 * Add Alert in the post content
 * @param string $msg alert content
 * @param string $color text and border color
 */
function cacl_post_alert($msg, $color="green"){

    $html =   "<div style=\"padding: 20px; border-color: $color;color: $color; border-width: 5px; border-style: solid;
                     margin-bottom: 15px; font-weight: bold;\">
                    $msg
                </div>";
    $func = function ($content) use ($html) {
        return $html."<div>$content</div>";
    };
    add_filter('the_content', $func, 1,20);
}
