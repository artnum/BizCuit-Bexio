<?php
/**
 * @author Etienne Bagnoud <etienne@artnum.ch>
 * @license MIT
 * @copyright 2023 Etienne Bagnoud
 * 
 */

namespace BizCuit;

require(__DIR__ . '/bxquery.php');
require(__DIR__ . '/bxobject.php');

use BizCuit\BXObject\BXObject;
use BizCuit\BXQuery\BXQuery;
use CURLFile;
use CurlHandle;
use Exception;
use stdClass;

/**
 * Base class containing cURL operations.
 */
class BexioCTX {
	public const endpoint = 'https://api.bexio.com/';
	protected CurlHandle $c;
	protected array $headers = [];
	protected object $values;
	protected string $token;

	/**
	 * Create the context with API token needed to access endpoints
	 *
	 * Of all authentications available, only the API token is avaible.
	 * 
	 * @link https://docs.bexio.com/#section/Authentication/API-Tokens 
	 * @param String $token The token.
	 */
	function __construct(String $token) {
		$this->c = curl_init();
		$this->token = $token;
		$this->headers = [
			'Accept: application/json',
			'Authorization: Bearer ' . $token
		];
		$this->values = new stdClass();
		$this->method = 'get';
	}

	private function set_url (String $url) {
		// remove leading slash
		while(substr($url, 0, 1) === '/') { $url = substr($url, 1); }
		// remove double slashes (bexio api return an error else)
		$url = str_replace('//', '/', $url);
		curl_setopt($this->c, CURLOPT_URL, $this::endpoint . $url);
	}

	private function set_method(String $method = 'get') {
		switch(strtolower($method)) {
			default:
			case 'get': curl_setopt($this->c, CURLOPT_HTTPGET, true); break;
			case 'post': curl_setopt($this->c, CURLOPT_POST, true); break;
			case 'delete': curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, 'DELETE'); break;
			case 'put': curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, 'PUT'); break;
			case 'patch': curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, 'PATCH'); break;
			case 'head': curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, 'HEAD'); break;
		}
	}

	/**
	 * Set the request body if any.
	 */
	private function set_body (string $body = ''):void {
		if (strlen($body) < 0) { return; }
		curl_setopt($this->c, CURLOPT_POSTFIELDS, $body);
	}

	private function reset():void {
		unset($this->url);
		unset($this->body);
		curl_reset($this->c);
	}

	function get_token ():string {
		return $this->token;
	}

	function __set(string $name, mixed $value):void {
		switch ($name) {
			case 'url':
			case 'method':
			case 'body':
			case 'user_id':
			case 'owner_id':
				$this->values->{$name} = $value;
				break;
		}
	}

	function __get(string $name):mixed {
		switch ($name) {
			default: return null;
			case 'url':
			case 'method':
			case 'body':
			case 'user_id':
			case 'owner_id':
				if (!property_exists($this->values, $name)) { return ''; }
				return $this->values->{$name};

		}
	}

	function __isset(string $name):bool	{
		switch ($name) {
			default: return false;
			case 'url':
			case 'method':
			case 'body':
			case 'user_id':
			case 'owner_id':
				return property_exists($this->values, $name);
		}
	}

	function __unset(string $name):void {
		switch($name) {
			default: return;
			case 'url':
			case 'method':
			case 'body':
			case 'user_id':
			case 'owner_id':
				unset($this->values->{$name});
		}
	}

	function handle_result ($data, $code, $type):stdClass|string|Array {
		switch($code) {
			case 200:
			case 201:
				if (strcasecmp($type, 'application/json') === 0) {
					return json_decode($data);
				}
				return $data;
			case 304: throw new Exception('The resource has not been changed', $code, new Exception($data));
            case 400: throw new Exception('The request parameters are invalid', $code, new Exception($data));
            case 401: throw new Exception('The bearer token or the provided api key is invalid', $code, new Exception($data));
            case 403: throw new Exception('You do not possess the required rights to access this resource',$code, new Exception($data));
            case 404: throw new Exception('The resource could not be found', $code, new Exception($data));
            case 411: throw new Exception('Length Required', $code, new Exception($data));
            case 415: throw new Exception('The data could not be processed or the accept header is invalid', $code, new Exception($data));
            case 422: throw new Exception('Could not save the entity', $code, new Exception($data));
            case 429: throw new Exception('Too many requests', $code, new Exception($data));
            case 500: throw new Exception('An unexpected condition was encountered', $code, new Exception($data));
            case 503: throw new Exception('The server is not available (maintenance work)', $code, new Exception($data));
			default: throw new Exception('Error with code ' . $code, $code, new Exception($data));
		}
	}

	/**
	 * Execute the request.
	 * @return stdClass|string The JSON response decoded or the raw value
	 * @throws Exception A generic exception with code matching the HTTP error
	 * code from the upstream API if any.
	 */
	function fetch ():stdClass|string|Array {
		try {
			$ratelimits = [
				'remaining' => 0,
				'limit' => 0,
				'reset' => 0
			];
			curl_setopt($this->c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->c, CURLOPT_HEADERFUNCTION, 
				function ($curl, $header) use (&$ratelimits) {
					$len = strlen($header);
					$parts = explode(':', $header);
					if (count($parts) < 2) { return $len; }					
					$parts[0] = strtolower(trim($parts[0]));
					
					switch($parts[0]) {
						default: return $len;

						case 'ratelimit-limit': 
						case 'ratelimit-remaining':
						case 'ratelimit-reset':
							$value = explode('-', $parts[0]);
							$type = array_pop($value);
							$ratelimits[$type] = intval(trim($parts[1]));
							break;
					}
					return $len;
				}
			);
			$this->set_method($this->method);
			$this->set_url($this->url);

			$headers = $this->headers;
			if ($this->method !== 'get' && $this->method !== 'head') { $this->set_body($this->body); }
		
			/* An API bug can be triggered if you send a GET without 
			 * Content-Length set to 0
			 */
			if (strlen($this->body) <= 0) {
				$headers = array_merge($this->headers, ['Content-Length: 0']);  
			}
		
			curl_setopt($this->c, CURLOPT_HTTPHEADER, $headers);
			$data = curl_exec($this->c);
			$code = curl_getinfo($this->c,  CURLINFO_HTTP_CODE);
			$type = curl_getinfo($this->c, CURLINFO_CONTENT_TYPE);
			$this->reset();
		} catch (Exception $e) {
			throw new Exception('Program error', 0, $e);
		}
		return $this->handle_result($data, $code, $type);
	}
}

/**
 * Base class for enpdoints.
 */
class BexioAPI {
	protected string $endpoint;
	protected Int|null $userid = null;
    protected Int|null $ownerid = null;
	protected BexioCTX $ctx;

	/**
	 * Create withing the given context.
	 * 
	 * @param BexioCTX $ctx The context in which the API will work.
	 * 
	 */
	function __construct(BexioCTX $ctx) {
		$this->ctx = $ctx;
		$this->endpoint = $ctx::endpoint;
	}

    /**
	 * Set current user id.
	 * 
	 * Some items, at creation, need a user id and/or an owner id. Setting this
	 * here allows to automatically add this to the item. If only user is set,
	 * when owner is needed it would be set to user.
	 * 
	 * @param Int $userid The id of the user for all creation from now on.
	 * @return void
	 * 
	 */
	function setCurrentUser (Int $userid):void {
		$this->userid = $userid;
	}

    /**
	 * Set current owner id.
	 * 
	 * Some items, at creation, need a user id and/or an owner id. This set the
	 * owner.
	 * 
	 * @param Int $ownerid The id of the owner for all creation from now on.
	 * @return void
	 * 
	 */
    function setCurrentOwner (Int $ownerid) {
        $this->ownerid = $ownerid;
    }

	/**
	 * Get the current type.
	 * 
	 * The current type correspond to an endpoint in the upstream API.
	 * 
	 * @return string The type name
	 */
	function getType():string {
		$parts = explode('\\', $this->className);
		return array_pop($parts);
	}
}

trait tBexioV2Api {
	const api_version = '2.0';
}

trait tBexioV3Api {
	const api_version = '3.0';
}

trait tBexioV4Api {
	const api_version = '4.0';

	/**
	 * Search request for version 4 of the API.
	 * 
	 * @see tBexioCollection::search()
	 */
	function search (BXQuery $query, Int $offset = 0, Int $limit = 100):array {
		/* 
			## API or Documenation bug, this should work but it doesn't (but sometime it does)
			$limit = 100;
			$page_num = ($offset + $limit) / $limit;
			$search_str_array = ['search_term' => null, 'page' => $page_num, 'limit' => $limit]; 
		*/
		$search_str_array = ['search_term' => null ];
		$fields = [];

		foreach($query->getRawQuery() as $query) {
			$field = urlencode($query->field);
			$term = urlencode($query->value);
			if (in_array($query->field, $this->search_fields)) {
				if (in_array($field, $fields)) { continue; }
				/*$fields[] = $field;
				if (!$search_str_array['search_term']) {
					$search_str_array['search_term'] = $term;
				}*/
				$search_str_array[$field] = $term;
				continue;
			}

			if (!isset($search_str_array[$field])) { $search_str_array[$field] = $term; }
		}

		if (!$search_str_array['search_term']) {
			unset($search_str_array['search_term']);
		}

		$a = [];
		foreach($search_str_array as $k => $v) { $a[] = $k . '=' . $v; }
		$qs = '?' . join(',', $a);

		$this->ctx->url = $this::api_version . '/' . $this::type . $qs;
		$this->ctx->method = 'get';
		$result = $this->ctx->fetch();
		return array_map(fn($e) => new $this->className($e), $result->data);
	}

	/**
	 * List request for version 4 of the API.
	 * 
	 * @see tBexioCollection::list()
	 */
	function list (Int $offset, Int $limit):array {
		return $this->search($this->newQuery(), $offset, $limit);
	}

	/**
	 * Get ID name for version 4 of the API.
	 * 
	 * @see tBexioCollection::getIdName()
	 */
	function getIdName ():string {
		$c = $this->className;
		return $c::ID;
	}

	/**
	 * Create new query for version 4 of the API.
	 * 
	 * @see tBexioCollection::newQuery()
	 */
	function newQuery ():BXquery {
		return new $this->query();
	}
}

trait tBexioUpload {
	function upload (string $filepath):stdClass|string|Array {
		$headers = [
			'Accept: application/json',
			'Authorization: Bearer ' . $this->ctx->get_token()
		];
		try {
			$c = curl_init();
			$curlfile = new CURLFile($filepath, mime_content_type($filepath), basename($filepath));
			$url = $this::api_version . '/' . $this::type;
			while(substr($url, 0, 1) === '/') { $url = substr($url, 1); }
			$url = str_replace('//', '/', $url);
			curl_setopt($c, CURLOPT_URL, $this->ctx::endpoint . $url);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($c, CURLOPT_POST, true);
			curl_setopt($c, CURLOPT_POSTFIELDS,['file' => $curlfile]);
			curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
			$data = curl_exec($c);
			$code = curl_getinfo($c,  CURLINFO_HTTP_CODE);
			$type = curl_getinfo($c, CURLINFO_CONTENT_TYPE);
			curl_close($c);
		} catch (Exception $e) {
			throw new Exception('Program error', 0, $e);
		}
		return $this->ctx->handle_result($data, $code, $type);
	}
}

trait tBexioCollection {
	/**
	 * Return the property name used as unique id for this collection.
	 * @return string ID name.
	 * 
	 * @api
	 */
	function getIdName ():string {
		$c = $this->className;
		return $c::ID;
	}

	/**
	 * Create a new BXQuery object for this collection.
	 * @return BXquery The BXQuery valid for this collection.
	 * 
	 * @api
	 */
	function newQuery ():BXquery {
		return new $this->query();
	}

	/**
	 * Search the collection.
	 * @param BXQuery $query The query object.
	 * @param Int $offset Offset in the search result,
	 * @param Int $limit Max number of search resutl.
	 * @return BXObject[] Matching items in the collection.
	 * 
	 * @api
	 */
	function search (BXQuery $query, Int $offset = 0, Int $limit = 500):array {
		$this->ctx->url = $this::api_version . '/' . $this::type .'/search' . sprintf('?limit=%d&offset=%d', $limit, $offset);
		$this->ctx->body = $query->toJson();
		$this->ctx->method = 'post';
		return array_map(fn($e) => new $this->className($e), $this->ctx->fetch());
	}

	/**
	 * List the collection.
	 * @param Int $offset Offset in the search result,
	 * @param Int $limit Max number of search result.
	 * @return BXObject[] Items in the collection.
	 * 
	 * @api
	 */
	function list (Int $offset = 0, Int $limit = 500):array {
		$this->ctx->url = $this::api_version . '/' . $this::type . sprintf('?limit=%d&offset=%d', $limit, $offset);
		return array_map(fn($e) => new $this->className($e), $this->ctx->fetch());
	}
}

trait tBexioObject {
	/**
	 * Create a new BXObject.
	 * @return BXObject New empty BXObject.
	 * 
	 * @api
	 */
	function new ():BXObject {
		return new $this->className();
	}

	/**
	 * Delete an item in the collection.
	 * @param Int|String|BXObject $id The id or the object to delete.
	 * @return Bool True on success, false otherwise.
	 * 
	 * @api
	 */
	function delete(Int|String|BXObject $id): Bool {
		if ($id instanceof BXObject) {
			$id = $id->getId();
		}
		$this->ctx->url = $this::api_version . '/' . $this::type . '/' . strval($id);
		$this->ctx->method = 'delete';

		return $this->ctx->fetch()->success;
		
	}

	/**
	 * Get an item from the collection
	 * @param Int|String|BXObject $id The id or the object to get.
	 * @param Array $options Options that can be passed as query string.
	 * @return BXObject The item as a BXObject.
	 * 
	 * @api
	 */
	function get (Int|String|BXObject $id, array $options = []):BXObject {
		if ($id instanceof BXObject) {
			$id = $id->getId();
		}
		$this->ctx->url = $this::api_version .'/' . $this::type . '/' .  strval($id);
		$query = [];
		foreach ($options as $name => $value) {
			$query[] = urlencode(strval($name)) . '=' . urlencode(strval($value));
		}
		if (!empty($query)) {
			$this->ctx->url .= '?' . join('&', $query);
		}
		return new $this->className($this->ctx->fetch());
	}

	/**
	 * Set an item in the collection
	 * 
	 * Setting an item would create the item if it don't have an ID or overwrite
	 * the item if it has an ID.
	 * 
	 * @param BXObject $content The object to be set.
	 * @return BXObject|false The item is returned as stored in collection or
	 * false in case of error
	 * 
	 * @api
	 */
	function set (BXObject $content):BXObject|false {
		if ($content::readonly) { return false; }

		/* try to fix user_id and owner_id if possible */
		if (!is_null($this->ctx->user_id)) {
			if ((!is_numeric($content->user_id) || is_null($content->user_id)) && in_array('user_id', $content::createProperties)) { 
				$content->user_id = $this->ctx->user_id; 
			}
		}
		if (!is_null($this->ctx->owner_id)) {
			if ((!is_numeric($content->owner_id) || is_null($content->owner_id)) && in_array('owner_id', $content::createProperties)) {
				$content->owner_id = $this->ctx->owner_id; 
			}
		}

		if (!$content->getId()) {
			$this->ctx->url = $this::api_version .'/' . $this::type;
			$this->ctx->method = 'post';
		} else {
			$this->ctx->url = $this::api_version .'/' . $this::type . '/' .  $content->getId();
			$this->ctx->method = 'put';
		}

		$this->ctx->body = $content->toJson();
		return new $this->className($this->ctx->fetch());
	}
	/**
	 * [!!!] Update an item in the collection
	 * 
	 * This should update property of an item. But the upstream API is buggy
	 * here, so no way to know what UPDATE does.
	 * 
	 * @param BXObject $content The object to be set.
	 * @return BXObject|false The item is returned as stored in collection or
	 * false in case of error
	 * 
	 * @api
	 */
	function update (BXObject $content):BXObject|false {
		if ($content::readonly) { return false; }

		if (!$content->getId()) { return $this->set($content); }
		$this->ctx->url = $this::api_version .'/' . $this::type . '/' .  $content->getId();
		/* API BUG according to documentation it should be "patch" but when 
		 * using "patch", I get error 404 as when use "post" with partial data 
		 * it update
		 */
		$this->ctx->method = 'post'; 
		$this->ctx->body = $content->changesToJson();
		return new $this->className($this->ctx->fetch());
	}

}

trait tBexioArchiveable {
	function archive (BXObject $content):bool {
		if ($content::readonly) { return false; }
		if (!$content->getId()) { return false; }
		$this->ctx->url = $this::api_version .'/' . $this::type . '/' .  $content->getId() . '/archive';
		$this->ctx->method = 'post';
		return $this->ctx->fetch()->success;
	}

	function unarchive (BXObject $content):bool {
		if ($content::readonly) { return false; }
		if (!$content->getId()) { return false; }
		$this->ctx->url = $this::api_version .'/' . $this::type . '/' .  $content->getId() . '/reactivate';
		$this->ctx->method = 'post';
		return $this->ctx->fetch()->success;
	}
}

trait tBexioNumberObject {
	function getByNumber (Int|String $id):BXObject {
		$this->ctx->url = $this::api_version . '/' . $this::type . '/search';
		$this->ctx->method = 'post';
		$this->ctx->body = json_encode([[
			'field' => $this::NR,
			'value' => strval($id),
			'criteria' => '='
		]]);
		return new $this->className($this->ctx->fetch()[0]);
	}
}

trait tBexioPDFObject {
	function getPDF(Int|BXObject $id):BXObject  {
		if ($id instanceof BXObject) {
			$id = $id->getId();
		}
		$this->ctx->url = $this::api_version . '/' . $this::type . '/' . strval($id) . '/pdf';
		return new \BizCuit\BXObject\PDF($this->ctx->fetch());
	}
}

trait tBexioProjectObject {
	function listByProject (Int|BXObject $projectId): Array {
		if ($projectId instanceof BXObject) {
			$projectId = $projectId->getId();
		}
		$results = [];
		$offset = 0;
		$count = 500;
		do {
			$list = $this->list($offset, $count);
			$results = array_merge(
				$results,
				array_map(
					fn($e) => new $this->className($e),
					array_filter($list, fn($e) => intval($e->project_id) === intval($projectId))
				)
			);
			$offset += $count;
		} while (count($list) === $count);
		return $results;
	}
}

/**
 * Represent the enpoint Countries 
 * 
 * @link https://docs.bexio.com/#tag/Countries
 * @api
 */
class BexioCountry extends BexioAPI {
	const type = 'country';
	protected string $className = 'BizCuit\BXObject\Country';
	protected string $query = 'BizCuit\BXQuery\Coutry';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Quotes
 * 
 * @link https://docs.bexio.com/#tag/Quotes
 * @api
 */
class BexioQuote extends BexioAPI {
	const type = 'kb_offer';
	protected string $className = 'BizCuit\BXObject\Quote';
	protected string $query = 'BizCuit\BXQuery\Quote';

	use tBexioV2Api, tBexioObject, tBexioPDFObject, tBexioProjectObject, tBexioCollection, tBexioNumberObject;
}

/**
 * Represent the enpoint Invoices
 * 
 * @link https://docs.bexio.com/#tag/Invoices
 * @api
 */
class BexioInvoice extends BexioAPI {
	const type = 'kb_invoice';
	protected string $className = 'BizCuit\BXObject\Invoice';
	protected string $query = 'BizCuit\BXQuery\Invoice';

	use tBexioV2Api, tBexioObject, tBexioPDFObject, tBexioProjectObject, tBexioCollection, tBexioNumberObject;
}

/**
 * Represent the enpoint Orders
 * 
 * @link https://docs.bexio.com/#tag/Orders
 * @api
 */
class BexioOrder extends BexioAPI {
	const type = 'kb_order';
	protected string $className = 'BizCuit\BXObject\Order';
	protected string $query = 'BizCuit\BXQuery\Order';

	use tBexioV2Api, tBexioObject, tBexioPDFObject, tBexioProjectObject, tBexioCollection, tBexioNumberObject;
}

/**
 * Represent the enpoint Contacts
 * 
 * @link https://docs.bexio.com/#tag/Contacts
 * @api
 */
class BexioContact extends BexioAPI {
	const type = 'contact';
	protected string $className = 'BizCuit\BXObject\Contact';
	protected string $query = 'BizCuit\BXQuery\Contact';


	use tBexioV2Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Projects
 * 
 * @link https://docs.bexio.com/#tag/Projects
 * @api
 */
class BexioProject extends BexioAPI {
	const type = 'pr_project';
	protected string $className = 'BizCuit\BXObject\Project';
	protected string $query = 'BizCuit\BXQuery\Project';

	use tBexioV2Api, tBexioObject, tBexioCollection, tBexioNumberObject, tBexioArchiveable;
}

/**
 * Represent the enpoint Contact Relations
 * 
 * @link https://docs.bexio.com/#tag/Contact-Relations
 * @api
 */
class BexioContactRelation extends BexioAPI {
	const type = 'contact_relation';
	protected string $className = 'BizCuit\BXObject\ContactRelation';
	protected string $query = 'BizCuit\BXQuery\ContactRelation';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Additional Addresses
 * 
 * @link https://docs.bexio.com/#tag/Additional-Addresses
 * @api
 */
class BexioAdditionalAddress extends BexioAPI {
	const type = 'additional_address';
	protected string $className = 'BizCuit\BXObject\AdditionalAddress';
	protected string $query = 'BizCuit\BXQuery\AdditionalAddress';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Notes
 * 
 * @link https://docs.bexio.com/#tag/Notes
 * @api
 */
class BexioNote extends BexioAPI {
	const type = 'note';
	protected string $className = 'BizCuit\BXObject\Note';
	protected string $query = 'BizCuit\BXQuery\Note';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint User Management
 * 
 * @todo Add fictionnal user management
 * @link https://docs.bexio.com/#tag/User-Management
 * @api
 */
class BexioUser extends BexioAPI {
	const type = 'users';
	protected string $className = 'BizCuit\BXObject\User';
	protected string $query = 'BizCuit\BXQuery\User';

	use tBexioV3Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Business Activities [read only]
 * 
 * @link https://docs.bexio.com/#tag/Business-Activities
 * @api
 */
class BexioBusinessActivity extends BexioAPI {
	const type = 'client_service';
	protected string $className = 'BizCuit\BXObject\ClientService';
	protected string $query = 'BizCuit\BXQuery\ClientService';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Salutations [read only]
 * 
 * @link https://docs.bexio.com/#tag/Salutations
 * @api
 */
class BexioSalutation extends BexioAPI {
	const type = 'salutation';
	protected string $className = 'BizCuit\BXObject\Salutation';
	protected string $query = 'BizCuit\BXQuery\Salutation';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Titles [read only]
 * 
 * @link https://docs.bexio.com/#tag/Titles
 * @api
 */
class BexioTitle extends BexioAPI {
	const type = 'title';
	protected string $className = 'BizCuit\BXObject\Title';
	protected string $query = 'BizCuit\BXQuery\Title';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Project Types [read only]
 * 
 * @link https://docs.bexio.com/#tag/Projects/operation/v2ListProjectType
 * @api
 */
class BexioProjectType extends BexioAPI {
	const type = 'pr_project_type';
	protected string $className = 'BizCuit\BXObject\ProjectType';
	protected string $query = 'BizCuit\BXQuery\ProjectType';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Project Status [read only]
 * 
 * @link https://docs.bexio.com/#tag/Projects/operation/v2ListProjectStatus
 * @api
 */
class BexioProjectStatus extends BexioAPI {
	const type = 'pr_project_state';
	protected string $className = 'BizCuit\BXObject\ProjectStatus';
	protected string $query = 'BizCuit\BXQuery\ProjectStatus';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Currencies [read only]
 * 
 * @link https://docs.bexio.com/#tag/Currencies
 * @api
 */
class BexioCurrency extends BexioAPI {
	const type = 'currencies';
	protected string $className = 'BizCuit\BXObject\Currency';
	protected string $query = 'BizCuit\BXQuery\Currency';

	use tBexioV3Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Expenses [read only]
 * 
 * @link https://docs.bexio.com/#tag/Expenses
 * @api
 */
class BexioExpense extends BexioAPI {
	const type = 'currencies';
	protected string $className = 'BizCuit\BXObject\Expense';
	protected string $query = 'BizCuit\BXQuery\Expense';
	protected $search_fields = [
		'vendor',
		'gross_min',
		'gross_max',
		'net_min',
		'net_max',
		'paid_on_start',
		'paid_on_end',
		'created_at_start',
		'created_at_end',
		'title',
		'currency_code',
		'document_no',
		'supplier_id',
		'project_id'
	];

	use tBexioV4Api, tBexioObject;
}

/**
 * Represent the enpoint Bank Accounts [read only]
 * 
 * @link https://docs.bexio.com/#tag/Bank-Accounts
 * @api
 */
class BexioBankAccount extends BexioAPI {
	const type = 'banking/accounts';
	protected string $className = 'BizCuit\BXObject\BankAccount';
	protected string $query = 'BizCuit\BXQuery\BankAccount';

	use tBexioV3Api, tBexioObject, tBexioCollection;
}

/**
 * Represent the enpoint Bills
 * 
 * @link https://docs.bexio.com/#tag/Bills
 * @api
 */
class BexioBills extends BexioAPI {
	const type = 'purchase/bills';
	protected string $className = 'BizCuit\BXObject\Bills';
	protected string $query = 'BizCuit\BXQuery\Bills';
	protected $search_fields = [
		'firstname_suffix',
		'lastname_company',
		'vendor_ref',
		'currency_code',
		'document_no',
		'title'
	];


	function setStatus (Int|String|BXObject $uuid, string $status):BXObject {
		if ($uuid instanceof BXObject) {
			$uuid = $uuid->getId();
		}
		$this->ctx->url = $this::api_version .'/' . $this::type . '/' .  strval($uuid) . '/bookings/' . $status;
		$this->ctx->method = 'PUT';
		$this->ctx->body = '';
		$result = $this->ctx->fetch();
		return $this->new($result);
	}

	use tBexioObject, tBexioV4Api;
}

/**
 * Represent the enpoint Files
 * 
 * @link https://docs.bexio.com/#tag/Files
 * @api
 */
class BexioFile extends BexioAPI {
	const type = 'files';
	protected string $className = 'BizCuit\BXObject\File';
	protected string $query = 'BizCuit\BXQuery\File';
	protected $uuid;

	function getId() {
		return $this->uuid;
	}

	/**
	 * Get a file from file endpoint.
	 * 
	 * Files use UUID most of the time, and the documentation says that they use
	 * ID but id don't work that way. The content is associated with the file
	 * which require two requests.
	 * 
	 * @param Int|String|BXObject The object to get.
	 * @param Array $options Options it not used here.
	 * @return BXObject The file as an object with content property added.
	 * 
	 */
	function get (Int|String|BXObject $uuid, array $options = []):BXObject {
		if ($uuid instanceof BXObject) {
			$uuid = $uuid->getId();
		}

		$this->ctx->url = $this::api_version .'/' . $this::type . '/' . strval($uuid);
		$file = new $this->className($this->ctx->fetch());
		$this->ctx->url = $this::api_version .'/' . $this::type . '/' .  strval($uuid) . '/download';
		$file->content = base64_encode($this->ctx->fetch());

		return $file;
	}

	use tBexioV3Api, tBexioObject, tBexioCollection, tBexioUpload;
}

class BexioOutgoingPayment extends BexioAPI {
	const type = 'purchase/outgoing-payments';
	protected string $className = 'BizCuit\BXObject\OutgoingPayment';
	protected string $query = 'BizCuit\BXQuery\OutgoingPayment';
	protected $uuid;
	protected $search_fields = [
		'bill_id'
	];

	function getId() {
		return $this->uuid;
	}

	use tBexioV4Api, tBexioObject;
}