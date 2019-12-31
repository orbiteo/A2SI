<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
define('DEBUG', true);
define('PS_SHOP_PATH', 'https://a2si.xiop.it');
define('PS_WS_AUTH_KEY', '4G3WUT9M8SRJCD1Y9ZT2MICYG2XF2MXV');
require_once('./PSWebServiceLibrary.php');


/*** EXTRACT FICHIER CLIENTS VERS SAP ***/
$dateDeLUpdate = date('Y-m-d');

$myFileCustomers = _PS_MODULE_DIR_.'/interfaceerp/exports/clients/OCPR'.$dateDeLUpdate.'.csv';//créer fichier .csv
$fhCustomers = fopen($myFileCustomers, 'w') or die("impossible de créer le fichier");
//Inscrire les en-tete du fichier csv
$enteteCustomers = ["ParentKey", "CardCode", "Name", "Phone1", "E_Mail"];
fputcsv($fhCustomers, $enteteCustomers);
$arrayCustomers = []; //Tableau recap de tous les clients

$myFileCustomersDetails = _PS_MODULE_DIR_.'/interfaceerp/exports/clients/OCRD'.$dateDeLUpdate.'.csv';//créer fichier .csv
$fhCustomersDetails = fopen($myFileCustomersDetails, 'w') or die("impossible de créer le fichier");
//Inscrire les en-tete du fichier csv
$enteteCustomersDetails = ["CardCode", "CardName", "VatLiable"];
fputcsv($fhCustomersDetails, $enteteCustomersDetails);
$arrayCustomersDetails = []; //Tableau recap de tous les details clients

$myFileAddresses = _PS_MODULE_DIR_.'/interfaceerp/exports/clients/CRD1'.$dateDeLUpdate.'.csv';//créer fichier .csv
$fhAddresses = fopen($myFileAddresses, 'w') or die("impossible de créer le fichier");
//Inscrire les en-tete du fichier csv
$enteteAddresses = ["ParentKey", "AddressName", "Street", "ZipCode", "City", "Country", "AddressType"];
fputcsv($fhAddresses, $enteteAddresses);
$arrayAddresses = []; //Tableau recap de toutes les adresses



//sortir le xml customers
$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
try {
  $xml = $webService->get(array('resource' => 'customers'));
  $totalCustomers = $xml->customers->children();
  //Faire une boucle pour sortir tous les id
  foreach ($totalCustomers as $customer) {
    $idCustomer = $customer->attributes();
    // faire un appel API avec chaque id
    $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/customers/'.$idCustomer));
    $customerDetails = $xml->children()->children();
    // extraire les données dans des variables puis dans un tableau par id, puis dans un tableau de tous les id
    $customerId = $customerDetails->id;
    $customerTitle = $customerDetails->id_gender;
    $customerEmail = $customerDetails->email;
    $customerPassword = $customerDetails->passwd;
    $customerLastname = $customerDetails->lastname;
    $customerFirstname = $customerDetails->firstname;
    $customerFullName = $customerFirstname.' '.$customerLastname;
    if($customerDetails->show_public_prices == 0) {
      $tvaOrNot = "vExempted";
    }
    else {
      $tvaOrNot = "vLiable";
    }
    $customerPhone = "";
    $customerPhoneMobile = "";
    $customerAdresse = "";
    $customerCp = "";
    $customerVille = "";

    // faire un appel API des adresses pour retrouver les adresses correspondant à l'id client
    $xml2 = $webService->get(array('resource' => 'addresses'));
    $totalAddresses = $xml2->addresses->children();
    //Faire une boucle pour sortir tous les id
    foreach ($totalAddresses as $address) {
      $idAddress = $address->attributes();
      // faire un appel API avec chaque id
      $xml3 = $webService->get(array('url' => PS_SHOP_PATH.'/api/addresses/'.$idAddress));
      $addressesDetail = $xml3->children()->children();
      $customerAddressId = $addressesDetail->id_customer;
      if(intval($customerAddressId) == intval($customerId) && $addressesDetail->deleted == "0") { // Si l'id_customer actuel est retrouvé dans les id des adresses et que l'adresse n'est pas deleted
        $customerPhone = $addressesDetail->phone;
        $customerPhoneMobile = $addressesDetail->phone_mobile;
        $customerAdresse = $addressesDetail->address1;
        $customerCp = $addressesDetail->postcode;
        $customerVille = $addressesDetail->city;
      }
    }

    //OCPR - "ParentKey", "CardCode", "Name", "Phone1", "E_Mail"
    $arrayAllCustomers = array($customerId, $customerId, $customerFullName, $customerPhone, $customerEmail);
    array_push($arrayCustomers, $arrayAllCustomers ); // ajouter chaque tableau client au tableau général

    //OCRD - "CardCode", "CardName", "VatLiable"
    $arrayAllCustomersDetails = array($customerId, $customerFullName, $tvaOrNot);
    array_push($arrayCustomersDetails, $arrayAllCustomersDetails);

    //CRD1 - "ParentKey", "AddressName", "Street", "ZipCode", "City", "Country", "AddressType"
    $arrayAllAdresses = array($customerId, $customerAdresse, $customerCp, $customerVille, "France", "bo_ShipTo" );
    array_push($arrayAddresses, $arrayAllAdresses);
  }
  // remplir le fichier csv avec ces données avec la méthode fputcsv()
  foreach ($arrayCustomers as $fields) {
    fputcsv($fhCustomers, $fields);
  }
  fclose($fhCustomers);
  foreach ($arrayCustomersDetails as $fields) {
    fputcsv($fhCustomersDetails, $fields);
  }
  fclose($fhCustomersDetails);
  foreach ($arrayAddresses as $fields) {
    fputcsv($fhAddresses, $fields);
  }
  fclose($fhAddresses);
}
catch (PrestaShopWebserviceException $e) {
    $trace = $e->getTrace();
    if ($trace[0]['args'][0] == 404) echo 'Bad ID';
    else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
    else echo $e->getMessage();
}
/*** / FIN EXTRACT FICHIER CLIENTS VERS SAP ***/
