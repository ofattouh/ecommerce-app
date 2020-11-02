<!DOCTYPE HTML>

<html>

	<!--
		Sign Up Form:
		This example flow illustrates how a Constant Contact account owner can add or update a contact in their account. 
		You will need an API Key and an access token which you can obtain from: http://constantcontact.mashery.com
	-->

<head>
    <title>PSHSA Sign Up Form</title>
    <link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.1/css/bootstrap-combined.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>


<?php
// require the autoloaders
require_once '../src/Ctct/autoload.php';
require_once '../vendor/autoload.php';

use Ctct\Components\Contacts\Contact;
use Ctct\ConstantContact;
use Ctct\Exceptions\CtctException;

// Enter your Constant Contact APIKEY and ACCESS_TOKEN
define("APIKEY", "cf3zr46nqxn2bfgmr8rr8n67");
define("ACCESS_TOKEN", "d306bc5f-9710-48db-a21e-03fc1c86c49b");

$cc = new ConstantContact(APIKEY);

// attempt to fetch lists in the account, catching any exceptions and printing the errors to screen
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

// check if the form was submitted
if (isset($_POST['email']) && strlen($_POST['email']) > 1) {
    $action = "Getting Contact By Email Address";
    try {
        // check to see if a contact with the email address already exists in the account
        $response = $cc->contactService->getContacts(ACCESS_TOKEN, array("email" => $_POST['email']));

        // create a new contact if one does not exist
        if (empty($response->results)) {
            $action = "Creating Contact";

            $contact = new Contact();
            $contact->addEmail($_POST['email']);
            $contact->addList($_POST['list']);
            $contact->first_name = $_POST['first_name'];
            $contact->last_name = $_POST['last_name'];

            /*
             * The third parameter of addContact defaults to false, but if this were set to true it would tell Constant
             * Contact that this action is being performed by the contact themselves, and gives the ability to
             * opt contacts back in and trigger Welcome/Change-of-interest emails.
             *
             * See: http://developer.constantcontact.com/docs/contacts-api/contacts-index.html#opt_in
             */
            $returnContact = $cc->contactService->addContact(ACCESS_TOKEN, $contact, true);

            // update the existing contact if address already existed
        } else {
            $action = "Updating Contact";

            $contact = $response->results[0];
            if ($contact instanceof Contact) {
                $contact->addList($_POST['list']);
                $contact->first_name = $_POST['first_name'];
                $contact->last_name = $_POST['last_name'];

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

        // catch any exceptions thrown during the process and print the errors to screen
    } catch (CtctException $ex) {
        echo '<span class="label label-important">Error ' . $action . '</span>';
        echo '<div class="container alert-error"><pre class="failure-pre">';
        print_r($ex->getErrors());
        echo '</pre></div>';
        die();
    }
}
else{
?>

	<body>
	<div class="well">
	    <h3>PSHSA Sign Up Form</h3>
	
	    <form class="form-horizontal" name="submitContact" id="submitContact" method="POST" action="signUpForm.php">
	        <div class="control-group">
	            <label class="control-label" for="email">Email</label>
	
	            <div class="controls">
	                <input type="email" id="email" name="email" placeholder="Email Address">
	            </div>
	        </div>
	        <div class="control-group">
	            <label class="control-label" for="first_name">First Name</label>
	
	            <div class="controls">
	                <input type="text" id="first_name" name="first_name" placeholder="First Name">
	            </div>
	        </div>
	        <div class="control-group">
	            <label class="control-label" for="last_name">Last Name</label>
	
	            <div class="controls">
	                <input type="text" id="last_name" name="last_name" placeholder="Last Name">
	            </div>
	        </div>
	        <div class="control-group">
	            <label class="control-label" for="list">List</label>
	
	            <div class="controls">
	                <select name="list">
	                    <?php
	                    foreach ($lists as $list) {
	                        echo '<option value="' . $list->id . '">' . $list->name . '</option>';
	                    }
	                    ?>
	                </select>
	            </div>
	        </div>
	        <div class="control-group">
	            <label class="control-label">
	                <div class="controls">
	                    <input type="submit" value="Submit" class="btn btn-primary"/>
	                </div>
	        </div>
	    </form>
	</div>

	</body>
</html>

<?php 
	} 
?>


<!-- Success Message -->
<?php if (isset($returnContact)):

	//print_r($returnContact); 
	//require 'download.php';
	
?>
	
    <div class="container alert-success">
	    <pre class="success-pre">
	    	<p>Thank you for submiting the Sign-Up form.</p>
	    	<p>You can download this file <a href='testPDF.pdf' target='blank'>here</a></p>
	    	<p>You can download this file <a href='testWord.doc' target='blank'>here</a></p>
		</pre>
	</div>
    
<?php endif; ?>


