<?php
/*
Plugin Name: Registration Import
Plugin URI: http://www.umfundi.com/registration-import/
Description: Allows the batch importation of users/members from CSV file to BuddyPress setup. Premium version -  Import from Eventbrite at the press of a button!
Author: Ian Molesworth
Author URI: http://www.umfundi.com
Version: 3.7
Date: 01/25/2015
Author Emailid: go@umfundi.com/skype:nimbusgb
*/

add_action( 'admin_init', 'change_time_out' );

function change_time_out() {
	/* max timeout to allow for mass user upload. */
	ini_set( 'max_execution_time', 0 );
	ini_set( 'memory_limit', '256M' );
}

function bmi_admin_css() {
	wp_enqueue_style( 'bmi-style-css', plugin_dir_url( __FILE__ ) . '/registrations-import.css' );
}

// add admin menu
add_action( 'admin_menu','registrationimport_menu' );

function registrationimport_menu() {
	$bmi_page_hook = add_submenu_page( 'users.php',
		'Registration Import',
		'Registration Import',
		'manage_options',
		'registrations-import',
		'registrationimport_page'
	);

	add_action( "admin_print_scripts-$bmi_page_hook", 'bmi_admin_css' );
}

// show import form
function registrationimport_page() {
	global $wpdb;

	if ( ! get_option( 'email-template' ) ) {
		$email_template = 'Hello {USERNAME}

Username: {USERNAME}
Password: {PASSWORD}
Login url: {LOGIN_URL}

Thanks
{SITE_ADMIN}';
		update_option( 'email-template', $email_template );

	}
	$bp_status = is_plugin_active( 'buddypress/bp-loader.php' );
	$wpm_status = is_plugin_active( 'membership/membership.php' );
	if ( $bp_status ) {
		// Check whether the avatars directory present or not. If not then create.
		$bp_plugin_details = get_plugin_data( ABSPATH .'wp-content/plugins/buddypress/bp-loader.php' );
		$bp_plugin_version = $bp_plugin_details['Version'];

		if ( $bp_plugin_version < 1.8 ) {
			define( 'AVATARS', ABSPATH . 'assets/avatars' );
		} else {
			define( 'AVATARS', ABSPATH . 'wp-content/uploads/avatars' );
		}
	}
	// User data fields list used to differentiate with user meta
	$wp_userdata_fields = array(
		'user_login', 'user_pass',
		'user_email', 'user_url', 'user_nicename',
		'display_name', 'user_registered', 'first_name',
		'last_name', 'nickname', 'description',
		'rich_editing', 'comment_shortcuts', 'admin_color',
		'use_ssl', 'show_admin_bar_front', 'show_admin_bar_admin',
		'role'
	);

	$html_message = $error_message = $not_imported_usernames = '';

	//Check whether the curent user has the access or not
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );

	// if the form is submitted
	$valid_nonce = array_key_exists( '_wpnonce', $_POST ) && wp_verify_nonce( $_POST['_wpnonce'], 'bmi_by_ytl' );
	if ( ( $valid_nonce ) && array_key_exists( 'mode', $_POST ) && ( 'submit' == $_POST['mode'] ) ) {
		if ( $bp_status ) {
			//Get the BP extra fields id name name
			$bp_xprofile_fields = $bp_xprofile_fields_with_default_value = array();

			$bp_extra_fields = $wpdb->get_results( 'SELECT id, type, name FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields' );

			$bpxfwdv_sql = 'SELECT name
							FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields
							WHERE type
								IN ("checkbox", "multiselectbox", "selectbox", "radio")
								AND parent_id=0';
			$bp_xprofile_fields_with_default_value = $wpdb->get_col( $bpxfwdv_sql );

			// Get xprofile field visibility
			$bp_fields_visibility = $wpdb->get_results( 'SELECT object_id, meta_value
															FROM ' . $wpdb->base_prefix . 'bp_xprofile_meta
															WHERE meta_key = "default_visibility"'
			);

			$xprofile_fields_visibility = array( 1 => 'public' );

			foreach ( $bp_fields_visibility as $bp_field_visibility ) {
				$xprofile_fields_visibility[$bp_field_visibility->object_id] = $bp_field_visibility->meta_value;
			}

			//Create an array of BP fields
			foreach ( $bp_extra_fields as $value ) {
				$bp_xprofile_fields[$value->id] = $value->name;
				$bp_fields_type[$value->id] = $value->type;
			}
		}
		$filetype = wp_check_filetype( $_FILES['csv_file']['name'] );

		if ( 'csv' !== $filetype['ext']  ) {
			$html_message = '<div class="updated">';
			$html_message .= 'Upload only .csv file!!';
			$html_message .= '</div>';
		} else {
			$avatar = isset( $_POST['avatar'] ) ? $_POST['avatar'] : false;

			// Check whether the admin wants to upload members avatar or not. If yes then
			// Check whether the avatars directory present or not. If not then create.
			if ( $avatar ) if ( ! file_exists( AVATARS ) ) mkdir( AVATARS, 0777 );

			ini_set( 'auto_detect_line_endings', TRUE );
			$handle = fopen( $_FILES['csv_file']['tmp_name'], 'r' );
			$first = true;
			$not_imported = '';
			$flag = 0;
			$user_import = 0;
			$not_import_message = '';
			$total_rows = $new_user_imported = $old_user_updated = $user_not_imported = 0;
			while ( ( $row = fgetcsv( $handle, filesize( $_FILES['csv_file']['tmp_name'] ), ',' ) ) !== false ) {
				$row = array_map( 'trim', $row );
				if ( 1 === count( $row ) ) continue;

				// If we are on the first line, get the columns name in headers array
				if ( $first ) {
					$headers = $row;
					$first = false;
					continue;
				}
				$total_rows++;

				// Separate user data from meta
				$userdata = $usermeta = $bpmeta = $bp_provided_fields = array();

				foreach ( $row as $ckey => $cvalue ) {
					if ( empty( $cvalue ) ) continue;

					$column_name = $headers[$ckey];

					$cvalue = utf8_encode( $cvalue );

					if ( strpos( $cvalue, '::' ) ) {
						$cvalue = explode( '::', $cvalue );
						$cvalue = array_filter( $cvalue, function( $item ) { return !empty( $item[0] ); } );
					}

					if ( in_array( $column_name, $wp_userdata_fields ) ) $userdata[$column_name] = $cvalue;
					else if ( $bp_status && array_search( $column_name, $bp_xprofile_fields ) ) {
							$bp_provided_fields[] = $column_name;
							$bpmeta[array_search( $column_name, $bp_xprofile_fields )] = $cvalue;
						}
					else $usermeta[$column_name] = $cvalue;
				}
				
				if ( !isset( $_POST['update_user'] ) && $bp_status ) {
					$bp_left_fields = array_diff( $bp_xprofile_fields_with_default_value, $bp_provided_fields );

					if ( count( $bp_left_fields ) ) {
						foreach ( $bp_left_fields as $bp_left_field ) {
							$bpf_sql = 'SELECT id, type
										FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields
										WHERE name="' . $bp_left_field . '"
											AND parent_id=0';
							$bp_fields = $wpdb->get_results( $bpf_sql );

							$bpfo_sql = 'SELECT name
										 FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields
										 WHERE parent_id=' . $bp_fields[0]->id . '
											AND is_default_option=1';
							$bp_field_options = $wpdb->get_results( $bpfo_sql );
							$field_options = array();

							if ( $bp_fields[0]->type == 'selectbox' || $bp_fields[0]->type == 'radio' ) {
								$bpmeta[$bp_fields[0]->id] = $bp_field_options[0]->name;
							} else {
								foreach ( $bp_field_options as $bp_field_option ) {
									$field_options[] = $bp_field_option->name;
								}
								$bpmeta[$bp_fields[0]->id] = maybe_unserialize( $field_options );
							}
						}
					}
				}
				
				// If no user data, comeout!
				if ( empty( $userdata ) ) continue;

				// If creating a new user and no password was set, let auto-generate one!
				if ( empty( $userdata['user_pass'] ) )
					$userdata['user_pass'] = wp_generate_password( 12, false );

				$userdata['user_login'] = strtolower( $userdata['user_login'] );

				$space_remove = array( 'user_login', 'user_nicename' );
				foreach ( $space_remove as $key ) {
					if ( isset( $userdata[$key] ) ) {
						$userdata[$key] = str_replace(' ', '', strtolower( $userdata[$key] ) );
					}
				}

				if ( ( $userdata['user_login'] == '' ) && ( $userdata['user_email'] == '' ) ) {
					$error_message .= '<br />user_login or/and user_email needed to import members for row ' . $total_rows;
					$user_not_imported++;
					continue;
				}
				else if ( $userdata['user_login'] == '' )
					$userdata['user_login'] = $userdata['user_email'];
				else if ( $userdata['user_email'] == '' )
					$userdata['user_email'] = $userdata['user_login'];

				//Check whether the user already exist or not
				$user_details = get_user_by( 'email', $userdata['user_email'] );
				// If user already exists
				if ( $user_details   ) { 
					// and we have ticked 'update users' then assign ID and update the account.
					if (isset( $_POST['update_user'])) {
						$userdata['ID'] = $user_details->data->ID;
						// clear the password if we have not allowed it's update/overwrite
						if ( !isset( $_POST['update_password'] ) ) {
							unset( $userdata['user_pass'] );
						}
					$user_id = wp_update_user( $userdata );
					}  
				} else {
					// if the update user does not exist then we just do an user insert.					
					// need to check if username already exists!
//				    $user_details = get_user_by( 'login', $userdata['user_login'] );			    
//				    error_log("CSV User details" . print_r($userdata, true)."\n",3,"mylog.log"); 
//				    error_log("WP User details" . print_r($user_details, true)."\n",3,"mylog.log"); 			    
					$user_id = wp_insert_user( $userdata );
				}
			
				
				// Is there an error?
				if ( is_wp_error( $user_id ) ) {
					$flag = 1;
					$user_not_imported++;
					$not_imported_usernames  .= '<b>' . $userdata['user_login'] . '</b> ' . $user_id->errors['existing_user_login'][0] . '<br />';
      				error_log("wp_error on user! User not imported! ". print_r($user_id,true)."\n", 3,"mylog.log");
				} else {
					//Upload user avatar if permission granted.
					if ( $bp_status && $avatar ) {
						$image_dir = AVATARS . '/'  . $user_id;
						mkdir( $image_dir, 0777 );
						$current_time = time();
						$destination_bpfull = $image_dir . '/' . $current_time . '-bpfull.jpg';
						$destination_bpthumb = $image_dir . '/' . $current_time . '-bpthumb.jpg';

						if ( array_key_exists( 'avatar', $usermeta ) ) {
							$usermeta['avatar'] = str_replace( ' ', '%20', $usermeta['avatar'] );
							$bpfull = $bpthumb = wp_get_image_editor( $usermeta['avatar'] );

							// Handle 404 avatar url
							if ( !is_wp_error( $bpfull ) ) {
								$bpfull->resize( 150, 150, true );
								$bpfull->save( $destination_bpfull );
								$bpthumb->resize( 50, 50, true );
								$bpthumb->save( $destination_bpthumb );
							}
						}
					}

					//User count
					if ( array_key_exists( 'ID', $userdata ) ) {
						$old_user_updated++;
					} else {
						$new_user_imported++;
					}

					$user_import = 1;

					// Insert xprofile field visibility state for user level.
					update_user_meta( $user_id, 'bp_xprofile_visibility_levels', $xprofile_fields_visibility );

					if ( isset( $bpmeta ) ) {
						//Added an entry in user_meta table for current user meta key is last_activity
						bp_update_user_last_activity( $user_id, date( 'Y-m-d H:i:s' ) );

						foreach ( $bpmeta as $bpmetakeyid => $bpmetavalue ) {
							xprofile_set_field_data( $bpmetakeyid, $user_id, $bpmetavalue );
						}
					}

					// If no error, let's update the user meta too!
					if ( $usermeta ) {
						if ( array_key_exists( 'member_group_ids', $usermeta ) ) {
							$member_group_ids = $usermeta['member_group_ids'];
							unset( $usermeta['member_group_ids'] );

							if ( is_array( $member_group_ids ) ) {
								//Attached members with BuddyPress groups
								foreach ( $member_group_ids as $member_group_id ) {
									groups_join_group( $member_group_id, $user_id );
								}
							} else {
								groups_join_group( $member_group_ids, $user_id );
							}
						}

						if ( is_plugin_active( 'membership/membership.php' ) ) {
							if ( array_key_exists( 'wpm_subs_id', $usermeta ) ) {
								$wpm_subs_id = $usermeta['wpm_subs_id'];
								unset( $usermeta['wpm_subs_id'] );
							}
							if ( array_key_exists( 'wpm_level_id', $usermeta ) ) {
								$wpm_level_id = $usermeta['wpm_level_id'];
								unset( $usermeta['wpm_level_id'] );
							}
							if ( array_key_exists( 'wpm_level_order', $usermeta ) ) {
								$wpm_level_order = $usermeta['wpm_level_order'];
								unset( $usermeta['wpm_level_order'] );
							}

							echo $gateway = isset( $usermeta['wpm_payment_gateway'] ) ? $usermeta['wpm_payment_gateway'] : 'admin';
							unset( $usermeta['wpm_payment_gateway'] );
							$member = new M_Membership($user_id);
							$member->add_subscription( $wpm_subs_id, $wpm_level_id, $wpm_level_order, $gateway );
						}

						foreach ( $usermeta as $metakey => $metavalue ) {
							$metavalue = maybe_unserialize( $metavalue );
							update_user_meta( $user_id, $metakey, $metavalue );
						}
					}

					if ( isset( $_POST['new_member_notification'] ) ) {
						if ( isset( $_POST['custom_notification'] ) ) {
							send_notifiction_to_new_user( $user_id, $userdata['user_pass'] );
						} else {
							wp_new_user_notification( $user_id, $userdata['user_pass'] );
						}
					}
				}

				if ( $user_import === 0 && $user_import === 0 )
					$not_import_message = 'No users imported.<br />';
				$not_import_message .= $not_imported_usernames;

				if ( $flag === 1 && $user_import === 1 ) {
					$not_import_message = 'Following user(s) are not imported as they are already registered in your website:<br />';
					$not_import_message .= $not_imported_usernames;
				}
			}

			$html_message = '<div class="updated">';
			$html_message .= $not_import_message;
			$html_message .= '<p style="color: #ff0000;">' . $error_message . '</p>';
			$html_message .= '<p>Total users in CSV: ' . $total_rows . '</p>';
			$html_message .= '<p>Total new users imported: '. $new_user_imported . '</p>';
			$html_message .= '<p>Total old users updated: '. $old_user_updated . '</p>';
			$html_message .= '<p style="color: #ff0000;">Total users not imported: ' . $user_not_imported . '</p>';
			$html_message .= "</div>";
		}
	}  // end of 'if mode is submit'

	if ( array_key_exists( 'save-email-template', $_POST ) ) {
		update_option( 'email-template', $_POST['email-template'] );
	}

	// Get the members import form
	get_form( $html_message );
} // end of 'function registrationimport_page()'


function get_form( $html_message ) {
?>
	<div class="wrap">
		<?php echo $html_message; ?>
		<div id="icon-users" class="icon32"><br /></div>
		<h2>Course Registration Import</h2>
		<p><strong>Select the CSV file you want to import.</strong></p>

		<form action="users.php?page=registrations-import" method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'bmi_by_ytl' ); ?>
			<input type="hidden" name="mode" value="submit" />
			<input type="file" name="csv_file" />
			<input type="submit" value="Import Registrations" />
			<br/><br/>
		<p style="color: red">Make sure to back up your database before proceeding!</p>
			<table class="bmi-options">
				<tr valign="top">
					<th scope="row">Update existing users: </th>
					<td>
						<label for="update_user">
							<input id="update_user" name="update_user" type="checkbox" value="1" />
							Allow existing users data to be updated. ( Users are matched by email address! )
						</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Update existing users password: </th>
					<td>
						<label for="update_password">
							<input id="update_password" name="update_password" type="checkbox" value="1" />
							By checking this checkbox existing users passowrd will be update. Otherwise remain unchanged.
						</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Notification: </th>
					<td>
						<label for="new_member_notification">
							<input id="new_member_notification" name="new_member_notification" type="checkbox" value="1" />
							Send username and password to new users.
						</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Custom Notification: </th>
					<td>
						<label for="custom_notification">
							<input id="custom_notification" name="custom_notification" type="checkbox" value="1" />
							Send custom notification message to users.
						</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Upload Avatar: </th>
					<td>
						<label for="avatar">
							<input id="avatar" name="avatar" type="checkbox" value="1" />
							Upload user avatar from CSV file. ( You must provide full path to the image.)
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Notice: </th>
					<td>The CSV file MUST be in the following format:</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
					#: File extension must be .csv 
					#: Field names should be in the top line in CSV file separated by comma(,) and delimited by double quote(").<br />
					#: For multivalued field value should be separate by :: in csv file.<br />
					#: CSV file should be saved in UTF8 format.<br />
					#: You do not have any blank rows in CSV file.<br />
					</td>
				</tr>
			</table>
		</form>

		<form action="users.php?page=registrations-import" method="post" enctype="multipart/form-data">
			<table>
				<tr>
					<th scope="row">Email Template</th>
					<td>
						<?php
	$args = array(
		'media_buttons' => false,
		'textarea_name ' => 'email-template1',
	);
	wp_editor( get_option( 'email-template' ), 'email-template', $args );
?>
						<br />
						<strong>
							Do not change {FIRSTNAME}, {LASTNAME}, {USERNAME}, {PASSWORD},  {LOGIN_URL}, {SITE_ADMIN}
						</strong>
					</td>
				</tr>
				<tr>
					<th scope="row"></th>
					<td>
						<input type="submit" class="save-email-template" name="save-email-template" value="Save email template"/>
					</td>
				</tr>
			</table>
		</form>
	</div>
	<div>
		<hr />
		<p><strong>WordPress default fields are as follows</strong></p>
		'user_login', 'user_pass',
		'user_email', 'user_url', 'user_nicename',
		'display_name', 'user_registered', 'first_name',
		'last_name', 'nickname', 'description',
		'rich_editing', 'comment_shortcuts', 'admin_color',
		'use_ssl', 'show_admin_bar_front', 'show_admin_bar_admin',
		'role'
		<?php if ( is_plugin_active( 'buddypress/bp-loader.php' ) ) { ?>
		<hr />
		<p><strong>BuddyPress profile fields are as follows</strong></p>
		<?php
			global $wpdb;
			$bp_extra_fields = $wpdb->get_col( 'SELECT name FROM ' . $wpdb->base_prefix . 'bp_xprofile_fields  WHERE parent_id = "0"  ' );
			echo implode( ', ', $bp_extra_fields );
		} ?>
		<?php if ( is_plugin_active( 'membership/membership.php' ) ) { ?>
		<hr />
		<p><strong>Membership plugin fields are as follows</strong></p>
		wpm_subs_id, wpm_level_id, wpm_level_order, wpm_payment_gateway, wp_membership_active
		<br />
		wp_membership_active field value can be <i>"yes"</i> or <i>"no"</i>
		<?php } ?>
		<hr />
	</div>
<?php
}

function send_notifiction_to_new_user( $user_id, $user_pass ) {
	wp_new_user_notification( $user_id, $user_pass, true );
}

if ( ! function_exists( 'wp_new_user_notification' ) ) {
	function wp_new_user_notification( $user_id, $plaintext_pass = '' , $custom = false ) {
		global $custom_message;

		$user = new WP_User( $user_id );

		$user_login = stripslashes( $user->user_login );
		$user_email = stripslashes( $user->user_email );

		$message  = sprintf( __( 'New user registration on %s:' ), get_option( 'blogname' ) ) . "\r\n\r\n";
		$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
		$message .= sprintf( __( 'E-mail: %s' ), $user_email ) . "\r\n";

		if ( empty( $plaintext_pass ) )
			return;

		if ( $custom ) {
			$replace_terms = array( '{FIRSTNAME}', 
									'{LASTNAME}', 
									'{USERNAME}', 
									'{PASSWORD}', 
									'{SITE_ADMIN}', 
									'{LOGIN_URL}', 
								);
			$current_data = array( get_user_meta( $user->ID, 'first_name', true ),
						 		   get_user_meta( $user->ID, 'last_name', true), 
						 		   $user_login, 
						 		   $plaintext_pass, 
						 		   get_option( 'blogname' ), 
						 		   wp_login_url(), 
						 		);
			$custom_message = get_option( 'email-template' );
			$message  = str_replace( $replace_terms, $current_data, $custom_message ) . "\r\n\r\n";
		} else {
			$message  = __( 'Hi there,' ) . "\r\n\r\n";
			$message .= sprintf( __( "Welcome to %s! Here's how to log in:" ), get_option( 'blogname' ) ) . "\r\n\r\n";
			$message .= wp_login_url() . "\r\n";
			$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n";
			$message .= sprintf( __( 'Password: %s' ), $plaintext_pass ) . "\r\n\r\n";
			$message .= sprintf( __( 'If you have any problems, please contact me at %s.' ), get_option( 'admin_email' ) ) . "\r\n\r\n";
			$message .= __( 'Adios!' );
		}

		wp_mail(
			$user_email,
			sprintf( __( '[%s] Your login details' ), get_option( 'blogname' ) ),
			$message
		);
	}
}