<?php

if(!class_exists('magic_connector')){

	class magic_connector {
        
        protected $woo;
        
        public $platform;
        
        public function __construct() {

            global $magic, $magic_woo, $wpdb;
			
            $this->woo = $magic_woo;
            $this->platform = 'woocommerce';
            
            $admin_url = admin_url('admin.php?page=magic&');
            
            $this->config = array(
				"url" => $magic_woo->tool_url,
				"tool_url" => $magic_woo->tool_url,
				"logo" => $magic_woo->assets_url . 'assets/images/logo.v3.png',
				"ajax_url" => $magic_woo->ajax_url,
				"admin_ajax_url" => $magic_woo->admin_ajax_url,
				"assets_url" => $magic_woo->assets_url,
				"load_jquery" => true,
				"root_path" => $magic_woo->app_path,
				"upload_path" => $magic_woo->upload_path,
				"upload_url" => $magic_woo->upload_url,
				"checkout_url" => $magic_woo->checkout_url,
                
                //admin setting
                "admin_assets_url" => $magic_woo->admin_assets_url,
                "admin_url" => $admin_url,
				
				"database" => array(
                    "host" => DB_HOST,
                    "user" => DB_USER,
                    "pass" => DB_PASSWORD,
                    "name" => DB_NAME,
					"prefix" => 'magic_'
                )
			);
			
			$magic->add_filter('product_price', array(&$this, 'filter_product_price'));
			$magic->add_filter('product', array(&$this, 'filter_product'));
			$magic->add_filter('products', array(&$this, 'filter_products'));
			$magic->add_filter('query_products', array(&$this, 'query_products'));
			$magic->add_filter('order_status', array(&$this, 'order_status_name'));
			$magic->add_action('before_order_products', array(&$this, 'update_order_status'));
			$magic->add_filter('product_base_price', array(&$this, 'product_base_price'));
			$magic->add_filter('product_templates', array(&$this, 'product_templates'));
			$magic->add_filter('settings_fields', array(&$this, 'settings_fields'));
			$magic->add_filter('product_tabs', array(&$this, 'product_tabs'));
			$magic->add_filter('init_settings', array(&$this, 'init_settings'));
			$magic->add_filter('after_save_settings', array(&$this, 'after_save_settings'));
			
			// Filter before listing all product bases
			$magic->add_filter('list_products', array(&$this, 'list_products'));
			
			$magic->add_filter('back_link', array(&$this, 'backToShop_link'));
            
            if (!is_admin()) {
	            $magic->add_action('ajax', array(&$this, 'ajax'));
	            $magic->add_action('js_init', array(&$this, 'js_init'));
	            $magic->add_filter('get_product', array(&$this, 'get_product'));
	            //$magic->add_filter('js_init', array(&$this, 'editor_js_init'));
				//$magic->add_filter('file-nav', function(){return false;});
				//$magic->add_filter('design-nav', function(){return false;});
            }
            
        }
		
        public function get_session($name) {
            return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
        }

        public function set_session($name, $value) {
            $_SESSION[$name] = $value;
        }

        public function cookie($name) {
            return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
        }
		
        public function is_admin() {
			
			return current_user_can('magic_access') ? true : false;
		}
		
		public function is_login() {
			global $magic;
			return $magic->connector->cookie('uid') || 0;
			
		}
		
		public function ajax () {
			
			global $magic;
			
			if (
				isset($_POST['subaction']) &&
				$_POST['subaction'] == 'woo_product_variation'
			) {
				
				header('content-type: application/json');
				
				$variable_product = wc_get_product( wp_get_post_parent_id( absint( $_POST['product_id'] ) ) );
			
			    if ( ! $variable_product ) {
			    	echo '{"success": false, "message": "Error, invalid product id."}';
			    	exit;
			    }
			
			    $data_store   = WC_Data_Store::load( 'product' );

			    // foreach ($_POST as $key => $detail) {
			    // 	if(strpos($key, 'attribute_') !== false && strlen($key) > 10 ){
			    // 		// unset($_POST[$key]);
			    // 		$_POST[strtolower($key)] = $detail;
			    // 	}
			    // }
			    // $variation_id = $data_store->find_matching_product_variation( $variable_product, ['attribute_%e4%ba%a7%e5%93%81' => 'Red'] );
			    $variation_id = $data_store->find_matching_product_variation( $variable_product, wp_unslash( $_POST ) );
			    $variation    = $variation_id ? $variable_product->get_available_variation( $variation_id ) : false;
			   
			    if (
			    	!isset($variation['magic']) ||
			    	$variation['magic'] === 0
			    ) {
				    echo '{"success": false, "message": "Error, The variation you choose does not support custom designs."}';
			    	exit;
			    }
				
				$products = $this->get_product(array(), 'variable:'.$variation['magic']);

				// if(isset($products[0]['ext_attributes']) && !empty($products[0]['ext_attributes'])){
				// 	foreach ($products[0]['ext_attributes'] as $key => $detailArr) {
				// 		if(strpos($key, ' ') !== false){
				// 			$newKey = preg_replace('/[ ]+/', '-',  $key);
				// 			$products[0]['ext_attributes'][$newKey] = $detailArr;
				// 			unset($products[0]['ext_attributes'][$key]);
				// 		}
				// 	}
				// }
				
				echo json_encode(array(
					"success" => true,
					"id" => $variation['magic'],
					"data" => $magic->lib->prepare_product($products[0])
				));
				
				exit;
					
			}
			
		}
		
        public function capabilities($cap) {
	        
			$_cap = current_user_can($cap);
		    $_cap = apply_filters('magic_woo_capabilities', $_cap, $cap);
		    
		    return $_cap;
		    
	    }
	    
		public function filter_product_price($price){
			
			if(function_exists('wc_price'))
				return wc_price( $price);
			else
				return $price;
		}
        
        public function filter_product($data) {
			
			// do not process for woocommerce variable product
			if (strpos($data['id'], 'variable:') !== false)
				return $data;
			
			/*
			*	$data['product'] is product CMS ID
			*	if not isset --> the product base can not checkout (missing cms product)
			*/
			
			if (
				(
					!isset($_GET['product_cms']) ||
					!isset($_POST['product_cms'])
				) && 
				(
					!isset($data['product']) || 
					empty($data['product'])
				)
			) return null;
			
			/*
			*	Incase force a product base with any product CMS 
			*/	
			
			if(isset($_GET['product_cms']) && $_GET['product_cms'] != $data['product'])
				$data['product'] = $_GET['product_cms'];
				
			if(isset($_POST['product_cms']) && $_POST['product_cms'] != $data['product'])
				$data['product'] = $_POST['product_cms'];
			
			global $wpdb, $magic;
			
			$cms_product = $wpdb->get_results(
				sprintf(
					"SELECT * FROM `%s` WHERE `ID`=%d",
					$wpdb->prefix.'posts',
					(Int)$data['product']
				)
			);
			$cms_template = get_post_meta($data['product'], 'magic_design_template', true );
		
			if (!isset($cms_product[0]))
				return null;
			
			$price_meta = $wpdb->get_results(
				"SELECT * FROM `{$wpdb->prefix}postmeta` WHERE `post_id` = {$data['product']} AND `meta_key` IN ('_regular_price', '_sale_price', '_price')"
			);
			
			if (count($price_meta) > 0) {
				
				$price = array(0, 0, 0);
				
				foreach ($price_meta as $pr) {
					if ($pr->meta_key == '_price')
						$price[0] = $pr->meta_value;
					if ($pr->meta_key == '_regular_price')
						$price[1] = $pr->meta_value;
					if ($pr->meta_key == '_sale_price')
						$price[2] = $pr->meta_value;
				}
				
				if ($price[2] !== 0 && $price[2] !== '')
					$data['price'] = $price[2];
				else if ($price[1] !== 0 && $price[1] !== '')
					$data['price'] = $price[1];
				else if ($price[0] !== 0 && $price[0] !== '')
					$data['price'] = $price[0];
					
			}
			
				
			$data['templates'] = array();
			
			if (
				isset($cms_template) && 
				!empty($cms_template) && 
				$cms_template != '%7B%7D'
			) {
				
				$cms_template = json_decode(urldecode($cms_template), true);
				
				if (isset($data['stages']->stages)) {
					foreach ($data['stages']->stages as $s => $d) {
						if (isset($cms_template[$s]))
							$data['stages']->stages->{$s}->template = $cms_template[$s];
						else unset($data['stages']->stages->{$s}->template);
					}
				} else {
					foreach ($data['stages'] as $s => $d) {
						if (isset($cms_template[$s]))
							$data['stages']->{$s}->template = $cms_template[$s];
						else unset($data['stages']->{$s}->template);
					}
				}
				
				$data['templates'] = $cms_template;
				
			}
			
			$data['name'] = $magic->lang($cms_product[0]->post_title);
			$data['sku'] = get_post_meta($data['product'], '_sku', true);
			$data['description'] = $magic->lang($cms_product[0]->post_content);
			
			return $data;
	        
        }
                
        public function filter_products($products) {
			
			$results = array();
			global $wpdb, $magic;
			
			$task = $magic->lib->esc('task');
			
			/*
			*	Select all product base	for task on CMS product
			*/
			
			if ($task == 'cms_product')
				return $products;
						
			if (count($products) > 0) {
				
				foreach ($products as $key => $data) {
					
					if (isset($data['product']) && !empty($data['product'])) {
						
						$cms_product = $wpdb->get_results("SELECT * FROM `{$wpdb->prefix}posts` WHERE `post_status` = 'publish' AND `id`=".$data['product']);
						
						if (isset($cms_product[0])) {
							
							$data['name'] = $magic->lang($cms_product[0]->post_title);
							if(!isset($data['description']) || $data['description'] == '' || $data['description'] == null){
								$data['description'] = $magic->lang($cms_product[0]->post_content);
							}
							if(function_exists('wc_get_product')){
								$_product = wc_get_product( $data['product'] );
								if(!empty($_product)) $data['price'] = $_product->get_price();
							}
							
							//get list of templates
							$cms_template = get_post_meta($data['product'], 'magic_design_template', true );
							$data['templates'] = array();
							
							if (isset($cms_template) && !empty($cms_template) && $cms_template != '%7B%7D') {
								$cms_template = json_decode(urldecode($cms_template), true);
								foreach ($cms_template as $s => $n) {
									$template = $magic->lib->get_template($cms_template[$s]['id']);
									$cms_template[$s]['upload'] = $template['upload'];
									$cms_template[$s]['price'] = isset($template['price']) ? $template['price'] : 0;
								}
								$data['templates'] = $cms_template;
							}
							
							array_push($results, $data);
							
						}
						
					}
				}
			}
			
	        return $results;
	        
        }
        
		public function query_products($query, $args) {
			
			global $wpdb, $magic;
			
			$search = $args['s'];
			
			if (!empty($search)) {
				$search = " AND (LOWER(p.name) LIKE '%{$search}%' OR LOWER(wpp.post_title) LIKE '%{$search}%')";
			}
			
			return array(
				"SELECT SQL_CALC_FOUND_ROWS p.*",
				"FROM {$magic->db->prefix}products p",
				"LEFT JOIN {$wpdb->prefix}posts wpp ON wpp.id = p.`product`",
				(!empty($args['category']) ?
					"LEFT JOIN {$magic->db->prefix}categories_reference cref ON cref.item_id = p.id ".
					"WHERE `p`.`author` = '{$magic->vendor_id}' AND p.active = 1 AND wpp.post_status = 'publish' AND cref.category_id = ".$args['category'].$search
					:
					"WHERE `p`.`author` = '{$magic->vendor_id}' AND p.active = 1 AND wpp.post_status = 'publish'".$search
				),
				"GROUP BY p.id",
				"ORDER BY p.`order`, p.`id` ASC",
				"LIMIT {$args['index']}, {$args['limit']}"
			);
			
		}
        
        public function orders($filter, $orderby, $ordering, $limit, $limit_start) {

            global $wpdb;

            $items = array('rows' => array());

            $ops = $this->config['database']['prefix'] . 'order_products';
            $wois = $wpdb->prefix . 'woocommerce_order_items';
            $woim = $wpdb->prefix . 'woocommerce_order_itemmeta';
            $posts = $wpdb->prefix . 'posts';
            $pom = $wpdb->prefix . 'postmeta';
			
			$items['rows'] = array();
			$total_count = 0;
			
			if(class_exists( 'WooCommerce' )){
				$where = array();
				$where[] = " pos.post_status <> 'trash'";
				
	            if (is_array($filter) && isset($filter['keyword']) && !empty($filter['keyword'])) {

	                $fields = explode(',', $filter['fields']);
	                $arr_keyword = array();
	                for ($i = 0; $i < count($fields); $i++) {
	                    $arr_keyword[] = sprintf(" %s LIKE '%s' ", $fields[$i], $filter['keyword']);
	                }

	                $fields = implode(' OR ', $arr_keyword);

	                $where[] = $fields;
	            }
				
				if (count($where) > 0)
					$where = (count($where) > 0) ? ' WHERE ' . implode(' AND ', $where) : '';

	            $orderby_str = '';
	            if ($orderby != null && $ordering != null)
	                $orderby_str = ' ORDER BY ' . $orderby . ' ' . $ordering;

	            $sql = "SELECT SQL_CALC_FOUND_ROWS " 
	                . " wois.*, pos.ID as id, wois.order_item_name as product_name, pos.post_status as status, pos.post_date as created, pos.post_modified as updated, max( CASE WHEN pom.meta_key = '_order_total' and pos.ID = pom.post_id THEN pom.meta_value END ) as total, max( CASE WHEN pom.meta_key = '_order_currency' and pos.ID = pom.post_id THEN pom.meta_value END ) as currency"
	                . " FROM $ops as ops "
	                . " LEFT JOIN $wois as wois ON ops.order_id = wois.order_id"
	                . " LEFT JOIN $posts as pos ON pos.ID = ops.order_id"
	                . " LEFT JOIN $pom as pom on pos.ID = pom.post_id"
	                . $where
	                . " GROUP BY pos.ID "
	                . $orderby_str
	                . " LIMIT $limit_start, $limit";
					
	            $rows = $wpdb->get_results($sql);
				
	            foreach ($rows as $row)
	                $items['rows'][] = (array) $row;

	            //get total 
	            
	            $sql = "SELECT FOUND_ROWS() ";

	            $total_count = $wpdb->get_var($sql);
			}
            

            $items['total_count'] = $total_count;
            $items['total_page'] = ($limit != null) ? ceil($total_count / $limit) : 0;
            
            return $items;
            
        }

        public function products_order($order_id, $filter, $orderby, $ordering) {
	        
            global $wpdb, $magic;

            $items = array('rows' => array());

            $ops = $this->config['database']['prefix'] . 'order_products';
            $wois = $wpdb->prefix . 'woocommerce_order_items';
            
            $where = array();
            
            $where[] = 'ops.order_id = '. $order_id;

            if (is_array($filter) && isset($filter['keyword']) && !empty($filter['keyword'])) {

                $where = array();
                $fields = explode(',', $filter['fields']);
                $arr_keyword = array();
                for ($i = 0; $i < count($fields); $i++) {
                    $arr_keyword[] = sprintf(" %s LIKE '%s' ", $fields[$i], $filter['keyword']);
                }

                $fields = '(' . implode(' OR ', $arr_keyword) . ')';

                $where[] = $fields;
                    
            }
            

            $orderby_str = '';
            if ($orderby != null && $ordering != null)
                $orderby_str = ' ORDER BY ' . $orderby . ' ' . $ordering;

            $sql = "SELECT "
                . "SQL_CALC_FOUND_ROWS *"
                . " FROM $ops as ops "
                . " JOIN $wois as wois ON wois.order_id = ops.order_id"
                . ' WHERE '. implode(' AND ', $where)
                . " GROUP BY ops.id "
                . $orderby_str;

            $rows = $wpdb->get_results($sql);

            foreach ($rows as $row)
                $items['rows'][] = (array) $row;

            //get total 
            $sql = "SELECT FOUND_ROWS() ";

            $total_count = $wpdb->get_var($sql);

            $items['total_count'] = $total_count;
            $items['total_page'] = 1;
			
			$order = new WC_Order( $order_id );
			$created_date = date('F j, Y, g:i a', strtotime($order->get_date_created()));
			$modified_date = date('F j, Y, g:i a', strtotime($order->get_date_modified()));
			
			$items['order'] = array(
				'created' => $created_date,
				'updated' => $modified_date,
				'total' => $order->get_total(),
				'status' => 'wc-' . $order->get_status()
			);
			
            return $items;
            
        }

		public function add_to_cart( $data ) {

			global $woocommerce, $magic, $magic_cart_adding;
			
			$magic_cart_adding = true;
			
			if ( is_array($data) && isset($woocommerce) ) {
				
				//remove all items from editor
				
				foreach( WC()->cart->get_cart() as $cart_item_key => $item ){
					if( 
						isset($item['magic_data'])
						&& 
						(
							( isset( $item['magic_incart'] ) && !$item['magic_incart'] ) ||
							!isset( $item['magic_incart'] ) 
						)
					) { 
						$woocommerce->cart->remove_cart_item( $cart_item_key );
					}
				}
				
				if ( $magic->connector->get_session('magic_cart_removed') == null )
					$removed_items = array();
				else 
					$removed_items = $magic->connector->get_session('magic_cart_removed');
				
				
				/*
				*	Start to add item to WooCommerce Cart
				*/
				
				$cart_ids = array();
				
				foreach ( $data as $cart_id => $item ) {
					
					if ( !isset( $item[ 'incart' ] ) ) {
						
						$product_id = $item[ 'product_cms' ];
						
						$item['cart_id'] = $cart_id;
						
						$extra_option = array(
							'magic_data' => $item
						);

						$variation_id = null;
						$variations = null;
						
						if(strpos($item['id'], 'variable') !== false){
							$variation_id = intval(preg_replace('/[^0-9]+/mi', '', $item['id']));
							$variations = (array)$item['ext_attributes'];
							foreach($variations  as $key => $variation){
								$attribute_key = 'attribute_' . sanitize_title( $key );
								$variations[$attribute_key] = $variation;
							}
						}
						//$woocommerce->cart->add_to_cart( $product_id, 1 , $variation_id, $variations , $extra_option );
						if ( false == $woocommerce->cart->add_to_cart( $product_id, 1 , $variation_id, $variations , $extra_option )) {
							return false;
						}
						
					}
					
					$cart_ids[] = $item['cart_id'];
					
				}
				
				$removed_items = array_diff( $removed_items, $cart_ids );
				
				$magic->connector->set_session('magic_cart_removed', $removed_items );
				
				return wc_get_cart_url();
				
			}
			
			return false;
			
		}
		
		public function statuses() {
			return wc_get_order_statuses();
		}
		
		public function update_order_status($order_id) {
			if(isset($_POST['order_status'])){
				$status = str_replace('wc-', '', $_POST['order_status']);
				$order = new WC_Order($order_id);
				$order->update_status($status);
			}
		}
		
		public function order_status_name($status) {
			return (function_exists('wc_get_order_status_name'))? wc_get_order_status_name($status) : $status;
		}
		
		public function product_base_price($price, $product_id){
			
			global $magic;
			
			if(function_exists('wc_get_product')){
				$product = wc_get_product( $product_id );
				$price = ($product)? $product->get_price(): 0;
			}
			
			return $price;
			
		}
		
		public function product_templates($data) {
			
			$item = $data['item'];
			
			if ($item->cms_id) {
				
				$cms_template = get_post_meta($item->cms_id, 'magic_design_template', true );
				
				$template_price = 0;
				
				if (
					isset($cms_template) && 
					!empty($cms_template) && 
					$cms_template != '%7B%7D'
				) {
					
					$data['templates'] = array();
					$cms_template = json_decode(urldecode($cms_template), true);
					
					foreach ($cms_template as $stage){
						if(!in_array($stage['id'], $data['templates'])){
							$data['templates'][] = $stage['id'];
						}
					}
					
				}
			}
			
			return $data['templates'];
		}
		
		public function product_tabs($arg) {
			
			global $magic, $wpdb;
			
			if (isset($_GET['id'])) {
				
				$product = $_GET['id'];

				foreach ($arg['tabs']['details:' . $magic->lang('Details')] as $index => $field) {
					if(
					 	$field['name'] == 'product' &&
						$product > 0
					){
						//find all product assigned
						$args = array (
						    'post_type'		=> array( 'product' ),
						    'meta_query'	=> array(
						        array(
						            'key'       => 'magic_product_base',
						            'value'     => $product,
						        ),
						    ),
						);
						$query = new WP_Query( $args );
						$products = array();
						if ( $query->have_posts() ) {
						    while ( $query->have_posts() ) {
						        $query->the_post();
						        $products[get_the_ID()] = get_the_title();
						    }
							
							$arg['tabs']['details:' . $magic->lang('Details')][$index]['options'] = $products;
							$arg['tabs']['details:' . $magic->lang('Details')][$index]['type'] = 'dropbox';
						}
						
						
					}
				}
			}
			return $arg;
		}
		
		public function settings_fields($arg){
			
			global $magic;
			
			$pages = get_pages();
			
			$pages_list = array();
			
			foreach ($pages as $page) {
				$pages_list[$page->ID] = $page->post_title;
			}
			
			$arg['tabs']['shop:'.$magic->lang('Shop')][] = array(
				'type' 		=> 'dropbox',
				'name' 		=> 'editor_page',
				'value' 	=> '',
				'label' 	=> $magic->lang('Editor Page'),
				'desc' 		=> $magic->lang('Page to display MagicRugs editor for design.'),
				'options' 	=> $pages_list
			);
			
			$arg['tabs']['shop:'.$magic->lang('Shop')][] = array(
				'type' => 'input',
				'name' => 'btn_text',
				'value' => 'Customize',
				'label' => $magic->lang('Button Text'),
				'desc' => $magic->lang('Customize button text. Default: Customize'),
			);
			
			$arg['tabs']['shop:'.$magic->lang('Shop')][] = array(
				'type' => 'toggle',
				'name' => 'btn_list',
				'value' => 1,
				'label' => $magic->lang('Button Listing'),
				'desc' => $magic->lang('Show Customize button on list products page or other listing'),
			);
			
			$arg['tabs']['shop:'.$magic->lang('Shop')][] = array(
				'type' => 'toggle',
				'name' => 'btn_page',
				'value' => 1,
				'label' => $magic->lang('Button Product Page'),
				'desc' => $magic->lang('Show Customize button on product page'),
			);
			
			$arg['tabs']['shop:'.$magic->lang('Shop')][] = array(
				'type' => 'toggle',
				'name' => 'email_design',
				'value' => 1,
				'label' => $magic->lang('Designs details in email'),
				'desc' => $magic->lang('Send details of designs in email for admin when orders are created, send to user when orders are completed'),
			);
			
			$arg['tabs']['editor:'.$magic->lang('Editor')][] = array(
				'type' => 'toggle',
				'name' => 'editor_iframe',
				'value' => 0,
				'default' => 'no',
				'label' => $magic->lang('Show header & footer'),
				'desc' => $magic->lang('Display MagicRugs editor in a page with header and footer of your theme'),
			);
			
			$arg['tabs']['editor:'.$magic->lang('Editor')][] = array(
				'type' => 'input',
				'name' => 'editor_iframe_width',
				'value' => 0,
				'default' => '100%',
				'label' => $magic->lang('Set width for editor'),
				'desc' => $magic->lang('Set width for editor in case you set showing header & footer (px, %, vw)'),
			);
			
			$arg['tabs']['editor:'.$magic->lang('Editor')][] = array(
				'type' => 'input',
				'name' => 'editor_iframe_height',
				'value' => 0,
				'default' => '80vh',
				'label' => $magic->lang('Set height for editor'),
				'desc' => $magic->lang('Set height for editor in case you set showing header & footer (px, %, vw)'),
			);
			
			return $arg;
		}
		
		public function init_settings($settings){
			
			$settings = array_merge($settings, array(
				'editor_page' => get_option('magic_editor_page', 0),
				'btn_list' => 1,
				'btn_text' => 'Customize',
				'btn_page' => 1,
				'email_design' => 1
			));
			
			return $settings;
		}
		
		public function after_save_settings($data){
			//update page editor
			$fields = array(
				'btn_list',
				'btn_text',
				'btn_page',
				'email_design',
			);
			
			$config = array();
			
			foreach($fields as $field){
				if (isset($data[$field]))
					$config[$field] = $data[$field];
				else $config[$field] = '';
			};
	
			if (isset($data['editor_page']))
				update_option('magic_editor_page', $data['editor_page']);
	
			update_option('magic_config', $config);
	
		}
		
		public function list_products ($res, $query) {
			
			if (
				isset($_POST['product_source']) && 
				$_POST['product_source'] == 'woo-variation'
			) {
				
				global $magic;
				
				if (!isset($query['limit']))
					$query['limit'] = 12;
				
				$index = ((Int)$query['index']);
				
				$args = array(
				    'taxonomy'   => "product_cat",
				    'hide_empty' => true
				);
				
				$product_categories = get_terms($args);
					
				$categories = array();
				
				foreach ($product_categories as $cat) {
					array_push($categories, array(
						"id" => $cat->term_id,
						"name" => $cat->name,
						"parent" => $cat->parent,
						"count" => $cat->count,
						"thumbnail" => "",
						"lv" => 0	
					));
				}
				
				$products = array();
				
				$args = array(
				    'post_type' => 'product_variation',
				    'post_status' => array('publish'),
				    'category' => $query['category'],
				    'posts_per_page' => $query['limit'],
				    'offset' => $index,
				    's' => $query['s'],
				    'meta_query' => array(
					    array(
					        'key' => '_variation_magic',
					        'value'   => array(''),
					        'compare' => 'NOT IN'
					    )
					)
				);
				
				$get_posts = new WP_Query;
				
				$variations = $get_posts->query( $args );
				$products = array();
				
				foreach ($variations as $vari) {
					$lumi_data = get_post_meta($vari->ID, '_variation_magic', true);
					$lumi = json_decode(urldecode($lumi_data), true);
					$stages = $magic->lib->dejson($lumi['stages']);
					$thumbnail_url = '';
					foreach ($stages as $stage) {
						if (empty($thumbnail_url)) {
							if ($stage->source == 'raws')
								$thumbnail_url = $magic->cfg->assets_url.'raws/'.$stage->url;
							else
								$thumbnail_url = $magic->cfg->upload_url.$stage->url;
						}
					}
					array_push($products, array(
						"id" => $vari->ID,
						"name" => $vari->post_title,
						"price" => 0,
						"product" => 0,
						"thumbnail" => "",
						"thumbnail_url" => $thumbnail_url,
						"template" => "0",
						"description" => $vari->post_excerpt,
						"magic_data" => $lumi_data,
						"stages" =>  $lumi['stages'],
						"variations" => "",
						"attributes" => "",
						"printings" => "",
						"order" => 0,
						"active" => 1,
						"author" => "",
						"created" => $vari->post_date,
						"updated" => $vari->post_modified	
					));
				}
				
				$total = $get_posts->found_posts;
				
				return array(
					'categories' => $categories,
					'products' => $products,
					'index' => $index+count($products),
					'limit' => $query['limit'],
					'total' => (Int)$total,
					's'		=> $query['s'],
					'category' => $query['category']
				);
			}
			
			return null;
			
		}
		
		public function backToShop_link ($link) {
			if (isset($_GET['product_cms']))
				return get_permalink($_GET['product_cms']);
			return $link;
		}
		
		public function do_action($name = '', $params = '') {
			do_action('magic_'.$name, $params);
		}
		
		public function apply_filters($name = '', $params = '', $params2 = null, $params3 = null) {
			return apply_filters('magic_'.$name, $params, $params2, $params3);
		}
		
		public function get_product($products, $pid) {

			if (
				$pid !== null &&
				!empty($pid) &&
				strpos($pid, 'variable:') !== false
			) {
				
				global $magic, $wpdb;
				
				$pid = (Int)str_replace('variable:', '', $pid);
				
				$data = get_post_meta($pid, '_variation_magic', true);
				
				if ($data && !empty($data)) {
					
					$data = json_decode(urldecode($data), true);
			
					$product = $wpdb->get_results(
						sprintf(
							"SELECT * FROM `%s` WHERE `ID`=%d",
							$wpdb->prefix.'posts',
							(Int)$pid
						)
					);
					
					$product = wc_get_product($pid);
					
					$stages = $magic->lib->dejson($data['stages']);
					foreach ($stages as $stageID => $stageData) {
						if(isset($stageData->template) && isset($stageData->template->id)){
							$tempalteStages = $wpdb->get_results(
								sprintf(
									"SELECT * FROM `%s` WHERE `ID`=%d",
									$magic->db->prefix.'templates',
									(Int)$stageData->template->id
								), ARRAY_A
							);
							if($tempalteStages != null && count($tempalteStages) == 1 ){
								$stages->$stageID->template->upload = $tempalteStages[0]['upload'];
							}
						}
					}
					$newStage = base64_encode(urlencode(json_encode($stages)));

					$woo_product = wc_get_product( $pid );

					$woo_product_parent = wc_get_product( $woo_product->get_parent_id() );

					$ext_attributes = array();
					$ext_attribute_name = array();
					$ext_list_variation = array();
					$ext_attributes_value = $woo_product->get_attributes();
					
					if( !empty($woo_product_parent->get_variation_attributes()) ){
						$ext_attributes = $woo_product_parent->get_variation_attributes();
						$all_attribute = $woo_product_parent->get_attributes();
						
						// foreach all product variation child
						foreach ($woo_product_parent->get_children() as $key => $product_child) {
							$single_variation = new WC_Product_Variation($product_child);
							$variation_data  = $single_variation->get_variation_attributes();
							if($single_variation->get_meta('_variation_magic')){
								// foreach all attribute in variation
								foreach ($variation_data as $keyAttribute => $detailAttribute) {
									$ext_list_variation[$product_child][substr($keyAttribute, 10, strlen($keyAttribute))] = $detailAttribute;
								}
							}
						}

						// foreach all variation
						foreach ($ext_attributes as $keyNameVariation => $detailVariation) {
							
							// foreach all attribute
							foreach ($all_attribute as $id_attribute => $detailAttribute) {
								// repleace variation name to attribute name
								if($detailAttribute['data']['name'] == $keyNameVariation){
									unset($ext_attributes[$keyNameVariation]);
									$ext_attributes[$id_attribute] = $detailVariation;
									if(strpos($keyNameVariation, 'pa_') !== false){
										$keyNameVariation =  substr($keyNameVariation, 3, strlen($keyNameVariation));
									}
									$ext_attribute_name[$id_attribute] = $keyNameVariation;
								}
							}

						}
					}
					
					// name for variation product with 3 variation
					$product_name = $product->get_name();

					if(isset($_POST['product_base']) && strpos($_POST['product_base'], 'variable') !== false){
						$product_id = intval(preg_replace('/[^0-9]+/mi', '', $_POST['product_base']));
		
						$product_temp = wc_get_product( $product_id );
						$productAttribute = $product_temp->get_variation_attributes();

						if($productAttribute != NULL && count($productAttribute) >= 3){
							$newname = ' - ';
							foreach ($productAttribute as $index => $detailAttribute) {
								if($detailAttribute != ''){
									$newname .= $detailAttribute.', ';
								}
							}
							if($newname != ' - '){
								$newname = substr($newname, 0, -2);
							}
							$product_name .= $newname;
						}
					}

					foreach ( $_POST as $key => $value ) {
						if ( 'attribute_' !== substr( $key, 0, 10 ) ) {
							continue;
						}
						$ext_attributes_value[ sanitize_title( wp_unslash( str_replace( 'attribute_', '', $key) ) ) ] = wp_unslash( $value );
						
					}

					$products[0] = array( 
						'id' => 'variable:'.$pid,
						'product' => isset($_POST['product_cms']) ? $_POST['product_cms'] : '',
						'cms_id' => isset($_POST['product_cms']) ? $_POST['product_cms'] : '',
						'color' => 'red',
						'name' => $product_name, 
						'sku' => $product->get_sku(), 
						'price' => $product->get_price(), 
						'description' => $product->get_description(), 
						// 'stages' => $data['stages'],
						'stages' => $newStage,
						'printings' => $data['printing'],
						'attributes' => array(),
						'variations' => array(),
						// 'ext_attributes' => $woo_product_parent->get_variation_attributes(),
						'ext_attributes' => $ext_attributes,
						'ext_attribute_name' => $ext_attribute_name,
						'ext_list_variation' => $ext_list_variation,
						'ext_attributes_value' => $ext_attributes_value,
						'ext_attributes_callback' => "
							let selects = wrp.find('div.magic-cart-field.ext-attribute select');
							selects.on('change', function(e) {
							// let selects = wrp.find('button#select_variable'); 
							// let optionsData = wrp.find('div.magic-cart-field.ext-attribute select');
							// selects.on('click', function(e) {
								let product_base_check = magic.fn.url_var('product_base', '');
								if(product_base_check.indexOf('variable:') != -1){
									let last_attribute = true;

									$('div.magic-cart-field.ext-attribute select').each(function() {
										if($(this).val() == '' || $(this).val() == null){
											last_attribute = false;
										}
									});

									// not last attribute, not to server
									if(last_attribute == false){
										return false;
									}
								}
								
								let sel = this,
									val = this.value,
									matching_variations = [];
									post_data = {
										nonce: 'MAGIC-SECURITY:'+magic.data.nonce,
										ajax: 'frontend',
										action: 'load_product',
										subaction: 'woo_product_variation',
										product_id: magic.data.product.replace('variable:', ''),
										product_cms: magic.fn.url_var('product_cms')
									};
								
								post_data['attribute_'+this.name] = val;
								
								selects.each(function() {
									var attribute_name = 'attribute_'+this.name;
									// if(attribute_name.indexOf(' ') != -1){
									// 	attribute_name = attribute_name.split(' ').join('-');
									// }
									if (post_data[attribute_name] === undefined) {
										post_data[attribute_name] = this.value;
									}
								});
								
								sel.value = sel.getAttribute('data-value');

								let thisValue = $(this).val();
								let thisName = $(this).attr('name');

								// foreach list variation
								$.each(magic.ops.product_data.ext_list_variation, function(p_id, select_data){
									var match = true;
									for ( var attr_name in select_data ) {
										if ( select_data.hasOwnProperty( attr_name ) ) {
											var val1 = select_data[ attr_name ];
											var val2 = post_data[ 'attribute_'+attr_name ];
											if ( val1 !== undefined && val2 !== undefined && val1.length !== 0 && val2.length !== 0 && val1 !== val2 ) {
												match = false;
											}
										}
									}
									if(match) matching_variations.push( select_data );
								});
								var variation = matching_variations.shift();
								
								if(variation){
									magic.f('Loading variation..');
									
									$.ajax({
										url: magic.data.ajax,
										method: 'POST',
										data: post_data,
										dataType: 'JSON',
										success: function(res) {
											
											if (res.success === false) {
												magic.fn.notice(res.message, 'error', 5000);
											} else {
												magic.render.product(res.data);
												
												Object.keys(res.data.ext_attributes_value).map(function(key) {
													magic.fn.set_url('attribute_'+key, res.data.ext_attributes_value[key]);
												});

												if(product_base_check.indexOf('variable:') != -1 && sessionStorage.getItem('MAGIC-ATTRIBUTE-VARIATION') != null && sessionStorage.getItem('MAGIC-ATTRIBUTE-VARIATION') != '' && sessionStorage.getItem('MAGIC-ATTRIBUTE-VARIATION') != 'undefined'){
													let list_selected = {};
													$('div.magic-cart-field.ext-attribute select').each(function() {
														if($(this).val() != '' || $(this).val() != null){
															list_selected[$(this).attr('name')] = $(this).val();
														}
													});

													let attribute_variation_data = JSON.parse(sessionStorage.getItem('MAGIC-ATTRIBUTE-VARIATION'));

													// foreach list variation
													$.each(attribute_variation_data, function(attribute_name, attribute_arr){
														
														$('select[name=\"'+attribute_name+'\"] option').remove();
														$('select[name=\"'+attribute_name+'\"]').html('<option value=\"\">Choose an option</option>');

														$.each(attribute_arr, function(index, detail){
															let existAttribute = $('select[name=\"'+attribute_name+'\"] option[value=\"'+detail+'\"]').length;
															let selected = '';
															if(detail == list_selected[attribute_name]){
																selected = 'selected';
															}
															if(existAttribute == 0){
																$('select[name=\"'+attribute_name+'\"]').append('<option '+selected+' value=\"'+detail+'\">'+detail+'</option>');
															}
														});

													});
												}

											};
											
											magic.f(false);
											
										}
									});									
								}

							}).each(function() {
								this.setAttribute('data-value', this.value);
							});
						"
					);
				}
			}
			
			return $products;
			
		}
		
		public function editor_js_init($cfg) {
			
			return $cfg;
			
			$pid = isset($_POST['product_base']) ? $_POST['product_base'] : '';
			
			if (
				!empty($pid) &&
				strpos($pid, 'variable:') !== false
			) {
				
				global $magic;
				
				$pid = (Int)str_replace('variable:', '', $pid);
				
				$data = get_post_meta($pid, '_variation_magic', true);
				
				if ($data && !empty($data)) {
					
					$data = json_decode(urldecode($data), true);
					$product = wc_get_product($pid);
					
					$stages = $magic->lib->dejson($data['stages']);
					
					foreach ($stages as $name => $stage) {
						if (isset($stage->template) && isset($stage->template->id)) {
							$template = $magic->lib->get_template($stage->template->id);
							$stages->{$name}->template->price = isset($template['price']) ? $template['price'] : 0;
							$stages->{$name}->template->upload = $template['upload'];
						}
					}
					
					$cfg['onload'] = array(
						'id' => 'variable:'.$pid,
						'name' => $product->name, 
						'sku' => $product->sku, 
						'price' => $product->price, 
						'description' => $product->description, 
						'stages' => $stages,
						'printings' => $magic->lib->get_printings($data['printing']),
						'variations' => '',
						'product' => isset($_POST['product_cms']) ? $_POST['product_cms'] : 0,
						'attributes' => array(
							'quantity' =>  array(
								'id' => "quantity", 
								'name' => "Quantity",
								'type' => "quantity",
								'value' => isset($_POST['quantity']) ? (Int)$_POST['quantity'] : 1
							)
						)
					);
				}
			}
			
			return $cfg;
			
		}
		
		public function setup() {
			
			if (!isset($_GET['step']))
				return;
			
			global $magic;
				
			$step = $_GET['step'];
			
			if ($step == '1') {
			
				$data = file_get_contents($_FILES['file']['tmp_name']);
				$data = json_decode(urldecode($data));
				
				$data->logo = @json_decode(urldecode($data->logo));
				
				update_option('magic_editor_page', $data->editor_page);
				
				if ($data->color != $magic->cfg->settings['primary_color']) {
					$magic->lib->render_css(array(
						'primary_color' => $data->color,
						'custom_css' => $magic->cfg->settings['custom_css']
					));
				}
				
				if (is_object($data->logo)) {
					
					$path = $magic->cfg->upload_path.'settings'.DS;
					$name = $data->logo->name;
					$img = explode(',', $data->logo->data);
					$img = base64_decode($img[1]);
					$count = 1;
					
					while (file_exists($path.$name)) {
						$name = $count.'-'.$data->logo->name;
						$count++;
					}
					
					@file_put_contents($path.$name, $img);
					
					$old_logo = str_replace(
						array($magic->cfg->upload_url, 'settings/'),
						array($magic->cfg->upload_path, 'settings'.DS), 
						$magic->cfg->settings['logo']
					);
					
					@unlink($old_logo);
					$magic->set_option('logo', $magic->cfg->upload_url.'settings/'.$name);
					
				}
				
				$magic->set_option('currency', $data->currency);
				$magic->set_option('conditions', $data->terms);
				$magic->set_option('primary_color', $data->color);
				$magic->set_option('editor_page', $data->editor_page);
				$magic->set_option('last_update', time());
				
				update_option('magic_setup', 'done');
				
				echo 1;
				
			} else if ($step == '2') {
				
				$path = WP_CONTENT_DIR.DS;
				$upath = $magic->cfg->upload_path.'user_data'.DS;
				
				$errors = array();
				
				if (is_dir($upath.'pack')) 
					$magic->lib->remove_dir($upath.'pack');
				
				if ($_GET['theme'] == '1' || $_GET['kc'] == '1') {
					
					if (
						!is_dir($path.'plugins'.DS.'kingcomposer') || 
						!is_dir($path.'themes'.DS.'magic')
					) {
						$download = $this->download(
							(is_ssl() ? 'https' : 'http').'://magicrugs.com/woocommerce/pack.zip',
							$upath,
							'pack'
						);
						
						if ($download !== true) {
							$errors[] = $download;
						} else {
							$unpack = $this->unpack('pack', $upath);
							if ($unpack !== true)
								array_push($errors, $unpack);
						}
					
					}
					
					if (is_dir($upath.'pack')) {
						
						if ($_GET['theme'] == '1' && !is_dir($path.'themes'.DS.'magic'))
							rename($upath.'pack'.DS.'magic', $path.'themes'.DS.'magic');
						
						if ($_GET['kc'] == '1' && !is_dir($path.'plugins'.DS.'kingcomposer'))
							rename($upath.'pack'.DS.'kingcomposer', $path.'plugins'.DS.'kingcomposer');
						
						$magic->lib->remove_dir($upath.'pack');
						
					}
					
					if (is_dir($path.'themes'.DS.'magic')) {
						update_option('template', 'magic');
						update_option('stylesheet', 'magic');
						update_option('current_theme', 'Magic');
					} else {
						$errors[] = 'Error: Could not download and active MagicRugs theme, please try again';
					}
					
					if (is_dir($path.'plugins'.DS.'kingcomposer')) {
						
						$current = get_option( 'active_plugins', array() );
						
						if (!in_array('kingcomposer'.DS.'kingcomposer.php', $current)) {
							$current[] = 'kingcomposer'.DS.'kingcomposer.php';
							sort($current);
							$current = array_filter($current, 'strlen');
							update_option('active_plugins', $current);
						}
						
					} else {
						$errors[] = 'Error: Could not download and active Kingcomposer Pagebuilder, please try again';
					}
				
				}
				
				if (count($errors) > 0) {
					echo json_encode($errors);
				} else {
					echo 1;
				}
				
				exit;
				
			}
		}
		
		private function download($url = '', $path = '', $name = '') {
			
			global $magic;
			
			if (empty($url) || empty($path) || empty($name)) {
				return 'Error: Missing params for downloading package';
			}
			
			$error = true;
			
			$file = $path.$name.'.zip';
			
			if (is_dir($path.$name))
				return true;
				
			@set_time_limit(300);
				
			/*$fh = @fopen($url, false, true);
			
			$data = @file_put_contents($file, $fh);
			
			@fclose($fh);*/
			
			$data = $magic->lib->remote_connect($url);
			@file_put_contents($file, $data);
			
			/*
			*	End downloading extentions
			*/
			
			if (strlen($data) === 0) {		
				$error = 'Error: Could not download file, make sure that the fopen() funtion on your server is enabled';
			} else if (strlen($data) < 250) {
				$error = 'Error: '.file_get_contents($file);
			}
			
			return $error;
			
		}
		
		private function unpack($name = '', $path = '') {
			
			if (empty($name) || !is_dir($path)) {
				return 'Error: Missing params for unpacking';
			}
			
			if (is_dir($path.$name))
				return true;
				
			global $magic;
			
			$file = $path.DS.$name.'.zip';
			
			$error = true;
			
			if (!class_exists('ZipArchive')) {
				$error = 'Error: Your server does not support ZipArchive to extract extensions';
			} else {
				
				if (!isset($this->zip))
					$this->zip = new ZipArchive;
				
				$res = $this->zip->open($file);
				
				if ($res === TRUE) {
					
					$this->zip->extractTo($path);
					$this->zip->close();
					
					if (is_dir($path.'__MACOSX'))
						$magic->lib->remove_dir($path.'__MACOSX');
					
				} else {
					$error = 'Error: Could not open file: '.$file;
				}
			}
			
			@unlink($file);
			
			return $error;
			
		}
		
		public function js_init() {
			
			if (isset($_GET['reorder'])) {
				
				$order_id = (int)$_GET['reorder'];
				$uid = get_post_meta($order_id, '_customer_user', true);
				
				global $magic;
				
				if ($uid != get_current_user_id()) {
				
					$this->js_init_error($magic->lang('Error: Could not reorder because it is not owned by you'));
						
				} else {
				
					$orders = $magic->db->rawQuery(sprintf(
						"SELECT * FROM `%s` WHERE `order_id` = '%d'",
			            $magic->lib->sql_esc($magic->db->prefix."order_products"),
						$order_id
			        ));
			        
					$morder = new WC_Order( $order_id );
			        
			        if (count($orders) > 0 && $morder->get_id()) {
					        
				        echo 'var orders = '.json_encode($orders).';';
			        
			    ?>
			    	
				    magic.f('<?php echo $magic->lang('Loading order data'); ?>');
				    magic.actions.add('db-ready', function() {
					    
					    magic.post({
							action: 'list_products'
						}, function(res) {
							
							magic.ops.products = res;
							localStorage.setItem('MAGIC-CART-DATA', '');
							
							var cart_data =  JSON.parse(localStorage.getItem('MAGIC-CART-DATA') || '{}'),
								first_cart_id = '',
								addcart = function(i) {
									
									$.get('<?php echo $magic->cfg->upload_url; ?>designs/'+orders[i].design+'.lumi', function(res) {
										
										res = JSON.parse(res);
										
										res.id = new Date().getTime().toString(36).toUpperCase();
										
										if (first_cart_id === '')
											first_cart_id = res.id;
											
										orders[i].data = JSON.parse(decodeURIComponent(atob(orders[i].data)));
										
										data = {
										    product_data : magic.ops.products.products.filter(function(p) {
										    	return p.id == orders[i].product_base
										    })[0],
										    price_total : <?php echo (float)$morder->get_total(); ?>,
										    updated : new Date().getTime(),
										    id : res.id,
										    template : res.template,
										    color : orders[i].data.color,
										    extra : 0,
										    color_name : orders[i].data.color_name,
										    options: orders[i].data.attributes,
										    states_data : {}
										};
										
										data.product_data.stages = magic.fn.dejson(data.product_data.stages);
										
										cart_data[res.id] = magic.fn.enjson(data);
										localStorage.setItem('MAGIC-CART-DATA', JSON.stringify(cart_data));
										magic.indexed.save([res], 'cart');
										
										if (orders[i+1] !== undefined) {
											addcart(i+1);
										} else {
											magic.fn.notice('<?php echo $magic->lang('Your order has been successfully replicated'); ?>', 'success', 5000);
											magic.fn.set_url('reorder', null);
											magic.cart.edit_item(first_cart_id);
										}
										
									});
								};
							
							addcart(0);
							
						});
					});
				    
				    magic.data.access_core = '';
				    
			    <?php   
			        
			        } else { // no order found
					
					$this->js_init_error($magic->lang('Error: Could not found the order'));
						
			        }
			        
		        }
			}
		}
		
		public function js_init_error($msg) {
			global $magic;
		?>	
			$('#magic-main').html('<div id="magic-no-product" style="display: block;">\
					<p>\
						<?php echo $msg; ?><br>\
						<?php echo $magic->lang('Please select a product to start designing'); ?></p>\
					<button class="magic-btn" id="magic-select-product">\
						<i class="magicx-android-apps"></i> <?php echo $magic->lang('Select another product'); ?>\
					</button>\
				</div>');
			$('#magic-select-product').on('click', function(e) {
				window.location.href = '<?php echo $magic->cfg->tool_url; ?>refresh=1';
				e.preventDefault();
			});
			magic.f(false);
			return false;
		<?php	
		}
		
		public function update() {
			
			global $magic;
			
			$magic_path = dirname(__FILE__);
			$update_path = $magic->cfg->upload_path.'tmpl'.DS.'magic';
			$backup_path = $magic->cfg->upload_path.'update_backup';
			
			$magic->lib->delete_dir($backup_path);
								
			return (
				@rename($magic_path, $backup_path) && 
				@rename($update_path, $magic_path)
			);
			
		}

    }

}
