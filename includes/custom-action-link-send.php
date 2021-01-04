<?php
/**
 * Code to send the custom action link
 */

// Register action to run on every contact form 7 submission
add_action( 'wpcf7_before_send_mail', 'cacl_on_form_submission', 10, 3 );

function cacl_on_form_submission($contact_form, $abort, $form_submission) {
    if ($contact_form->id() == ACL_FORM_ID){
        // do something with form_submission

        $data = $form_submission->get_posted_data();
        [$key, $id]  = cacl_generate_key($data);

        do_action('cacl_send_data', $key, $id ,$data);

    }

}


/**
 * Generate a unique key and stores in the db alongside with data
 *
 * @param mixed $data the array that should be saved alongside the key
 * @return array $key, $id the generated key after being inserted into the db
 * @since    1.0.0
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


    $res = $wpdb->insert($wpdb->prefix."action_link_db", [
            'action_link_key' => $wp_hasher->HashPassword( $key ),
            'data' => maybe_serialize($data)
        ]
    );

    $id = $wpdb->insert_id;

    return [$key, $id];

}


/* -------------- actions to send data -------------------- */


add_action("cacl_send_data", 'cacl_send_email', 10, 3);
add_action('cacl_send_data', 'cacl_create_user', 10, 3);

function cacl_create_user($key, $id, $data){
    // need to do something here
    $userdata = [
        'user_login' => $data['member-first-name']." ".$data['member-last-name'],
        'user_email' => $data['member-email'],
        'first_name' => $data['member-first-name'],
        'last-name'  => $data['member-last-name'],
    ];

    $res = wp_insert_user($userdata);

    if (!is_int($res)){ //If everything okay $red is the user id
        error_log("ACL: create user:".$res);
    }
}
/**
 * Send an email with the action link
 **/
function cacl_send_email($key, $id, $data){

    $to = $data['member-email'];
    $subject = "Verify IFSA Member";
    $content = "Use this link to verify that {$data['member-first-name']} is a member of your LC [action_link Verify]";

    $action_link_regex = '@\[action_link[[:space:]](.*)\]@';

    preg_match($action_link_regex, $content, $m);

    $link_name = $m[1] ? $m[1] : "Click on the action link";// Set default value of there has been no match

    $link = add_query_arg([
        'action_link_key' => $key,
        'action_link_id' => $id,
    ], home_url());

    $action_link_html = "<a href=$link>$link_name </a>";

    $header = "Content-Type: text/html; charset=UTF-8";

    $content = preg_replace($action_link_regex, $action_link_html, $content);

    $res = wp_mail($to, $subject, $content, $header);

    if(!$res){
        error_log("ACL: Problem in sending email to ".$to);
    }

}