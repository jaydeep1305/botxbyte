<?php
/**
 * File : class-article-innovator
 *
 * @package Article_Innovator
 */

namespace Botxbyte\Article_Innovator;

require __DIR__ . '/vendor/autoload.php';
use Dgoring\DomQuery\DomQuery;
/**
 * Class Article_Innovator
 *
 * @package    botxbyte
 * @subpackage botxbyte/article-innovator
 */
class Article_Innovator {
	const ADMIN_PAGE      = 'toplevel_page_article-innovator-gj';
	const MENU_TITLE      = 'Article Innovator';
	const MENU_CAPABILITY = 'manage_options';
	const MENU_SLUG       = 'article-innovator-gj';

	/**
	 * Ajax Actions List
	 *
	 * @var array
	 */
	protected $ajax_actions = array(
		'page_selectors',
		'page_test_selector',
		'page_settings',
		'page_urls',
		'page_keywords',
		'page_prompts_url',
		'page_prompts_keyword',
		'fetch_article_innovator_urls_table_data',
		'get_selector_data',
		'delete_selector_data',
	);

	/**
	 * Construct
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup Hooks
	 */
	public function setup_hooks() {
		add_action( 'admin_menu', array( $this, 'setup_admin_menu' ) );

		// AJAX calls providers.
		$this->setup_ajax_actions();

		// Enqueue styles and scripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );

		// Cron jobs scheduling.
		$this->setup_cron_jobs();

		// Download csv - Remove it.
		add_action( 'admin_post_urls_table_csv', array( $this, 'urls_table_csv' ) );
	}

	/**
	 * Summary
	 *
	 * Description
	 */
	public function setup_admin_menu() {
		// Code for setting up admin menu goes here.
		add_menu_page( self::MENU_TITLE, self::MENU_TITLE, self::MENU_CAPABILITY, self::MENU_SLUG, array( $this, 'main_page' ) );
		add_submenu_page( self::MENU_SLUG, 'Test', 'Test', self::MENU_CAPABILITY, 'my-Test', array( $this, 'perform_background_task' ) );
	}

	/**
	 * Summary
	 */
	public function setup_ajax_actions() {
		foreach ( $this->ajax_actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, $action ) );
		}
	}

	/**
	 * Summary
	 *
	 * @param wp_hook $hook Description.
	 */
	public function enqueue_scripts_styles( $hook ) {
		// Code for enqueuing scripts and styles goes here.
		if ( self::ADMIN_PAGE !== $hook ) {
			return;
		}

		$styles = array(
			'bootstrap-css'         => 'https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css',
			'datatables-css'        => 'https://cdn.datatables.net/1.13.5/css/jquery.dataTables.min.css',
			'datatables-button-css' => 'https://cdn.datatables.net/buttons/1.7.0/css/buttons.dataTables.min.css',
		);

		$scripts = array(
			'bootstrap-js'         => 'https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.min.js',
			'datatables-js'        => 'https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js',
			'datatables-button-js' => 'https://cdn.datatables.net/buttons/1.7.0/js/dataTables.buttons.min.js',
			'jszip-js'             => 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js',
			'pdfmake-js'           => 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js',
			'vfs-fonts-js'         => 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js',
			'buttons-html5-js'     => 'https://cdn.datatables.net/buttons/1.7.0/js/buttons.html5.min.js',
			'sweetalert'           => 'https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js',
		);

		foreach ( $styles as $id => $path ) {
			wp_register_style(
				$id,
				$path,
				array(),
				'1.0'
			);
			wp_enqueue_style( $id );
		}

		foreach ( $scripts as $id => $path ) {
			wp_register_script(
				$id,
				$path,
				array( 'jquery' ),
				'1.0',
				true
			);
			wp_enqueue_script( $id );
		}
	}

	/**
	 * Summary
	 */
	public function setup_cron_jobs() {
		// Code for setting up cron jobs goes here.
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'article_innovator_gj_cron_hook', array( $this, 'perform_background_task' ) );
		add_action( 'init', array( $this, 'setup_cron' ) );
		add_action( 'init', array( $this, 'save_openai_prompt_config_manual' ) );
	}

	/**
	 * Summary
	 */
	public function main_page() {
		include 'pages/all-tabs.php';
	}

	// Extra Functions - Utils.

	/**
	 * Summary
	 *
	 * @throws Exception Our execption class.
	 *
	 * @param string $url Description.
	 * @param string $domain_name Description.
	 * @param string $structure Description.
	 */
	public function map_slug( $url, $domain_name, $structure ) {
		try {
			$parsed_url = wp_parse_url( $url );

			if ( ! isset( $parsed_url['path'] ) ) {
				throw new \Exception( 'Invalid URL: ' . $url );
			}

			$path_segments             = $this->trim_and_split_slash( $parsed_url['path'] );
			$structure_segments        = $this->trim_and_split_slash( $structure );
			$slug_map                  = array( 'domain' => $domain_name );
			$structure_segments_length = count( $structure_segments );

			for ( $i = 0; $i < $structure_segments_length; $i++ ) {
				if ( isset( $path_segments[ $i ] ) && isset( $structure_segments[ $i + 1 ] ) ) {
					$map_key              = str_replace( array( '{', '}' ), '', $structure_segments[ $i + 1 ] );
					$slug_map[ $map_key ] = $path_segments[ $i ];
				}
			}
			return $slug_map;

		} catch ( \Exception $e ) {
			error_log( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Summary
	 *
	 * @param type $path Description.
	 */
	private function trim_and_split_slash( $path ) {
		$trimmed_path = trim( $path, '/' );
		return explode( '/', $trimmed_path );
	}

	/**
	 * Summary
	 *
	 * @param type $author_name Description.
	 */
	private function find_author_id( $author_name ) {
		try {
			if ( empty( $author_name ) ) {
				$user = get_user_by( 'login', 'admin' );
				return ! empty( $user ) ? $user->ID : false;
			}

			$user = get_user_by( 'login', $author_name );
			if ( ! $user ) {
				$user_id = wp_create_user( $author_name, wp_generate_password( 12, false ), '' );
				$user    = new \WP_User( $user_id );
			}

			return ! empty( $user ) && ! is_wp_error( $user ) ? $user->ID : false;
		} catch ( \Exception $e ) {
			error_log( 'Error finding author: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Summary
	 *
	 * @param type $category_name Description.
	 */
	private function find_category_id( $category_name ) {
		try {
			if ( empty( $category_name ) ) {
				return get_option( 'default_category' );
			}

			$category = get_term_by( 'name', $category_name, 'category' );
			if ( ! $category ) {
				$category = wp_create_category( $category_name );
			}

			return ! empty( $category ) && ! is_wp_error( $category ) ? $category->term_id : false;
		} catch ( \Exception $e ) {
			error_log( 'Error finding category: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Summary
	 *
	 * @param type $tag_name Description.
	 */
	private function find_tag_id( $tag_name ) {
		try {
			if ( empty( $tag_name ) ) {
				return false;
			}

			$tag = term_exists( $tag_name, 'post_tag' );
			if ( ! $tag ) {
				$tag = wp_insert_term( $tag_name, 'post_tag' );
			}

			return ! empty( $tag ) && ! is_wp_error( $tag ) ? $tag['term_id'] : false;
		} catch ( \Exception $e ) {
			error_log( 'Error finding tag: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Summary
	 *
	 * @param type $url Description.
	 */
	private function get_clean_domain_name( $url ) {
		try {
			$domain_name = str_replace( array( 'https://www.', 'http://www.', 'https://', 'http://' ), '', $url );
			return explode( '/', $domain_name, 2 )[0];
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		return false;
	}

	/**
	 * Summary
	 *
	 * @param type $url Description.
	 * @param type $selectors Description.
	 */
	private function parse_content( string $url, \stdClass $selectors ) {
		try {
			// Fetch the page
			$curl = curl_init( $url );
			if ( $curl === false ) {
				throw new \Exception( 'Failed to initialize cURL' );
			}

			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			$html = curl_exec( $curl );

			if ( $html === false ) {
				throw new \Exception( 'Failed to fetch web page: ' . curl_error( $curl ) );
			}

			curl_close( $curl );

			// Create a new Dom Document
			$dom = new \DOMDocument();

			// Suppress errors due to invalid HTML structures
			$previousValue = libxml_use_internal_errors( true );

			if ( $dom->loadHTML( $html ) === false ) {
				throw new \Exception( 'Failed to load HTML string into DOM' );
			}

			// Clear any errors generated
			libxml_clear_errors();
			libxml_use_internal_errors( $previousValue );

			// Now you can handle HTML with ease
			$xpath = new \DOMXpath( $dom );
			if ( isset( $selectors->remove_xpath_query ) ) {
				$removeElements = unserialize( $selectors->remove_xpath_query );

				if ( is_array( $removeElements ) ) {
					foreach ( $removeElements as $tag ) {
						$elements = $xpath->query( $tag );

						foreach ( $elements as $element ) {
							$element->parentNode->removeChild( $element );
						}
					}
				}
			}

			$html = $dom->saveHTML();

			try {
				$dom = DomQuery::create( $html );
			} catch ( \Exception $ex ) {
				// Log exception error message rather than hard-coded string
				error_log( 'Caught exception: ' . $ex->getMessage() );
				return array( 'error' => $ex->getMessage() );
			}

			$data = array();
			// Define skip keys outside of the loop
			$skipKeys = array( 'id', 'remove_xpath_query', 'domain_name', 'slug_structure' );

			foreach ( $selectors as $key => $selector ) {
				try {
					$selector = trim( $selector );

					if ( empty( $selector ) || in_array( $key, $skipKeys ) ) {
						continue;
					}

					$value = $dom->find( $selector )->text();

					$data[ $key ] = $value;
				} catch ( \Exception $ex ) {
					error_log( $ex->getMessage() );
					return array( 'error' => $ex->getMessage() );
				}
			}

			return $data;
		} catch ( \Exception $ex ) {
			// If something went wrong, log the error message
			error_log( $ex->getMessage() );
			return array( 'error' => $ex->getMessage() );
		}

		// If everything above fails, return null
		return null;
	}

	// DB Functions
	private function db_save_selectors( $selectors_data ) {
		try {
			global $wpdb;
			$table_name = $wpdb->prefix . 'gj_article_innovator_selectors';
			$wpdb->replace( $table_name, $selectors_data );
			return true;
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		return false;
	}
	private function db_get_selectors( $domain_name ) {
		try {
			global $wpdb;
			$table_name = $wpdb->prefix . 'gj_article_innovator_selectors';
			$selectors  = $wpdb->get_results( "SELECT * FROM `$table_name` where `domain_name` = '$domain_name' ", OBJECT );
			return $selectors;
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		return false;
	}
	private function db_get_domain_name_count_in_selectors( $domain_name ) {
		try {
			global $wpdb;
			$selector_table = $wpdb->prefix . 'gj_article_innovator_selectors';
			$count          = $wpdb->get_results( "SELECT COUNT(*) as count FROM `$selector_table` WHERE `domain_name` = '$domain_name'" );
			return $count;
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		return false;
	}

	/**
	 * Saves URLs in the database.
	 *
	 * @param string $url The URL to be saved.
	 *
	 * @return bool Returns true if a record is successfully replaced, otherwise false.
	 */
	private function db_save_urls( $url ) {
		// Global objects
		global $wpdb;

		// Table name
		$url_table = $wpdb->prefix . 'gj_article_innovator_urls';

		// Placeholder for the result
		$result = false;

		// Use try-catch to properly handle errors
		try {

			// Replace the record
			$result = $wpdb->replace(
				$url_table,
				array(
					'url'    => esc_url( $url ), // Sanitizing input
					'status' => 'INITIATE',
				)
			);

			// Verify result
			if ( $result === false ) {
				throw new \Exception( $wpdb->last_error );
			}
		} catch ( \Exception $ex ) {

			// Error handling
			error_log( 'Exception caught in db_save_urls: ' . $ex->getMessage() );

		}

		return $result;
	}

	private function db_save_keywords( $keyword ) {
		try {
			global $wpdb;

			$table_name                     = $wpdb->prefix . 'gj_article_innovator_keywords';
			$article_innovator_keyword_data = array(
				'keyword' => $keyword,
				'status'  => 'INITIATE',
			);
			return $wpdb->replace( $table_name, $article_innovator_keyword_data );
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		return false;
	}

	// Pages Functions
	public function page_selectors() {
		try {
			if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
				wp_send_json_error( 'Require Post Method' );
			}

			$form_data = $_POST['form_data'];
			$data      = array();

			// Adding or initializing remove_elements to hold values in an array.
			$data['remove_elements'] = array();

			// Convert form_data to associative array
			foreach ( $form_data as $item ) {
				$key   = sanitize_text_field( $item['name'] );
				$value = stripslashes( htmlspecialchars( $item['value'] ) );

				if ( $key === 'remove_elements[]' && trim( $value ) ) {
					$data['remove_elements'][] = $value;
				} else {
					$data[ $key ] = $value;
				}
			}

			$data_to_insert = array(
				'domain_name'        => $data['domain'],
				'slug_structure'     => $data['slug_structure'],
				'title_selector'     => stripslashes( htmlspecialchars( $data['title_selector'] ) ),
				'content_selector'   => stripslashes( htmlspecialchars( $data['content_selector'] ) ),
				'author_selector'    => stripslashes( htmlspecialchars( $data['author_selector'] ) ),
				'category_selector'  => stripslashes( htmlspecialchars( $data['category_selector'] ) ),
				'tag_selector'       => stripslashes( htmlspecialchars( $data['tag_selector'] ) ),
				'remove_xpath_query' => serialize( $data['remove_elements'] ),
			);

			$this->db_save_selectors( $data_to_insert );
			wp_send_json_success( 'Successfully added.' );
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		wp_die(); // all ajax handlers die when finished
	}
	public function page_test_selector() {
		try {
			if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
				wp_send_json_error( 'Require Post Method' );
			}

			$form_data = $_POST['form_data'];
			$data      = array();
			// Convert form_data to associative array
			foreach ( $form_data as $item ) {
				$data[ sanitize_text_field( $item['name'] ) ] = sanitize_text_field( $item['value'] );
			}
			$form_data = $data;

			// Check if URL exists in the submitted form data
			if ( isset( $form_data['test_url'] ) ) {
				// sanitize POST fields
				$test_url = htmlspecialchars( $form_data['test_url'], FILTER_SANITIZE_URL );

				$domain_name = $this->get_clean_domain_name( $test_url );
				if ( ! $domain_name ) {
					wp_send_json_error( 'Domain Name Error.' );
				}

				$article_innovator_selectors = $this->db_get_selectors( $domain_name );
				if ( empty( $article_innovator_selectors ) ) {
					wp_send_json_error( 'Domain not found in selectors.' );
				}
				$selectors = $article_innovator_selectors[0];
				// get selectors from options
				if ( ! empty( $selectors ) ) {
					// get content and data using selectors
					$data = $this->parse_content( $test_url, $selectors );
					if ( isset( $data['error'] ) ) {
						wp_send_json_error( json_encode( $data ) );
					}
					wp_send_json_success( json_encode( $data ) );
				}
			}
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		wp_die();
	}
	public function page_settings() {
		try {
			if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
				wp_send_json_error( 'Require Post Method' );
			}
			$form_data = $_POST['form_data'];
			$data      = array();
			// Convert form_data to associative array
			foreach ( $form_data as $item ) {
				$data[ sanitize_text_field( $item['name'] ) ] = sanitize_text_field( $item['value'] );
			}
			update_option( 'article_innovator_every_minutes', $data['every_minutes'] );
			update_option( 'article_innovator_number_of_urls', $data['number_of_urls'] );
			update_option( 'article_innovator_openai_key', $data['openai_key'] );
			update_option( 'article_innovator_openai_model', $data['openai_model'] );
			wp_send_json_success( 'Success fully saved.' );
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		wp_die();
	}

	public function page_urls() {
		try {
			if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
				wp_send_json_error( 'Require Post Method' );
			}

			$form_data = $_POST['form_data'];
			$data      = array();

			// Convert form_data to associative array
			foreach ( $form_data as $item ) {
				$data[ sanitize_text_field( $item['name'] ) ] = sanitize_text_field( $item['value'] );
			}

			if ( isset( $data['urls'] ) ) {

				// Split URLs based on newline
				$urls = preg_split( "/\r\n|\n|\r| /", trim( html_entity_decode( $data['urls'] ) ) );

				// Process each URL
				foreach ( $urls as $url ) {

					// Extract domain name from URL using predefined function
					$domain_name = $this->get_clean_domain_name( $url );

					// Check domain_name in selectors table
					$count = $this->db_get_domain_name_count_in_selectors( $domain_name );

					// Throw error if domain is not in selectors table
					if ( ! $count ) {
						throw new \Exception( 'Separator not found for domain:' . $domain_name );
					}

					// Insert url to articles table
					$result = $this->db_save_urls( $url );
					if ( ! $result ) {
						wp_send_json_error( "insert error found on : $url" );
					}
				}
				wp_send_json_success( 'Successfully, change the status of data.' );
			} else {
				throw new \Exception( 'No URLs provided' );
			}
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		wp_die();
	}
	public function page_keywords() {
		try {
			if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
				wp_send_json_error( 'Require Post Method' );
			}

			$form_data = $_POST['form_data'];
			$data      = array();
			// Convert form_data to associative array
			foreach ( $form_data as $item ) {
				$data[ sanitize_text_field( $item['name'] ) ] = sanitize_text_field( $item['value'] );
			}
			$form_data = $data;
			if ( isset( $form_data['keywords'] ) ) {
				$keywords = $form_data['keywords'];
				$keywords = stripcslashes( $keywords );
				$keywords = html_entity_decode( $keywords );
				$keywords = explode( "\\n", trim( $keywords ) );
				$keywords = array_map(
					function( $line ) {
						return str_replace( "\\r", '', $line );
					},
					$keywords
				);
				foreach ( $keywords as $keyword ) {
					$keyword = html_entity_decode( $keyword );
					$result  = $this->db_save_keywords( $keyword );
					if ( ! $result ) {
						wp_send_json_error( "insert error found on : $keyword" );
					}
				}
				wp_send_json_success( 'Successfully, change the status of data.' );
				wp_die();
			}
			// If something went wrong
			wp_send_json_error( 'Some error message.' );
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		wp_die();
	}

	public function page_prompts_url() {
		try {
			if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
				wp_send_json_error( 'Require Post Method' );
			}
			$form_data = $_POST['form_data'];
			$form_data = html_entity_decode( $form_data );
			$form_data = stripslashes( $form_data );
			$jsonData  = json_decode( $form_data );
			update_option( 'article_innovator_openai_prompt_url_data', $jsonData );
			wp_send_json_success( 'Successfully saved.' );
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		wp_die(); // all ajax handlers die when finished
	}
	public function page_prompts_keyword() {
		try {
			if ( $_SERVER['REQUEST_METHOD'] != 'POST' ) {
				wp_send_json_error( 'Require Post Method' );
			}
			$form_data = $_POST['form_data'];
			$form_data = html_entity_decode( $form_data );
			$form_data = stripslashes( $form_data );
			$jsonData  = json_decode( $form_data );
			update_option( 'article_innovator_openai_prompt_keyword_data', $jsonData );
			wp_send_json_success( 'Successfully saved.' );
		} catch ( \Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}
		wp_die(); // all ajax handlers die when finished
	}


	// Updated ^

	// Scheduling Functions
	public function perform_background_task() {
		$number_of_urls = (int) get_option( 'article_innovator_number_of_urls', 1 );
		// Get the URLs desired, this function should return an array of URLs
		try {
			global $wpdb;
			$table_name                  = $wpdb->prefix . 'gj_article_innovator_urls';
			$article_innovator_urls_data = $wpdb->get_results( "SELECT * FROM `$table_name` where `status` = 'INITIATE' order by id desc LIMIT $number_of_urls", OBJECT );
			foreach ( $article_innovator_urls_data as $article_innovator_url_data ) {
				$this->change_status_url( $article_innovator_url_data, 'PROCESS' );
			}

			$this->download_specific_article_innovator_urls_to_post_data( $article_innovator_urls_data );
		} catch ( \Exception $ex ) {
			error_log( $ex->getMessage() );
		}

		// Get the Keywords desired, this function should return an array of Keywords
		try {
			global $wpdb;
			$table_name                      = $wpdb->prefix . 'gj_article_innovator_keywords';
			$article_innovator_keywords_data = $wpdb->get_results( "SELECT * FROM `$table_name` where `status` = 'INITIATE' order by id desc LIMIT $number_of_urls", OBJECT );

			foreach ( $article_innovator_keywords_data as $article_innovator_keyword_data ) {
				$this->change_status_keyword( $article_innovator_keyword_data, 'PROCESS' );
			}

			$this->download_specific_article_innovator_keywords_to_post_data( $article_innovator_keywords_data );
		} catch ( \Exception $ex ) {
			error_log( $ex->getMessage() );
		}
	}

	public function cron_schedules( $schedules ) {
		// add a custom schedule called 'every_ten_minutes'
		$every_minutes = get_option( 'article_innovator_every_minutes', 10 );

		$schedules['article_innovator_every_number_minutes'] = array(
			'interval' => $every_minutes * MINUTE_IN_SECONDS,
			'display'  => __( 'article_innovator Every Number Minutes' ),
		);

		return $schedules;
	}

	public function setup_cron() {
		// Make sure our cron job is registered
		if ( ! wp_next_scheduled( 'article_innovator_gj_cron_hook' ) ) {
			wp_schedule_event( time(), 'article_innovator_every_number_minutes', 'article_innovator_gj_cron_hook' );
		}
	}


	public function download_specific_article_innovator_urls_to_post_data( $article_innovator_urls_data ) {
		foreach ( $article_innovator_urls_data as $article_innovator_url_data ) {
			$website_url = $article_innovator_url_data->url;
			echo 'Processing - ' . $website_url . "\n";
			$domain_name = str_replace( 'https://www.', '', $website_url );
			$domain_name = str_replace( 'https://', '', $domain_name );
			$domain_name = str_replace( 'http://www.', '', $domain_name );
			$domain_name = str_replace( 'https://', '', $domain_name );
			$domain_name = explode( '/', $domain_name, 2 )[0];
			// Check domain_name in selector
			global $wpdb;
			$table_name                       = $wpdb->prefix . 'gj_article_innovator_selectors';
			$article_innovator_selectors_data = $wpdb->get_results( "SELECT * FROM `$table_name` where `domain_name` = '$domain_name'", OBJECT );
			if ( empty( $article_innovator_selectors_data ) ) {
				error_log( 'Domain Name is not in Selector. - ' . $website_url );
				wp_die();
			}
			$this->change_status_url( $article_innovator_url_data, 'SELECTORS_FOUND' );
			$article_innovator_selectors_data = $article_innovator_selectors_data[0];
			$slug_map                         = $this->map_slug( $website_url, $domain_name, $article_innovator_selectors_data->slug_structure );
			if ( ! array_key_exists( 'slug', $slug_map ) ) {
				echo 'Ignore - ' . $website_url . "\n";
				continue;
			}
			$slug      = $slug_map['slug'];
			$selectors = array(
				'title_selector'     => $article_innovator_selectors_data->title_selector,
				'content_selector'   => $article_innovator_selectors_data->content_selector,
				'category_selector'  => $article_innovator_selectors_data->category_selector,
				'author_selector'    => $article_innovator_selectors_data->author_selector,
				'tag_selector'       => $article_innovator_selectors_data->tag_selector,
				'remove_xpath_query' => $article_innovator_selectors_data->remove_xpath_query,
			);
			$this->change_status_url( $article_innovator_url_data, 'SLUG_STRUCTURE_DONE' );
			$website_url = html_entity_decode( $website_url );

			// $data = $this->parse_content( $website_url, $selectors );
			// print_r( $data );
			$data = array();
			echo '<br/><br/>------Scraping Done <br/><br/>';
			$this->change_status_url( $article_innovator_url_data, 'SCRAPING_DONE' );
			if ( is_array( $data ) ) {
				/*----- GPT PROMPT ------*/
				$scraping_data = array(
					'title'   => $data['title_selector'],
					'content' => $data['content_selector'],
				);
				/*
					-- Get the Information --*/
				// Get prompt config
				$openai_key    = get_option( 'article_innovator_openai_key', '' );
				$openai_model  = get_option( 'article_innovator_openai_model', '' );
				$config        = get_option( 'article_innovator_openai_prompt_config', array() );
				$output_format = $config['output_format'];

				// Get prompt structure
				$prompt_structure = get_option( 'article_innovator_openai_prompt_url_data', '' );
				if ( empty( $prompt_structure ) ) {
					$this->change_status_url( $article_innovator_url_data, 'MISSING_PROMPT' );
					continue;
				}

				$prompt_commands_obj             = new Prompt_Commands();
				Prompt_Commands::$openai_api_key = $openai_key;
				Prompt_Commands::$model          = $openai_model;
				Prompt_Commands::$max_tokens     = 2000;

				$messages = array();

				$ai_content           = array();
				$ai_generated_message = '[]';
				foreach ( $prompt_structure as $ps ) {
					if ( $ps->role == 'system' ) {
						array_push(
							$messages,
							array(
								'role'    => $ps->role,
								'content' => $ps->message,
							)
						);
					}
					if ( $ps->role == 'user' ) {
						$output_structure = '';
						foreach ( $output_format as $of ) {
							if ( $of['name'] == $ps->output_format ) {
								$output_structure = $of['structure'];
							}
						}
						$message = $ps->message;
						// Replace with static variables like {scraping_content}
						$message  = str_replace( '{scraping_content}', trim( $scraping_data['content'] ), $message );
						$message  = str_replace( '{scraping_title}', $scraping_data['title'], $message );
						$message .= "\nOutput: \n" . $output_structure;
						array_push(
							$messages,
							array(
								'role'    => $ps->role,
								'content' => $message,
							)
						);
					}
					if ( $ps->role == 'assistant' ) {
						if ( $ps->command == 'normal' ) {
							$ai_generated_message = $prompt_commands_obj->execute_openai_request( $messages );
							$this->change_status_url( $article_innovator_url_data, 'ASSISTANT_NORMAL_DONE' );

							try {
								$ai_generated_message = json_decode( $ai_generated_message );} catch ( \Exception $ex ) {
								$this->change_status_url( $article_innovator_url_data, 'ERROR_JSON_EXTRACT' );
								print_r( $ex->getMessage() );
								error_log( $ex->getMessage() );
								continue;
								}
						} elseif ( $ps->command == 'expand_content' ) {
							$ai_generated_message = $prompt_commands_obj->execute_openai_request( $messages );
							$this->change_status_url( $article_innovator_url_data, 'ASSISTANT_NORMAL_IN_EXPAND_DONE' );

							try {
								$ai_generated_message = json_decode( $ai_generated_message );} catch ( \Exception $ex ) {
								$this->change_status_url( $article_innovator_url_data, 'ERROR_JSON_EXTRACT' );
								print_r( $ex->getMessage() );
								error_log( $ex->getMessage() );
								continue;
								}

														$ai_generated_message = $prompt_commands_obj->expand_content_with_formatting_for_url( $ai_generated_message );
														$this->change_status_url( $article_innovator_url_data, 'ASSISTANT_EXPAND_CONTENT_DONE' );

						} else {
							// Make it blank
						}
					}
				}
				if ( isset( $ai_generated_message->content ) ) {
					$ai_generated_message = $prompt_commands_obj->convert_json_to_html_content_for_url( $ai_generated_message );
				} else {
					$this->change_status_url( $article_innovator_url_data, 'ERROR_JSON_EXTRACT_CONTENT' );
					continue;
				}
				// $ai_generated_message = $prompt_commands_obj->depricated_format_html_content_for_url($ai_generated_message);

				// Find or create author
				$author_id = null;
				if ( array_key_exists( 'author_selector', $data ) ) {
					$author_id = $this->find_author_id( $data['author_selector'] );
				}

				// Find or create tags
				$tag_ids = null;
				if ( array_key_exists( 'tag_selector', $data ) ) {
					$tags    = explode( ',', $data['tag_selector'] );
					$tag_ids = array();
					foreach ( $tags as $tag_name ) {
						$tag_ids[] = $this->find_tag_id( trim( $tag_name ) );
					}
				}

				// Find or create category
				$category_id = null;
				if ( array_key_exists( 'category_selector', $data ) ) {
					$category_id = $this->find_category_id( $data['category_selector'] );
				}

				$wordpress_post = array(
					'post_title'   => $ai_generated_message->title,
					'post_content' => html_entity_decode( $ai_generated_message->content ),
					'post_status'  => 'draft',
					'post_type'    => 'post',
					'post_name'    => $slug, // This is the slug
				);
				if ( ! is_null( $author_id ) ) {
					$wordpress_post['post_author'] = $author_id;
				}
				if ( ! is_null( $tag_ids ) ) {
					print_r( $tag_ids );
					$wordpress_post['tags_input'] = $tag_ids;
				}
				if ( ! is_null( $category_id ) ) {
					$wordpress_post['post_category'] = $category_id;
				}

				$post_id                             = wp_insert_post( $wordpress_post, true );
				$article_innovator_url_data->post_id = $post_id;
				$this->change_status_url( $article_innovator_url_data, 'SUCCESS' );
				echo 'Success - ' . $website_url . "\n";
			} else {
				$this->change_status_url( $article_innovator_url_data, 'ERROR' );
			}
		}
	}

	public function download_specific_article_innovator_keywords_to_post_data( $article_innovator_keywords_data ) {
		foreach ( $article_innovator_keywords_data as $article_innovator_keyword_data ) {
			$keyword = $article_innovator_keyword_data->keyword;
			$keyword = html_entity_decode( $keyword );
			/*
			----- GPT PROMPT ------*/
			/*
				-- Get the Information --*/
			// Get prompt config
			$openai_key       = get_option( 'article_innovator_openai_key', '' );
			$openai_model     = get_option( 'article_innovator_openai_model', '' );
			$config           = get_option( 'article_innovator_openai_prompt_config', array() );
			$output_format    = $config['output_format'];
			$prompt_structure = get_option( 'article_innovator_openai_prompt_keyword_data', '' );
			if ( empty( $prompt_structure ) ) {
				$this->change_status_keyword( $article_innovator_keyword_data, 'MISSING_PROMPT' );
				continue;
			}
			// Get prompt structure
			$prompt_commands_obj             = new Prompt_Commands();
			Prompt_Commands::$openai_api_key = $openai_key;
			Prompt_Commands::$model          = $openai_model;
			Prompt_Commands::$max_tokens     = 2000;

			$messages = array();

			echo '<pre>';
			$ai_content           = array();
			$ai_generated_message = '[]';
			foreach ( $prompt_structure as $ps ) {
				if ( $ps->role == 'system' ) {
					array_push(
						$messages,
						array(
							'role'    => $ps->role,
							'content' => $ps->message,
						)
					);
				}
				if ( $ps->role == 'user' ) {
					$output_structure = '';
					foreach ( $output_format as $of ) {
						if ( $of['name'] == $ps->output_format ) {
							$output_structure = $of['structure'];
						}
					}
					$message = $ps->message;
					// Replace with static variables like {scraping_content}
					$message  = str_replace( '{keyword}', trim( $keyword ), $message );
					$message .= "\nOutput: \n" . $output_structure;
					array_push(
						$messages,
						array(
							'role'    => $ps->role,
							'content' => $message,
						)
					);
				}
				if ( $ps->role == 'assistant' ) {
					if ( $ps->command == 'normal' ) {
						$ai_generated_message = $prompt_commands_obj->execute_openai_request( $messages );
						$this->change_status_keyword( $article_innovator_keyword_data, 'ASSISTANT_NORMAL_DONE' );

						try {
							$ai_generated_message = json_decode( $ai_generated_message );} catch ( \Exception $ex ) {
							$this->change_status_keyword( $article_innovator_keyword_data, 'ERROR_JSON_EXTRACT' );
							print_r( $ex->getMessage() );
							error_log( $ex->getMessage() );
							continue;
							}
					} elseif ( $ps->command == 'expand_content' ) {
						$ai_generated_message = $prompt_commands_obj->execute_openai_request( $messages );
						$this->change_status_keyword( $article_innovator_keyword_data, 'ASSISTANT_NORMAL_IN_EXPAND_DONE' );
						try {
							$ai_generated_message = json_decode( $ai_generated_message );} catch ( \Exception $ex ) {
							$this->change_status_keyword( $article_innovator_keyword_data, 'ERROR_JSON_EXTRACT' );
							print_r( $ex->getMessage() );
							error_log( $ex->getMessage() );
							continue;
							}
												$ai_generated_message = $prompt_commands_obj->expand_content_with_formatting_for_keyword( $ai_generated_message );
												$this->change_status_keyword( $article_innovator_keyword_data, 'ASSISTANT_EXPAND_CONTENT_DONE' );
					} else {
						// Make it blank
					}
				}
			}
			if ( isset( $ai_generated_message->content ) ) {
				$ai_generated_message = $prompt_commands_obj->convert_json_to_html_content_for_keyword( $ai_generated_message );
			} else {
				$this->change_status_keyword( $article_innovator_keyword_data, 'ERROR_JSON_EXTRACT_CONTENT' );
				continue;
			}
			// $ai_generated_message = $prompt_commands_obj->depricated_format_html_content_for_url($ai_generated_message);

			// Find or create tags
			if ( property_exists( $ai_generated_message, 'tags' ) ) {
				$tags    = explode( ',', $ai_generated_message->tags );
				$tag_ids = array();
				foreach ( $tags as $tag_name ) {
					$tag_ids[] = $this->find_tag_id( trim( $tag_name ) );
				}
			}

			// Find or create category
			if ( property_exists( $ai_generated_message, 'category' ) ) {
				$category_id = $this->find_category_id( $ai_generated_message->category );
			}

			$wordpress_post                          = array(
				'post_title'    => $ai_generated_message->title,
				'post_content'  => html_entity_decode( $ai_generated_message->content ),
				'post_status'   => 'draft',
				'post_type'     => 'post',
				'post_name'     => isset( $slug ) ? $slug : '', // This is the slug
				'tags_input'    => isset( $tag_ids ) ? $tag_ids : array(), // Adds tags to the post
				'post_category' => isset( $category_id ) ? array( $category_id ) : array(), // Adds category to the post
			);
			$post_id                                 = wp_insert_post( $wordpress_post, true );
			$article_innovator_keyword_data->post_id = $post_id;
			$this->change_status_keyword( $article_innovator_keyword_data, 'SUCCESS' );
			print_r( 'Success' );
		}
	}

	private function fetch_article_innovator_urls_table_data() {
		global $wpdb;
		$table_name             = $wpdb->prefix . 'gj_article_innovator_urls';
		$article_innovator_urls = $wpdb->get_results( "SELECT * FROM `$table_name`", OBJECT );

		$data = array();
		foreach ( $article_innovator_urls as $article_innovator_url ) {
			$data[] = array(
				'id'  => $article_innovator_url->id,
				'url' => $article_innovator_url->url,
			);
		}
		wp_send_json( $data );
		wp_die();
	}

	private function urls_table_csv() {
		ini_set( 'memory_limit', '1024M' );

		global $wpdb;
		$table_name             = $wpdb->prefix . 'gj_article_innovator_urls';
		$article_innovator_urls = $wpdb->get_results( "SELECT * FROM `$table_name`", ARRAY_A );

		// Use output buffering to prevent header issues
		ob_start();

		$file_name = 'article_innovator_urls.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $file_name );

		$output = fopen( 'php://output', 'w' );

		fputcsv( $output, array( 'ID', 'Url', 'article_innovator Url' ) );

		foreach ( $article_innovator_urls as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );
		ob_end_flush();
		exit();
	}

	public function get_selector_data() {
		$id = intval( $_POST['id'] );

		global $wpdb;
		$table_name = $wpdb->prefix . 'gj_article_innovator_selectors';
		$selector   = $wpdb->get_row( "SELECT * FROM $table_name WHERE id = $id" );

		if ( $selector ) {
			$remove_xpath_query           = array();
			$selector->remove_xpath_query = unserialize( $selector->remove_xpath_query );
			foreach ( $selector->remove_xpath_query as $rxq ) {
				array_push( $remove_xpath_query, html_entity_decode( $rxq ) );
			}

			wp_send_json( $selector );
		} else {
			wp_send_json_error( 'Selector not found' );
		}

		wp_die();
	}

	private function delete_selector_data() {
		$id = intval( $_POST['id'] );

		global $wpdb;
		$table_name = $wpdb->prefix . 'gj_article_innovator_selectors';
		$wpdb->delete( $table_name, array( 'id' => $id ) );
		wp_send_json_success( 'Successfully deleted.' );
		wp_die();
	}

	public function change_status_url( $article_innovator_url_data, $status ) {
		global $wpdb;
		$table_name                         = $wpdb->prefix . 'gj_article_innovator_urls';
		$article_innovator_url_data->status = $status;
		$article_innovator_url_data         = get_object_vars( $article_innovator_url_data );
		$wpdb->replace( $table_name, $article_innovator_url_data );
	}

	public function change_status_keyword( $article_innovator_keyword_data, $status ) {
		global $wpdb;
		$table_name                             = $wpdb->prefix . 'gj_article_innovator_keywords';
		$article_innovator_keyword_data->status = $status;
		$article_innovator_keyword_data         = get_object_vars( $article_innovator_keyword_data );
		$wpdb->replace( $table_name, $article_innovator_keyword_data );
	}

	// Prompt Functions
	public function save_openai_prompt_config_manual() {
		$config   = array();
		$commands = array( 'normal', 'expand_content' );
		$roles    = array( 'system', 'user', 'assistant' );
		// Output format
		$op_struct_json          = array(
			'title'            => 'generated catchy title',
			'meta_description' => 'meta description should be here',
			'meta_title'       => 'meta title should be here',
			'category'         => 'Perfect category for this article',
			'tags'             => 'list of tags with , seperated',
			'content'          => array(
				array(
					'heading_tag'  => 'From H1 to H6 tag based on structure',
					'headline'     => 'Write an engaging headline for H1 to H6 tag',
					'html_content' => 'HTML content contains strong, table, ul, li tags with html content',
				),
			),
		);
		$output_format           = array(
			array(
				'name'      => 'op_struct_json',
				'structure' => json_encode( $op_struct_json ),
			),
		);
		$config['commands']      = $commands;
		$config['roles']         = $roles;
		$config['output_format'] = $output_format;
		update_option( 'article_innovator_openai_prompt_config', $config );
	}

}
