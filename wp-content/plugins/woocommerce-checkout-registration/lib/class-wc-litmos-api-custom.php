<?php
/**
 * WooCommerce Litmos Custom Code
 * Added a custom parameter: search field to the Litmos API call
 * Disable sending course invitation emails only for blended courses
 * @author: Omar M.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Litmos API Custom Wrapper class
 *
 * Wrapper for the Litmos API
 *
 * @link http://help.litmos.com/developer-api/
 * @since 1.1
 */
class WC_Litmos_API_Custom {


	/** @var string API host */
	const HOST = 'https://api.litmos.com/v1.svc/';

	/** @var string API endpoint */
	private $endpoint;

	/** @var string app source name */
	private $source = 'woocommerce_litmos';

	/** @var string request arguments required with every API request. */
	private $auth_args = '';

	/** @var mixed response string or array  */
	private $response;

	/** @var string response code */
	private $response_code;

	/** @var string users uri */
	private $users_uri = 'users';

	/** @var array args to use with wp_remote_*() */
	private $wp_remote_http_args = array(
		'method'      => '',
		'timeout'     => '30',
		'redirection' => 0,
		'httpversion' => '1.0',
		'sslverify'   => false,
		'blocking'    => true,
		'headers'     => array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json'
		),
		'body'        => '',
		'cookies'     => array()
	);


	/**
	 * Constructor
	 *
	 * @since 1.0
	 * @param string $api_key required Litmos API key
	 * @return \WC_Litmos_API
	 */
	public function __construct( $api_key = '' ) {

		$this->auth_args = sprintf( '?apikey=%s&source=%s', $api_key, $this->source );
	}

	/** User Functions */

	/**
	 * Get list of users filtered by a custom parameter: search field for this user
	 * GET /users
	 *
	 * @since 1.1
	 * @return array
	 */
	public function get_users($username) {

		$this->set_endpoint( $this->users_uri );

		$this->http_request( 'GET', $username, 'search' );

		$this->parse_response();

		return $this->response;
	}


	/**
	 * Get user id by username filtered by custom parameter: search field
	 * @since 1.2.2
	 * @param string $username litmos username
	 * @return mixed litmos user id / false if username not found
	 */
	public function get_user_id_by_username_custom( $username ) {
		foreach ( $this->get_users($username) as $user ) {
			if ( strtolower($user['UserName']) == strtolower($username) ) {
				return $user['Id'];
			}
		}

		return false;
	}

	/**
	 * Assign courses to a single user
	 * Disable sending course invitation emails only for blended courses
	 * POST /users/{userid}/courses
	 *
	 * @since 1.0
	 * @param string $user_id litmos user ID
	 * @param array $course_ids simple array of course IDs to assign to user
	 * @throws Exception if required fields are blank
	 * @return array
	 */
	public function assign_courses_to_user_custom( $user_id, $course_ids ) {

		if( ! $user_id || empty( $course_ids ) ) {
			throw new Exception( __( 'Assign Course: User ID or Course is blank', WC_Litmos::TEXT_DOMAIN ) );
		}

		$this->set_endpoint( $this->users_uri . '/' . $user_id . '/courses' );

		// awful hack because Litmos' API is finicky with JSON
		$xml = '<Courses>';
		foreach( $course_ids as $course ) :
			$xml .= '<Course>';
			$xml .= '<Id>' . $course . '</Id>';
			$xml .= '</Course>';
		endforeach;
		$xml .= '</Courses>';

		$this->wp_remote_http_args['body'] = $xml;
		$this->wp_remote_http_args['headers']['Accept'] = 'application/xml';
		$this->wp_remote_http_args['headers']['Content-Type'] = 'application/xml';

		//$this->http_request( 'POST', '', 'disableemail' );
		$this->http_request( 'POST', '', 'enableemail' );

		$this->parse_response();

		return $this->response;
	}

	/**
	 * Perform error checking on response and return assoc array from JSON decode if no errors
	 *
	 * @since 1.0
	 * @return array
	 */
	private function parse_response() {

		//check for error codes
		$this->check_http_status_code();

		// return associative array
		$this->response = json_decode( $this->response, true );
	}


	/**
	 * Checks response HTTP status code according to Litmos API documentation
	 *
	 * @since 1.0
	 * @throws Exception If HTTP status code other than 200/201 or if HTTP status code not set
	 */
	private function check_http_status_code() {

		if ( isset( $this->response['response']['code'] ) ) {

			// Successful request, return the response body for JSON decode & code for success check
			if ( '200' == $this->response['response']['code'] || '201' == $this->response['response']['code'] ) {

				$this->response_code = $this->response['response']['code'];
				$this->response      = $this->response['body'];

			} else {

				// Failed request
				throw new Exception( sprintf( __( 'Error Code: %s | Error Message: %s', WC_Litmos::TEXT_DOMAIN ), $this->response['response']['code'], strip_tags( $this->response['body'] ) ) );
			}

		} else {

			throw new Exception( __( 'Response HTTP Code Not Set', WC_Litmos::TEXT_DOMAIN ) );
		}
	}


	/**
	 * Checks for valid data when creating or updating an API resource
	 * JSON encodes data and sets body of WP HTTP Request
	 *
	 * @since 1.0
	 * @param $data mixed data to be JSON-ified
	 * @throws Exception if $data is not array
	 */
	private function encode( $data ) {

		if ( ! is_array( $data ) ) {
			throw new Exception( __( 'JSON Encode : Passed Data is not array', WC_Litmos::TEXT_DOMAIN ) );
		}

		$this->wp_remote_http_args['body'] = json_encode( $data );
	}


	/**
	 * Set Endpoint URI
	 *
	 * @since 1.0
	 * @param $uri API Type URI (users, courses, etc)
	 * @return void
	 */
	private function set_endpoint( $uri ) {

		$this->endpoint = self::HOST . $uri;

		// XML Hack so set this with every request if not using XML
		$this->wp_remote_http_args['headers']['Accept'] = 'application/json';
		$this->wp_remote_http_args['headers']['Content-Type'] = 'application/json';
	}


	/**
	 * Performs HTTP Request
	 *
	 * @since 1.0
	 * @param string $method HTTP method to use for request
	 * @throws Exception Blank/invalid endpoint or HTTP method, WP HTTP API error
	 * Custom Parameter: search for a user or disable sending emails from litmos only for blended courses
	 * @return array
	 */
	private function http_request( $method, $username='', $customParameter='') {

		// Check for blank endpoint or method
		if ( ! $this->endpoint || ! $method ) {
			throw new Exception( __( 'Endpoint and / or HTTP Method is blank.', WC_Litmos::TEXT_DOMAIN ) );
		}

		// Check that method is a valid http method
		if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'DELETE' ) ) ) {
			throw new Exception( __( 'Requested HTTP Method is invalid.', WC_Litmos::TEXT_DOMAIN ) );
		}

		// set the method
		$this->wp_remote_http_args['method'] = $method;

		//$customParameterValue = ($customParameter == '')? '' : '&search='.$customParameter;

		if ($customParameter == 'search'){
			$customParameterValue = '&search='.$username;
		}
		elseif ($customParameter == 'disableemail'){
			$customParameterValue = '&sendmessage=false';
		}
		elseif ($customParameter == 'enableemail'){
			$customParameterValue = '&sendmessage=true';
		}
		else{
			$customParameterValue = '';
		}
				
		// perform HTTP request with endpoint / args
		$this->response = wp_remote_request( esc_url_raw( $this->endpoint . $this->auth_args . $customParameterValue), $this->wp_remote_http_args );

		// WP HTTP API error like Network timeout, etc.
		if ( is_wp_error( $this->response ) ) {
			throw new Exception( $this->response->get_error_message() );
		}

		// Check for proper response / body
		if ( ! isset( $this->response['response'] ) ) {
			throw new Exception( __( 'Empty Response', WC_Litmos::TEXT_DOMAIN ) );
		}

		if ( ! isset( $this->response['body'] ) ) {
			throw new Exception( __( 'Empty Body', WC_Litmos::TEXT_DOMAIN ) );
		}
	}


} //end \WC_Litmos_API_Custom class
