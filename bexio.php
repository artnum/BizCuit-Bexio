<?php
/* (c) 2023 Etienne Bagnoud */
namespace BizCuit;

require(__DIR__ . '/bxquery.php');
require(__DIR__ . '/bxobject.php');

use BizCuit\BXObject\ROObject;
use BizCuit\BXObject\BXObject;
use BizCuit\BXQuery\BXQuery;
use Exception;
use stdClass;

class BexioCTX {
	const endpoint = 'https://api.bexio.com/';
	protected $c = '';
	protected array $headers = [];
	protected object $values;
	protected string $token;

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

	private function set_body (string $body = '') {
		if (strlen($body) < 0) { return; }
		curl_setopt($this->c, CURLOPT_POSTFIELDS, $body);
	}

	private function reset() {
		unset($this->url);
		unset($this->body);
		curl_reset($this->c);
	}

	function __set(string $name, string $value) {
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

	function __get(string $name) {
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

	function __isset(string $name)	{
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

	function __unset(string $name) {
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

	function __clone() {
		$newCtx = new BexioCTX($this->token);
		if (isset($this->user_id)) { $newCtx->user_id = $this->user_id; }
		if (isset($this->owner_id)) { $newCtx->owner_id = $this->owner_id; }
		return $newCtx;
	}

	function fetch () {
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
			else { 
				/* A bug can be triggered if you send a GET without Content-Length 
				 * set to 0
				 */
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
}

class BexioAPI {
	protected $endpoint = 'https://api.bexio.com/';
	protected $c;
	protected $userid = null;
    protected $ownerid = null;
	protected $headers;
	protected $class;
	protected $ctx;
	protected $type;

	function __construct(BexioCTX $ctx) {
		$this->ctx = $ctx;
	}

    /* set current user for request that need a user id if not passed */
	function setCurrentUser (Int $userid) {
		$this->userid = $userid;
	}
    function setCurrentOwner (Int $ownerid) {
        $this->ownerid = $ownerid;
    }
	function getType():string {
		$parts = explode('\\', $this->class);
		return array_pop($parts);
	}
}

trait tBexioV2Api {
	protected $api_version = '2.0';
}

trait tBexioV3Api {
	protected $api_version = '3.0';
}

trait tBexioV4Api {
	protected $api_version = '4.0';

	function search (BXQuery $query, Int $offset = 0, Int $limit = 100) {
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
				$fields[] = $field;
				if (!$search_str_array['search_term']) {
					$search_str_array['search_term'] = $term;
				}
				$search_str_array['fields[' . (count($fields) - 1) . ']'] = $field;
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

		$this->ctx->url = $this->api_version . '/' . $this->type . $qs;
		$this->ctx->method = 'get';
		$result = $this->ctx->fetch();
		return array_map(fn($e) => new $this->class($e), $result->data);
	}

	function list (Int $offset, Int $limit) {
		return $this->search($this->newQuery(), $offset, $limit);
	}

	function getIdName ():string {
		$c = $this->class;
		return $c::ID;
	}

	function newQuery ():BXquery {
		return new $this->query();
	}
}

trait tBexioCollection {
	function getIdName ():string {
		$c = $this->class;
		return $c::ID;
	}

	function newQuery ():BXquery {
		return new $this->query();
	}

	function search (BXQuery $query, Int $offset = 0, Int $limit = 500):array {
		$this->ctx->url = $this->api_version . '/' . $this->type .'/search' . sprintf('?limit=%d&offset=%d', $limit, $offset);
		$this->ctx->body = $query->toJson();
		$this->ctx->method = 'post';
		return array_map(fn($e) => new $this->class($e), $this->ctx->fetch());
	}

	function list (Int $offset = 0, Int $limit = 500):array {
		$this->ctx->url =$this->api_version . '/' . $this->type . sprintf('?limit=%d&offset=%d', $limit, $offset);
		return array_map(fn($e) => new $this->class($e), $this->ctx->fetch());
	}
}

trait tBexioObject {
	function new ():BXObject {
		return new $this->class();
	}

	function delete(Int|String|BXObject $id): Bool {
		if ($id instanceof BXObject) {
			$id = $id->getId();
		}
		$this->ctx->url = $this->api_version . '/' . $this->type . '/' . strval($id);
		$this->ctx->method = 'delete';

		return $this->ctx->fetch()->success;
		
	}

	function get (Int|String|BXObject $id, array $options = []):BXObject {
		if ($id instanceof BXObject) {
			$id = $id->getId();
		}
		$this->ctx->url = $this->api_version .'/' . $this->type . '/' .  strval($id);
		$query = [];
		foreach ($options as $name => $value) {
			$query[] = urlencode(strval($name)) . '=' . urlencode(strval($value));
		}
		if (!empty($query)) {
			$this->ctx->url .= '?' . join('&', $query);
		}
		return new $this->class($this->ctx->fetch());
	}

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
			$this->ctx->url = $this->api_version .'/' . $this->type;
			$this->ctx->method = 'post';
		} else {
			$this->ctx->url = $this->api_version .'/' . $this->type . '/' .  $content->getId();
			$this->ctx->method = 'put';
		}

		$this->ctx->body = $content->toJson();
		return new $this->class($this->ctx->fetch());
	}

	function update (BXObject $content):BXObject|false {
		if ($content::readonly) { return false; }

		if (!$content->getId()) { return $this->set($content); }
		$this->ctx->url = $this->api_version .'/' . $this->type . '/' .  $content->getId();
		/* API BUG according to documentation it should be "patch" but when 
		 * using "patch", I get error 404 as when use "post" with partial data 
		 * it update
		 */
		$this->ctx->method = 'post'; 
		$this->ctx->body = $content->changesToJson();
		return new $this->class($this->ctx->fetch());
	}

}

trait tBexioArchiveable {
	function archive (BXObject $content):bool {
		if ($content::readonly) { return false; }
		if (!$content->getId()) { return false; }
		$this->ctx->url = $this->api_version .'/' . $this->type . '/' .  $content->getId() . '/archive';
		$this->ctx->method = 'post';
		return $this->ctx->fetch()->success;
	}

	function unarchive (BXObject $content):bool {
		if ($content::readonly) { return false; }
		if (!$content->getId()) { return false; }
		$this->ctx->url = $this->api_version .'/' . $this->type . '/' .  $content->getId() . '/reactivate';
		$this->ctx->method = 'post';
		return $this->ctx->fetch()->success;
	}
}

trait tBexioNumberObject {
	function getByNumber (Int|String $id):BXObject {
		$this->ctx->url = $this->api_version . '/' . $this->type . '/search';
		$this->ctx->method = 'post';
		$this->ctx->body = json_encode([[
			'field' => $this->class::NR,
			'value' => strval($id),
			'criteria' => '='
		]]);
		return new $this->class($this->ctx->fetch()[0]);
	}
}

trait tBexioPDFObject {
	function getPDF(Int|BXObject $id):BXObject  {
		if ($id instanceof BXObject) {
			$id = $id->getId();
		}
		$this->ctx->url = $this->api_version . '/' . $this->type . '/' . strval($id) . '/pdf';
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
					fn($e) => new $this->class($e),
					array_filter($list, fn($e) => intval($e->project_id) === intval($projectId))
				)
			);
			$offset += $count;
		} while (count($list) === $count);
		return $results;
	}
}

class BexioCountry extends BexioAPI {
	protected $type = 'country';
	protected $class = 'BizCuit\BXObject\Country';
	protected $query = 'BizCuit\BXQuery\Coutry';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioQuote extends BexioAPI {
	protected $type = 'kb_offer';
	protected $class = 'BizCuit\BXObject\Quote';
	protected $query = 'BizCuit\BXQuery\Quote';

	use tBexioV2Api, tBexioObject, tBexioPDFObject, tBexioProjectObject, tBexioCollection, tBexioNumberObject;
}

class BexioInvoice extends BexioAPI {
	protected $type = 'kb_invoice';
	protected $class = 'BizCuit\BXObject\Invoice';
	protected $query = 'BizCuit\BXQuery\Invoice';

	use tBexioV2Api, tBexioObject, tBexioPDFObject, tBexioProjectObject, tBexioCollection, tBexioNumberObject;
}

class BexioOrder extends BexioAPI {
	protected $type = 'kb_order';
	protected $class = 'BizCuit\BXObject\Order';
	protected $query = 'BizCuit\BXQuery\Order';

	use tBexioV2Api, tBexioObject, tBexioPDFObject, tBexioProjectObject, tBexioCollection, tBexioNumberObject;
}

class BexioContact extends BexioAPI {
	protected $type = 'contact';
	protected $class = 'BizCuit\BXObject\Contact';
	protected $query = 'BizCuit\BXQuery\Contact';


	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioProject extends BexioAPI {
	protected $type = 'pr_project';
	protected $class = 'BizCuit\BXObject\Project';
	protected $query = 'BizCuit\BXQuery\Project';

	use tBexioV2Api, tBexioObject, tBexioCollection, tBexioNumberObject, tBexioArchiveable;
}

class BexioContactRelation extends BexioAPI {
	protected $type = 'contact_relation';
	protected $class = 'BizCuit\BXObject\ContactRelation';
	protected $query = 'BizCuit\BXQuery\ContactRelation';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioAdditionalAddress extends BexioAPI {
	protected $type = 'additional_address';
	protected $class = 'BizCuit\BXObject\AdditionalAddress';
	protected $query = 'BizCuit\BXQuery\AdditionalAddress';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioNote extends BexioAPI {
	protected $type = 'note';
	protected $class = 'BizCuit\BXObject\Note';
	protected $query = 'BizCuit\BXQuery\Note';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioUser extends BexioAPI {
	protected $type = 'users';
	protected $class = 'BizCuit\BXObject\User';
	protected $query = 'BizCuit\BXQuery\User';

	use tBexioV3Api, tBexioObject, tBexioCollection;
}

class BexioBusinessActivity extends BexioAPI {
	protected $type = 'client_service';
	protected $class = 'BizCuit\BXObject\ClientService';
	protected $query = 'BizCuit\BXQuery\ROObject';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioSalutation extends BexioAPI {
	protected $type = 'salutation';
	protected $class = 'BizCuit\BXObject\Salutation';
	protected $query = 'BizCuit\BXQuery\ROObject';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioTitle extends BexioAPI {
	protected $type = 'title';
	protected $class = 'BizCuit\BXObject\Title';
	protected $query = 'BizCuit\BXQuery\ROObject';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioProjectType extends BexioAPI {
	protected $type = 'pr_project_type';
	protected $class = 'BizCuit\BXObject\ProjectType';
	protected $query = 'BizCuit\BXQuery\ROObject';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}
class BexioProjectStatus extends BexioAPI {
	protected $type = 'pr_project_state';
	protected $class = 'BizCuit\BXObject\ROObject';
	protected $query = 'BizCuit\BXQuery\ROObject';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioBills extends BexioAPI {
	protected $type = 'purchase/bills';
	protected $class = 'BizCuit\BXObject\Bills';
	protected $query = 'BizCuit\BXQuery\Bills';
	protected $search_fields = [
		'firstname_suffix',
		'lastname_company',
		'vendor_ref',
		'currency_code',
		'document_no',
		'title'
	];


	function setStatus (Int|String|BXObject $uuid, string $status) {
		if ($uuid instanceof BexioFile) {
			$uuid = $uuid->getId();
		}
		$this->ctx->url = $this->api_version .'/' . $this->type . '/' .  strval($uuid) . '/bookings/' . $status;
		$this->ctx->method = 'PUT';
		$this->ctx->body = '';
		error_log($this->ctx->url);
		$result = $this->ctx->fetch();
		return $this->new($result);
	}

	use tBexioObject, tBexioV4Api;
}

class BexioFile extends BexioAPI {
	protected $type = 'files';
	protected $class = 'BizCuit\BXObject\File';
	protected $query = 'BizCuit\BXQuery\File';
	protected $uuid;

	function getId() {
		return $this->uuid;
	}

	function get (Int|String|BXObject $uuid, array $options = []) {
		if ($uuid instanceof BexioFile) {
			$uuid = $uuid->getId();
		}

		$this->ctx->url = $this->api_version .'/' . $this->type . '/' . strval($uuid);
		$result = $this->ctx->fetch();
		$file = $this->new($result);
		$this->ctx->url = $this->api_version .'/' . $this->type . '/' .  strval($uuid) . '/download';
		
		$file->content = base64_encode($this->ctx->fetch());
		return $file;
	}

	use tBexioV3Api, tBexioObject, tBexioCollection;
}