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
 * @package     WC-Litmos/Admin
 * @author      SkyVerge
 * @copyright   Copyright (c) 2013-2017, SkyVerge, Inc.
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

defined( 'ABSPATH' ) or exit;

/**
 * Litmos Admin class
 *
 * Loads admin settings page and adds related hooks / filters
 *
 * @since 1.0
 */
class WC_Litmos_Admin {


	/** @var string sub-menu page hook suffix */
	private $settings_tab_id = 'litmos';


	/**
	 * Setup admin class
	 *
	 * @since 1.0
	 * @return \WC_Litmos_Admin
	 */
	public function __construct() {

		// add 'Litmos' tab to WC settings
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 100 );

		// show settings
		add_action( 'woocommerce_settings_tabs_' . $this->settings_tab_id, array( $this, 'render_settings' ) );

		// save settings
		add_action( 'woocommerce_update_options_' . $this->settings_tab_id, array( $this, 'save_settings' ) );

		// show litmos user ID field on edit user pages
		add_action( 'show_user_profile', array( $this, 'render_litmos_user_id_meta_field' ) );
		add_action( 'edit_user_profile', array( $this, 'render_litmos_user_id_meta_field' ) );

		// save litmos user ID field
		add_action( 'personal_options_update',  array( $this, 'save_litmos_user_id_meta_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_litmos_user_id_meta_field' ) );

		// add litmos course ID select for simple products
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_simple_product_course_selection' ) );

		// save litmos course ID for products of all product types
		add_action( 'woocommerce_process_product_meta', array( $this, 'process_simple_product_course_selection' ) );

		// add litmos course ID select for variation
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_variable_product_course_selection' ), 1, 3 );

		// save litmos course ID for individual variation
		add_action( 'woocommerce_save_product_variation', array( $this, 'process_variable_product_course_selection' ) );

		// Add Javascript to bulk change variation courses.
		add_action( 'woocommerce_product_data_panels', array( $this, 'add_variable_product_course_selection_js' ), 99 );

		// Add 'Create Litmos Account' order meta box order action
		add_action( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_actions' ) );

		// Process 'Create Litmos Account' order meta box order action
		add_action( 'woocommerce_order_action_wc_litmos_export', array( $this, 'process_order_meta_box_actions' ) );
	}


	/**
	 * Add tab to WooCommerce Settings tabs
	 *
	 * @since 1.0
	 * @param array $settings_tabs tabs array sans 'Litmos' tab
	 * @return array $settings_tabs now with 100% more 'Litmos' tab!
	 */
	public function add_settings_tab( $settings_tabs ) {

		$settings_tabs[ $this->settings_tab_id ] = __( 'Litmos', 'woocommerce-litmos' );

		return $settings_tabs;
	}


	/**
	 * Render the 'Litmos' settings page
	 *
	 * @since 1.0
	 */
	public function render_settings() {

		woocommerce_admin_fields( $this->get_settings() );
	}


	/**
	 * Save the 'Litmos' settings page
	 *
	 * @since 1.0
	 */
	public function save_settings() {

		woocommerce_update_options( $this->get_settings() );
	}


	/**
	 * Display a field for the Litmos user ID meta on the view/edit user page
	 *
	 * @since 1.1
	 * @param WP_User $user user object for the current edit page
	 */
	public function render_litmos_user_id_meta_field( $user ) {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		?>
		<h3><?php _e( 'Litmos User Details', 'woocommerce-litmos' ) ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="_wc_litmos_customer_id"><?php esc_html_e( 'User ID', 'woocommerce-litmos' ); ?></label></th>
				<td>
					<input type="text" name="_wc_litmos_user_id" id="_wc_litmos_user_id" value="<?php echo esc_attr( get_user_meta( $user->ID, '_wc_litmos_user_id', true ) ); ?>" class="regular-text" /><br/>
					<span class="description"><?php esc_html_e( 'The Litmos User ID for the user. Only edit this if necessary.', 'woocommerce-litmos' ); ?></span>
				</td>
			</tr>
		</table>
		<?php
	}


	/**
	 * Save the Litmos User ID meta field on the view/edit user page
	 *
	 * @since 1.1
	 * @param int $user_id identifies the user to save the settings for
	 */
	public function save_litmos_user_id_meta_field( $user_id ) {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! empty( $_POST['_wc_litmos_user_id'] ) ) {
			update_user_meta( $user_id, '_wc_litmos_user_id', trim( $_POST['_wc_litmos_user_id'] ) );
		} else {
			delete_user_meta( $user_id, '_wc_litmos_user_id' );
		}
	}


	/**
	 * Add 'Export to Litmos' link to order actions select box on edit order page
	 *
	 * @since 1.2
	 * @param array $actions order actions array to display
	 * @return array
	 */
	public function add_order_meta_box_actions( $actions ) {

		// add download to CSV action
		$actions['wc_litmos_export'] = __( 'Export to Litmos', 'woocommerce-litmos' );

		return $actions;
	}


	/**
	 * Process the 'Export to Litmos' link in order actions select box on edit order page by
	 * creating a new learner account & assign the purchased course to the user
	 *
	 * @since 1.2
	 * @param WC_Order $order
	 */
	public function process_order_meta_box_actions( $order ) {

		$handler = new WC_Litmos_Handler( $order->id );

		$handler->export();
	}


	/**
	 * Add 'Litmos Course Code' select box on 'General' tab of simple product write-panel
	 *
	 * {BR 2017-02-22} We may want to move the post / ID references here to use the product object in the future.
	 *
	 * @since 1.0
	 */
	public function add_simple_product_course_selection() {
		global $post;

		$course_id = get_post_meta( $post->ID, '_wc_litmos_course_id', true );
		?>
		<div class="options_group litmos show_if_simple show_if_external">
			<p class="form-field _wc_litmos_course_id_field">
				<label for="_wc_litmos_course_id"><?php esc_html_e( 'Litmos Course Code', 'woocommerce-litmos' ); ?></label>
				<select id="_wc_litmos_course_id" name="_wc_litmos_course_id" style="min-width: 250px; max-width: 300px;">
					<option value="none"></option>
					<?php foreach ( $this->get_courses_for_select_input() as $value => $label ) : ?>
						<?php printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $value, $course_id, false ), esc_html__( $label ) ); ?>
					<?php endforeach; ?>
				</select>
				<?php echo wc_help_tip( __( 'Select the Litmos Course that this product is linked to.', 'woocommerce-litmos' ) ) ; ?>
			</p>
		</div>
		<?php
	}


	/**
	 * Saves 'Litmos Course Code' select box on 'General' tab of simple product write-panel
	 *
	 * @since 1.0
	 * @param int $post_id post ID of product being saved
	 */
	public function process_simple_product_course_selection( $post_id ) {

		if ( isset( $_POST[ '_wc_litmos_course_id' ] ) && 'none' != $_POST[ '_wc_litmos_course_id' ] ) {

			update_post_meta( $post_id, '_wc_litmos_course_id', $_POST[ '_wc_litmos_course_id' ] );
		}
	}


	/**
	 * Add 'Litmos Course Code' select box on 'Variations' tab of variable product write-panel
	 *
	 * @since 1.0
	 * @param int $loop_count current variation count
	 * @param array $variation_data individual variation data
	 * @param WP_Post $variation Product Variation object
	 */
	public function add_variable_product_course_selection( $loop_count, $variation_data, $variation ) {

		// add meta data to the $variation_data array
		$variation_data = array_merge( get_post_meta( $variation->ID ), $variation_data );

		$course_id = ( isset( $variation_data[ '_wc_litmos_course_id' ][0] ) ? $variation_data[ '_wc_litmos_course_id' ][0] : '' );
		?>
		<div>
			<p class="form-row form-row-first">
				<label for="<?php echo 'wc_litmos_select_' . $loop_count; ?>"><?php esc_html_e( 'Litmos Course Code', 'woocommerce-litmos' ); ?>
					<a class="tips" data-tip="<?php esc_attr_e( 'Select the Litmos Course that this product is linked to.', 'woocommerce-litmos' ); ?>" href="#">[?]</a>
				</label>
				<select id="<?php echo 'wc_litmos_select_' . $loop_count; ?>" name="<?php printf( '%s[%s]', 'variable_wc_litmos_course_id', $loop_count ); ?>" class="wc_litmos_course_select" style="min-width: 250px;">
					<option value="none"></option>
					<?php foreach( $this->get_courses_for_select_input() as $value => $label ) : ?>
						<?php printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $value, $course_id, false ), esc_html__( $label ) ); ?>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="form-row form-row-last" style="margin-top: 2.8em;"><a id="<?php echo 'wc_litmos_select_' . $loop_count; ?>" class="wc_litmos_bulk_set_courses" href="#"><?php esc_html_e( 'Set all other variations to this course', 'woocommerce-litmos' ); ?></a></p>
		</div>
		<?php
	}


	/**
	 * Add 'Set all other variations to this course' javascript
	 *
	 * @since 1.0
	 */
	public function add_variable_product_course_selection_js() {

		wc_enqueue_js(
		  '$( "#woocommerce-product-data" ).on( "click", "a.wc_litmos_bulk_set_courses", function() {
			var selector = $( this ).attr( "id" );
			$( ".wc_litmos_course_select" ).val( $( "#" + selector ).val() );
			return false;
		} );' );
	}


	/**
	 * Save courses assigned to product variations
	 *
	 * @since 1.0
	 * @param int $variation_id Product Variation ID
	 */
	public function process_variable_product_course_selection( $variation_id ) {

		// find the index for the given variation ID and save the associated cost
		if ( false !== ( $i = array_search( $variation_id, $_POST['variable_post_id'] ) ) ) {

			if ( 'none' !== $_POST['variable_wc_litmos_course_id'][ $i ] ) {

				update_post_meta( $variation_id, '_wc_litmos_course_id', $_POST['variable_wc_litmos_course_id'][ $i ] );
			}
		}
	}


	/**
	 * Create array of courses in format required for select input box display
	 *
	 * @since 1.0
	 * @return array associative array in format key = Course ID, value = Course Code - Course Name
	 */
	private function get_courses_for_select_input() {

		// check if course transient exists
		if ( false === ( $select_options = get_transient( 'wc_litmos_courses' ) ) ) {

			// try to fetch fresh course list from API
			try {
				$courses = wc_litmos()->get_api()->get_courses();
			}

			// log any errors
			catch( Exception $e ) {

				wc_litmos()->log( $e->getMessage() );

				// return a blank array so select box is valid
				return array();
			}

			// build course list array for use in select input box
			foreach ( $courses as $course ) {

				$select_options[ $course['Id'] ] = sprintf( '%s - %s', $course['Code'], $course['Name'] );
			}

			// set 15 minute transient
			set_transient( 'wc_litmos_courses', $select_options, 60*15 );
		}

		return $select_options;
	}


	/**
	 * Returns settings array for use by render/save/install default settings methods
	 *
	 * @since 1.0
	 * @return array settings
	 */
	public static function get_settings() {

		return array(

			array(
				'name' => __( 'Settings', 'woocommerce-litmos' ),
				'type' => 'title'
			),

			array(
				'id'       => 'wc_litmos_api_key',
				'name'     => __( 'API Key', 'woocommerce-litmos' ),
				'desc_tip' => __( 'Log into your Litmos account to find your API key.', 'woocommerce-litmos' ),
				'type'     => 'text',
				'class'    => 'regular-text',
			),


			array(
				'id'      => 'wc_litmos_auto_create_accounts',
				'name'    => __( 'Automatically Create Litmos Accounts', 'woocommerce-litmos' ),
				'desc'    => __( 'Enable this automatically create Litmos accounts after order payment.', 'woocommerce-litmos' ),
				'default' => 'yes',
				'type'    => 'checkbox'
			),

			array(
				'id'      => 'wc_litmos_disable_messages',
				'name'    => __( 'Disable Messages', 'woocommerce-litmos' ),
				'desc'    => __( 'Enable this to disable messages and welcome emails to new users.', 'woocommerce-litmos' ),
				'default' => 'no',
				'type'    => 'checkbox'
			),

			array(
				'id'      => 'wc_litmos_skip_first_login',
				'name'    => __( 'Skip First Login', 'woocommerce-litmos' ),
				'desc'    => __( 'Enable this allow new users to skip the first login page, where they can set their password and fill out additional information.', 'woocommerce-litmos' ),
				'default' => 'no',
				'type'    => 'checkbox'
			),

			array(
				'id'      => 'wc_litmos_reset_course_duplicate_purchase',
				'name'    => __( 'Reset Course Results for Duplicate Purchases', 'woocommerce-litmos' ),
				'desc'    => __( 'Enable this to reset the course results for a user when they purchase the same course more than once.', 'woocommerce-litmos' ),
				'default' => 'yes',
				'type'    => 'checkbox'
			),

			array( 'type' => 'sectionend' ),
		);
	}


}
