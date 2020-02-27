<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');
define('DEBUG', true);
define('PS_SHOP_PATH', 'https://a2si.xiop.it');
define('PS_WS_AUTH_KEY', '4G3WUT9M8SRJCD1Y9ZT2MICYG2XF2MXV');
require_once('./PSWebServiceLibrary.php');

/*** MISE À JOUR FICHIER CLIENTS ***/
$arrayClients = []; // array qui va récupérer toutes les infos client du fichier csv
$dateDeLUpdate = date('Y-m-d');
if (($handle = fopen(_PS_MODULE_DIR_.'/interfaceerp/imports/customers/'.$dateDeLUpdate.'_001_customer.csv', 'r')) !== FALSE) { // Import du fichier .csv
  while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
    if(is_numeric($data[0])) { //On vérifier que la colonne id_client soit un int
      array_push($arrayClients, $data);
    }
    elseif(empty($data[0])) { // En cas de nouveau client, la colonne id client et vide, on va en créé un faux
      $SQL = Db::getInstance()->executeS("SELECT MAX(id_customer) AS idMax
        FROM "._DB_PREFIX_."customer"); // retourne l'id le + élevé
        $data[0] = (int)$SQL[0]["idMax"]+1; // id client
        array_push($arrayClients, $data);
    }
  }

  for($i=0 ; $i<count($arrayClients) ; $i++){
      // Création du link_rewrite sans accent, espace, etc...
      $adresseSansAccent = strtr($arrayClients[$i][7], '@ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ', 'aAAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');
      $adresseSansPointVirgule = strtr($adresseSansAccent, ';', ','); //adresse facturation
      $adresse2SansAccent = strtr($arrayClients[$i][8], '@ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ', 'aAAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');
      $adresse2SansPointVirgule = strtr($adresse2SansAccent, ';', ',');
      $adresse3SansAccent = strtr($arrayClients[$i][13], '@ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ', 'aAAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');
      $adresse3SansPointVirgule = strtr($adresse3SansAccent, ';', ','); // adresse livraison
      $adresse4SansAccent = strtr($arrayClients[$i][14], '@ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ', 'aAAAAAACEEEEIIIIOOOOOUUUUYaaaaaaceeeeiiiioooooouuuuyy');
      $adresse4SansPointVirgule = strtr($adresse4SansAccent, ';', ',');

      /*** APPEL API PRESTASHOP ***/
      $webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
      try {
          //Mise à jour des clients existants
          $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/customers/'.$arrayClients[$i][0]));
          //récupération node category
          $customer = $xml->children()->children();
          // Nodes obligatoires
          $customer->id = (int)$arrayClients[$i][0];
          $customer->lastname = $arrayClients[$i][4];
          $customer->firstname = $arrayClients[$i][5];
          $customer->email = $arrayClients[$i][3];

          // Nodes optionnels
          $customer->active = (int)$arrayClients[$i][1];
          $customer->id_shop_group = 1;
          $customer->id_shop = 1;
          $customer->id_default_group = 3;
          $customer->id_lang = 2;
          $customer->company = $arrayClients[$i][18];
          $customer->code_sap = $arrayClients[$i][19];

          //Envoie des données
          $opt = array('resource' => 'customers');
          $opt['putXml'] = $xml->asXML();
          $opt['id'] = (int)$arrayClients[$i][0]; //Obligatoire
          $xml = $webService->edit($opt);
          $ps_id_customer = $xml->customer->id;

          // Si colonne adresse facturation non empty:
          if(!empty($arrayClients[$i][6])) {
            // Modification de la table adresse // adresse de facturation
            try {
              $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/addresses/'.$arrayClients[$i][6]));
              $address = $xml->children()->children();
              // Nodes obligatoires
              $address->id_customer = $ps_id_customer;
              $address->id_country = 8; //france
              $address->alias = 'Facturation'; 
              $address->lastname = $arrayClients[$i][4];
              $address->firstname = $arrayClients[$i][5];
              $address->address1 = $adresseSansPointVirgule;
              $address->city = $arrayClients[$i][10];
              // Nodes optionnels
              $address->address2 = $adresse2SansPointVirgule;
              $address->postcode = $arrayClients[$i][9];

              //Envoie des données
              $opt = array('resource' => 'addresses');
              $opt['putXml'] = $xml->asXML();
              $opt['id'] = (int)$arrayClients[$i][6]; //Obligatoire
              $xml = $webService->edit($opt);
            }
            catch (PrestaShopWebserviceException $e) {
              $trace = $e->getTrace();
              if ($trace[0]['args'][0] == 404) echo 'Bad ID';
              else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
              else echo $e->getMessage();
            }
          }
        // Si colonne adresse livraison non empty:
        if(!empty($arrayClients[$i][12])) { 
          // Modification de la table adresse // adresse de livraison
          try {
            $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/addresses/'.$arrayClients[$i][12]));
            $address = $xml->children()->children();
            // Nodes obligatoires
            $address->id_customer = $ps_id_customer;
            $address->id_country = 8; //france
            $address->alias = 'Livraison'; 
            $address->lastname = $arrayClients[$i][4];
            $address->firstname = $arrayClients[$i][5];
            $address->address1 = $adresse3SansPointVirgule;
            $address->city = $arrayClients[$i][10];
            // Nodes optionnels
            $address->address2 = $adresse4SansPointVirgule;
            $address->postcode = $arrayClients[$i][9];

            //Envoie des données
            $opt = array('resource' => 'addresses');
            $opt['putXml'] = $xml->asXML();
            $opt['id'] = (int)$arrayClients[$i][12]; //Obligatoire
            $xml = $webService->edit($opt);
          }
          catch (PrestaShopWebserviceException $e) {
            $trace = $e->getTrace();
            if ($trace[0]['args'][0] == 404) echo 'Bad ID';
            else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
            else echo $e->getMessage();
          }
        }
      }
      catch (PrestaShopWebserviceException $e) {
          $trace = $e->getTrace();
          if ($trace[0]['args'][0] == 404){ // id client non trouvé = nouveau client, on le créé
            //Création du client
            $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/customers?schema=blank'));
    
            //récupération node category
            $customer = $xml->children()->children();
            // Nodes obligatoires
            $customer->lastname = $arrayClients[$i][4];
            $customer->firstname = $arrayClients[$i][5];
            $customer->email = $arrayClients[$i][3];
    
            // Nodes optionnels
            $customer->active = (int)$arrayClients[$i][1];
            $customer->id_shop_group = 1;
            $customer->id_shop = 1;
            $customer->id_default_group = 3;
            $customer->id_lang = 2;
            $customer->company = $arrayClients[$i][18];
            $customer->code_sap = $arrayClients[$i][19];
    
              //Envoie des données
            $opt = array('resource' => 'customers');
            $opt['postXml'] = $xml->asXML();
            $xml = $webService->add($opt);
            $ps_id_customer = $xml->customer->id;

            // Si colonne adresse facturation non empty:
            if(!empty($arrayClients[$i][7])) {
              // Création de l'adresse // adresse de facturation
              $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/addresses?schema=blank'));
              $address = $xml->children()->children();
              // Nodes obligatoires
              $address->id_customer = $ps_id_customer;
              $address->id_country = 8; //france
              $address->alias = 'Facturation'; 
              $address->lastname = $arrayClients[$i][4];
              $address->firstname = $arrayClients[$i][5];
              $address->address1 = $adresseSansPointVirgule;
              $address->city = $arrayClients[$i][10];
              // Nodes optionnels
              $address->address2 = $adresse2SansPointVirgule;
              $address->postcode = $arrayClients[$i][9];

              //Envoie des données
              $opt = array('resource' => 'addresses');
              $opt['postXml'] = $xml->asXML();
              $xml = $webService->add($opt);
            }
            // Si colonne adresse livraison non empty:
            if(!empty($arrayClients[$i][13])) { 
            // Création de l'adresse // adresse de livraison
            $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/addresses?schema=blank'));
            $address = $xml->children()->children();
            // Nodes obligatoires
            $address->id_customer = $ps_id_customer;
            $address->id_country = 8; //france
            $address->alias = 'Livraison'; 
            $address->lastname = $arrayClients[$i][4];
            $address->firstname = $arrayClients[$i][5];
            $address->address1 = $adresse3SansPointVirgule;
            $address->city = $arrayClients[$i][10];
            // Nodes optionnels
            $address->address2 = $adresse4SansPointVirgule;
            $address->postcode = $arrayClients[$i][9];

            //Envoie des données
            $opt = array('resource' => 'addresses');
            $opt['postXml'] = $xml->asXML();
            $xml = $webService->add($opt);
          }
       }
        else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
        else echo $e->getMessage();
      }
      /*** / FIN APPEL API PRESTASHOP ***/
    }
  }
/*** / FIN MISE À JOUR FICHIER CLIENTS ***/
