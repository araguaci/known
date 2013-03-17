<?php

    /**
     * Handles pages in the system (and, by extension, the idno API).
     * 
     * Developers shoudld extend the getContent, postContent and dataContent 
     * methods as follows:
     * 
     * getContent: echoes HTML to the page
     * 
     * postContent: handles content submitted to the page (assuming that form
     * elements were correctly signed)
     * 
     * @package idno
     * @subpackage core
     */

	namespace Idno\Core {
	
	    class Page extends \Idno\Common\Component {
		
		// Property that defines whether this page may forward to
		// other pages. True by default.
		private $forward = true;
		
		// Property intended to store parsed data from JSON magic input
		// variable
		private $data = array();
		
		// Stores the response code that we'll be sending back. Can be
		// changed with setResponse
		private $response = 200;
		
		/**
		 * Internal function used to handle GET requests.
		 * Performs some administration functions and hands off to
		 * getContent().
		 */
		function get() {
		    site()->session()->APIlogin();
		    $this->parseJSONPayload();
		    $this->getContent();
		    if (http_response_code() != 200)
			http_response_code($this->response);
		}
		
		/**
		 * Internal function used to handle POST requests.
		 * Performs some administration functions, checks for the 
		 * presence of a POST token, and hands off to postContent().
		 * 
		 * @param $forward boolean If this is set to true, forward the page; otherwise return data.
		 */
		function post($forward = true) {
		    $this->forward = $forward;
		    if (site()->actions()->validateToken('', false)) {
			site()->session()->APIlogin();
			$this->parseJSONPayload();
			$this->postContent();
		    } else {

		    }
		    $this->forward('/');    // If we haven't forwarded yet, do so (if we can)
		    if (http_response_code() != 200)
			http_response_code($this->response);
		}
		
		/**
		 * Internal function used to handle PUT requests.
		 * Performs some administration functions, checks for the 
		 * presence of a form token, and hands off to postContent().
		 * 
		 * @param $forward boolean If this is set to true, forward the page; otherwise return data.
		 */
		function put($forward = true) {
		    $this->forward = $forward;
		    if (site()->actions()->validateToken('', false)) {
			site()->session()->APIlogin();
			$this->parseJSONPayload();
			$this->putContent();
		    } else {
			
		    }
		    $this->forward('/');    // If we haven't forwarded yet, do so (if we can)
		    if (http_response_code() != 200)
			http_response_code($this->response);
		}
		
		/**
		 * Internal function used to handle DELETE requests.
		 * Performs some administration functions, checks for the 
		 * presence of a form token, and hands off to postContent().
		 * 
		 * @param $forward boolean If this is set to true, forward the page; otherwise return data.
		 */
		function delete($forward = true) {
		    $this->forward = $forward;
		    if (site()->actions()->validateToken('', false)) {
			site()->session()->APIlogin();
			$this->parseJSONPayload();
			$this->deleteContent();
		    } else {
			
		    }
		    $this->forward('/');    // If we haven't forwarded yet, do so (if we can)
		    if (http_response_code() != 200)
			http_response_code($this->response);
		}
		
		/**
		 * Automatically matches JSON/XMLHTTPRequest GET requests.
		 * Sets the template to JSON and then calls get().
		 */
		function get_xhr() {
		    site()->template()->setTemplateType('json');
		    $this->get();
		}
		
		/**
		 * Automatically matches JSON/XMLHTTPRequest POST requests.
		 * Sets the template to JSON and then calls post().
		 */
		function post_xhr() {
		    site()->template()->setTemplateType('json');
		    $this->post(false);
		}
		
		/**
		 * Automatically matches JSON/XMLHTTPRequest PUT requests.
		 * Sets the template to JSON and then calls put().
		 */
		function put_xhr() {
		    site()->template()->setTemplateType('json');
		    $this->put(false);
		}
		
		/**
		 * Automatically matches JSON/XMLHTTPRequest PUT requests.
		 * Sets the template to JSON and then calls delete().
		 */
		function delete_xhr() {
		    site()->template()->setTemplateType('json');
		    $this->delete(false);
		}
		
		/**
		 * To be extended by developers
		 */
		function getContent() {}
		
		/**
		 * To be extended by developers
		 */
		function postContent() {}
		
		/**
		 * To be extended by developers
		 */
		function putContent() {}
		
		/**
		 * To be extended by developers
		 */
		function deleteContent() {}
		
		/**
		 * If this page is allowed to forward, send a header to move
		 * the browser on. Otherwise, do nothing
		 * 
		 * @param string $location Location to forward to (eg "/foo/bar")
		 */
		function forward($location = '') {
		    if (empty($location)) $location = site()->config()->url;
		    if (!empty($this->forward)) {
			header('Location: ' . $location);
			exit;
		    }
		}
		
		/**
		 * Placed in pages to ensure that only logged-in users can
		 * get at them. Sets response code 401 and tries to forward
		 * to the front page.
		 */
		function gatekeeper() {
		    if (!site()->session()->isLoggedIn()) {
			$this->setResponse(401);
			$this->forward();
		    }
		}
		
		/**
		 * Set the response code for the page. Note: this will be overridden
		 * if the main system response code is already not 200
		 * 
		 * @param type $code 
		 */
		function setResponse($code) {
		    $code = (int) $code;
		    $this->response = $code;
		}
		
		/**
		 * Provide access to page data
		 * @return array
		 */
		function &data() {
		    return $this->data;
		}
		
		/**
		 * Finds a JSON payload associated with the current page request
		 * and parses any variables into $this->data
		 */
		function parseJSONPayload() {
		    
		    // First, let's see if we've been sent anything in form input
		    if (!empty($_REQUEST['json'])) {
			$json = trim($_REQUEST['json']);
			if ($parsed = @json_decode($json, true)) {
			    $this->data = array_merge($parsed, $this->data());
			}
		    }
		    
		    if ($_SERVER['REQUEST_METHOD'] != 'GET') {
			$body = @file_get_contents('php://input');
			$body = trim($body);
			if (!empty($body)) {
			    if ($parsed = @json_decode($body, true)) {
				$this->data = array_merge($parsed, $this->data());
			    }
			}
		    }
		    
		}
		
		/**
		 * Retrieves input.
		 * 
		 * @param string $name Name of the input variable
		 * @param boolean $filter Whether or not to filter the variable for safety (default: false)
		 * @return mixed
		 */
		function getInput($name, $filter = false) {
		    if (!empty($name)) {
			if (!empty($_REQUEST[$name])) {
			    $value = $_REQUEST[$name];
			} else if (!empty($this->data[$name])) {
			    $value = $this->data[$name];
			}
			if (!empty($value)) { 
			    if ($filter == true) {
				// TODO: add some kind of sensible filter
			    }
			    return $value;
			}
		    }
		    return false;
		}
		
	    }
	    
	}

