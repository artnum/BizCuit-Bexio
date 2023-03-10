<?php
/* (c) 2023 Etienne Bagnoud */
namespace BizCuit;

require(__DIR__ . '/bxquery.php');
require(__DIR__ . '/bxobject.php');

use BizCuit\BXObject\BXObject;
use BizCuit\BXQuery\BXQuery;
use Exception;
use stdClass;

class BexioCTX {
	const endpoint = 'https://api.bexio.com/';
	protected $c = '';
	protected $headers = [];
	protected $values;
	protected $token;

	function __construct(String $token) {
		$this->c = curl_init();
		$this->token = $token;
		$this->headers = [
			'Accept: application/json',
			'Authorization: Bearer ' . $token
		];
		$this->values = new stdClass();
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
			case 'get': break;
			case 'post': curl_setopt($this->c, CURLOPT_POST, true); break;
			case 'delete': curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, 'DELETE'); break;
			case 'put': curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, 'PUT'); break;
			case 'patch': curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, 'PATCH'); break;
			case 'head': curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, 'HEAD'); break;
		}
	}

	private function set_body (String $body = '') {
		if (strlen($body) <= 0) { return; }
		curl_setopt($this->c, CURLOPT_POSTFIELDS, $body);
	}

	private function reset() {
		unset($this->url);
		unset($this->method);
		unset($this->body);
		curl_reset($this->c);
	}

	function __set(String $name, String $value) {
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

	function __get(String $name) {
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

	function __isset($name)	{
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

	function __unset($name) {
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
			curl_setopt($this->c, CURLOPT_HTTPHEADER, $this->headers);
			curl_setopt($this->c, CURLOPT_RETURNTRANSFER, true);
			$this->set_method($this->method);
			$this->set_url($this->url);
			if ($this->method !== 'get' && $this->method !== 'head') { $this->set_body($this->body); }
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
			default: throw new Exception('Error', $code, new Exception($data));
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
}

trait tBexioV2Api {
	protected $api_version = '2.0';
}

trait tBexioV3Api {
	protected $api_version = '3.0';
}

trait tBexioV4Api {
	protected $api_version = '4.0';
}

trait tBexioCollection {
	function search (BXQuery $query, Int $offset = 0, Int $limit = 500) {
		$this->ctx->url = $this->api_version . '/' . $this->type .'/search' . sprintf('?limit=%d&offset=%d', $limit, $offset);
		$this->ctx->body = $query->toJson();
		$this->ctx->method = 'post';
		return array_map(fn($e) => new $this->class($e), $this->ctx->fetch());
	}

	function list (Int $offset = 0, Int $limit = 500) {
		$this->ctx->url =$this->api_version . '/' . $this->type . sprintf('?limit=%d&offset=%d', $limit, $offset);
		return array_map(fn($e) => new $this->class($e), $this->ctx->fetch());
	}
}

trait tBexioObject {
	function new ():BXObject {
		return new $this->class();
	}

	function delete(Int|BXObject $id): Bool {
		if ($id instanceof BXObject) {
			$id = $id->getId();
		}
		$this->ctx->url = $this->api_version . '/' . $this->type . '/' . strval($id);
		$this->ctx->method = 'delete';

		return $this->ctx->fetch()->success;
		
	}

	function get (Int|BXObject $id) {
		if ($id instanceof BXObject) {
			$id = $id->getId();
		}
		$this->ctx->url = $this->api_version .'/' . $this->type . '/' .  strval($id);
		return new $this->class($this->ctx->fetch());
	}

	function set (BXObject $content) {
		if ($content::readonly) { return false; }

		if ((empty($content->user_id) || is_null($content->user_id)) && in_array('user_id', $content::createProperties)) { 
			$content->user_id = $this->ctx->user_id; 
		}
		if ((empty($content->owner_id) || is_null($content->owner_id)) && in_array('owner_id', $content::createProperties)) {
			$content->owner_id = $this->ctx->owner_id; 
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

	function update (BXObject $content) {
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

trait tBexioNumberObject {
	function getByNumber (Int|String $id) {
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
		return new \BizCuit\BXObject\File($this->ctx->fetch());
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

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioQuote extends BexioAPI {
	protected $type = 'kb_offer';
	protected $class = 'BizCuit\BXObject\Quote';

	use tBexioV2Api, tBexioObject, tBexioPDFObject, tBexioProjectObject, tBexioCollection, tBexioNumberObject;
}

class BexioInvoice extends BexioAPI {
	protected $type = 'kb_invoice';
	protected $class = 'BizCuit\BXObject\Invoice';

	use tBexioV2Api, tBexioObject, tBexioPDFObject, tBexioProjectObject, tBexioCollection, tBexioNumberObject;
}

class BexioOrder extends BexioAPI {
	protected $type = 'kb_order';
	protected $class = 'BizCuit\BXObject\Order';

	use tBexioV2Api, tBexioObject, tBexioPDFObject, tBexioProjectObject, tBexioCollection, tBexioNumberObject;
}

class BexioContact extends BexioAPI {
	protected $type = 'contact';
	protected $class = 'BizCuit\BXObject\Contact';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioProject extends BexioAPI {
	protected $type = 'pr_project';
	protected $class = 'BizCuit\BXObject\Project';

	use tBexioV2Api, tBexioObject, tBexioCollection, tBexioNumberObject;
}

class BexioContactRelation extends BexioAPI {
	protected $type = 'contact_relation';
	protected $class = 'BizCuit\BXObject\ContactRelation';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioAdditionalAddress extends BexioAPI {
	protected $type = 'additional_address';
	protected $class = 'BizCuit\BXObject\AdditionalAddress';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioNote extends BexioAPI {
	protected $type = 'note';
	protected $class = 'BizCuit\BXObject\Note';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}

class BexioUser extends BexioAPI {
	protected $type = 'users';
	protected $class = 'BizCuit\BXObject\ROObject';

	use tBexioV3Api, tBexioObject, tBexioCollection;
}

class BexioBusinessActivity extends BexioAPI {
	protected $type = 'client_service';
	protected $class = 'BizCuit\BXObject\ROObject';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}