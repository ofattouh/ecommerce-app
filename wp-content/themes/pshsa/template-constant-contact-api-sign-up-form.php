<?php
/* Template Name: Constant Contact API - Sign Up Form */

/** 
 * API php-sdk @ https://github.com/constantcontact/php-sdk
 * This template uses a Constant Contact owner account to add or update a contact to their account. 
 * An API Key and an access token can be obtained from: http://constantcontact.mashery.com
 * @package presscore
 * @since presscore 0.1
 */

// File Security Check
if ( ! defined( 'ABSPATH' ) ) { exit; }

$APIKEY       = 'cf3zr46nqxn2bfgmr8rr8n67';
$ACCESS_TOKEN = 'd306bc5f-9710-48db-a21e-03fc1c86c49b';

define( 'APIKEY', $APIKEY );
define( 'ACCESS_TOKEN', $ACCESS_TOKEN );
define( 'SITEHOMEDIR', dirname(dirname(dirname(dirname(__FILE__)))) ); 

// require the autoloaders
require_once SITEHOMEDIR.'/constant-contact-api/src/Ctct/autoload.php';
require_once SITEHOMEDIR.'/constant-contact-api/vendor/autoload.php';

use Ctct\Components\Contacts\Contact;
use Ctct\ConstantContact;
use Ctct\Exceptions\CtctException;

$cc = new ConstantContact(APIKEY);

/* Attempt to fetch lists in the account, catching any exceptions and printing the errors to screen
try {
    $lists = $cc->listService->getLists(ACCESS_TOKEN);
} catch (CtctException $ex) {
    foreach ($ex->getErrors() as $error) {
        print_r($error);
    }
    if (!isset($lists)) {
        $lists = null;
    }
}

foreach ($lists as $list) {
    echo '<br>'.$list->id . '>' . $list->name;
}
*/


// Check if the form was submitted
if ( isset($_POST['signupccapi_submitted']) ) {	
    $action = "Getting Contact By Email Address";
    
    try {
        // check to see if a contact with the email address already exists in the account
        $response = $cc->contactService->getContacts(ACCESS_TOKEN, array("email" => $_POST['signupccapi_email']));

        // create a new contact if one does not exist
        if (empty($response->results)) {
            $action = "Creating Contact";

            $contact = new Contact();
            $contact->addEmail($_POST['signupccapi_email']);
            
            // Add the contact to the List
            if (isset($_POST['signupccapi_list'])) {
		        if (count($_POST['signupccapi_list']) > 1) {
		            foreach ($_POST['signupccapi_list'] as $list) {
		                $contact->addList($list);
		            }
		        } 
		        else {
		            $contact->addList($_POST['signupccapi_list'][0]);
		        }
		    }
			    
            $contact->first_name   = $_POST['signupccapi_first_name'];
            $contact->last_name    = $_POST['signupccapi_last_name'];
            $contact->company_name = $_POST['signupccapi_company_name'];

            /*
             * The third parameter of addContact defaults to false, but if this were set to true it would tell Constant
             * Contact that this action is being performed by the contact themselves, and gives the ability to
             * opt contacts back in and trigger Welcome/Change-of-interest emails.
             *
             * See: http://developer.constantcontact.com/docs/contacts-api/contacts-index.html#opt_in
             */
            $returnContact = $cc->contactService->addContact(ACCESS_TOKEN, $contact, true);

        } 
        // update the existing contact if address already existed
        else {
            $action = "Updating Contact";

            $contact = $response->results[0];
            if ($contact instanceof Contact) {
	            
				// Update the contact in the List
			    if (isset($_POST['signupccapi_list'])) {
			        if (count($_POST['signupccapi_list']) > 1) {
			            foreach ($_POST['signupccapi_list'] as $list) {
			                $contact->addList($list);
			            }
			        } 
			        else {
			            $contact->addList($_POST['signupccapi_list'][0]);
			        }
			    }
    
                $contact->first_name   = $_POST['signupccapi_first_name'];
                $contact->last_name    = $_POST['signupccapi_last_name'];
				$contact->company_name = $_POST['signupccapi_company_name'];
				
                /*
                 * The third parameter of updateContact defaults to false, but if this were set to true it would tell
                 * Constant Contact that this action is being performed by the contact themselves, and gives the ability to
                 * opt contacts back in and trigger Welcome/Change-of-interest emails.
                 *
                 * See: http://developer.constantcontact.com/docs/contacts-api/contacts-index.html#opt_in
                 */
                $returnContact = $cc->contactService->updateContact(ACCESS_TOKEN, $contact, true);
            } else {
                $e = new CtctException();
                $e->setErrors(array("type", "Contact type not returned"));
                throw $e;
            }
        }
	}
    // Catch any exceptions thrown during the process and print the errors to screen 
    catch (CtctException $ex) {
        print_r($ex->getErrors());
        die();
    }
}

?>



<?php

// Word Press page template code 

$config = Presscore_Config::get_instance();
$config->set('template', 'page');
$config->base_init();

get_header(); ?>

		<?php if ( presscore_is_content_visible() ): ?>	

			<div id="content" class="content" role="main">

			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

				<?php do_action('presscore_before_loop'); ?>

				<!-- Success Message -->
				<?php if (isset($returnContact)): 
				
//  				print_r($returnContact);
//  				print_r($_POST);

					$confirmation_page = get_page_by_path( 'health-and-safety-trends-report-confirmation-page', OBJECT, 'page' ); 
					echo $confirmation_page->post_content;
						   					
				endif;
				
				if ( ! isset($_POST['signupccapi_submitted']) ): ?>
					<form class="form-horizontal" name="signupccapi_form" id="signupccapi_form" method="POST" action="<?php the_permalink(); ?>">
						<?php the_content(); ?>
					</form>	
				<?php endif; ?>	
					
				<?php presscore_display_share_buttons( 'page' ); ?>

				<?php comments_template( '', true ); ?>

			<?php endwhile; ?>

			<?php else : ?>
				<?php get_template_part( 'no-results', 'page' ); ?>
			<?php endif; ?>

			</div><!-- #content -->

			<?php do_action('presscore_after_content'); ?>

		<?php endif; // if content visible ?>

<?php get_footer(); ?>