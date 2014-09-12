<?php
/**
 * OKAPI class for ok.com social network
 *
 * @package server API methods
 * @link    http://apiok.ru/
 * @autor   Oleg Illarionov, Cyril Arkhipenko
 * @version 1.0
 */

namespace OK;

class OKAPI {

	private $app_id;
	private $app_key;
	private $app_secret_key;
	private $api_url;

	public function __construct($cfg) {
		// set default
		$cfg += array(
			'api_url' => 'http://api.ok.ru/fb.do',
		);

		$this->api_url        = $cfg['api_url'];
		$this->app_id         = $cfg['app_id'];
		$this->app_key        = $cfg['app_key'];
		$this->app_secret_key = $cfg['app_secret_key'];
	}

	public function exec($method, $params = array(), $client = false) {
		$params['application_key'] = $this->app_key;
		$params['method']          = $method;
		$params['format']          = 'JSON';

		if ($client) {
			$params['session_key'] = @$client['session_key'];
			$params['sig']         = $this->sign($params, @$client['session_secret_key']);
		} else {
			$params['sig'] = $this->sign($params, $this->app_secret_key);
		}

		$query = $this->api_url . '?' . http_build_query($params);

		$context = stream_context_create(
			array(
				'http' =>
					array(
						'timeout'       => 5,
						'ignore_errors' => true
					)
			));

		$response = json_decode($r = file_get_contents($query, false, $context), true);

		if (isset($response['error_msg'])) {
			throw new \Exception($response['error_msg']);
		}

		return $response;
	}

	public function sign($params, $secret) {
		ksort($params);
		$sig = '';
		foreach ($params as $k => $v) {
			$sig .= $k . '=' . $v;
		}
		$sig .= $secret;

		return md5($sig);
	}

	public function response($data) {
		return json_encode(
			array(
				'response' => $data
			));
	}

	public function error($error_code, $error_msg, $critical = false) {
		return json_encode(
			array(
				'error' => array(
					'error_code' => $error_code,
					'error_msg'  => $error_msg,
					'critical'   => $critical
				)
			));
	}

	public function upload($url, $files) {
		$boundary = uniqid('---------------------------', true);
		$header   = "Content-Type: multipart/form-data; boundary={$boundary}";
		$content  = "--{$boundary}";

		foreach ($files as $name => $path) {
			$file_contents = file_get_contents($path);
			$file_name     = basename($path);
			$mime_type     = mime_content_type($path);
			$content .= "\n";
			$content .= "Content-Disposition: file; name=\"{$name}\"; filename=\"{$file_name}\"\n";
			$content .= "Content-Type: {$mime_type}\n";
			$content .= "Content-Transfer-Encoding: binary\n\n";
			$content .= $file_contents;
			$content .= "\n";
			$content .= "--{$boundary}";
		}

		$content .= "--\n";

		$context = stream_context_create(
			array(
				'http' => array(
					'method'  => 'POST',
					'header'  => $header,
					'content' => $content,
				)
			));

		$response = json_decode(file_get_contents($url, false, $context), true);

		if (isset($response['error'])) {
			throw new \Exception("{$response['error']['error_msg']}", $response['error']['error_code']);
		}

		return $response;
	}
}
