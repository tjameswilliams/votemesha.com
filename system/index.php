<?
namespace mf;
session_start();
/*
 *	Author: Tim Williams
 *	URL: http://newpdev.com
 *	Wordpress Custom Site Framework
 */
spl_autoload_register('mf\autoload_classes');
/**
 * fw\autoload_classes function
 *
 * Allows classes to be included without explicitly writing an include for each class file each time a class is required.
 * @return void
 * @author Tim Williams
 */
function autoload_classes( $class_name )
{
	if( file_exists( __DIR__ . '/classes/' . $class_name . '.class.php' ) )
	{
		include __DIR__ . '/classes/' . $class_name . '.class.php';
	}
}

require __DIR__.'/vendor/autoload.php';

$router = new \AltoRouter();

$router->setBasePath('/system');

$router->map( 'GET', '/cart', function() {
  $cart = new \Cart();
  header('Content-Type: application/json');
  echo json_encode($cart->getCart(), JSON_NUMERIC_CHECK);
});

$router->map( 'POST', '/add_item', function() {
  $item = json_decode(file_get_contents("php://input"), true);
  $cart = new \Cart();
  $cart->updateItem($item);
  header('Content-Type: application/json');
  echo json_encode($cart->getCart(), JSON_NUMERIC_CHECK);
});

$router->map( 'POST', '/remove_item', function() {
  $item = json_decode(file_get_contents("php://input"), true);
  $cart = new \Cart();
  $cart->removeItem($item);
  header('Content-Type: application/json');
  echo json_encode($cart->getCart(), JSON_NUMERIC_CHECK);
});

$router->map( 'POST', '/get_checkout', function() {
	$cart = new \Cart();
	$checkout_url = $cart->getCheckout();
	header('Content-Type: application/json');
	if( !$checkout_url ) {
		echo json_encode(['error' => $cart->error]);
	} else {
		echo json_encode(['redirect' => $checkout_url], JSON_NUMERIC_CHECK);
	}
});

$router->map( 'POST', '/process_checkout', function() {
	$data = json_decode(file_get_contents("php://input"), true);
	$cart = new \Cart();
	header('Content-Type: application/json');
	echo json_encode($cart->processCheckout($data['token'],$data['PayerID']),JSON_NUMERIC_CHECK);
});

$router->map( 'POST', '/get_confirmation', function() {
	$data = json_decode(file_get_contents("php://input"), true);
	$cart = new \Cart();
	$cart_ref = $cart->getCart();
	$cart->sendConfirmationEmail();
	$cart->clearCart();
	header('Content-Type: application/json');
	echo json_encode($cart_ref, JSON_NUMERIC_CHECK);
});

$router->map( 'GET', '/orders', function() {
	auth();
		$cart = new \Cart();
	header('Content-Type: application/json');
	echo json_encode($cart->getOrders(), JSON_NUMERIC_CHECK);
});

$router->map('POST', '/update_order', function() {
	$order = json_decode(file_get_contents("php://input"), true);
	auth();
	$cart = new \Cart();
	header('Content-Type: application/json');
	echo json_encode(['success' => $cart->updateOrder($order)], JSON_NUMERIC_CHECK);
});

function auth() {
	$face = array_pop( explode( '/', rtrim($_SERVER['HTTP_REFERER'],'/') ));
	if( $face !== 'face' ) {
		header( $_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found');
		die;
	}
}

// match current request url
$match = $router->match();

//var_dump($match);

// call closure or throw 404 status
if( $match && is_callable( $match['target'] ) ) {
	call_user_func_array( $match['target'], $match['params'] );
} else {
	// no route was matched
	header( $_SERVER["SERVER_PROTOCOL"] . ' 404 Not Found');
}
