<?php
defined ( 'BASEPATH' ) or exit ( 'No direct script access allowed' );
include_once (dirname(__FILE__) . "/PriceProfile.php");
include_once (dirname ( __FILE__ ) . "/Event.php");
class Product extends CI_Controller {
	const base_path = "/Images/";
	
	public function __construct() {
		parent::__construct ();
		$this->load->library ( 'session' );
		$this->load->library(array('form_validation'));
	}
	
	public function index() {
		$this->load->template ( 'errors/404' );
	}
	public function showSinglePicture($prod_id) {
		$data['product']  = Product::getProduct($prod_id);
		$this->load->template ( 'product/single_picture_view', $data );
	}
	
	public static function buildFilePath($p)
	{
		//Datum-String in Datum umwandeln
		$date=date_create($p->prod_date);
		//Dateipfad erstellen. Bsp.: "/Images/2016/12/001.png"
		$path = Product::base_path . date_format($date,"o/m") . "/" . $p->prod_filepath;
		return $path;
	}
	
	public static function getProduct($prod_id)
	{
		$CI =& get_instance();
		$CI->load->model('product_model');
		$product = $CI->product_model->getSingleProduct($prod_id);
 		$product[0]->product_variants = Product::getProductVariants($prod_id);
 		$product[0]->prod_filepath = Product::buildFilePath($product[0]);
		return $product[0];
	}
	
	
	public static function getProductVariants($prod_id)
	{
		$CI =& get_instance();
		$CI->load->model('product_model');
		$product_variants = $CI->product_model->getProductVariants($prod_id);
		
		//Preis aus Preisprofil besorgen
		foreach ($product_variants as $product_variant) {
			$event = Event::getSingleEventById ( $product_variant->prod_even_id );
			$price = PriceProfile::getPriceByProductType ( $event->even_prpr_id, $product_variant->prva_prty_id );
			$product_variant->price = $price;
		}

		return $product_variants;
	}
	
	function insert()
	{
// 		$this->form_validation->set_rules('dateiupload', 'Dateiname', 'trim|required|min_length[3]|max_length[30]');
		
		//Konstanten setzten
		$prod_status = ProductStatus::locked;
		$prod_date = date("Y-m-d H:i:s");
	
		//Event laden
		$prod_even_id = $this->input->post('even_id');
		$this->load->model('event_model');
		$event = $this->event_model->getSingleEventById($prod_even_id);
		

		$files = $_FILES;
		$cpt = count($_FILES['dateiupload']['name']);
		for($i=0; $i<$cpt; $i++)
		{
			//Dateidaten können geändert werden
			$filename = $files['dateiupload']['name'][$i];
			$_FILES['dateiupload']['name'] = $filename;
			$_FILES['dateiupload']['type']= $files['dateiupload']['type'][$i];
			$_FILES['dateiupload']['tmp_name']= $files['dateiupload']['tmp_name'][$i];
			$_FILES['dateiupload']['error']= $files['dateiupload']['error'][$i];
			$_FILES['dateiupload']['size']= $files['dateiupload']['size'][$i];
		
			if($filename)
			{
				$prod_name =  $this->get_name($filename);
				$prod_filepath = $filename;
				
				$data = array(
						'prod_date' => $prod_date,
						'prod_even_id' => $prod_even_id,
						'prod_name' => $prod_name,
						'prod_status' => $prod_status,
						'prod_filepath' => $prod_filepath
				);
				
				$this->load->model('product_model');
				
				//Produkt einfügen
				$new_prod_id = $this->product_model->insert_product($data);
				if ($new_prod_id)
				{
					// Varianten mit Preisprofil aus Event anlegen
					$this->insert_product_variant($new_prod_id, $event[0]);
				}
				else
				{
					// error
					$this->session->set_flashdata('msg','<div class="alert alert-danger text-center">Oops! Error.  Please try again later!!!</div>');
				}
			}
			else
			{
				// error
				$this->session->set_flashdata('msg','<div class="alert alert-danger text-center">Keine Datei ausgewählt!!!</div>'.$dateiupload.'');
			}
		}
		
		
		//Wasserzeichen hochladen
		/*
		 * HIer muss noch etwas passieren
		 */
		$this->upload('./Images/');
		
		//Orginal-Dateien uploaden
		$this->upload('../Images/');
		
		//Eventseite neu laden
		redirect('event/' . $event[0]->even_url);
		
	}
	
	function get_name($filename)
	{
		$name = "";
		$pos = strpos($filename, "\\");
		$pos2 = strrpos($filename, ".");
	
	 	$name = substr($filename, $pos, $pos2); 
		return $name;
	}
	
	function get_fileext($filename)
	{
		$pos2 = strrpos($filename, ".");
	
		$ext = substr($filename, $pos2, strlen($filename)-$pos2);
		return $ext;
	}
	
	function upload($path)
	{
		// Monat und Jahr für Uploadordner festlegen
		$month = date('m');
		$year = date('Y');
				
		$config['upload_path']          = $path . $year .'/'. $month;
		$config['allowed_types']        = 'gif|jpg|png';

		$this->load->library('upload', $config);
		$this->upload->initialize($config);
		
		
			
		if ( ! $this->upload->do_upload())
		{
			$this->session->set_flashdata('msg',$this->upload->display_errors());
		}
		else
		{
			$finfo=$this->upload->data();
			$this->session->set_flashdata('msg','<div class="alert alert-success text-center"> '.$finfo['file_name'].' hochgeladen!</div>');
		
		}
	}
	
	function insert_product_variant($prod_id, $event)
	{
		$prpr_id= $event->even_prpr_id;
		$this->load->model('product_model');
		$price_profile = PriceProfile::getPriceProfile($prpr_id);
		//Zu allen Formaten im Preisprofil wird eine Variante angelegt
		foreach ($price_profile->prices as $price)
		{
			$data = array(
					'prva_prod_id' => $prod_id,
					'prva_prty_id' => $price->prpt_prty_id
			);
			$this->product_model->insert_product_variant($data);
		}
	}
}

abstract class ProductStatus
{
	const undefined	= 0;
	const locked	= 1;
	const approved	= 2;
	const deleted	= 3;
}
