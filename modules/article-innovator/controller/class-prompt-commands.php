<?php
/**
 * Contains Prompt Commands
 *
 * @package   Botxbyte\Article_Innovator
 */

namespace Botxbyte\Article_Innovator;

/**
 * Class Prompt_Commands
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/includes
 */

if ( ! class_exists( 'Prompt_Commands' ) ) {
	/**
	 * Prompt_Commands Class
	 */
	class Prompt_Commands {

		/**
		 * Openai API Key
		 *
		 * @var string
		 */
		public static $openai_api_key;

		/**
		 * Openai Model
		 *
		 * @var string
		 */
		public static $model;

		/**
		 * Openai Max Tokens
		 *
		 * @var int
		 */
		public static $max_tokens;

		/**
		 * Execute Openai Request
		 *
		 * @param array $messages Passing chat messages.
		 * @return json
		 */
		public function execute_openai_request( $messages ) {
			for ( $try_count = 0; $try_count < 3; $try_count++ ) {
				echo "gpt call - $try_count\n<br/>";
				try {
					$curl = curl_init();
					curl_setopt_array(
						$curl,
						array(
							CURLOPT_URL            => 'https://api.openai.com/v1/chat/completions',
							CURLOPT_RETURNTRANSFER => true,
							CURLOPT_ENCODING       => '',
							CURLOPT_MAXREDIRS      => 10,
							CURLOPT_TIMEOUT        => 0,
							CURLOPT_FOLLOWLOCATION => true,
							CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
							CURLOPT_CUSTOMREQUEST  => 'POST',
							CURLOPT_POSTFIELDS     => '{
							"model": "' . self::$model . '",
							"messages": ' . json_encode( $messages ) . ',
							"temperature": 1,
							"max_tokens": ' . strval( self::$max_tokens ) . ',
							"top_p": 1,
							"frequency_penalty": 0,
							"presence_penalty": 0
						}',
							CURLOPT_HTTPHEADER     => array(
								'Content-Type: application/json',
								'Authorization: Bearer ' . self::$openai_api_key,
							),
						)
					);
					$response = curl_exec( $curl );
					curl_close( $curl );
					$response = json_decode( $response );
					print_r( $response );
					if ( isset( $response->error ) ) {
						print_r( $response->error );
						continue;
					}
					if ( isset( $response->choices ) ) {
						$choices = $response->choices;
						$choices = $choices[0];
						if ( $choices->finish_reason == 'length' ) {
							$max_tokens  = intval( self::$max_tokens );
							$max_tokens  = $max_tokens + 100;
							$return_data = $this->execute_openai_request( self::$openai_api_key, self::$model, $messages, $max_tokens );
							return $return_data;
						}
						return $choices->message->content;
					}
				} catch ( \Exception $ex ) {
					error_log( $ex->getMessage() );
				}
			}
		}

		public function expand_content_with_formatting_for_url( $ai_generated_message ) {
			try {
				$system_prompt     = 'You are SEO Writer.';
				$expand_prompt     = 'Expand the content upto 200 maximum words';
				$formatting_prompt = ' with Formmating the content and connecting line with Relevant strong (for few words only), italique (for few words only), underline (for few words only), table, ul, li html tags content';

				$probability                   = '4/10';
				list($numerator, $denominator) = explode( '/', $probability );
				$zeroes                        = $denominator - $numerator;
				$prob_array                    = array_fill( 0, $numerator, 1 );
				$prob_array                    = array_merge( $prob_array, array_fill( 0, $zeroes, 0 ) );

				print_r( $prob_array );

				// Now $prob_array contains 4 ones and 6 zeroes
				shuffle( $prob_array );   // shuffle to randomize the 1s and 0s
				$prob_array_key = 0;
				$content        = $ai_generated_message->content;
				foreach ( $content as $key => $c ) {
					$prompt = $expand_prompt;
					if ( $prob_array[ $prob_array_key ] ) {
						$prompt = $expand_prompt . $formatting_prompt;
					}
					if ( $prob_array_key == count( $prob_array ) ) {
						$prob_array_key = 0;
					}
					$prob_array_key += 1;
					$messages        = array(
						array(
							'role'    => 'system',
							'content' => $system_prompt,
						),
						array(
							'role'    => 'user',
							'content' => $prompt . ' : ' . $c->html_content,
						),
					);
					$c->html_content = $this->execute_openai_request( $messages );
					$content[ $key ] = $c;
					print_r( $c );
				}
				$ai_generated_message->content = $content;
				return $ai_generated_message;
			} catch ( \Exception $ex ) {
				error_log( $ex->getMessage() );
			}
		}

		public function convert_json_to_html_content_for_url( $ai_generated_message ) {
			try {
				$content        = $ai_generated_message->content;
				$return_content = '';
				foreach ( $content as $key => $c ) {
					$heading_tag     = $c->heading_tag;
					$headline        = $c->headline;
					$html_content    = $c->html_content;
					$return_content .= "<$heading_tag>$headline</$heading_tag>\n<p>$html_content</p>";
				}
				$ai_generated_message->content = htmlentities( $return_content );
				return $ai_generated_message;
			} catch ( \Exception $ex ) {
				error_log( $ex->getMessage() );
			}
		}

		public function expand_content_with_formatting_for_keyword( $ai_generated_message ) {
			try {
				$system_prompt                 = 'You are SEO Writer.';
				$expand_prompt                 = 'Expand the content upto 200 maximum words';
				$formatting_prompt             = ' with Formmating the content and connecting line with Relevant strong (for few words only), italique (for few words only), underline (for few words only), table, ul, li html tags content';
				$probability                   = '4/10';
				list($numerator, $denominator) = explode( '/', $probability );
				$zeroes                        = $denominator - $numerator;
				$prob_array                    = array_fill( 0, $numerator, 1 );
				$prob_array                    = array_merge( $prob_array, array_fill( 0, $zeroes, 0 ) );

				// Now $prob_array contains 4 ones and 6 zeroes
				shuffle( $prob_array );   // shuffle to randomize the 1s and 0s

				$prob_array_key = 0;
				$content        = $ai_generated_message->content;
				foreach ( $content as $key => $c ) {
					$prompt = $expand_prompt;
					if ( $prob_array[ $prob_array_key ] ) {
						$prompt = $expand_prompt . $formatting_prompt;
					}
					if ( $prob_array_key == count( $prob_array ) ) {
						$prob_array_key = 0;
					}
					$prob_array_key += 1;
					$messages        = array(
						array(
							'role'    => 'system',
							'content' => $system_prompt,
						),
						array(
							'role'    => 'user',
							'content' => $prompt . ' : ' . $c->html_content,
						),
					);
					$c->html_content = $this->execute_openai_request( $messages );
					$content[ $key ] = $c;
					print_r( $c );
				}
				$ai_generated_message->content = $content;
				return $ai_generated_message;
			} catch ( \Exception $ex ) {
				error_log( $ex->getMessage() );
			}
		}

		public function convert_json_to_html_content_for_keyword( $ai_generated_message ) {
			try {
				$content        = $ai_generated_message->content;
				$return_content = '';
				foreach ( $content as $key => $c ) {
					$heading_tag     = $c->heading_tag;
					$headline        = $c->headline;
					$html_content    = $c->html_content;
					$return_content .= "<$heading_tag>$headline</$heading_tag>\n<p>$html_content</p>";
				}
				$ai_generated_message->content = htmlentities( $return_content );
				return $ai_generated_message;
			} catch ( \Exception $ex ) {
				error_log( $ex->getMessage() );
			}
		}

		public function depricated_format_html_content_for_url( $ai_generated_message ) {
			try {
				$system_prompt = 'You are SEO Writer.';
				$prompt        = 'Formmating the content with strong, italique, underline, h1..h6, blockquote, table, ul, li html tags content: ';

				$content  = html_entity_decode( $ai_generated_message->content );
				$messages = array(
					array(
						'role'    => 'system',
						'content' => $system_prompt,
					),
					array(
						'role'    => 'user',
						'content' => $prompt . $content,
					),
				);
				print_r( $messages );
				$ai_generated_message->content = htmlentities( $this->execute_openai_request( $messages ) );
				return $ai_generated_message;
			} catch ( \Exception $ex ) {
				error_log( $ex->getMessage() );
			}
		}
	}
}
