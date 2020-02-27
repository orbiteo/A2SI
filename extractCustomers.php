<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
define('DEBUG', true);
define('PS_SHOP_PATH', 'https://a2si.xiop.it');
define('PS_WS_AUTH_KEY', '4G3WUT9M8SRJCD1Y9ZT2MICYG2XF2MXV');
require_once('./PSWebServiceLibrary.php');


/*** EXTRACT FICHIER CLIENTS VERS SAP ***/
$dateDeLUpdate = date('Y-m-d');

$myFileCustomers = _PS_MODULE_DIR_.'/interfaceerp/exports/clients/clients'.$dateDeLUpdate.'.csv';
$fhCustomers = fopen($myFileCustomers, 'w') or die("impossible de créer le fichier");
//Inscrire les en-tete du fichier csv
$enteteCustomers = ["Id Client", "Code SAP"];
fputcsv($fhCustomers, $enteteCustomers);
$arrayCustomers = []; //Tableau recap de tous les clients

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
    $customerCodeSap = $customerDetails->code_sap;

    //OCPR - "Id Client", "Code SAP"
    $arrayAllCustomers = array($customerId, $customerCodeSap);
    array_push($arrayCustomers, $arrayAllCustomers ); // ajouter chaque tableau client au tableau général
  }
  // remplir le fichier csv avec ces données avec la méthode fputcsv()
  foreach ($arrayCustomers as $fields) {
    fputcsv($fhCustomers, $fields);
  }
  fclose($fhCustomers);
}
catch (PrestaShopWebserviceException $e) {
    $trace = $e->getTrace();
    if ($trace[0]['args'][0] == 404) echo 'Bad ID';
    else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
    else echo $e->getMessage();
}
/*** / FIN EXTRACT FICHIER CLIENTS VERS SAP ***/
