<?php
/* (c) 2023 Etienne Bagnoud */
namespace BizCuit;

require('bxquery.php');
require('bxobject.php');

use BizCuit\BXObject\BXObject;
use Exception;
use stdClass;



class BexioAPI {
	protected $endpoint = 'https://api.bexio.com/';
	protected $c;
	protected $userid = null;
    protected $ownerid = null;
	protected $headers;
	protected $class;

	function __construct(String $token) {
		$this->c = curl_init();
		$this->headers = [
			'Accept: application/json',
			'Authorization: Bearer ' . $token
		];
	}

	function getHeaders() {
		return $this->headers;
	}

	function getCurl() {
		return $this->c;
	}

	protected function init() {
		curl_reset($this->c);
		curl_setopt($this->c, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($this->c, CURLOPT_RETURNTRANSFER, true);
	}
    protected function error(String $msg) {
        error_log($msg);
    }
	protected function do() {
		$data = curl_exec($this->c);
		$code = curl_getinfo($this->c,  CURLINFO_HTTP_CODE);
		if (!$data) { error_log("ERROR: $code => $data\n"); return null; }
		switch(intval($code)) {
			case 200:
			case 201: break;
            case 304: error_log("ERROR The resource has not been changed\n\t$code => '$data'\n"); return null;
            case 400: error_log("ERROR The request parameters are invalid\n\t$code => '$data'\n"); return null;
            case 401: error_log("ERROR The bearer token or the provided api key is invalid\n\t$code => '$data'\n"); return null;
            case 403: error_log("ERROR You do not possess the required rights to access this resource\n\t$code => '$data'\n"); return null;
            case 404: error_log("ERROR The resource could not be found / is unknown\n\t$code => '$data'\n"); return null;
            case 411: error_log("ERROR Length Required\n\t$code => '$data'\n"); return null;
            case 415: error_log("ERROR The data could not be processed or the accept header is invalid\n\t$code => '$data'\n"); return null;
            case 422: error_log("ERROR Could not save the entity\n\t$code => '$data'\n"); return null;
            case 429: error_log("ERROR Too many requests\n\t$code => '$data'\n"); return null;
            case 500: error_log("ERROR An unexpected condition was encountered\n\t$code => '$data'\n"); return null;
            case 503: error_log("ERROR The server is not available (maintenance work)\n\t$code => '$data'\n"); return null;
			default:
				error_log("ERROR: Unknown\n\t$code => '$data'\n");
				return null;
		}
        try {
		    $result = json_decode($data);
			if (is_array($result)) { 
				return array_map(function ($e) { return new $this->class($e); }, $result);
			}
			return new $this->class($result);
        } catch (Exception $e) {
            error_log("ERROR " . $e->getMessage() . "\n");
            return null;
        }
	}

    /*** Users ***/
	function getUsers(): False|Array {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . '3.0/users');
		curl_setopt($this->c, CURLOPT_HTTPGET, true);
		curl_setopt($this->c, CURLOPT_POST, false);
		$result = $this->do();
        if ($result === null) { return false; }
        if (!is_array($result)) { return false; }
        return $result;
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
	function search (BXQuery\BXQuery $query, Int $offset = 0, Int $limit = 500) {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . $this->api_version . '/' . $this->type . sprintf('?limit=%d&offset=%d', $limit, $offset));
		curl_setopt($this->c, CURLOPT_POST, true);
		curl_setopt($this->c, CURLOPT_POSTFIELDS, $query->toJson());

		$result = $this->do();
		if ($result === null) { return false; }
		return $result;
	}

	function list (Int $offset = 0, Int $limit = 500) {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . $this->api_version . '/' . $this->type . sprintf('?limit=%d&offset=%d', $limit, $offset));

		$result = $this->do();
        if ($result === null) { return false; }
        return $result;
	}
}

trait tBexioObject {
	function __construct (BexioAPI $from) {
		$this->c = $from->getCurl();
		$this->headers = $from->getHeaders();
	}

	function delete(Int|String $id): Bool {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . $this->api_version . '/' . $this->type . '/' . strval($id));
		curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, 'DELETE');

		$result = $this->do();
        if ($result === null) { return false; }
        return $result->succes;
	}

	function get (Int|String $id) {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . $this->api_version .'/' . $this->type . '/' .  strval($id));

		$result = $this->do();
		if ($result === null) { return false; }
		error_log('kb_status ::: ' . $result->kb_item_status_id);
		return $result;
	}

	function set (BXObject $content) {
		if ($content::readonly) { return false; }

		$this->init();
		echo $this->endpoint . $this->api_version .'/' . $this->type . '/' .  $content->getId() . "\n";
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . $this->api_version .'/' . $this->type . '/' .  $content->getId());
		curl_setopt($this->c, CURLOPT_POST, true);
		curl_setopt($this->c, CURLOPT_POSTFIELDS, $content->toJson());

		$result = $this->do();
		if ($result === null) { return false; }
		return $result;
	}
}

trait tBexioNumberObject {
	function getByNumber (Int|String $id) {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . $this->api_version . '/' . $this->type . '/search');
		curl_setopt($this->c, CURLOPT_POST, true);
		curl_setopt($this->c, CURLOPT_POSTFIELDS, json_encode([[
			'field' => $this->class::NR,
			'value' => strval($id),
			'criteria' => '='
		]]));

		$result = $this->do();
		if ($result === null) { return false; }
		return $result[0];
	}
}

trait tBexioPDFObject {
	protected $pdf = 'pdf';

	function getPDF(Int|String $id): False|BXObject {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . $this->api_version . '/' . $this->type . '/' . strval($id) . '/' . $this->pdf);
		$result = $this->do();
		error_log(var_export($result, true));
		if ($result === null) { return false; }
		return $result;
	}
}

trait tBexioProjectObject {
	protected $project_attr_name = 'project_id';
	function listByProject (Int $projectId): False|Array {
		$this->init();
		/* too bad, can't search by project_id */
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . $this->api_version . '/' . $this->type); 

		$result = $this->do();
		if ($result === null) { return false; }
		$out = [];
		foreach($result as $object) {
			if ($object->{$this->project_attr_name} === $projectId) { $out[] = $object; }
		}
		return $out;
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