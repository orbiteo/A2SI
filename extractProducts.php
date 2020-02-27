<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
define('DEBUG', true);
define('PS_SHOP_PATH', 'https://a2si.xiop.it');
define('PS_WS_AUTH_KEY', '4G3WUT9M8SRJCD1Y9ZT2MICYG2XF2MXV');
require_once('./PSWebServiceLibrary.php');


/*** EXTRACT FICHIER PRODUITS VERS SAP ***/
//créer fichier .csv
$dateDeLUpdate = date('Y-m-d');
$myFile = _PS_MODULE_DIR_.'/interfaceerp/exports/produits/produits'.$dateDeLUpdate.'.csv';
$fh = fopen($myFile, 'w') or die("impossible de créer le fichier");
//Inscrire les en-tete du fichier csv
$entete = ["Product ID", "Reference article"];
fputcsv($fh, $entete);

$arrayDetailsAllProducts = []; //Tableau recap de toutes les commandes

$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
try {
  $xml = $webService->get(array('resource' => 'products'));
  $totalproducts = $xml->products->children();
  //Faire une boucle pour sortir tous les id
  foreach ($totalproducts as $product) {
    $idProduct = $product->attributes();
    // faire un appel API avec chaque id
    $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/products/'.$idProduct));
    $productDetails = $xml->children()->children();
    // extraire les données dans des variables puis dans un tableau par id, puis dans un tableau de tous les id
    $productId = $productDetails->id;
    $productReference = $productDetails->reference;
    $arrayDetailsProduct = array($productId, $productReference); 
    array_push($arrayDetailsAllProducts, $arrayDetailsProduct); // ajouter chaque tableau client au tableau général
  }
  // remplir le fichier csv avec ces données avec la méthode fputcsv()
  foreach ($arrayDetailsAllProducts as $fields) {
    fputcsv($fh, $fields);
  }
  fclose($fh);
}
catch (PrestaShopWebserviceException $e) {
    $trace = $e->getTrace();
    if ($trace[0]['args'][0] == 404) echo 'Bad ID';
    else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
    else echo $e->getMessage();
}
/*** FIN EXTRACT FICHIER PRODUITS VERS SAP ***/
