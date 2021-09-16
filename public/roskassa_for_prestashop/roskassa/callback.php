<?php
/**
 * @author  Syntlex Dev https://syntlex.info
 * @copyright 2005-2021  Syntlex Dev
 * @license : GNU General Public License
 * @subpackage Payment plugin for Roskassa
 * @Product : Payment plugin for Roskassa
 * @Date  : 24 March 2021
 * @Contact : cmsmodulsdever@gmail.com
 * This plugin is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 *  without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU General Public License for more details <http://www.gnu.org/licenses/>.
 *
 **/

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__) . '/roskassa.php');

$roskassa = new roskassa();

if(isset($_REQUEST['shop_id']) && isset($_REQUEST['order_id'])
 && isset($_REQUEST['amount']) && isset($_REQUEST['currency']) && isset($_REQUEST['sign']))
{
  // для чека на кассе
  $order_id = $_REQUEST['order_id'];

  $dataIntegrityCode = Configuration::get('ROSKASSA_MNT_DATAINTEGRITY_CODE');
  $arrSign = $_POST;
  unset($arrSign['sign']);
  ksort($arrSign);
  $str = http_build_query($arrSign);
  $signature = md5($str . $dataIntegrityCode);
  if ($_REQUEST['sign'] == $signature)
  {
    $id_cart = (int)(Tools::substr($_REQUEST['order_id'], 0, strpos($_REQUEST['order_id'], '_')));
    $secure_cart = explode('_', $_REQUEST['order_id']);
    $id_order = Order::getIdByCartId($secure_cart[0]);
    $order = new Order($id_order);

    $amount = number_format($order->total_paid, 2, '.', '');
    if ($amount == $_REQUEST['amount'])
    {
      $status = Configuration::get('ROSKASSA_OS_SUCCEED');
      if(!$status){
        $status = Configuration::get('PS_OS_PAYMENT');
    }
    $order->setCurrentState($status);

   // поднять все данные по заказу
    $products = $order->getProducts();
    $customer = new Customer($order->id_customer);

   // пробить чек
    $clientEmail = $customer->email;

   // товары
    $inventory = array();
    if (is_array($products) && count($products)) {
      foreach ($products AS $product) {
        $inventory[] = array(
          'name' => trim(preg_replace("/&?[a-z0-9]+;/i", "", htmlspecialchars($product['product_name']))),
          'price' => $product['product_price'],
          'quantity' => $product['product_quantity'],
          'vatTag' => '1105'
        );
     }
   }
   $kassa_inventory = json_encode($inventory);

   // если есть товары и e-mail клиента ответим в xml
   if (count($inventory) && $clientEmail) {

    $resultCode = 200;

    $kassa_customer = $clientEmail;


    $deliveryAmount = $order->total_shipping;
    $kassa_delivery = ($deliveryAmount > 0) ? $deliveryAmount : null;

    header("Content-type: application/xml");

    $signature = md5($resultCode . $_REQUEST['shop_id'] . $_REQUEST['order_id'] . $dataIntegrityCode);
    $result = '<?xml version="1.0" encoding="UTF-8" ?>';
    $result .= '<MNT_RESPONSE>';
    $result .= '<shop_id>' . $_REQUEST['shop_id'] . '</shop_id>';
    $result .= '<order_id>' . $_REQUEST['order_id'] . '</order_id>';
    $result .= '<MNT_RESULT_CODE>' . $resultCode . '</MNT_RESULT_CODE>';
    $result .= '<sign>' . $signature . '</sign>';

    if ($kassa_inventory || $kassa_customer || $kassa_delivery) {
      $result .= '<MNT_ATTRIBUTES>';
    }

    if ($kassa_inventory) {
      $result .= '<ATTRIBUTE>';
      $result .= '<KEY>INVENTORY</KEY>';
      $result .= '<VALUE>' . $kassa_inventory . '</VALUE>';
      $result .= '</ATTRIBUTE>';
    }

    if ($kassa_customer) {
      $result .= '<ATTRIBUTE>';
      $result .= '<KEY>CUSTOMER</KEY>';
      $result .= '<VALUE>' . $kassa_customer . '</VALUE>';
      $result .= '</ATTRIBUTE>';
    }

    if ($kassa_delivery) {
      $result .= '<ATTRIBUTE>';
      $result .= '<KEY>DELIVERY</KEY>';
      $result .= '<VALUE>' . $kassa_delivery . '</VALUE>';
      $result .= '</ATTRIBUTE>';
    }

    if ($kassa_inventory || $kassa_customer || $kassa_delivery) {
      $result .= '</MNT_ATTRIBUTES>';
    }

    $result .= '</MNT_RESPONSE>';

    echo $result;

    exit;

  }
  else {
    die('SUCCESS');
  }
}
else
{
  die('FAIL');
}
}
else
{
  die('FAIL');
}
} 
else if (isset($_REQUEST['id']) && isset($_REQUEST['order_id']) && isset($_REQUEST['status'])){
  if (!empty($_REQUEST['id']) && !empty($_REQUEST['order_id']) && $_REQUEST['status'] == 1) {
    die('SUCCESS');
} else {
  die('FAIL');
} 
} 
else
{
  die('FAIL');
}
?>