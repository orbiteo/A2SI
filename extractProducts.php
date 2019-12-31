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
$entete = ["Product ID", "Active", "Name", "Price", "Reference", "Supplier reference", "Supplier id", "Manufacturer id", "Width", "Height", "Depth", "Weigth", "Quantity", "Minimal quantity", "Short description", "Description", "Available for order"];
fputcsv($fh, $entete);

$arrayDetailsAllProducts = []; //Tableau recap de toutes les commandes

//sortir le xml products
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
    $productActive = $productDetails->active;
    $productName = $productDetails->name->language[0][0];
    //$productCategories = $productDetails->active; // faire un boucle pour sortir tous les id de categorie
    $productPrice = $productDetails->price;
    $productReference = $productDetails->reference;
    $productSupplierRefence = $productDetails->supplier_reference;
    $productIdSupplier = $productDetails->id_supplier;
    $productIdManufacturer = $productDetails->id_manufacturer;
    $productWidth = $productDetails->width;
    $productHeight = $productDetails->height;
    $productDepth = $productDetails->depth;
    $productWeight = $productDetails->weight;
    $productQuantity = $productDetails->quantity;
    $productMinimalQuantity = $productDetails->minimal_quantity;
    $productDescriptionShort = $productDetails->description_short->language[0][0];
    $productDescription = $productDetails->description->language[0][0];
    $productAvailableForOrder = $productDetails->available_for_order;
    $arrayDetailsProduct = array($productId, $productActive, $productName, $productPrice, $productReference, $productSupplierRefence, $productIdSupplier, $productIdManufacturer, $productWidth, $productHeight, $productDepth, $productWeight, $productQuantity, $productMinimalQuantity, $productDescriptionShort, $productDescription, $productAvailableForOrder); // id, active, name, categories, price, reference, supplier reference, supplier, manufacturer, width, height, depth, weight, quantity, minimal quantity, short description, description, available for order
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
