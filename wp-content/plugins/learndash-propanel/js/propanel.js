jQuery(function($){

	var panel = $('#ld_assignments_widget');

	if(panel.length){

		panel

			.on('click', 'a.assignment_complete', function(e){

				e.preventDefault();

				var a = $(e.target),

					item = a.closest('.activity_item'),

					lesson = a.attr('data-lesson'),

					user = a.attr('data-user')

				;

				item.find('.activity_item_actions').fadeOut('fast', function(){

					item.find('.activity_loading').fadeIn('fast');

				});

				$.post(item.find('.activity_lesson a').attr('href'), {userid: user, attachment_mark_complete: lesson}, function(){

					item.find('.assignment_status').html(panel.find('.activity_lang_completed').html());



					item.find('.activity_item_actions').show();

					item.find('.activity_loading').hide();



					item.removeClass('assignment_pendant');

				});

			})

			.on('click', 'a.assignment_delete', function(e){

				if(confirm(panel.find('.activity_lang_confirm').text())){

					e.preventDefault();

					var a = $(e.target),

						item = a.closest('.activity_item'),

						id = a.attr('data-id'),

						name = a.attr('data-name')

					;

					item.find('.activity_item_actions').fadeOut('fast', function(){

						item.find('.activity_deleting').fadeIn('fast');

					});

					$.get(

						item.find('.activity_lesson a').attr('href'), 

						{
							learndash_delete_attachment: id
						},

						function(){

							item.slideUp();

						}

					);

				}

			});

		;

	}

});