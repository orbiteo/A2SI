<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
define('DEBUG', true);
define('PS_SHOP_PATH', 'https://a2si.xiop.it');
define('PS_WS_AUTH_KEY', '4G3WUT9M8SRJCD1Y9ZT2MICYG2XF2MXV');
require_once('./PSWebServiceLibrary.php');


/*** DÉBUT EXTRACT ORDERS ***/
$dateDeLUpdate = date('Y-m-d');
$myFileQuotes = _PS_MODULE_DIR_.'/interfaceerp/exports/devis/quotes'.$dateDeLUpdate.'.csv';
$fhQuotes = fopen($myFileQuotes, 'w') or die("impossible de créer le fichier");
//Inscrire les en-tete du fichier csv
$enteteOrders = ["Type", "ID Client", "ID quote", "Nom Client", "Email CLient", "ID Adresse Facturation", "Ligne 1 Adresse Facturation", "CP Adresse Facturation", "Ville Adresse Facturation", "ID Adresse Livraison", "Ligne 1 Adresse Livraison", "CP Adresse Livraison", "Ville Adresse Livraison", "Reference Produit", "Quantite", "Siren"];
fputcsv($fhOrders, $enteteOrders);

$orderReference = "";

$arrayORDR = []; //Tableau recap de toutes les commandes

$SQL = Db::getInstance()->executeS("SELECT id_quotes, id_customer, products 
    FROM "._DB_PREFIX_."quotes");

var_dump($SQL);

//sortir le xml orders
/*$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
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

    if($orderDetails->current_state != "5" && $orderDetails->current_state != "6") { // si l'état de la commande n'est ni livrée (5), ni annulée (6), on remplit le tableau:
      $invoiceAddress = $orderDetails->id_address_invoice;
      $deliveryAdress = $orderDetails->id_address_delivery;
      $orderCustomerId = $orderDetails->id_customer;
      $orderReference = $orderDetails->reference;
      $orderTotalPaid = $orderDetails->total_paid_tax_excl;
      $orderDate = $orderDetails->date_add;
      $orderPaymentType = $orderDetails->payment;
      $totalShipping = $orderDetails->total_shipping_tax_excl;
      $idTransp = $orderDetails->id_carrier;
      $payment = $orderDetails->payment;
      $orderCustomerName = "";
      $customerEmail = "";
      $customerSiret = "";
      $orderIdsProducts = "";
      $orderQuantities = "";

      $productsDetails = $orderDetails->associations->children()->children();
      foreach ($productsDetails as $key) {
        $quantityPerId = $key->product_quantity;
        $productPrice = $key->product_price;
        $productReference = $key->product_reference;

        $arrayDetailsOrder = array("L", "", $orderReference, "", "", "", "", "", "", "", "", "", "", $productReference, $quantityPerId, $productPrice, "", "", "", "", "", "");
        array_push($arrayORDR, $arrayDetailsOrder);
      }

      $xml5 = $webService->get(array('url' => PS_SHOP_PATH.'/api/addresses/'.$invoiceAddress));
      $addressFact = $xml5->children()->children();
      $ligne1Fact = $addressFact->address1;
      $cpFact = $addressFact->postcode;
      $villeFact = $addressFact->city;

      $xml6 = $webService->get(array('url' => PS_SHOP_PATH.'/api/addresses/'.$deliveryAdress));
      $addressDeliv = $xml6->children()->children();
      $ligne1Deliv = $addressDeliv->address1;
      $cpDeliv = $addressDeliv->postcode;
      $villeDeliv = $addressDeliv->city;
      try {
        $xml3 = $webService->get(array('url' => PS_SHOP_PATH.'/api/customers/'.$orderCustomerId));
        $customerDetail = $xml3->children()->children();
        $idCustomerDetail = $customerDetail->id;
          $orderCustomerName = $customerDetail->firstname .' '.$customerDetail->lastname;
          $customerEmail = $customerDetail->email;
          $customerSiret = $customerDetail->siret;
      }
      catch (PrestaShopWebserviceException $e) {
        $trace = $e->getTrace();
        if ($trace[0]['args'][0] == 404) { //Si id client inexistant 
          $idCustomerDetail = "";
          $orderCustomerName = "";
          $customerEmail = "";
          $customerSiret = "";
        }
        else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
        else echo $e->getMessage();
      }
        
      //}
      $arrayEnteteOrder = array("T", $idCustomerDetail, $orderReference, $orderCustomerName, $customerEmail, $invoiceAddress, $ligne1Fact, $cpFact, $villeFact, $deliveryAdress, $ligne1Deliv, $cpDeliv, $villeDeliv, "", "", "", $orderTotalPaid, $totalShipping, $idTransp, $orderDate, $payment, $customerSiret);
      array_push($arrayORDR, $arrayEnteteOrder); // ajouter chaque tableau client au tableau général
    }
  }
  // remplir le fichier csv avec ces données avec la méthode fputcsv()
  foreach ($arrayORDR as $fields) {
    fputcsv($fhOrders, $fields);
  }
  fclose($fhOrders);
}
catch (PrestaShopWebserviceException $e) {
    $trace = $e->getTrace();
    if ($trace[0]['args'][0] == 404) echo 'Bad ID';
    else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
    else echo $e->getMessage();
}*/
/*** / FIN EXTRACT ORDERS ***/
