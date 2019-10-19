<?php
/*
 * Plugin Name: WooCommerce PalPay Payment Gateway
 * Plugin URI: https://zeiadh.com/woocommerce/palpay-gateway-plugin/
 * Description: Take credit card payments on your store for PalPay.
 * Author: Zeiad Habbab
 * Author URI: https://zeiadh.com/
 * Version: 1.0.1
 *

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */

define("BASELINK",get_site_url());
define("MERCHANTID","709903264");
define("ACQUIRERID","000089");
define("PALPAYLINK","https://e-commerce.bop.ps/EcomPayment/RedirectAuthLink");
define("PALPAYPASS","RF7b74pk");
define("CURRENCY","376"); // ILS > 376  USD > 840
define("ENC",201215);


add_filter( 'woocommerce_payment_gateways', 'misha_add_gateway_class' );
function misha_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Misha_Gateway'; // your class name is here
	return $gateways;
}


function install_my_plugin() {
    my_plugin_endpoint();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'install_my_plugin' );

/**
* Flush rewrite rules
*/
function unistall_my_plugin() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'unistall_my_plugin' );

/**
* Add the endpoint
*/
function my_plugin_endpoint() {
    add_rewrite_endpoint( 'palpayorder', EP_ROOT );
}
add_action( 'init', 'my_plugin_endpoint' );

// Inicia session
function start_session() {
    if( !session_id() ) {
        session_start();
    }
}
add_action('init', 'start_session', 1);


function wdm_my_custom_message( $order_id ){
	global $wp_session;
	//$wp_session = WP_Session::get_instance();

	global $woocommerce;
	global $error;
	$order_id =  wc_get_order_id_by_order_key($_GET['key']);
	$order = new WC_Order( $order_id );
	//if(!$wp_session['fa'.$order_id]){
	//	$wp_session['fa'.$order_id] = 0;
	//}
    if ($order->get_status() == "failed" ) {
		//$wp_session['fa'.$order_id] = $wp_session['fa'.$order_id]++ ;
		//wc_add_notice(  'معلومات البطاقة غير صحيحة, أولايوجد رصيد في بطاقتك', 'error' );
		$order->update_status( 'pending' );
		echo '<div class="woocommerce-notices-wrapper">
				<ul class="woocommerce-error" role="alert">
					<li>معلومات البطاقة غير صحيحة, أولايوجد رصيد في بطاقتك</li>
				</ul>
			</div>';
			//echo $wp_session['fa'.$order_id];
			//if($wp_session['fa'.$order_id]>2){
			//	if ( wp_redirect( "https://www.bestdeal.ps/" ) ) {
			//		exit;
			//	}
			//}
	} 
	
 
}

//add_action( 'woocommerce_review_order_before_cart_contents','wdm_my_custom_message',10,1);
add_action( 'woocommerce_pay_order_before_submit','wdm_my_custom_message',10,1);


function get_private_order_notes( $order_id){
    global $wpdb;

    $table_perfixed = $wpdb->prefix . 'comments';
    $results = $wpdb->get_results("
        SELECT *
        FROM $table_perfixed
        WHERE  `comment_post_ID` = $order_id
        AND  `comment_type` LIKE  'order_note'
    ");

    foreach($results as $note){
        $order_note[]  = array(
            'note_id'      => $note->comment_ID,
            'note_date'    => $note->comment_date,
            'note_author'  => $note->comment_author,
            'note_content' => $note->comment_content,
        );
    }
    return $order_note;
}

function my_plugin_proxy_function( $query ) { 
 $action = $query->get('palpayorder');
	
  if ( $query->is_main_query() ) {
    // this is for security!
   
	if( strrpos($action,"pporder") === false ){
		
	}else{
		return call_user_func('redirect_to_palpay', $action); 
	}
	
	if( strrpos($action,"orderbackpal") === false ){
		
	}else{
		 
	
		return call_user_func('palpay_done', $action); 
	}
		
  }
}
add_action( 'pre_get_posts', 'my_plugin_proxy_function' );

function palpay_done($action){
	
	
	if(isset($_POST['MerID'])){
	
	
		$MerID = $_POST['MerID'];
		$AcqID = $_POST['AcqID'];
		$orderID = $_POST['OrderID'];
		$order = wc_get_order($orderID);	
		
		$ResponseCode = intval($_POST['ResponseCode']);
		$ReasonCode = intval($_POST['ReasonCode']);
		$ReasonDescr = $_POST['ReasonCodeDesc'];
		$Ref = $_POST['ReferenceNo'];
		$PaddedCardNo = $_POST['PaddedCardNo'];
		$Signature = $_POST['Signature'];
		//Authorization code is only returned in case of successful transaction,indicated with a value of 1
		//for both response code and reason code
		if($ResponseCode==1 && $ReasonCode==1)
		{
			$AuthNo = $_POST['AuthCode'];
		}
		
		
		
		$password = PALPAYPASS;
		$merchantID = MERCHANTID;
		$acquirerID = ACQUIRERID;
		//Form the plaintext string that used to product the hash it sent byconcatenatingPassword, Merchant ID, Acquirer ID and Order ID
		//This will give: 1234abcd | 0011223344 | 402971 | TestOrder12345 (spaces and |introduced here for clarity)
		$toEncrypt = $password.$merchantID.$acquirerID.$orderID;
		//Produce the hash using SHA1
		//This will give fed389f2e634fa6b62bdfbfafd05be761176cee9
		$sha1Signature = sha1($toEncrypt);
		//Encode the signature using Base64
		//This will give /tOJ8uY0+mtivfv6/QW+dhF2zuk=
		$expectedBase64Sha1Signature = base64_encode(pack("H*",$sha1Signature));
		// signature verification is performed simply by comparing the signature weproduced with the one sent
		$verifySignature = ($expectedBase64Sha1Signature == $Signature);

		if($ReasonCode == 150){
			global $woocommerce;
			
			$order->payment_complete();
			$order->add_order_note( 'Order paid before !', false );
			wp_redirect( $order->get_checkout_order_received_url() );
			exit;
		}
			
		if($ReasonCode == 2){
			
			global $woocommerce;
			$order = wc_get_order($orderID);	
			$order->update_status( 'failed' );
			$order->add_order_note( 'order is failed for card information ', false );
			cancelOrder($orderID);
			wp_redirect(  $order->get_checkout_payment_url());
			
			
	
			exit;
		}
		if($ReasonCode == 6 || $ReasonCode == 10 || $ReasonCode == 41){
			global $woocommerce;
			$order = wc_get_order($orderID);	
			$order->update_status( 'failed' );
			$order->add_order_note( 'order is failed Connection was expired due to timeout in the checkout', false );
			cancelOrder($orderID);
			wp_redirect(  $order->get_checkout_payment_url());
			exit;	
			

				 
		}
			
			if($ReasonCode == 6){
			global $woocommerce;
			$order = wc_get_order($orderID);	
			$order->update_status( 'failed' );
			$order->add_order_note( 'order is failed Connection was expired due to timeout in the checkout', false );
			cancelOrder($orderID);
			wp_redirect(  $order->get_checkout_payment_url());
			exit;	
			

				 
		}

		if($ReasonCode == 1){
			global $woocommerce;
			$order = wc_get_order($orderID);	
			$order->payment_complete();
			// Reduce stock levels
			$order->reduce_order_stock();

			$order->add_order_note( 'Hi, your order is paid! Thank you!', true );
			wp_redirect( $order->get_checkout_order_received_url() );
			exit;
		}
	} 
}


function cancelOrder($order_id){
	global $woocommerce;
	global $error;
	$order = new WC_Order( $order_id );
	
	$failed_count = 0;
	
	foreach(get_private_order_notes($order_id) as $note){
		if(strpos($note['note_content'], 'failed')  === false  ){
			
		}else{
			$failed_count++;
		}
	}
	
	if($failed_count > 3){
		$order->add_order_note( 'order was cancelled becuase of 5 time failed tries', false );
		$order->update_status( 'cancelled' );
	}
	
}

function redirect_to_palpay($order_id){
	$order_id = str_replace("pporder/","",$order_id);
	$order_id = $order_id - ENC;
	
	global $woocommerce;
	$order = new WC_Order( $order_id );
 
	//Version
	$version = "1.0.0";
	//Merchant ID
	$merchantID = MERCHANTID;
	//Acquirer ID
	$acquirerID = ACQUIRERID;
	//The SSL secured URL of the merchant to which will send the transaction result
	//This should be SSL enabled – note https:// NOT http://
	$responseURL = BASELINK."/palpayorder/orderbackpal/";
	$link = PALPAYLINK;
	//Purchase Amount
	$purchaseAmt = $order->get_total();
	
	//Pad the purchase amount with 0's so that the total length is 13 characters,i.e. 20.50 will become0000000020.50
	$purchaseAmt = str_pad($purchaseAmt, 13 , "0", STR_PAD_LEFT);
	//Remove the dot (.) from the padded purchase amount( will know fromcurrency how many digits toconsider as decimal)
	//0000000020.50 will become 000000002050 (notice there is no dot)
	$formattedPurchaseAmt = substr($purchaseAmt,0,10).substr($purchaseAmt,11);
	//US Dollar currency ISO Code; see relevant appendix for ISO codes of othercurrencies
	$currency = CURRENCY;
	//The number of decimal points for transaction currency, i.e. in this examplewe indicate that US Dollar has 2decimal points
	$currencyExp = 2;
	//Order number
	$orderID = $order_id;
	//Specify we want not only to authorize the amount but also capture at the sametime. Alternative value could be M (for capturing later)
	$captureFlag = "M";
	//Password
	$password = PALPAYPASS;
	//Form the plaintext string to encrypt by concatenating Password, Merchant ID,Acquirer ID, Order ID,Formatter Purchase Amount and Currency
	//This will give 1234abcd | 0011223344 | 402971 | TestOrder12345 | 000000002050 |840 (spaces and |introduced here for clarity)
	$toEncrypt =
	$password.$merchantID.$acquirerID.$orderID.$formattedPurchaseAmt.$currency;
	//Produce the hash using SHA1. This will give b14dcc7842a53f1ec7a621e77c106dfbe8283779
	$sha1Signature = sha1($toEncrypt);
	//Encode the signature using Base64 before transmitting to
	//This will give sU3MeEKlPx7HpiHnfBBt++goN3k=
	$base64Sha1Signature = base64_encode(pack("H*",$sha1Signature));
	//The name of the hash algorithm use to create the signature; can be MD5 orSHA1; the latter is preffered and is what we used in this example
	$signatureMethod = "SHA1";
	
			?>
	<html>
		<body>
			<!-- Form with all request fields as prepared in PHP code above. Note all
			fields are hidden -->
			<form method="post" name="paymentForm" id="paymentForm"
				action="<?php echo $link; ?>">
				<input type="hidden" name="Version" value="<?php echo $version?>"><br>
				<input type="hidden" name="MerID" value="<?php echo $merchantID?>"><br>
				<input type="hidden" name="AcqID" value="<?php echo $acquirerID?>"><br>
				<input type="hidden" name="MerRespURL" value="<?php echo $responseURL
				?>"><br>
				<input type="hidden" name="PurchaseAmt" value="<?php echo
				$formattedPurchaseAmt ?>"><br>
				<input type="hidden" name="PurchaseCurrency" value="<?php echo
				$currency?>"><br>
				<input type="hidden" name="PurchaseCurrencyExponent" value="<?php echo
				$currencyExp?>"><br>
				<input type="hidden" name="OrderID" value="<?php echo $orderID?>"><br>
				<input type="hidden" name="CaptureFlag" value="<?php echo $captureFlag?>"><br>
				<input type="hidden" name="Signature" value="<?php echo $base64Sha1Signature?>"><br>
				<input type="hidden" name="SignatureMethod" value="<?php echo $signatureMethod?>"><br>
			</form>
			<!-- Automatic submission of request form to upon load using JavaScript -->
			<script language="JavaScript">
				document.forms["paymentForm"].submit();
			</script>
		</body>
	</html>			
			
			<?php
			
	exit;
}

 
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'misha_init_gateway_class' );
function misha_init_gateway_class() {
 
	class WC_Misha_Gateway extends WC_Payment_Gateway {
 		//add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {
 
			$this->id = 'misha'; // payment gateway plugin ID
			$this->icon = plugin_dir_url( __FILE__ )."checkout.png"; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = true; // in case you need a custom credit card form
			$this->method_title = 'PalPay Gateway';
			$this->method_description = 'PalPay Standard redirects customers to PalPay to enter their payment information.	'; // will be displayed on the options page
		 
			// gateways can support subscriptions, refunds, saved payment methods,
			// but in this tutorial we begin with simple payments
			$this->supports = array(
				'products'
			);
		 
			// Method with all the options fields
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->testmode = 'yes' === $this->get_option( 'testmode' );
			$this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
			$this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
		 
			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
 
		 
		
 
 		}
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){
 
			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable PalPay Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Credit Card',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => '',
				)
			);
 
	 	}
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
				// ok, let's display some description before the payment form
			if ( $this->description ) {
				// display the description with <p> tags etc.
				echo wpautop( wp_kses_post( $this->description ) );
			}
		 
			// I will echo() the form, but you can close PHP tags and print it directly in HTML
			echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
		 
			// Add this action hook if you want your custom gateway to support it
			do_action( 'woocommerce_credit_card_form_start', $this->id );
		 
			// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
			
		 
			do_action( 'woocommerce_credit_card_form_end', $this->id );
		 
			echo '<div class="clear"></div></fieldset>';
 
		}
  
 
		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields(){
	 
			return true;
		 
		}
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
		 
			global $woocommerce;
		 
			$order = new WC_Order( $order_id );

			// Mark as on-hold (we're awaiting the cheque)
			$order->update_status('on-hold', __( 'Awaiting PalPay payment', 'woocommerce' ));

			
			// Remove cart
			$woocommerce->cart->empty_cart();

			$encryptId = $order_id + ENC;
			
			// Return thankyou redirect
			return array(
				'result' => 'success',
				'redirect' => BASELINK . "/palpayorder/pporder/$encryptId?ppo=$encryptId"
			);
			 
			 ?>
			 
			 
		<?php
			  
		 
		}
 	}
}



