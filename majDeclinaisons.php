<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
define('DEBUG', true);
define('PS_SHOP_PATH', 'https://a2si.xiop.it');
define('PS_WS_AUTH_KEY', '4G3WUT9M8SRJCD1Y9ZT2MICYG2XF2MXV');
require_once('./PSWebServiceLibrary.php');

$arrayFichesCombinations = [];
$dateDeLUpdate = date('Y-m-d');
if (($handle = fopen(_PS_MODULE_DIR_."/interfaceerp/imports/combinations/".$dateDeLUpdate."_301_declinaisons.csv", "r")) !== FALSE) { // Import du fichier .csv
  while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
    if(is_numeric($data[0])) { //On vérifie que la colonne id soit un int
      array_push($arrayFichesCombinations, $data);
    }
    elseif(empty($data[0]) && !empty($data[1])) { // On va créer une déclinaison que si l'id produit est déjà créé
      $SQL = Db::getInstance()->executeS("SELECT MAX(id_product_attribute) AS idMax
        FROM "._DB_PREFIX_."product_attribute"); // retourne l'id le + élevé
        $data[0] = (int)$SQL[0]["idMax"]+1; // fake id_combination créé 
        array_push($arrayFichesCombinations, $data);
    }
  }
  for($i=0 ; $i<count($arrayFichesCombinations) ; $i++){
        // Création du link_rewrite sans accent, espace, etc...
        $unwanted_array = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
        $link_rewriteSansAccent = strtr($arrayFichesCombinations[$i][3], $unwanted_array);
        //$link_rewriteSansAccent = strtr($data[2], '@ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ', 'aAAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');
        $link_rewriteSansEspace = strtr($link_rewriteSansAccent, ' ', '-');
        $link_rewriteSansApost = strtr($link_rewriteSansEspace, "'", '-');
        $link_rewriteSansPoint = strtr($link_rewriteSansApost, ".", '-');
        $link_rewriteSansVirgule = strtr($link_rewriteSansPoint, ",", '-');
        $link_rewriteMinuscules = strtolower($link_rewriteSansVirgule);
        $nameSansApos = strtr($link_rewriteSansAccent,  "'", '-');
        $nameSansAposMin = strtolower($nameSansApos);

        /*** APPEL API PRESTASHOP ***/
        $webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
        try {
            //Récupération du format xml attendu en retour
            $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/combinations/'.$arrayFichesCombinations[$i][0]));

            //récupération node product
            $declinaison = $xml->children()->children();
            // Nodes obligatoires =>
            $declinaison->id_product = (int)$arrayFichesCombinations[$i][1];
            $declinaison->minimal_quantity = 1;

            // Nodes optionnels =>
            $declinaison->reference = $nameSansApos;
            $declinaison->unit_price_impact = floatval($arrayFichesCombinations[$i][4]);

            //Envoi des données
            $opt = array('resource' => 'combinations');
            $opt['putXml'] = $xml->asXML();
            $opt['id'] = (int)$arrayFichesCombinations[$i][0]; //Obligatoire
            $xml = $webService->edit($opt);
            $ps_id_combination = $xml->combination->id;

            // Mise à jour des quantités dans la table stock_availables
            $SQL = Db::getInstance()->executeS("SELECT id_stock_available
            FROM "._DB_PREFIX_."stock_available
            WHERE id_product = ". $arrayFichesCombinations[$i][1]."
            AND id_product_attribute = ".$ps_id_combination); // rechercher dans un 1er temps l'id correspondant à l'id_product_attribute
            $ps_id_stock_available = (int)$SQL[0]["id_stock_available"];

            try {
              //Récupération du format xml attendu en retour
              $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/stock_availables/'.$ps_id_stock_available));//récupération node product
              $stock = $xml->children()->children();
              $stock->id_product = (int)$arrayFichesCombinations[$i][1];
              $stock->id_product_attribute = (int)$arrayFichesCombinations[$i][0];
              $stock->quantity = (int)$arrayFichesCombinations[$i][5];
              $stock->depends_on_stock = 0; // Toujours à zéro
              $stock->out_of_stock = 0; // 0=en stock 2=out of stock

              //Envoi des données
              $opt = array('resource' => 'stock_availables');
              $opt['putXml'] = $xml->asXML();
              $opt['id'] = $ps_id_stock_available; //Obligatoire
              $xml = $webService->edit($opt);
            } 
            catch (PrestaShopWebserviceException $e) {
              if ($trace[0]['args'][0] == 404) echo 'Bad ID';
              else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
              else echo $e->getMessage();
            }

        }
        // Création d'une déclinaison :
        catch (PrestaShopWebserviceException $e) {
            $trace = $e->getTrace();
            if ($trace[0]['args'][0] == 404) { // Si id inexistant => création de la déclinaison
              //Récupération du format xml attendu en retour
              $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/combinations?schema=blank'));

              //récupération node product
              $declinaison = $xml->children()->children();
              // Nodes obligatoires =>
              $declinaison->id_product = (int)$arrayFichesCombinations[$i][1];
              $declinaison->minimal_quantity = 1;

              // Nodes optionnels =>
              $declinaison->reference = $nameSansApos;
              $declinaison->unit_price_impact = floatval($arrayFichesCombinations[$i][4]);

              //Envoi des données
              $opt = array('resource' => 'combinations');
              $opt['postXml'] = $xml->asXML();
              $xml = $webService->add($opt);
              $ps_id_combination = $xml->combination->id;

              try {
                //Récupération du format xml attendu en retour
                $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/stock_availables?schema=blank'));//récupération node product
                $stock = $xml->children()->children();
                $stock->id_product = (int)$arrayFichesCombinations[$i][1];
                $stock->id_product_attribute = (int)$arrayFichesCombinations[$i][0];
                $stock->quantity = (int)$arrayFichesCombinations[$i][5];
                $stock->depends_on_stock = 0; // Toujours à zéro
                $stock->out_of_stock = 0; // 0=en stock 2=out of stock

                //Envoi des données
                $opt = array('resource' => 'stock_availables');
                $opt['postXml'] = $xml->asXML();
                $xml = $webService->add($opt);
              } 
              catch (PrestaShopWebserviceException $e) {
                if ($trace[0]['args'][0] == 404) echo 'Bad ID';
                else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
                else echo $e->getMessage();
              }

            }
            else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
            else echo $e->getMessage();
        }
        /*** FIN APPEL API PRESTASHOP ***/
  }
}
