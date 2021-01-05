<?php
/**
 * Code to send the custom action link
 */

function cacl_send_setup_hooks(){
    // Register action to run on every contact form 7 submission before sending the mail
    add_action( 'wpcf7_before_send_mail', 'cacl_on_form_submission', 10, 3 );

}


function cacl_on_form_submission($contact_form, &$abort, $form_submission) {
    if ($contact_form->id() == ACL_FORM_ID){

        /** Generate a key and save the associated data in the db */
        $data = $form_submission->get_posted_data();
        $res = cacl_generate_key($data);
        if (is_wp_error($res)) {
            $form_submission->set_response($res->get_error_message());
            $abort = true;
        }
        [$key, $id]  = $res;

        /** Create the user */
        $success = cacl_create_user($data);
        if (is_wp_error($success)) {
            $form_submission->set_response($success->get_error_message());
            $abort = true;
        }

        /** Send the email to the LC */
        $success = cacl_send_email($key, $id, $data);
        if (is_wp_error($success)) {
            $form_submission->set_response($success->get_error_message());
            $abort = true;
        }

    }

}


/**
 * Generate a unique key and stores in the db alongside with data
 *
 * @param mixed $data the array that should be saved alongside the key
 * @return array | WP_Error $key, $id the generated key after being inserted into the db
 */
function cacl_generate_key($data){
    // insert the key and the data in the database
    global $wp_hasher;
    global $wpdb;

    // Generate something random for a confirmation key.
    $key = wp_generate_password( 30, false );

    // Return the key, hashed.
    if ( empty( $wp_hasher ) ) {
        require_once ABSPATH . WPINC . '/class-phpass.php';
        $wp_hasher = new PasswordHash( 8, true );
    }


    $res = $wpdb->insert(CACL_TABLE_NAME, [
            'action_link_key' => $wp_hasher->HashPassword( $key ),
            'data' => maybe_serialize($data)
        ]
    );

    if ($res == false){
        return new WP_Error("cacl_key_creatation", "Error in creating key. Contact your website adminstator");
    }

    $id = $wpdb->insert_id;

    return [$key, $id];

}


/* -------------- actions to send data -------------------- */

/**
 * Send an email with the action link
 * @param $data array the data from the form
 * @return bool|WP_Error
 */
function cacl_create_user($data){
    $userdata = [
        'user_login' => $data['member-first-name']." ".$data['member-last-name'],
        'user_email' => $data['member-email'],
        'first_name' => $data['member-first-name'],
        'last-name'  => $data['member-last-name'],
    ];

    $res = wp_insert_user($userdata);

    if (!is_int($res)){ //If everything okay $red is the user id
        // Need to report back to form submission that something is going wrong!!
        error_log("CACL: create user:".$res->get_error_message());
        return new WP_Error("cacl_create_user", $res->get_error_message());
    }

    return true;
}

/**
 * Send an email with the action link
 * @param $key string the key that needs to sent
 * @param $id int the action_link id
 * @param $data array the data from the form
 * @return bool|WP_Error
 */
function cacl_send_email($key, $id, $data){

    $to = $data['lc-email'];
    $subject = "Verify IFSA Member";
    $content = "Dear IFSA LC,<br>
                A member of your LC has requested a verification process. <br>
                If {$data['member-first-name']} {$data['member-last-name']} is a member of your LC verify them by clicking on <br> 
                [action_link Verify Member] <br>
                If {$data['member-first-name']} is not from your LC or you are not sure <strong>ignore </strong>this email.
                <br><br>
                For any question get in touch with web@ifsa.net";

    $action_link_regex = '@\[action_link[[:space:]](.*)\]@';

    preg_match($action_link_regex, $content, $m);

    $link_name = $m[1] ? $m[1] : "Click on the action link";// Set default value of there has been no match

    $link = add_query_arg([
        'action_link_key' => $key,
        'action_link_id' => $id,
    ], home_url());

    $action_link_html = "<a href=$link>$link_name </a>";

    $header = "Content-Type: text/html; charset=UTF-8 \n Reply-To: web@ifsa.net";

    $content = preg_replace($action_link_regex, $action_link_html, $content);

    $res = wp_mail($to, $subject, $content, $header);

    if(!$res){
        error_log("CACL: Problem in sending email to ".$to);
        return new WP_Error("CACL", "Problem in sending email.
         Try again later of contact website administrator if the problem persists");
    }

    return true;

}