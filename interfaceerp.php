<?php
/**
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

define('DEBUG', true);
define('PS_SHOP_PATH', 'https://a2si.xiop.it');
define('PS_WS_AUTH_KEY', '4G3WUT9M8SRJCD1Y9ZT2MICYG2XF2MXV');
require_once('PSWebServiceLibrary.php');

if (!defined('_PS_VERSION_')) {
    exit;
}

class InterfaceErp extends Module
{
   // protected $config_form = false;
    public function __construct()
    {
        $this->name = 'interfaceerp';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Orbiteo';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Interface crm');
        $this->description = $this->l('Mise à jour stock Prestashop<->CRM');

        $this->confirmUninstall = $this->l('Êtes-vous certain de vouloir desinstaller le module?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Execute module install SQL statements
        $sql_file = dirname(__FILE__).'/install/install.sql';
        if (!$this->loadSQLFile($sql_file)) {
            return false;
        }

        if (!$this->registerHook('actionCronJob')) {
            return false;
        }
        if (!$this->installTab('AdminCatalog', 'AdminInterfaceErp', 'Interface crm') ||
            !$this->installTab('AdminParentCustomer', 'AdminCustInterfaceErp', 'Interface crm client')
        ) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        // Execute module install SQL statements
        $sql_file = dirname(__FILE__).'/install/uninstall.sql';
        if (!$this->loadSQLFile($sql_file)) {
            return false;
        }

        // Delete configuration values
        Configuration::deleteByName('MYMOD_MAJPRESTA');
        Configuration::deleteByName('MYMOD_MAJERP');

        if(!$this->uninstallTab('AdminInterfaceErp') ||
            !$this->uninstallTab('AdminCustInterfaceErp')
        ) {
            return false;
      }

      return true;
    }

    public function installTab($parent, $class_name, $name)
    {
        $tab = new Tab();
        $tab->id_parent = (int)Tab::getIdFromClassName($parent);
        $tab->name = array();
        foreach(Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        $tab->class_name = $class_name;
        $tab->module = $this->name;
        $tab->active = 1;
        return $tab->add();
    }

    public function uninstallTab($class_name)
    {
        $id_tab = (int)Tab::getIdFromClassName($class_name);
        $tab = new Tab((int)$id_tab);
        return $tab->delete();
    }

    public function processConfiguration()
    {
        if (Tools::isSubmit('submit_interfaceerp_form'))
        {
            $enable_majPresta = Tools::getValue('enable_majPresta');
            $enable_majErp = Tools::getValue('enable_majErp');
            Configuration::updateValue('MYMOD_MAJPRESTA', $enable_majPresta);
            Configuration::updateValue('MYMOD_MAJERP', $enable_majErp);
            $this->context->smarty->assign('confirmation', 'ok');
        }
    }

    public function assignConfiguration()
    {
        $enable_majPresta = Configuration::get('MYMOD_MAJPRESTA');
        $enable_majErp = Configuration::get('MYMOD_MAJERP');
        $this->context->smarty->assign('enable_majPresta', $enable_majPresta);
        $this->context->smarty->assign('enable_majErp', $enable_majErp);
    }

    public function hookActionCronJob()
    {
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
                $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/customers/')); // Toute la base clients

                //récupération node id_client
                $customers = $xml->children()->children();
                foreach($customers as $customer) {
                  $idExistingCustomer = $customer[0][0]['id'];
                  if(intval($idExistingCustomer) == $data[0]) { // si l'id client est existant dans la base on le met à jour:
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
                        $opt['putXml'] = $xml->asXML(); // Put pour modifier et id obligatoire
                        $opt['id'] = (int)$data[0]; //Obligatoire
                        $xml = $webService->edit($opt);

                    }
                    catch (PrestaShopWebserviceException $e) {
                        $trace = $e->getTrace();
                        if ($trace[0]['args'][0] == 404) echo 'Bad ID';
                        else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
                        else echo $e->getMessage();
                    }
                  }
                  else { // Sinon on le créé
                    try {
                      $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/customers?schema=blank'));
                      //récupération node category
                      $customer = $xml->children()->children();
                      // Nodes obligatoires
                      $customer->id = (int)$data[0];
                      $customer->passwd = $data[4];
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
                      $opt['postXml'] = $xml->asXML();
                      $xml = $webService->add($opt);
                    }
                    catch (PrestaShopWebserviceException $e) {
                        $trace = $e->getTrace();
                        if ($trace[0]['args'][0] == 404) echo 'Bad ID';
                        else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
                        else echo $e->getMessage();
                    }
                  }
                }
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

      /*** MISE À JOUR FICHIER PRODUITS ***/
      if (($handle = fopen(_PS_MODULE_DIR_."/interfaceerp/DF_WEB_products_import.csv", "r")) !== FALSE) { // Import du fichier .csv
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
          $num = count($data);
          if(is_numeric($data[0])) { //On vérifier que la colonne id_catégorie soit un int
            if($data[3] != "NULL") { // On vérifie que la catégorie est non NULL
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
                  $xml = $webService->get(array('url' => PS_SHOP_PATH.'/api/products/'.$data[0]));

                  //récupération node product
                  $product = $xml->children()->children();
                  // Nodes obligatoires =>
                  $product->id = (int)$data[0];
                  $product->price = floatval($data[4]);
                  $product->link_rewrite->language[0][0] = $link_rewriteMinuscules;
                  $product->link_rewrite->language[0][0]['id'] = 2;
                  $product->link_rewrite->language[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/languages/' . 2;

                  $product->name->language[0][0] = $link_rewriteSansAccent;
                  $product->name->language[0][0]['id'] = 2;
                  $product->name->language[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/languages/' . 2;

                  // Nodes optionnels =>

                  // Catégories associées au produit
                  $arrayCategories = explode(",", $data[20]);
                  foreach($arrayCategories as $cat) {
                    $product->associations->categories->category[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/categories/'. intval($cat);
                    $product->associations->categories->category->id = intval($cat);
                  }
                  $product->active = (int)$data[1];
                  $product->reference = $data[6];
                  $product->supplier_reference = $data[7];
                  // les fournisseurs et fabricants sont à renseigner avec l'id correspondant si besoin
                  $product->width = floatval($data[10]);
                  $product->height = floatval($data[11]);
                  $product->depth = floatval($data[12]);
                  $product->weight = floatval($data[13]);
                  //$product->quantity = $data[14];
                  $product->minimal_quantity = $data[15];
                  $product->description_short->language[0][0] = $data[16];
                  $product->description_short->language[0][0]['id'] = 2;
                  $product->description_short->language[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/languages/' . 2;
                  $product->description->language[0][0] = $data[17];
                  $product->description->language[0][0]['id'] = 2;
                  $product->description->language[0][0]['xlink:href'] = PS_SHOP_PATH . '/api/languages/' . 2;
                  $product->available_for_order = $data[18];

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
      }
      /*** /FIN MAJ FICHIER PRODUITS ***/

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

      /*** DÉBUT EXTRACT ORDERS ***/
      $myFileOrders = _PS_MODULE_DIR_.'/interfaceerp/exports/orders/ORDR'.$dateDeLUpdate.'.csv';
      $fhOrders = fopen($myFileOrders, 'w') or die("impossible de créer le fichier");
      //Inscrire les en-tete du fichier csv
      $enteteOrders = ["DocNum", "DocType", "Handwritten", "Printed", "DocDate", "DocDueDate", "CardCode", "CardName", "Address", "NumAtCard", "PaymentMethod"];
      fputcsv($fhOrders, $enteteOrders);

      // 2 fichiers commande à renvoyer à SAP
      $myFile2 = _PS_MODULE_DIR_.'/interfaceerp/exports/orders/RDR1'.$dateDeLUpdate.'.csv';
      $fh2 = fopen($myFile2, 'w') or die("impossible de créer le fichier");
      //Inscrire les en-tete du fichier csv
      $entete2 = ["ParentKey", "ItemCode", "ItemDescription", "Quantity", "Price"];
      fputcsv($fh2, $entete2);

      $arrayORDR = []; //Tableau recap de toutes les en-tete de commandes
      $arrayRDR1 = []; //Tableau recap de toutes les details de commandes

      //sortir le xml orders
      $webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);
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
          $invoiceAddress = $orderDetails->id_address_invoice;
          $orderCustomerId = $orderDetails->id_customer;
          $orderReference = $orderDetails->reference;
          $orderTotalPaid = $orderDetails->total_paid;
          $orderDate = $orderDetails->date_add;
          $orderPaymentType = $orderDetails->payment;
          $orderCustomerName = "";
          $orderIdsProducts = "";
          $orderQuantities = "";

          $productsDetails = $orderDetails->associations->children()->children();
          foreach ($productsDetails as $key) {
            $idProduct = $key->product_id;
            $quantityPerId = $key->product_quantity;
            $productName = $key->product_name;
            $productPrice = $key->product_price;

            $arrayDetailsOrder = array($orderId, $idProduct, $productName, $quantityPerId, $productPrice);
            array_push($arrayRDR1, $arrayDetailsOrder);
          }

            $xml5 = $webService->get(array('url' => PS_SHOP_PATH.'/api/addresses/'.$invoiceAddress));
            $addressDetail = $xml5->children()->children();
            $orderInvoiceAddress = $addressDetail->address1 .' '.$addressDetail->postcode .' '.$addressDetail->city;

          // faire un appel API des customers pour retrouver les noms correspondants aux commandes
          $xml2 = $webService->get(array('resource' => 'customers'));
          $totalCustomers = $xml2->customers->children();
          //Faire une boucle pour sortir tous les id
          foreach ($totalCustomers as $customer) {
            $idCustomer = $customer->attributes();
            // faire un appel API avec chaque id
            $xml3 = $webService->get(array('url' => PS_SHOP_PATH.'/api/customers/'.$idCustomer));
            $customerDetail = $xml3->children()->children();
            $idCustomerDetail = $customerDetail->id;
            if(intval($orderCustomerId) == intval($idCustomerDetail)) {
              $orderCustomerName = $customerDetail->firstname .' '.$customerDetail->lastname;
            }
          }
          $arrayEnteteOrder = array($orderId, "dDocument_Items", "tYES", "psNo", $orderDate, $orderDate, $orderCustomerId, $orderCustomerName, $orderInvoiceAddress, $orderReference, $orderPaymentType);
          array_push($arrayORDR, $arrayEnteteOrder); // ajouter chaque tableau client au tableau général
        }
        // remplir le fichier csv avec ces données avec la méthode fputcsv()
        foreach ($arrayORDR as $fields) {
          fputcsv($fhOrders, $fields);
        }
        fclose($fhOrders);

        foreach ($arrayRDR1 as $fields2) {
          fputcsv($fh2, $fields2);
        }
        fclose($fh2);
      }
      catch (PrestaShopWebserviceException $e) {
          $trace = $e->getTrace();
          if ($trace[0]['args'][0] == 404) echo 'Bad ID';
          else if ($trace[0]['args'][0] == 401) echo 'Bad auth key';
          else echo $e->getMessage();
      }
      /*** / FIN EXTRACT ORDERS ***/
    }

    public function loadSQLFile($sql_file) {
        $sql_content = file_get_contents($sql_file);
        $sql_content = str_replace('PREFIX_', _DB_PREFIX_, $sql_content);
        $sql_requests = preg_split("/;\s*[\r\n]+/", $sql_content);
        $result = true;
        foreach($sql_requests as $request) {
            if(!empty($request)) {
                $result &= Db::getInstance()->execute(trim($request));
            }
        }
        return $result;
    }

    public function getCronFrequency() {
        return array(
            'hour' => -1,
            'day' => -1,
            'month' => -1,
            'day_of_week' => -1
        );
    }

    public function getContent() //On affiche le contenu
    {
        $this->processConfiguration();
        $this->assignConfiguration();
        return $this->display(__FILE__, 'getContent.tpl');
    }
}
