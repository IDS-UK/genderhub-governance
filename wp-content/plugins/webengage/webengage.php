<?php
/*
Plugin Name: WebEngage
Plugin URI: http://webengage.com
Description: WebEngage lets you collect feedback from your website visitors. With WebEngage, you can also conduct in-site surveys to gather realtime insights from your customers. Oh, did we tell you that you can also use WebEngage to push notification messages to visitors on your website? Yes, we made throwing discount codes to visitors on your site real easy! To get started: 1) Click the "Activate" link to the left of this description, 2) You'll be taken to the <a href="options-general.php?page=webengage">WebEngage configuration</a> page; sign-up to create your account. That's it! Get an end-to-end feedback, survey management and push notification solution in less than 5 minutes.
Version: 2.0.3
Author: WebEngage
Author URI: http://webengage.com/about-us
*/
// rendring the webengage widget code
function render_webengage() {
  $webengage_license_code = get_option('webengage_license_code');
  // render the widget if license code is present
  if ($webengage_license_code && $webengage_license_code !== '') {
  ?>
	<!-- Added via WebEngage Wordpress Plugin 2.0.3 -->
	<script id="_webengage_script_tag" type="text/javascript">
	var webengage;
	!function(e,t,n){function o(e,t){e[t[t.length-1]]=function(){r.__queue.push([t.join("."),arguments])}}var i,s,r=e[n],g=" ",l="init options track onReady".split(g),a="feedback survey notification".split(g),c="options render clear abort".split(g),p="Open Close Submit Complete View Click".split(g),u="identify login logout setAttribute".split(g);if(!r||!r.__v){for(e[n]=r={__queue:[],__v:"5.0",user:{}},i=0;i<l.length;i++)o(r,[l[i]]);for(i=0;i<a.length;i++){for(r[a[i]]={},s=0;s<c.length;s++)o(r[a[i]],[a[i],c[s]]);for(s=0;s<p.length;s++)o(r[a[i]],[a[i],"on"+p[s]])}for(i=0;i<u.length;i++)o(r.user,["user",u[i]]);var f=t.createElement("script"),d=t.getElementById("_webengage_script_tag");f.type="text/javascript",f.async=!0,f.src=("https:"==t.location.protocol?"//ssl.widgets.webengage.com":"//cdn.widgets.webengage.com")+"/js/widget/webengage-min-v-5.0.js",d.parentNode.insertBefore(f,d)}}(window,document,"webengage");

	webengage.init('<?php echo $webengage_license_code; ?>');

	</script>
<?php
  }
}
// calling render_webengage while rendering wp_footer
add_action('wp_footer', 'render_webengage');
// initialising option
function set_webengage_options () {
  update_option('webengage_redirect_on_first_activation', 'true');
}
// deleting option
function unset_webengage_options () {
  delete_option('webengage_license_code');
  delete_option('webengage_widget_status');
  delete_option('webengage_redirect_on_first_activation');
}
// plugin definitions
define( 'WE_BASENAME', plugin_basename( __FILE__ ) );
define( 'WE_BASEFOLDER', plugin_basename( dirname( __FILE__ ) ) );
define( 'WE_FILENAME', str_replace( WE_BASEFOLDER.'/', '', plugin_basename(__FILE__) ) );
// activation/deactivation hooks
register_activation_hook(WP_PLUGIN_DIR . '/' . WE_BASENAME, 'set_webengage_options' );
//register_deactivation_hook(WP_PLUGIN_DIR . '/' . WE_BASENAME, 'unset_webengage_options' );
register_uninstall_hook(WP_PLUGIN_DIR . '/' . WE_BASENAME , 'unset_webengage_options' );
// including the options page
require_once(WP_PLUGIN_DIR . '/webengage/options.php');
// adding the settings link in the WE plugin description in plugin listing page
function filter_plugin_meta($links, $file) {
	/* create link */
	if ( $file == WE_BASENAME ) {
		array_push(
			$links,
			sprintf( '<a href="options-general.php?page=%s">%s</a>', WE_FILENAME, __('Settings') )
		);
	}
	return $links;
}

add_filter( 'plugin_action_links', 'filter_plugin_meta', 10, 2);

// redirect to webengage settings page on activation of plugin
add_action('admin_init', 'webengage_redirect');
function webengage_redirect () {
  if (get_option('webengage_redirect_on_first_activation') == 'true') {
    update_option('webengage_redirect_on_first_activation', 'false');
    wp_redirect(get_option('siteurl'). '/wp-admin/options-general.php?page=webengage');
  }
}
?>
