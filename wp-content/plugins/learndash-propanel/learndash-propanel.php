<?php
/**
 * @package LearnDash Pro Panel
 * @version 1.0.11
 */
/*
Plugin Name: LearnDash Pro Panel
Plugin URI: http://www.learndash.com
Description: Easily manage and view your LearnDash LMS activity.
Version: 1.0.11
Author: LearnDash
Author URI: http://www.learndash.com
*/


if(!class_exists('LearnDash_Propanel')):

class LearnDash_Propanel{
	static function addColorScheme(){
		
	 	wp_admin_css_color(
			'propanel', 
			'LearnDash Pro Panel #1',
			plugins_url('css/colorscheme.css', __FILE__),
			array('#485B79', '#FB9337', '#fffff', '#666666')
		);
		wp_admin_css_color(
  			 'propanel2',
   			__('LearnDash Pro Panel #2'),
   			plugins_url('css/colorscheme2.css', __FILE__),
   			array('#485B79', '#beeb20', '#f4f4f4', '#2683AE')
 		);
		wp_admin_css_color(
  			 'propanel3',
   			__('LearnDash Pro Panel #3'),
   			plugins_url('css/colorscheme3.css', __FILE__),
   			array('#737373', '#3381a7', '#f4f4f4', '#666666')
 		);
		
		$admin_role = get_role('administrator');
		$admin_role->add_cap('propanel_widgets');
	}

	static function createDashboardWidgets(){
		if(current_user_can('propanel_widgets')){
			wp_add_dashboard_widget(
				'ld_activity', 
				__('LearnDash Activity', 'ld_propanel'), 
				array('LearnDash_Propanel', 'activityPanel')	
			);
			wp_add_dashboard_widget(
				'ld_assignments', 
				__('Uploaded Assignments',  'ld_propanel'), 
				array('LearnDash_Propanel', 'assignmentsPanel')
			);
		}
	}

	/**
	 * Create the Activity dashboard markup
	 * @return [type] [description]
	 */
	static function activityPanel(){
		global $wpdb;

		$table = $wpdb->usermeta;
		if(is_multisite())
		$sql = $wpdb->prepare("SELECT user_id, meta_value FROM $table WHERE meta_key = '_sfwd-quizzes' AND user_id IN (SELECT user_id FROM $table where meta_key = 'primary_blog'  AND meta_value='%d')", $GLOBALS['blog_id']);
		else
		$sql = "SELECT user_id, meta_value FROM $table WHERE meta_key = '_sfwd-quizzes'";

		$meta_list = $wpdb->get_results( $sql, ARRAY_A );

		$sorted_quizzes = self::sortQuizzes($meta_list);

		$activity = array();
		$users = array();

		foreach($sorted_quizzes as $time => $quizdata){
			$userid = $quizdata['user'];
			if(array_search($userid, $users) === FALSE && sizeof($activity) < 10){
				$users[] = $userid;
				$userData = get_userdata($userid);
				$quiz = get_post( $quizdata['quiz'] );

				$activity[] = array(
					'user' => $userData->user_nicename, 
					'userLink' => admin_url('user-edit.php?user_id=' . $userData->ID . '#submit'),
					'type' => 'quiz',
					'date' => date('l, F j, Y', $time),
					'quiz' => $quiz->post_title,
					'quizLink' => get_edit_post_link($quiz->ID),
					'score' => $quizdata['score'],
					'count' => $quizdata['count'],
					'pass' => $quizdata['pass']
				);

				$progress = get_user_meta($userid, '_sfwd-course_progress', true);

				if(!empty($progress)){
					$activity[] = self::extractProgress($userData, $progress);
				}
			}
		}

		if(sizeof($activity) < 10){
			$table = $wpdb->usermeta;
			if(is_multisite())
			$sql = $wpdb->prepare("SELECT user_id, meta_value FROM $table WHERE meta_key = '_sfwd-course_progress' AND user_id IN (SELECT user_id FROM $table where meta_key = 'primary_blog' AND meta_value='%d')", $GLOBALS['blog_id']);
			else
			$sql = "SELECT user_id, meta_value FROM $table WHERE meta_key = '_sfwd-course_progress'";


			$meta_list = $wpdb->get_results( $sql, ARRAY_A );

			for($i = sizeof($meta_list) - 1; $i>=0; $i--){
				if(sizeof($activity) >= 10)
					continue;

				$progress = $meta_list[$i];
				$userId = $progress['user_id'];
				if( array_search($userId, $users) === FALSE && !empty($progress) ){
					$activity[] = self::extractProgress(get_userdata($userId), unserialize($progress['meta_value']));
					$users[] = $userId;
				}
			}

		}

		include 'tpl/activity_panel.php';
	}

	/**
	 * Create the assigments dashboard widget markup
	 * @return [type] [description]
	 */
	static function assignmentsPanel(){
		global $wpdb;
		$opt = array(
				'post_type'		=> 'sfwd-assignment',
				'page_per_posts'=> -1,
			);
		$assignments = get_posts($opt);
		
		$ordered = array();
		$users = array();
		$posts = array();
		foreach ($assignments as $assignment) {
			if(empty($users[$assignment->post_author]))
				$users[$assignment->post_author] = get_user_by("id", $assignment->post_author);

			if(empty($users[$assignment->post_author]))
				continue;
			$meta = get_post_meta($assignment->ID);
			$lesson_id  = @$meta["lesson_id"][0];
		
			if(empty($lesson_id))
				continue;
		
			if(empty($posts[$lesson_id]))
				$posts[$lesson_id] = get_post($lesson_id);

			if(empty($posts[$lesson_id]))
				continue;
		
			$lesson = $posts[$lesson_id];
			$user = $users[$assignment->post_author];
			$completed = learndash_is_assignment_approved_by_meta($assignment->ID); 
			$assData = self::extractAssignmentDataNew($assignment, $user, $lesson, $meta, $completed);
			if($completed)
				$ordered[] = $assData;
			else
				array_unshift($ordered, $assData);				
		}
		
		/*$table = $wpdb->prefix . 'postmeta';
		$sql = "SELECT post_id, meta_value FROM $table WHERE meta_key = 'sfwd_lessons-assignment'";

		$meta_list = $wpdb->get_results( $sql, ARRAY_A );
		$ordered = array();
		foreach($meta_list as $post_assignments){
			$postid = $post_assignments['post_id'];
			$lesson = get_post($postid);
			if($lesson && $lesson->post_status == 'publish'){
				$assignments = unserialize($post_assignments['meta_value']);
				$assignments = array_reverse($assignments['assignment'], true);

				foreach($assignments as $id => $ass){
					$user = get_user_by("login",$ass['user_name']);
					$progress = learndash_get_course_progress($user->ID, $postid);
					$completed = !empty($progress['this']->completed);
						$ass['id'] = $id;
						$assData = self::extractAssignmentData($ass, $user, $lesson, $completed);
						if($completed)
							$ordered[] = $assData;
						else
							array_unshift($ordered, $assData);
				}
			}
		}*/

		include 'tpl/assignments_panel.php';
	}
	static function extractAssignmentDataNew($assignment, $user, $lesson, $meta, $completed) {
		return array(
			'id' => $assignment->ID,
			'user' => $user->user_nicename,
			'userLink' => admin_url('user-edit.php?user_id=' . $user->ID . '#submit'),
			'userid' => $user->ID,
			'completed' => $completed,
			'lesson' => $lesson->post_title,
			'lessonLink' => get_permalink($lesson->ID),
			'lessonid' => $lesson->ID,
			'name' => @$meta['file_name'][0],
			'url' => @$meta['file_link'][0],
			'path' => @$meta['file_path'][0]
		);		
	}

	static function extractAssignmentData($ass, $user, $lesson, $completed){
		return array(
			'id' => $ass['id'],
			'user' => $user->user_nicename,
			'userLink' => admin_url('user-edit.php?user_id=' . $user->ID . '#submit'),
			'userid' => $user->ID,
			'completed' => $completed,
			'lesson' => $lesson->post_title,
			'lessonLink' => get_permalink($lesson->ID),
			'lessonid' => $lesson->ID,
			'name' => $ass['file_name'],
			'url' => $ass['file_link'],
			'path' => $ass['file_path']
		);
	}

	static function extractProgress($userData, $progress){
		$courseProgress = false;
		foreach($progress as $id => $course){
			$courseProgress = $course;
			$courseProgress['id'] = $id;
			continue;
		}
		$course = get_post($courseProgress['id']);
		return array(
			'user' => $userData->user_nicename,
			'userLink' => admin_url('user-edit.php?user_id=' . $userData->ID . '#submit'),
			'type' => 'course',
			'course' => $course->post_title,
			'courseLink' => get_edit_post_link($course->ID),
			'completed' => $courseProgress['completed'],
			'total' => $courseProgress['total']
		);
	}

	static function sortQuizzes($dbquiz){
		$sorted = array();
		foreach($dbquiz as $quiz){
			$quizzes = unserialize($quiz['meta_value']);
			$data = $quizzes[sizeof($quizzes) - 1];
			$data['user'] = $quiz['user_id'];
			$sorted[$data['time']] = $data;
		}
		krsort($sorted);
		return $sorted;
	}

	static function i18nize() {
		load_plugin_textdomain( 'ld_propanel', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' ); 	
	}

	static function addResources(){
		wp_enqueue_style('propanel', plugins_url('css/propanel.css', __FILE__));
//	  	wp_enqueue_style('propanel2', plugins_url('css/propanel2.css', __FILE__));
//		wp_enqueue_style('propanel2', plugins_url('css/propanel3.css', __FILE__));
		wp_enqueue_script('propanel', plugins_url('js/propanel.js', __FILE__), array('jquery'));
//	  	wp_enqueue_script('propanel2', plugins_url('js/propanel.js', __FILE__), array('jquery'));
//		wp_enqueue_script('propanel3', plugins_url('js/propanel.js', __FILE__), array('jquery'));
	}

	static function setColorScheme(){
		$user = get_current_user_id();
		update_user_option($user, 'admin_color', 'propanel', true);
	  	update_user_option($user, 'admin_color', 'propanel2', true);
		update_user_option($user, 'admin_color', 'propanel3', true);
	}
}


add_action( 'admin_init', array('LearnDash_Propanel','addColorScheme'));
add_action( 'wp_dashboard_setup', array('LearnDash_Propanel','createDashboardWidgets'));
add_action( 'admin_enqueue_scripts', array('LearnDash_Propanel','addResources'));
add_action( 'plugins_loaded', array('LearnDash_Propanel','i18nize'));
//register_activation_hook( __FILE__, array( 'LearnDash_Propanel', 'setColorScheme' ) );

endif;


// Load the auto-update class
add_action('init', 'nss_plugin_updater_activate_learndash_propanel');
function nss_plugin_updater_activate_learndash_propanel()
{
	//if(!class_exists('nss_plugin_updater'))
    require_once (dirname(__FILE__).'/wp_autoupdate_sfwd_lms.php');
	
	$nss_plugin_updater_plugin_remote_path = 'http://support.learndash.com/';
    $nss_plugin_updater_plugin_slug = plugin_basename(__FILE__);

    new nss_plugin_updater_learndash_propanel ($nss_plugin_updater_plugin_remote_path, $nss_plugin_updater_plugin_slug);
}
