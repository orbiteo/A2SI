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
$enteteQuotes = ["Type", "ID Client", "ID quote", "Nom Client", "Email CLient", "Reference Produit", "Quantite", "Siren"];
fputcsv($fhQuotes, $enteteQuotes);

$orderReference = "";

$arrayQuotes = []; //Tableau recap de tous les devis

$SQL = Db::getInstance()->executeS("SELECT id_quote, id_customer, products
    FROM "._DB_PREFIX_."quotes");

$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);



for($i=0 ; $i<count($SQL) ; $i++) { // boucle qui scale les devis

    $id_quote = $SQL[$i]['id_quote'];
    $id_customer = $SQL[$i]['id_customer'];
    $customerName = '';
    $customerEmail = '';
    $customerSiret = '';

    try {
        $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/customers/'.$id_customer)); // On va sortir chaque fiche client   
        $customer = $xml->children()->children();
        $customerName = $customer->lastname;
        $customerEmail = $customer->email;
        $customerSiret = $customer->siret;
    }
    catch (PrestaShopWebserviceException $e) { // si id_customer inexistant
        $trace = $e->getTrace();
        if ($trace[0]['args'][0] == 404){
            $customerName = "Non trouvé";
            $customerEmail = "Non trouvé";
            $customerSiret = "non trouvé";
        }
        else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
        else echo $e->getMessage();
    }

    $arrayProductRow = unserialize($SQL[$i]['products']); // Sortir sous forme de tableau le format json de la table
    $ids_product = array();
    $quantities_product = array();
    foreach($arrayProductRow as $details) { // pour chaque poroduit présent dans le devis on sort son id et sa quantité dans un tableau
        array_push($ids_product, $details["id"]);
        array_push($quantities_product, $details["quantity"]);
    }
    // Appeler la table products avec chaque id pour sortir leurs références et créer une ligne pour chaque
    for($j=0 ; $j<count($ids_product) ; $j++) { // pour chaque id produit, on va chercher la ref produit
        try {
            $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/products/'.$ids_product[$j])); // On va sortir chaque fiche client   
            $product = $xml->children()->children();
            $productReference = $product->reference;
        }
        catch (PrestaShopWebserviceException $e) { // si id_product inexistant
            $trace = $e->getTrace();
            if ($trace[0]['args'][0] == 404){
                $productReference = "Non trouvé";
            }
            else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
            else echo $e->getMessage();
        }
        $arrayDetQuotes = array("L", $productReference, $quantities_product[$j]);
        array_push($arrayQuotes, $arrayDetQuotes); // ajouter chaque tableau détail quotes au tableau général
    }

    $arrayEnteteQuotes = array("T", $id_customer, $id_quote, $customerName, $customerEmail, $customerSiret);
    array_push($arrayQuotes, $arrayEnteteQuotes); // ajouter chaque tableau quotes au tableau général  
}

 // remplir le fichier csv avec ces données avec la méthode fputcsv()
 foreach ($arrayQuotes as $fields) {
    fputcsv($fhQuotes, $fields);
  }
  fclose($fhQuotes);
