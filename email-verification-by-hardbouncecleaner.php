<?php
/*
Plugin Name: Email Verification by HardBounceCleaner
Plugin URI: https://www.hardbouncecleaner.com/
Description: This plugin finds all the emails in Wordpress, checks if these emails are valid. You will then be able to download these lists of cleaned emails.
Author: HardBounceCleaner
Version: 1.0
Author URI: https://www.hardbouncecleaner.com/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Requires at least: 4.7
Tested up to: 4.9.6

Text Domain: email-verification-by-hardbouncecleaner
Domain Path: /languages
*/

// Make sure we don't expose any info if called directly
use HardBounceCleaner\EmailVerification;

if ( ! function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'EVH_VERSION', 1.0 );
define( 'EVH_PLUGIN_PREFIX', 'email_verification_by_hardbouncecleaner' );
define( 'EVH_PLUGIN_DIR', dirname( __FILE__ ) );

// Send a crash report ?
$send_crash_report = get_option( EVH_PLUGIN_PREFIX . '_send_crash_report ', null );
if ( $send_crash_report === null ) {
	$send_crash_report = 0;
	add_option( EVH_PLUGIN_PREFIX . '_send_crash_report', $send_crash_report );
}
if ( isset( $_GET['send_crash_report'] ) && $_GET['send_crash_report'] ) {
	// crash report accecpted
	update_option( EVH_PLUGIN_PREFIX . '_send_crash_report', 1 );
	$send_crash_report = 1;
}
define( 'EVH_PLUGIN_CRASH_REPORT', $send_crash_report );

require_once EVH_PLUGIN_DIR . '/lib/Exception.php';
require_once EVH_PLUGIN_DIR . '/lib/EmailVerification.php';

register_activation_hook( __FILE__, array( 'HardBounceCleaner\EmailVerification', 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( 'HardBounceCleaner\EmailVerification', 'plugin_deactivation' ) );

// inside WordPress administration interface
if ( is_admin() ) {

	add_action('plugins_loaded', 'email_verification_by_hardbouncecleaner_load_textdomain');
	function email_verification_by_hardbouncecleaner_load_textdomain() {
		load_plugin_textdomain( 'email-verification-by-hardbouncecleaner', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );
	}

	// admin_menu
	add_action( 'admin_menu', 'email_verification_by_hardbouncecleaner_setup_menu' );
	function email_verification_by_hardbouncecleaner_setup_menu() {
		add_menu_page( __( 'Email Verification by HardBounceCleaner' ), __( 'Email Verification' ), 'manage_options', 'email-verification-by-hardbouncecleaner',
			'email_verification_by_hardbouncecleaner_render_list_page', 'dashicons-email-alt' );
	}

	// css stylesheet
	add_action( 'admin_enqueue_scripts', 'load_admin_styles' );
	function load_admin_styles() {
		wp_enqueue_style( 'custom_css', plugins_url('/admin/css/custom.css', __FILE__), false, EVH_VERSION );
	}

	function email_verification_by_hardbouncecleaner_render_list_page() {


		if ( ! class_exists( 'HardBounceCleaner_List_Table' ) ) {
			require_once( EVH_PLUGIN_DIR . '/list-table.php' );
		}
		$listTable = new HardBounceCleaner_List_Table();
		$listTable->prepare_items();

		if ( ! class_exists( 'HardBounceCleaner_File_Table' ) ) {
			require_once( EVH_PLUGIN_DIR . '/file-table.php' );
		}
		$fileTable = new HardBounceCleaner_File_Table();
		$fileTable->prepare_items();

		// Api Key
		if ( isset( $_GET['api_key'] ) ) {
			update_option( EVH_PLUGIN_PREFIX . '_api_key', trim( $_GET['api_key'] ) );
			update_option( EVH_PLUGIN_PREFIX . '_api_error_message', '' );
		}

		$api_key = get_option( EVH_PLUGIN_PREFIX . '_api_key', null );
		if ( $api_key === null ) {
			add_option( EVH_PLUGIN_PREFIX . '_api_key', '' );
			$api_key = '';
		}
		$api_error_message = get_option( EVH_PLUGIN_PREFIX . '_api_error_message', null );
		if ( $api_error_message === null ) {
			add_option( EVH_PLUGIN_PREFIX . '_api_error_message', '' );
			$api_error_message = '';
		}

		$language = substr( get_locale(), 0, 2 );
		?>

		<div class="wrap">

			<h2><?php echo __( "Email Verification by HardBounceCleaner" ); ?></h2>

			<?php if ( ! EVH_PLUGIN_CRASH_REPORT ): ?>
				<div class="update-nag">
					<form id="api-key-form" method="get" action="">
						<label><?php echo __( "Activate the Crash report ?" ); ?></label>
						<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
						<input type="hidden" name="send_crash_report" value="1"/>

						<input type="submit" name="save" class="button button-primary" value="<?php echo __( "Activate" ); ?>">
					</form>
				</div>
			<?php endif; ?>


			<div class="panel-container">
				<div class="panel">
					<h3><?php echo __( "Lexicon" ); ?></h3>
					<ul>
						<li><b><?php echo __( "Email" ); ?></b> : <?php echo __( "The syntax of the emails are good." ); ?><br>
							<ul class="list-color">
								<li><span style="color: blue"><?php echo __( "Blue: Pending verification" ); ?></span></li>
								<li><span style="color: red"><?php echo __( "Red: symbolize an invalid email" ); ?></span></li>
								<li><span style="color: green"><?php echo __( "Green: the email really exist" ); ?></span></li>
								<li><span style="color: orange"><?php echo __( "Orange: the email is valid at 80%" ); ?></span></li>
								<li><span style="color: purple"><?php echo __( "Purple: informations are missing (need the connection to HardBounceCleaner)" ); ?></span></li>
							</ul>
						</li>
						<li><b><?php echo __( "Role" ); ?></b> :
							<?php echo __( "Role-based email addresses (like admin@, help@, sales@) are email addresses that are not associated with a particular person, be careful if you send a BtoC newsletter." ); ?>
						</li>
						<li><b><?php echo __( "Disposable" ); ?></b> :
							<?php echo __( "Disposable email is a service that allows a registered user to receive email at a temporary address that expires after a certain time period elapses." ); ?>
						</li>
						<li><b><?php echo __( "Mx" ); ?></b> :
							<?php echo __( "An email exchanger (MX) is the server who will receive your emails, if it's not valid no email can be send." ); ?>
						</li>
						<li><b><?php echo __( "Risky" ); ?></b> :
							<?php echo __( "Some providers use valid email addresses as Honeypot to catch Spam sender, be careful if your emails are too old or are not double opt-in." ); ?>
							<span class="label label-info"><?php echo __( "Connection with HardBouncerCleaner needed" ); ?></span>
						</li>
						<li><b><?php echo __( "Email exist" ); ?></b> :
							<?php echo __( "Check if the email is valid and exist or not." ); ?>
							<span class="label label-info"><?php echo __( "Connection with HardBouncerCleaner needed" ); ?></span>
						</li>

					</ul>
				</div>
				<div class="panel">
					<h3><?php echo __( "How it works" ); ?></h3>
					<p>
						<?php echo __( "The emails in your WordPress database will be find, and checked daily by WP-Cron." ); ?>
						<?php echo __( "To be sur your WP-Cron system is working, you should install the WP-Cron Status Checker plugin, and check the result in your Dashboard." ); ?>
					</p>
					<p><?php echo __( "For the first activation a maximum of 100 emails will be detected, you will see the full list the day after." ); ?></p>
					<h3>
						<?php echo __( "Connection with HardBounceCleaner" ); ?>
						<small>(<?php echo __( "optional" ); ?>)</small>
					</h3>
					<p>
						<?php echo __( "First you have to create a Free account." ); ?>
						<a href="https://www.hardbouncecleaner.com/<?php echo $language; ?>/?utm_source=Worpress&utm_campaign=plugin#start-a-free-trial" target="_blank"><?php echo __( "Create a Free account" ); ?></a>
					</p>
					<p>
						<?php echo __( "Once it's done go to the API section  and copy the key below." ); ?>
						<a href="https://www.hardbouncecleaner.com/<?php echo $language; ?>/admin/api?utm_source=Worpress&utm_campaign=plugin" target="_blank"><?php echo __( "API section" ); ?></a>
					</p>
					<div>
						<form id="api-key-form" method="get" action="">
							<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
							<label for="api-key" id="api-key-prompt-text"><?php echo __( "Api Key" ); ?></label>
							<input type="text" name="api_key" id="api-key" autocomplete="off" value="<?php echo $api_key; ?>">
							<input type="submit" name="save" class="button button-primary" value="<?php echo __( "Save" ); ?>">
						</form>
					</div>
					<?php if ( strlen( $api_error_message ) ): ?>
						<p style="color: red"><?php echo $api_error_message; ?></p>
					<?php endif; ?>

					<h3><?php echo __( "Need Help ?" ); ?></h3>
					<p><?php echo __( "You can contact us at hello@hardbouncecleaner.com for any questions." ); ?></p>
				</div>
			</div>

			<form id="list-filter" method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
				<input type="hidden" name="id" value="list-filter"/>
				<h3><?php echo __( "Email list" ); ?></h3>
				<?php $listTable->display() ?>
			</form>

			<form id="file-filter" method="get">
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
				<input type="hidden" name="id" value="file-filter"/>
				<h3><?php echo __( "Files to download" ); ?></h3>
				<?php $fileTable->display() ?>
			</form>

		</div>
		<?php
	}
}


// scheduled cron task
add_action( EVH_PLUGIN_PREFIX . '_scheduled_cron_daily', array( 'HardBounceCleaner\EmailVerification', 'cron_daily' ) );
if ( ! wp_next_scheduled( EVH_PLUGIN_PREFIX . '_scheduled_cron_daily' ) ) {
	wp_schedule_event( time(), 'daily', EVH_PLUGIN_PREFIX . '_scheduled_cron_daily' );
}
add_action( EVH_PLUGIN_PREFIX . '_scheduled_cron_hourly', array( 'HardBounceCleaner\EmailVerification', 'cron_hourly' ) );
if ( ! wp_next_scheduled( EVH_PLUGIN_PREFIX . '_scheduled_cron_hourly' ) ) {
	wp_schedule_event( time(), 'hourly', EVH_PLUGIN_PREFIX . '_scheduled_cron_hourly' );
}

