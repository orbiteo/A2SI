<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
define('DEBUG', true);
define('PS_SHOP_PATH', 'https://a2si.xiop.it');
define('PS_WS_AUTH_KEY', '4G3WUT9M8SRJCD1Y9ZT2MICYG2XF2MXV');
require_once('./PSWebServiceLibrary.php');

$row = 1;
if (($handle = fopen(_PS_MODULE_DIR_."/DF_WEB_declinaisons.csv", "r")) !== FALSE) { // Import du fichier .csv
  while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
    $num = count($data);
    $row++;
    if(is_numeric($data[0])) { //On vérifier que la colonne id_catégorie soit un int
        // Création du link_rewrite sans accent, espace, etc...
        $unwanted_array = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
        $link_rewriteSansAccent = strtr($data[2], $unwanted_array);
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
            $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/combinations?schema=blank'));

            //récupération node product
            $declinaison = $xml->children()->children();
            // Nodes obligatoires =>
            $declinaison->id_product = (int)$data[0];

            // Nodes optionnels =>
            $declinaison->quantity = (int)$data[10];
            $declinaison->reference = $nameSansApos;
            $declinaison->unit_price_impact = floatval($data[7]);
            $declinaison->minimal_quantity = (int)$data[11];

            $declinaison->price = floatval($data[4]);
            $declinaison->link_rewrite->language[0][0] = $link_rewriteMinuscules;
            $declinaison->link_rewrite->language[0][0]['id'] = 2;
            $declinaison->link_rewrite->language[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/languages/' . 2;

            $declinaison->name->language[0][0] = $link_rewriteSansAccent;
            $declinaison->name->language[0][0]['id'] = 2;
            $declinaison->name->language[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/languages/' . 2;



            // Catégories associées au produit
            $arrayCategories = explode(",", $data[20]);
            foreach($arrayCategories as $cat) {
              $declinaison->associations->categories->category[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/categories/'. intval($cat);
              $declinaison->associations->categories->category->id = intval($cat);
            }
            $declinaison->active = (int)$data[1];
            $declinaison->reference = $data[6];
            $declinaison->supplier_reference = $data[7];
            // decommenter lorsqu'on aura les id fournisseurs et fabricants dans le fichier =>
            /*$declinaison->id_supplier = (int)$data[8];
            $declinaison->id_supplier[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/suppliers/' . (int)$data[8];
            $declinaison->id_manufacturer = $data[9];
            $declinaison->id_manufacturer[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/manufacturers/' . (int)$data[9];*/

            $declinaison->width = floatval($data[10]);
            $declinaison->height = floatval($data[11]);
            $declinaison->depth = floatval($data[12]);
            $declinaison->weight = floatval($data[13]);
            //$declinaison->quantity = $data[14];
            $declinaison->minimal_quantity = $data[15];
            $declinaison->description_short->language[0][0] = $data[16];
            $declinaison->description_short->language[0][0]['id'] = 2;
            $declinaison->description_short->language[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/languages/' . 2;
            $declinaison->description->language[0][0] = $data[17];
            $declinaison->description->language[0][0]['id'] = 2;
            $declinaison->description->language[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/languages/' . 2;
            $declinaison->available_for_order = $data[18];

            //Envoi des données
            $opt = array('resource' => 'products');
            $opt['putXml'] = $xml->asXML();
            $opt['id'] = (int)$data[0]; //Obligatoire
            $xml = $webService->edit($opt);
        }
        catch (PrestaShopWebserviceException $e) {
            $trace = $e->getTrace();
            if ($trace[0]['args'][0] == 404) echo 'Bad ID';
            else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
            else echo $e->getMessage();
        }
        /*** FIN APPEL API PRESTASHOP ***/
    }
  }
}
