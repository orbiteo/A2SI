<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
define('DEBUG', true);
define('PS_SHOP_PATH', 'https://a2si.xiop.it');
define('PS_WS_AUTH_KEY', '4G3WUT9M8SRJCD1Y9ZT2MICYG2XF2MXV');
require_once('./PSWebServiceLibrary.php');

$dateDeLUpdate = date('Y-m-d');
/*** DÉBUT EXTRACT ORDERS ***/
$myFile = _PS_MODULE_DIR_.'/interfaceerp/exports/orders/ORDR'.$dateDeLUpdate.'.csv';
$fh = fopen($myFile, 'w') or die("impossible de créer le fichier");
//Inscrire les en-tete du fichier csv
$entete = ["DocNum", "DocType", "Handwritten", "Printed", "DocDate", "DocDueDate", "CardCode", "CardName", "Address","PaymentMethod"];
fputcsv($fh, $entete);

// 2 fichiers commande à renvoyer à SAP
$myFile2 = _PS_MODULE_DIR_.'/interfaceerp/exports/orders/RDR1'.$dateDeLUpdate.'.csv';
$fh2 = fopen($myFile2, 'w') or die("impossible de créer le fichier");
//Inscrire les en-tete du fichier csv
$entete2 = ["ParentKey", "ItemCode", "ItemDescription", "Quantity", "Price"];
fputcsv($fh2, $entete2);

$arrayORDR = []; //Tableau recap de toutes les en-tete de commandes
$arrayRDR1 = []; //Tableau recap de toutes les details de commandes

//sortir le xml orders
$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
try {
  $xml = $webService->get(array('resource' => 'orders'));
  $totalOrders = $xml->orders->children();
  //Faire une boucle pour sortir tous les id
  foreach ($totalOrders as $order) {
    $idOrder = $order->attributes();
    // faire un appel API avec chaque id
    $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/orders/'.$idOrder));
    $orderDetails = $xml->children()->children();
    // extraire les données dans des variables puis dans un tableau par id, puis dans un tableau de tous les id
    $orderId = $orderDetails->id;
    $invoiceAddress = $orderDetails->id_address_invoice;
    $orderCustomerId = $orderDetails->id_customer;
    $orderReference = $orderDetails->reference;
    $orderTotalPaid = $orderDetails->total_paid;
    $orderDate = $orderDetails->date_add;
    $orderPaymentType = $orderDetails->payment;
    $orderCustomerName = "";
    $orderIdsProducts = "";
    $orderQuantities = "";

    $productsDetails = $orderDetails->associations->children()->children();
    foreach ($productsDetails as $key) {
      $idProduct = $key->product_id;
      $quantityPerId = $key->product_quantity;
      $productName = $key->product_name;
      $productPrice = $key->product_price;

      $arrayDetailsOrder = array($orderId, $idProduct, $productName, $quantityPerId, $productPrice);
      array_push($arrayRDR1, $arrayDetailsOrder);
    }

      $xml5 = $webService->get(array('url' => PS_SHOP_PATH.'/api/addresses/'.$invoiceAddress));
      $addressDetail = $xml5->children()->children();
      $orderInvoiceAddress = $addressDetail->address1 .' '.$addressDetail->postcode .' '.$addressDetail->city;

    // faire un appel API des customers pour retrouver les noms correspondants aux commandes
    $xml2 = $webService->get(array('resource' => 'customers'));
    $totalCustomers = $xml2->customers->children();
    //Faire une boucle pour sortir tous les id
    foreach ($totalCustomers as $customer) {
      $idCustomer = $customer->attributes();
      // faire un appel API avec chaque id
      $xml3 = $webService->get(array('url' => PS_SHOP_PATH.'/api/customers/'.$idCustomer));
      $customerDetail = $xml3->children()->children();
      $idCustomerDetail = $customerDetail->id;
      if(intval($orderCustomerId) == intval($idCustomerDetail)) {
        $orderCustomerName = $customerDetail->firstname .' '.$customerDetail->lastname;
      }
    }
    $arrayEnteteOrder = array($orderId, "dDocument_Items", "tYES", $orderDate, $orderDate, $orderCustomerId, $orderCustomerName, $orderInvoiceAddress, $orderPaymentType);
    array_push($arrayORDR, $arrayEnteteOrder); // ajouter chaque tableau client au tableau général
  }
  // remplir le fichier csv avec ces données avec la méthode fputcsv()
  foreach ($arrayORDR as $fields) {
    fputcsv($fh, $fields);
  }
  fclose($fh);

  foreach ($arrayRDR1 as $fields2) {
    fputcsv($fh2, $fields2);
  }
  fclose($fh2);
}
catch (PrestaShopWebserviceException $e) {
    $trace = $e->getTrace();
    if ($trace[0]['args'][0] == 404) echo 'Bad ID';
    else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
    else echo $e->getMessage();
}
/*** / FIN EXTRACT ORDERS ***/
