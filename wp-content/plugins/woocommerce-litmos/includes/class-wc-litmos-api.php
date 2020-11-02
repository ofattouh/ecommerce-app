<?php
/**
 * WooCommerce Litmos
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Litmos to newer
 * versions in the future. If you wish to customize WooCommerce Litmos for your
 * needs please refer to http://docs.woocommerce.com/document/litmos/ for more information.
 *
 * @package     WC-Litmos/API
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2017, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Litmos API Wrapper class
 *
 * Wrapper for the Litmos API
 *
 * @link http://help.litmos.com/developer-api/
 * @since 1.0
 */
class WC_Litmos_API {


	/** @var string API host */
	const HOST = 'https://api.litmos.com/v1.svc/';

	/** @var string API endpoint */
	private $endpoint;

	/** @var string app source name */
	private $source = 'woocommerce_litmos';

	/** @var array request parameters */
	private $request_params = array();

	/** @var array authorization params required with every API request. */
	private $auth_params;

	/** @var mixed response string or array  */
	private $response;

	/** @var string response code */
	private $response_code;

	/** @var string users uri */
	private $users_uri = 'users';

	/** @var string teams uri */
	private $teams_uri = 'teams';

	/** @var string courses uri */
	private $courses_uri = 'courses';

	/** @var string achievements uri */
	private $achievements_uri = 'achievements';

	/** @var array args to use with wp_remote_*() */
	private $wp_remote_http_args = array(
		'method'      => '',
		'timeout'     => '30',
		'redirection' => 0,
		'httpversion' => '1.0',
		'sslverify'   => true,
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

		$this->auth_params = array( 'apikey' => $api_key, 'source' => $this->source );
	}

	/** User Functions */

	/**
	 * Get list of users
	 *
	 * GET /users
	 *
	 * @since 1.0
	 * @return array
	 */
	public function get_users() {

		$this->set_endpoint( $this->users_uri );

		// this doesn't support more than 9999 users, but Litmos doesn't seem to have a way to get that many users anyway
		// see https://litmos.zendesk.com/entries/20957932-Users-Get-a-list-of-users-Create-Edit-users
		$this->request_params = array( 'limit' => 9999 );

		$this->http_request( 'GET' );

		$this->parse_response();

		return $this->response;
	}


	/**
	 * Search for a user by specified search string
	 *
	 * GET /users
	 *
	 * @since 1.4.1
	 * @param $search string the string to search for, usually a username or email address
	 * @return array
	 */
	public function search_users( $search ) {

		$this->set_endpoint( $this->users_uri );

		$this->request_params = array( 'search' => $search, 'limit' => 1 );

		$this->http_request( 'GET' );

		$this->parse_response();

		return $this->response;
	}


	/**
	 * Get single user info
	 *
	 * GET /users/{userid}
	 *
	 * @since 1.0
	 * @param string $litmos_user_id
	 * @throws Exception if User ID is blank
	 * @return array|string
	 */
	public function get_user( $litmos_user_id ) {

		if ( ! $litmos_user_id ) {
			throw new Exception( __( 'Get User: User ID is blank', 'woocommerce-litmos' ) );
		}

		$this->set_endpoint( $this->users_uri . '/' . $litmos_user_id );

		$this->http_request( 'GET' );

		$this->parse_response();

		return $this->response;
	}


	/**
	 * Get single-sign-on link for user
	 *
	 * GET /users/{userid}
	 *
	 * @since 1.0
	 * @param string $user_id
	 * @throws Exception
	 * @return string
	 */
	public function get_user_sso_link( $user_id ) {

		if ( ! $user_id ) {
			throw new Exception( __( 'SSO: User ID is blank', 'woocommerce-litmos' ) );
		}

		$this->set_endpoint( $this->users_uri . '/' . $user_id );

		$this->http_request( 'GET' );

		$this->parse_response();

		if ( isset( $this->response['LoginKey'] ) && ! empty( $this->response['LoginKey'] ) ) {

			return $this->response['LoginKey'];

		} else {

			return( '#' );
		}
	}


	/**
	 * Get user id by username
	 *
	 * @since 1.4.1
	 * @param string $username_or_email litmos username or email address
	 * @return mixed litmos user id / false if username not found
	 */
	public function get_user_id( $username_or_email ) {

		foreach ( $this->search_users( $username_or_email ) as $user ) {

			if ( $user['UserName'] == $username_or_email || $user['Email'] == $username_or_email ) {
				return $user['Id'];
			}
		}

		return false;
	}


	/**
	 * Create single user
	 *
	 * PUT /users/{userid}
	 *
	 * @since 1.0
	 * @param array $user associative array of properties to create for user. Required: UserName, FirstName, LastName, DisableMessages, SkipFirstLogin
	 * @throws Exception if required fields are blank
	 * @return array
	 */
	public function create_user( $user ) {

		if( ! $user['UserName'] || ! $user['FirstName'] || ! $user['LastName'] || ! isset( $user['DisableMessages'] ) || ! isset( $user['SkipFirstLogin'] ) ) {
			throw new Exception( __( 'Create User: Required field is blank', 'woocommerce-litmos' ) );
		}

		$this->encode( $user );

		$this->set_endpoint( $this->users_uri );

		$this->http_request( 'POST' );

		$this->parse_response();

		return $this->response;
	}


	/**
	 * Update single user
	 *
	 * PUT /users/{userid}
	 *
	 * @since 1.0
	 * @param array $user associative array of user properties to update. Required: Id, UserName, FirstName, LastName, Active
	 * @throws Exception if required fields are blank
	 * @return array
	 */
	public function update_user( $user ) {

		if( ! $user['Id'] || ! $user['UserName'] || ! $user['FirstName'] || ! $user['LastName'] || ! isset( $user['Active'] ) ) {
			throw new Exception( __( 'Update User: Required field is blank', 'woocommerce-litmos' ) );
		}

		$this->encode( $user );

		$this->set_endpoint( $this->users_uri . '/' . $user['Id'] );

		$this->http_request( 'PUT' );

		$this->parse_response();

		return $this->response;
	}


	/**
	 * Get Courses assigned to single user
	 *
	 * GET /users/{userid}/courses
	 *
	 * @since 1.0
	 * @param string $user_id litmos user ID
	 * @throws Exception if required fields are blank
	 * @return array
	 */
	public function get_courses_assigned_to_user( $user_id ) {

		if( ! $user_id ) {
			throw new Exception( __( 'Get Courses Assigned to User: User ID is blank', 'woocommerce-litmos' ) );
		}

		$this->set_endpoint( $this->users_uri . '/' . $user_id . '/courses');

		$this->http_request( 'GET' );

		$this->parse_response();

		return $this->response;
	}


	/** Course Functions */

	/**
	 * Get listing of courses
	 *
	 * GET /courses
	 *
	 * @since 1.0
	 * @return array
	 */
	public function get_courses() {

		$this->set_endpoint( $this->courses_uri );

		// The Litmos API defaults to 100 courses per request, so we need to max this out for folks with more courses
		$this->request_params = array(
			'limit' => 1000,
		);

		$this->http_request( 'GET' );

		$this->parse_response();

		return $this->response;
	}

	/**
	 * Get course by it's ID
	 *
	 * GET /courses/{$id}
	 *
	 * @since 1.0
	 * @param string $id course ID
	 * @return array
	 */
	public function get_course_by_id( $id ) {

		$this->set_endpoint( $this->courses_uri . '/' . $id );

		$this->http_request( 'GET' );

		$this->parse_response();

		return $this->response;
	}

	/**
	 * Assign courses to a single user
	 *
	 * POST /users/{user-id}/courses
	 *
	 * @since 1.0
	 * @param string $user_id litmos user ID
	 * @param array $course_ids simple array of course IDs to assign to user
	 * @throws Exception if required fields are blank
	 * @return array
	 */
	public function assign_courses_to_user( $user_id, $course_ids ) {

		if ( ! $user_id || empty( $course_ids ) ) {
			throw new Exception( __( 'Assign Course: User ID or Course is blank', 'woocommerce-litmos' ) );
		}

		$user_courses_endpoint = $this->users_uri . '/' . $user_id . '/courses';

		if ( 'yes' == get_option( 'wc_litmos_disable_messages' ) ) {

			// Disable sending course invitation emails - note that this will disable the inital invitation for _new_ users too @TZ 2015-12-10
			// @link https://litmos.zendesk.com/entries/20942916-Users-Get-assigned-courses-with-results-Add-Remove-Courses
			$this->request_params = array( 'sendmessage' => 'false' );
		}

		$this->set_endpoint( $user_courses_endpoint );

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

		$this->http_request( 'POST' );

		$this->parse_response();

		return $this->response;
	}

	/**
	 * Reset the course results for a single user
	 *
	 * PUT /users/{userid}/courses{$courseid}/reset
	 *
	 * @since 1.0
	 * @param string $user_id litmos user ID
	 * @param array $course_id litmos course ID to reset
	 * @throws Exception if required fields are blank
	 * @return array
	 */
	public function reset_course_results( $user_id, $course_id ) {

		if( ! $user_id || empty( $course_id ) ) {
			throw new Exception( __( 'Reset Course Results: User ID or Course is blank', 'woocommerce-litmos' ) );
		}

		$this->set_endpoint( "{$this->users_uri}/{$user_id}/courses/{$course_id}/reset" );

		$this->http_request( 'PUT' );

		$this->parse_response();

		return $this->response;
	}


	/** Team Functions */

	/**
	 * Create a team
	 *
	 * POST /teams
	 *
	 * @since 1.0
	 * @param array $team associative array of team info to create, required: Name, optional: Description
	 * @throws Exception if required fields are blank
	 * @return array
	 */
	public function create_team( $team ) {

		if( empty( $team ) || ! isset( $team['Name'] ) || ! isset( $team['Description'] ) ) {
			throw new Exception( __( 'Create Team: Name or Description is blank', 'woocommerce-litmos' ) );
		}

		$this->set_endpoint( $this->teams_uri );

		$this->encode( $team );

		$this->http_request( 'POST' );

		$this->parse_response();

		return $this->response;
	}


	/**
	 * Assign multiple users to a team
	 *
	 * POST /teams/{team-id}/users
	 *
	 * @since 1.0
	 * @param string $team_id team ID to assign users to
	 * @param array $user_ids simple array of users IDs to assign to team
	 * @throws Exception if required fields are blank
	 * @return array
	 */
	public function assign_users_to_team( $team_id, $user_ids ) {

		if( ! $team_id || empty( $user_ids ) ) {
			throw new Exception( __( 'Assign Users to Team: Team ID or User IDs are blank', 'woocommerce-litmos' ) );
		}

		$this->set_endpoint( $this->teams_uri . '/' . $team_id . '/users' );

		// awful hack because Litmos' API is finicky with JSON
		$xml = '<Users>';
		foreach( $user_ids as $user_id ) :
			$xml .= '<User>';
			$xml .= '<Id>' . $user_id . '</Id>';
			$xml .= '</User>';
		endforeach;
		$xml .= '</Users>';

		$this->wp_remote_http_args['body'] = $xml;
		$this->wp_remote_http_args['headers']['Accept'] = 'application/xml';
		$this->wp_remote_http_args['headers']['Content-Type'] = 'application/xml';

		$this->http_request( 'POST' );

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
				throw new Exception( sprintf( __( 'Error Code: %1$s | Error Message: %2$s', 'woocommerce-litmos' ), $this->response['response']['code'], strip_tags( $this->response['body'] ) ) );
			}

		} else {

			throw new Exception( __( 'Response HTTP Code Not Set', 'woocommerce-litmos' ) );
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
			throw new Exception( __( 'JSON Encode : Passed Data is not array', 'woocommerce-litmos' ) );
		}

		$this->wp_remote_http_args['body'] = json_encode( $data );
	}


	/**
	 * Set Endpoint URI
	 *
	 * @since 1.0
	 * @param string $uri API Type URI (users, courses, etc)
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
	 * @return array
	 */
	private function http_request( $method ) {

		// Check for blank endpoint or method
		if ( ! $this->endpoint || ! $method ) {
			throw new Exception( __( 'Endpoint and / or HTTP Method is blank.', 'woocommerce-litmos' ) );
		}

		// Check that method is a valid http method
		if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'DELETE' ) ) ) {
			throw new Exception( __( 'Requested HTTP Method is invalid.', 'woocommerce-litmos' ) );
		}

		// set the method
		$this->wp_remote_http_args['method'] = $method;

		// clear the request body if this is a GET request
		// WP will throw a warning if GET request bodies contain anything but an array
		if ( 'GET' === $method && ! is_array( $this->wp_remote_http_args['body'] ) ) {
			$this->wp_remote_http_args['body'] = '';
		}

		// perform HTTP request with endpoint / args
		$this->response = wp_safe_remote_request( $this->endpoint . '?' . http_build_query( array_merge( $this->request_params, $this->auth_params ) ), $this->wp_remote_http_args, '&' );

		// WP HTTP API error like Network timeout, etc.
		if ( is_wp_error( $this->response ) ) {
			throw new Exception( $this->response->get_error_message() );
		}

		// Check for proper response / body
		if ( ! isset( $this->response['response'] ) ) {
			throw new Exception( __( 'Empty Response', 'woocommerce-litmos' ) );
		}

		if ( ! isset( $this->response['body'] ) ) {
			throw new Exception( __( 'Empty Body', 'woocommerce-litmos' ) );
		}
	}


}
