<?
/**
 * Simple cart class for tracking orders.
 *
 *
CREATE TABLE cart (
	id INT(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    paid TINYINT(1) DEFAULT 0,
    email VARCHAR(120) DEFAULT NULL,
    first_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    address VARCHAR(200) DEFAULT NULL,
    city VARCHAR(30) DEFAULT NULL,
    state VARCHAR(30) DEFAULT NULL,
    phone VARCHAR(25) DEFAULT NULL,
    status VARCHAR(10) DEFAULT 'pending',
    zip INT(10) DEFAULT NULL,
    ppal_id VARCHAR(25) DEFAULT NULL,
    notes TEXT,
    time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) Engine InnoDB;


CREATE TABLE order_item (
	id INT(10) UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    cart_id INT(10) UNSIGNED,
    name VARCHAR(200) DEFAULT NULL,
    qty INT(11),
    single_price DOUBLE(4,2),
    group_price DOUBLE(6,2),
    FOREIGN KEY (cart_id) REFERENCES cart(id) ON DELETE CASCADE
) Engine InnoDB

 */
class cart extends dbHelper {

  public $cart_id;

  public $SQL = [
    'getCartSingle' => 'SELECT * FROM cart WHERE id = ?',
    'getAllOrders' => 'SELECT * FROM cart',
    'insertCart' => 'INSERT INTO cart (status) VALUES ("pending")',
    'upsertItem' => 'INSERT INTO order_item (id,cart_id,name,qty,single_price,group_price)
      VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
      name = VALUES(name),
      qty = VALUES(qty),
      single_price = VALUES(single_price),
      group_price = VALUES(group_price)',
    'removeItemSql' => 'DELETE FROM order_item WHERE id = ?',
    'getCartItems' => 'SELECT * FROM order_item WHERE cart_id = ?',
    'updateCart' => 'UPDATE cart SET
      email = ?,
      first_name = ?,
      last_name = ?,
      address = ?,
      city = ?,
      state = ?,
      zip = ?,
      phone = ?,
      paid = 1,
      ppal_id = ?
      WHERE id = ?',
    'updateOrderSql' => 'UPDATE cart SET
      status = ?,
      notes = ?
      WHERE id = ?',
  ];

  public $products = [
    [
      'name' => 'XS Unisex FACE T-Shirt',
      'size' => 'Extra Small',
      'description' => '',
      'price' => 25.00,
    ],
    [
      'name' => 'S Unisex FACE T-Shirt',
      'size' => 'Small',
      'description' => '',
      'price' => 25.00,
    ],
    [
      'name' => 'M Unisex FACE T-Shirt',
      'size' => 'Medium',
      'description' => '',
      'price' => 25.00,
    ],
    [
      'name' => 'L Unisex FACE T-Shirt',
      'size' => 'Large',
      'description' => '',
      'price' => 25.00,
    ],
    [
      'name' => 'XL Unisex FACE T-Shirt',
      'size' => 'Extra Large',
      'description' => '',
      'price' => 25.00,
    ],
    [
      'name' => 'XXL Unisex FACE T-Shirt',
      'size' => 'Extra Extra Large',
      'description' => '',
      'price' => 25.00,
    ],
  ];

  public function __construct() {
    $db_config = json_decode(file_get_contents(__DIR__.'/../config/db.json'));
    parent::__construct($db_config->database,$db_config->user,$db_config->password);

    if( isset($_SESSION['cart_id']) ) {
      $this->cart_id = $_SESSION['cart_id'];
    }
  }

  public function getOrders() {
    $orders = $this->getAllOrders();
    foreach( $orders as &$order ) {
      $order['items'] = $this->getCartItems($order['id']);
      $order['total'] = 0;
      foreach( $order['items'] as $item ) {
        $order['total'] += $item['group_price'];
      }
    }
    return $orders;
  }

  public function updateOrder($order) {
    return $this->updateOrderSql($order['status'],$order['notes'],$order['id']);
  }

  public function getCart() {
    if( $this->cart_id ) {
      $cart = $this->getCartSingle((int)$this->cart_id);
      if( empty($cart) ) {
        $this->cart_id = null;
        return $this->getCart();
      } else {
        $cart['items'] = $this->getCartItems($this->cart_id);
        $cart['products'] = $this->products;
        $cart['total'] = 0;
        foreach( $cart['products'] as &$product ) {
          foreach( $cart['items'] as $item ) {
            if( $item['name'] == $product['name'] ) {
              $product['in_cart'] = true;
              $product['qty'] = $item['qty'];
              $cart['total'] += $item['group_price'];
            }
          }
        }
        return $cart;
      }
    } else {
      $this->insertCart();
      $_SESSION['cart_id'] = $this->insert_id;
      $this->cart_id = $this->insert_id;
      return $this->getCart();
    }
  }

  public function updateItem($item) {
    $pref = $this->_getItem($item['name']);
    if( $item['qty'] < 1 && !empty($item['id']) ) {
      $this->removeItem($item);
    } else {
      $this->upsertItem(
        $item_id = !empty($item['id']) ? $item['id'] : null,
        $item['cart_id'],
        $pref['name'],
        $item['qty'],
        $pref['price'],
        (int)$item['qty']*$pref['price']
      );
    }
  }

  public function removeItem($item) {
    $this->removeItemSql($item['id']);
  }

  public function getCheckout() {
    $cart = $this->getCart();

    $PayPal = $this->_getPPal();

    $link_back = 'http://'.$_SERVER['HTTP_HOST'];
    $SECFields = array(
      'maxamt' => $cart['total'], 					// The expected maximum total amount the order will be, including S&H and sales tax.
      'returnurl' => $link_back, 							    // Required.  URL to which the customer will be returned after returning from PayPal.  2048 char max.
      'cancelurl' => $link_back, 							    // Required.  URL to which the customer will be returned if they cancel payment on PayPal's site.
      'hdrimg' => 'http://votemesha.com/creative/social_splash.png', 			// URL for the image displayed as the header during checkout.  Max size of 750x90.  Should be stored on an https:// server or you'll get a warning message in the browser.
      //'logoimg' => 'https://www.angelleye.com/images/angelleye-logo-190x60.jpg', 					// A URL to your logo image.  Formats:  .gif, .jpg, .png.  190x60.  PayPal places your logo image at the top of the cart review area.  This logo needs to be stored on a https:// server.
      'brandname' => 'Vote 4 Mesha', 							                                // A label that overrides the business name in the PayPal account on the PayPal hosted checkout pages.  127 char max.
      //'customerservicenumber' => '816-555-5555', 				                                // Merchant Customer Service number displayed on the PayPal Review page. 16 char max.
    );

    $Payments = array();
    $Payment = array(
      'amt' => $cart['total'], 	// Required.  The total cost of the transaction to the customer.  If shipping cost and tax charges are known, include them in this value.  If not, this value should be the current sub-total of the order.
    );

    array_push($Payments, $Payment);

    $PayPalRequestData = array(
      'SECFields' => $SECFields,
      'Payments' => $Payments,
    );

    $PayPalResult = $PayPal->SetExpressCheckout($PayPalRequestData);

    if($PayPal->APICallSuccessful($PayPalResult['ACK']))
    {
      $_SESSION['paypal_token'] = isset($PayPalResult['TOKEN']) ? $PayPalResult['TOKEN'] : '';
      //header('Location: ' . $PayPalResult['REDIRECTURL']);
      return $PayPalResult['REDIRECTURL'];
    }
    else
    {
      $this->error = $PayPalResult['ERRORS'];
      return false;
    }

  }

  public function processCheckout($token,$payer_id) {
    $_SESSION['paypal_token'] = $token;
    $_SESSION['paypal_payer_id'] = $payer_id;
    $cart = $this->getCart();
    $PayPal = $this->_getPPal();

    $PayPalResult = $PayPal->GetExpressCheckoutDetails($_SESSION['paypal_token']);

    if($PayPal->APICallSuccessful($PayPalResult['ACK']))
    {
      $_SESSION['paypal_payer_id'] = isset($PayPalResult['PAYERID']) ? $PayPalResult['PAYERID'] : '';
      //$_SESSION['phone_number'] = isset($PayPalResult['PHONENUM']) ? $PayPalResult['PHONENUM'] : '';
      $_SESSION['email'] = isset($PayPalResult['EMAIL']) ? $PayPalResult['EMAIL'] : '';
      $_SESSION['first_name'] = isset($PayPalResult['FIRSTNAME']) ? $PayPalResult['FIRSTNAME'] : '';
      $_SESSION['last_name'] = isset($PayPalResult['LASTNAME']) ? $PayPalResult['LASTNAME'] : '';

      $payments = $PayPal->GetPayments($PayPalResult);

      foreach($payments as $payment)
      {
        $_SESSION['shipping_name'] = isset($payment['SHIPTONAME']) ? $payment['SHIPTONAME'] : '';
        $_SESSION['shipping_street'] = isset($payment['SHIPTOSTREET']) ? $payment['SHIPTOSTREET'] : '';
        $_SESSION['shipping_city'] = isset($payment['SHIPTOCITY']) ? $payment['SHIPTOCITY'] : '';
        $_SESSION['shipping_state'] = isset($payment['SHIPTOSTATE']) ? $payment['SHIPTOSTATE'] : '';
        $_SESSION['shipping_zip'] = isset($payment['SHIPTOZIP']) ? $payment['SHIPTOZIP'] : '';
        $_SESSION['shipping_country_code'] = isset($payment['SHIPTOCOUNTRYCODE']) ? $payment['SHIPTOCOUNTRYCODE'] : '';
        $_SESSION['shipping_country_name'] = isset($payment['SHIPTOCOUNTRYNAME']) ? $payment['SHIPTOCOUNTRYNAME'] : '';
      }
    }

    $DECPFields = array(
      'token' => $_SESSION['paypal_token'], 								// Required.  A timestamped token, the value of which was returned by a previous SetExpressCheckout call.
      'payerid' => $_SESSION['paypal_payer_id'], 							// Required.  Unique PayPal customer id of the payer.  Returned by GetExpressCheckoutDetails, or if you used SKIPDETAILS it's returned in the URL back to your RETURNURL.
    );

    $Payments = array();
    $Payment = array(
      'amt' => number_format($cart['total'],2), 	    // Required.  The total cost of the transaction to the customer.  If shipping cost and tax charges are known, include them in this value.  If not, this value should be the current sub-total of the order.
      'itemamt' => number_format($cart['total'],2),       // Subtotal of items only.
      'currencycode' => 'USD', 					                                // A three-character currency code.  Default is USD.
      //'shippingamt' => number_format($_SESSION['shopping_cart']['shipping'],2), 	// Total shipping costs for this order.  If you specify SHIPPINGAMT you mut also specify a value for ITEMAMT.
      //'handlingamt' => number_format($_SESSION['shopping_cart']['handling'],2), 	// Total handling costs for this order.  If you specify HANDLINGAMT you mut also specify a value for ITEMAMT.
      //'taxamt' => number_format($_SESSION['shopping_cart']['tax'],2), 			// Required if you specify itemized L_TAXAMT fields.  Sum of all tax items in this order.
      'shiptoname' => $_SESSION['shipping_name'], 					            // Required if shipping is included.  Person's name associated with this address.  32 char max.
      'shiptostreet' => $_SESSION['shipping_street'], 					        // Required if shipping is included.  First street address.  100 char max.
      'shiptocity' => $_SESSION['shipping_city'], 					            // Required if shipping is included.  Name of city.  40 char max.
      'shiptostate' => $_SESSION['shipping_state'], 					            // Required if shipping is included.  Name of state or province.  40 char max.
      'shiptozip' => $_SESSION['shipping_zip'], 						            // Required if shipping is included.  Postal code of shipping address.  20 char max.
      'shiptocountrycode' => $_SESSION['shipping_country_code'], 				    // Required if shipping is included.  Country code of shipping address.  2 char max.
      'shiptophonenum' => @$_SESSION['phone_number'],  				            // Phone number for shipping address.  20 char max.
      'paymentaction' => 'Sale', 					                                // How you want to obtain the payment.  When implementing parallel payments, this field is required and must be set to Order.
    );

    array_push($Payments, $Payment);

    $PayPalRequestData = array(
      'DECPFields' => $DECPFields,
      'Payments' => $Payments,
    );

    $PayPalResult = $PayPal->DoExpressCheckoutPayment($PayPalRequestData);

    if($PayPal->APICallSuccessful($PayPalResult['ACK']))
    {
      $payments_info = $PayPal->GetExpressCheckoutPaymentInfo($PayPalResult);

      foreach($payments_info as $payment_info)
      {
        $_SESSION['paypal_transaction_id'] = isset($payment_info['TRANSACTIONID']) ? $payment_info['TRANSACTIONID'] : '';
        $_SESSION['paypal_fee'] = isset($payment_info['FEEAMT']) ? $payment_info['FEEAMT'] : '';
      }
      $this->updateCart(
        $_SESSION['email'],
        $_SESSION['first_name'],
        $_SESSION['last_name'],
        $_SESSION['shipping_street'],
        $_SESSION['shipping_city'],
        $_SESSION['shipping_state'],
        $_SESSION['shipping_zip'],
        @$_SESSION['phone_number'],
        $_SESSION['paypal_transaction_id'],
        $cart['id']
      );
      return ['success' => true, 'transaction_id' => $_SESSION['paypal_transaction_id'], 'fee' => $_SESSION['paypal_fee']];
    }
    else
    {
      return ['error' => $PayPalResult['ERRORS']];
    }
  }

  public function sendConfirmationEmail() {
    $cart = $this->getCart();
    ob_start();
    extract( $cart );
    include __DIR__ . '/../templates/order_confirmation.tpl';
    $email_html = ob_get_clean();
    $to = $cart['email'];
    $subject = 'Order #'.$cart['id'];
    $headers = "From: no-reply@votemesha.com\r\n";
    $headers .= "Reply-To: t.james.williams@gmail.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
    mail($to, $subject, $email_html, $headers);
  }

  public function clearCart() {
    session_unset();
  }

  private function _getPPal() {
    $ppal_config = json_decode(file_get_contents(__DIR__.'/../config/paypal.json'));
    $ppal_sandbox = false;
    $ppal_domain = $ppal_config->sandbox ? 'https://api-3t.sandbox.paypal.com/nvp/' : 'https://api-3t.paypal.com/nvp/';

    $ppal_log = __DIR__.'/../log/';

    $config = array(
      'Sandbox' => $ppal_config->sandbox,
      'APIUsername' => $ppal_config->username,
      'APIPassword' => $ppal_config->password,
      'APISignature' => $ppal_config->signature,
      'PrintHeaders' => true,
      'LogResults' => true,
      'LogPath' => $ppal_log,
    );

    return new PayPal($config);
  }



  private function _getItem($name) {
    foreach( $this->products as $product ) {
      if( $name == $product['name'] ) {
        return $product;
      }
    }
  }

}
