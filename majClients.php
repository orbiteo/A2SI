<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
define('DEBUG', true);
define('PS_SHOP_PATH', 'https://a2si.xiop.it');
define('PS_WS_AUTH_KEY', '4G3WUT9M8SRJCD1Y9ZT2MICYG2XF2MXV');
require_once('./PSWebServiceLibrary.php');

/*** MISE À JOUR FICHIER CLIENTS ***/
if (($handle = fopen(_PS_MODULE_DIR_.'/interfaceerp/DF_WEB_customers_import.csv', 'r')) !== FALSE) { // Import du fichier .csv
  while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
    $num = count($data);
    if(is_numeric($data[0])) { //On vérifier que la colonne id_client soit un int
      // Création du link_rewrite sans accent, espace, etc...
      $link_rewriteSansAccent = strtr($data[2], '@ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ', 'aAAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');
      $link_rewriteSansEspace = strtr($link_rewriteSansAccent, ' ', '-');
      $link_rewriteSansApost = strtr($link_rewriteSansEspace, "'", '-');
      $link_rewriteSansPoint = strtr($link_rewriteSansApost, ".", '-');
      $link_rewriteMinuscules = strtolower($link_rewriteSansPoint);
      $nameSansApos = strtr($link_rewriteSansAccent,  "'", '-');
      $nameSansAposMin = strtolower($nameSansApos);

      /*** APPEL API PRESTASHOP ***/
      $webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
      try {
          //Mise à jour des clients existants
          $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/customers/'.$data[0]));

          //récupération node category
          $customer = $xml->children()->children();
          // Nodes obligatoires
          $customer->id = (int)$data[0];
          //$customer->passwd = $data[4];
          $customer->lastname = $data[5];
          $customer->firstname = $data[6];
          $customer->email = $data[3];

          // Nodes optionnels
          $customer->active = (int)$data[1];
          $customer->id_gender = (int)$data[2];
          $customer->newsletter = (int)$data[7];
          $customer->id_shop_group = 1;
          $customer->id_shop = 1;
          $customer->id_default_group = 3;
          $customer->id_lang = 2;

          //Envoie des données
          $opt = array('resource' => 'customers');
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
      /*** / FIN APPEL API PRESTASHOP ***/
    }
  }
}
/*** / FIN MISE À JOUR FICHIER CLIENTS ***/
