<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/*
	Mad Mimi for PHP
	v2.0.1 - Cleaner, faster, and much easier to use and extend. (In my opinion!)
	
	For release notes, see the README that should have been included.
	
	_______________________________________

	Copyright (C) 2010 Mad Mimi LLC 
	Authored by Nicholas Young <nicholas@madmimi.com> ...and a host of contributors.

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.
*/

if (!function_exists('curl_init')) {
  die('Mad Mimi for PHP requires the PHP cURL extension.');
}

class MadMimi {
	function __construct() {
		
		$this->ci =& get_instance();

		$this->ci->load->config('madmimi');
		
		$this->ci->load->library('spyc');
		
		$this->username = $this->ci->config->item('madmimi_email');
		$this->api_key = $this->ci->config->item('madmimi_api');
		$this->debug = $this->ci->config->item('madmimi_debug');
		
		//$this->username = 'brett@digitalwaxworks.com';
		//$this->api_key ='7432373827dc1d12b91c46fa0ab71234';
		//$this->debug = FALSE;
		
	}
	function default_options() {
		return array('username' => $this->username, 'api_key' => $this->api_key);
	}
	function DoRequest($path, $options, $return_status = false, $method = 'GET', $mail = false) {
		$url = "";
		if ($method == 'GET') {
			$request_options = "?";
		} else {
			$request_options = "";
		}
		$request_options .= http_build_query($options);
		if ($mail == false) {
			$url .= "http://api.madmimi.com{$path}";
		} else {
			$url .= "https://api.madmimi.com{$path}";
		}
		if ($method == 'GET') {
			$url .= $request_options;
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		if ($return_status == true) {
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		} else {
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		}
		switch($method) {
			case 'GET':
				break;
			case 'POST':
				curl_setopt($ch, CURLOPT_POST, TRUE);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $request_options);
				if (strstr($url, 'https')) {
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
				}
				break;
		}
		if ($this->debug == true) {
			echo "URL: {$url}<br />";
			if ($method == 'POST') {
				echo "Request Options: {$request_options}";
			}
		} else {
			$result = curl_exec($ch) or die(curl_error($ch));
		}
		curl_close($ch);
		if ($this->debug == false) {
			return $result;
		}
	}
	function build_csv($arr) {
		$csv = "";
		$keys = array_keys($arr);
		foreach ($keys as $key => $value) {
			$csv .= $value . ",";
		}
		$csv = substr($csv, 0, -1);
		$csv .= "\n";
		foreach ($arr as $key => $value) {
			$csv .= $value . ",";
		}
		$csv = substr($csv, 0, -1);
		$csv .= "\n";
		return $csv;
	}
	function Import($csv_data, $return = false) {
		$options = array('csv_file' => $csv_data) + $this->default_options();
		$request = $this->DoRequest('/audience_members', $options, $return, 'POST');
		return $request;
	}
	function Lists($return = false) {
		$request = $this->DoRequest('/audience_lists/lists.xml', $this->default_options(), $return);
		return $request;
	}
	function AddUser($user, $return = false) {
		$csv = $this->build_csv($user);
		$this->Import($csv, $return);
	}
	function RemoveUser($email, $list_name, $return = false) {
		$options = array('email' => $email) + $this->default_options();
		$request = $this->DoRequest('/audience_lists/' . rawurlencode($list_name) . "/remove", $options, $return, 'POST');
		return $request;
	}
	function Memberships($email, $return = false) {
		$url = str_replace('%email%', $email, '/audience_members/%email%/lists.xml');
		$request = $this->DoRequest($url, $this->default_options(), $return);
		return $request;
	}
	function NewList($list_name, $return = false) {
		$options = array('name' => $list_name) + $this->default_options();
		$request = $this->DoRequest('/audience_lists', $options, $return, 'POST');
		return $request;
	}
	function DeleteList($list_name, $return = false) {
		$options = array('_method' => 'delete') + $this->default_options();
		$request = $this->DoRequest('/audience_lists/' . rawurlencode($list_name), $options, $return, 'POST');
		return $request;
	}
	function SendMessage($options, $yaml_body = null, $return = false) {
		if (class_exists('Spyc') && $yaml_body != null) {
			$options['body'] = Spyc::YAMLDump($yaml_body);
		}
		$options = $options + $this->default_options();
		if (isset($options['list_name'])) {
			$request = $this->DoRequest('/mailer/to_list', $options, $return, 'POST', true);
		} else {
			$request = $this->DoRequest('/mailer', $options, $return, 'POST', true);
		}
		return $request;
	}
	function SendHTML($options, $html, $return = false) {
		if ((!strstr($html, '[[tracking_beacon]]')) && (!strstr($html, '[[peek_image]]'))) {
			die('Please include either the [[tracking_beacon]] or the [[peek_image]] macro in your HTML.');
		}
		$options = $options + $this->default_options();
		$options['raw_html'] = $html;
		if (isset($options['list_name'])) {
			$request = $this->DoRequest('/mailer/to_list', $options, $return, 'POST', true);
		} else {
			$request = $this->DoRequest('/mailer', $options, $return, 'POST', true);
		}
		return $request;
	}
	function SendPlainText($options, $message, $return = false) {
		if (!strstr($message, '[[unsubscribe]]')) {
			die('Please include the [[unsubscribe]] macro in your text.');
		}
		$options = $options + $this->default_options();
		$options['raw_plain_text'] = $message;
		if (isset($options['list_name'])) {
			$request = $this->DoRequest('/mailer/to_list', $options, $return, 'POST', true);
		} else {
			$request = $this->DoRequest('/mailer', $options, $return, 'POST', true);
		}
		echo $request;
		return $request;
		
	}
	function SuppressedSince($unix_timestamp, $return = false) {
		$request = $this->DoRequest('/audience_members/suppressed_since/' . $unix_timestamp . '.txt', $this->default_options(), $return);
		return $request;
	}
	function Promotions($page = 1, $return = false) {
		$options = array('page' => $page) + $this->default_options();
		$request = $this->DoRequest('/promotions.xml', $options, $return);
		return $request;
	}
	function MailingStats($promotion_id, $mailing_id, $return = false) {
		$url = str_replace("%promotion_id%", $promotion_id, "/promotions/%promotion_id%/mailings/%mailing_id%.xml");
		$url = str_replace("%mailing_id%", $mailing_id, $url);
		$request = $this->DoRequest($url, $this->default_options(), $return);
		return $request;
	}
	function Search($query_string, $raw = false, $return = false) {
		$options = array('query' => $query_string, 'raw' => $raw) + $this->default_options();
		$request = $this->DoRequest('/audience_members/search.xml', $options, $return);
		return $request;
	}
	function Events($unix_timestamp, $return = false) {
		$request = $this->DoRequest('/audience_members/events_since/' . $unix_timestamp . '.xml', $this->default_options(), $return);
		return $request;
	}
	function Status($transaction_id, $return = false) {
		$request = $this->DoRequest('/mailers/status/' . $transaction_id, $this->default_options(), $return);
		return $request;
	}
	function Suppress($email, $return = false) {
		$path = str_replace('%email%', $email, '/audience_members/%email%/suppress_email');
		$request = $this->DoRequest($path, $this->default_options(), $return, 'POST', false);
		return $request;
	}
}