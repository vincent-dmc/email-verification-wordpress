<?php


namespace HardBounceCleaner;


/**
 * Class Exception
 * @package HardBounceCleaner
 */
class Exception extends \Exception {

	/**
	 * Exception constructor.
	 *
	 * @param string $message
	 * @param int $code
	 * @param Exception|null $previous
	 * @param array $data
	 */
	public function __construct( $message, $code = 0, Exception $previous = null, $data = array() ) {

		self::sendCrashReport( $message );

		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			// not cli
			$this->display_message( $message, $data );
		}

		parent::__construct( $message, $code, $previous );
	}

	/**
	 * @param $message
	 */
	public static function sendCrashReport( $message ) {
		global $wp_version;

		$send_crash_report = get_option( EVH_PLUGIN_PREFIX . '_send_crash_report ', null );
		if ( $send_crash_report === null ) {
			$send_crash_report = 0;
			add_option( EVH_PLUGIN_PREFIX . '_send_crash_report ', $send_crash_report );
		}
		if ( ! $send_crash_report ) {
			return;
		}

		$request_uri = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = $_SERVER['REQUEST_URI'];
		}

		$data['php_version'] = phpversion();
		$data['wp_version']  = $wp_version;
		$data['request_uri'] = $request_uri;
		$data['siteurl']     = get_option( 'siteurl', '' );
		$data['admin_email'] = get_option( 'admin_email', '' );

		// send crash report if user is ok
		$data_string = '';
		foreach ( $data as $key => $value ) {
			$data_string .= '<b>' . htmlentities( $key ) . '</b> : ' . htmlentities( $value ) . '<br>';
		}
		wp_mail( 'hello@hardbouncecleaner.com', "WP Crash Report $request_uri", $data_string.$message, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * @param $message
	 * @param array $data
	 */
	public function display_message( $message, $data = array() ) {

		echo '<h1 style="color: red;">' . __( 'An exception occured' ) . '</h1>';
		echo '<p style="color: red;">' . $message . '</p>';
	}

}
