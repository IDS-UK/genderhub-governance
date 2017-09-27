<?php
	/*
		Available Variables:
		$course_id 		: (int) ID of the course
		$course 		: (object) Post object of the course
		$course_settings : (array) Settings specific to current course

		$courses_options : Options/Settings as configured on Course Options page
		$lessons_options : Options/Settings as configured on Lessons Options page
		$quizzes_options : Options/Settings as configured on Quiz Options page

		$user_id 		: Current User ID
		$logged_in 		: User is logged in
		$current_user 	: (object) Currently logged in user object

		$course_status 	: Course Status
		$has_access 	: User has access to course or is enrolled.
		$materials 		: Course Materials
		$has_course_content		: Course has course content
		$lessons 		: Lessons Array
		$quizzes 		: Quizzes Array
		$lesson_progression_enabled 	: (true/false)
		$has_topics		: (true/false)
		$lesson_topics	: (array) lessons topics
	*/

	/* $course_settings Array
		(
		    [course_materials] => material text
		    [course_price] => 1
		    [course_price_type] => paynow
		    [course_access_list] => 1,2,3,5,3,9,4
		    [course_lesson_orderby] =>
		    [course_lesson_order] => ASC
		    [course_prerequisite] =>
		)
	*/

	/* Show Course Status */

	if($logged_in) {
		$course_progress = get_user_meta(get_current_user_id(), '_sfwd-course_progress', true);
		$completed = intVal($course_progress[$course_id]['completed']) == 4;
		?>
		<?php if($completed){ ?>
			<div class="CourseComplete">
				<img src="<?php echo get_bloginfo('template_directory') . '/assets/img/badges/complete/badge_course_complete.png' ?> ">
				<h2>Congratulations.</h2>
				<p>You have completed the course</p>
			</div>
    <?php }else{ ?>
    	<!--<span id='learndash_course_status'>
				<strong><?php _e('Course Status:', 'learndash') ?></strong> <?php echo $course_status; ?>
				<br>
			</span><br>-->
		<?php }
  }

	echo $content;

	if ( !$has_access ) {
		echo learndash_payment_buttons($post);
	}
?>