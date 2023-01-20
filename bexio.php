<?php
/* (c) 2023 Etienne Bagnoud */
namespace BizCuit;

use Exception;
use stdClass;

class BexioAPI {
	private $endpoint = 'https://api.bexio.com/';
	private $c;
	private $userid = null;
    private $ownerid = null;
	private $headers;

	function __construct(String $token) {
		$this->c = curl_init();
		$this->headers = [
			'Accept: application/json',
			'Authorization: Bearer ' . $token
		];
	}

	private function init() {
		curl_reset($this->c);
		curl_setopt($this->c, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($this->c, CURLOPT_RETURNTRANSFER, true);
	}
    private function error(String $msg) {
        error_log($msg);
    }
	private function do() {
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
		    return json_decode($data);
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

    /*** Contacts  ***/
    function initContact(): Array {
        return [
            'contact_type_id' => 1,
            'name_1' => '',
            'name_2' => null,
            'salutation_id' => null,
            'salutation_form' => null,
            'titel_id' => null,
            'birthday' => null,
            'address' => null,
            'postcode' => null,
            'city' => null,
            'country_id' => null,
            'mail' => null,
            'mail_second' => null,
            'phone_fixed' => null,
            'phone_fixed_second' => null,
            'phone_mobile' => null,
            'fax' => null,
            'url' => null,
            'skype_name' => null,
            'remarks' => null,
            'language_id' => null,
            'contact_group_ids' => null,
            'contact_branch_ids' => null
        ];
    }

	function createContact (Array $contact): False|Int {
		if (empty($contact['owner_id']) && ($this->ownerid || $this->userid)) { $contact['owner_id'] = $this->userid ? $this->userid : $this->ownerid; }
		if (empty($contact['user_id'])  && $this->userid) { $contact['user_id'] = $this->userid; }

        if (empty($contact['name_1']) || empty($contact['contact_type_id']) || empty($contact['user_id']) || empty($contact['owner_id'])) {
            return $this->error("Missing required fiedl for note");
        }

		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . '2.0/contact');
		curl_setopt($this->c, CURLOPT_POST, true);
		$body = json_encode($contact);
		curl_setopt($this->c, CURLOPT_POSTFIELDS, $body);
		$result = $this->do();
		if ($result === null) { return false; }
		return $result->id;
	}

	function createContactRelation(Int $sup, Int $sub, String $description = 'Contact'): False|Int {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . '2.0/contact_relation');
		curl_setopt($this->c, CURLOPT_POST, true);
		curl_setopt($this->c, CURLOPT_POSTFIELDS, json_encode([
			'contact_id' => $sup,
			'contact_sub_id' => $sub,
			'description' => $description
		]));

		$result = $this->do();
		if ($result === null) { return false; }
		return $result->id;
	}

    /*** NOTE  ***/
    /* return an empty note */
    function initNote (): Array {
        return [
            'subject' => '',
            'info' => '',
		    'contact_id' => null,
		    'pr_project_id' => null,
		    'entry_id' => null,
		    'module_id' => null
	    ];
    }

	function deleteNote(Int $noteid): Bool {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . '2.0/note/' . $noteid);
		curl_setopt($this->c, CURLOPT_CUSTOMREQUEST, 'DELETE');

		$result = $this->do();
        if ($result === null) { return false; }
        return $result->succes;
	}

    /* Create a note, return id */
	function createNote(Array $note): False|Int {
		if (empty($note['user_id']) && $this->userid) { $note['user_id'] = $this->userid; }
		if (empty($note['event_start']) && $this->userid) { $note['event_start'] = (new \DateTime())->format('Y-m-d H:m:s'); }

        if (empty($note['user_id']) || empty($note['event_start']) || empty($note['subject'])) {
            return $this->error("Missing required field for note");
        }

		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . '2.0/note');
		curl_setopt($this->c, CURLOPT_POST, true);
		curl_setopt($this->c, CURLOPT_POSTFIELDS, json_encode($note));

		$result = $this->do();
		if ($result === null) { return false; }
		return intval($result->id);
	}

    /* A note with specific subject exists */
	function hasNote(String $subject): False|Int {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . '2.0/note/search');
		curl_setopt($this->c, CURLOPT_POST, true);
		$body = json_encode(
			[[
				'field' => 'subject',
				'value' => $subject,
				'criteria' => '='
			]]
		);

		curl_setopt($this->c, CURLOPT_POSTFIELDS, $body);
		$data = $this->do();
		if ($data === null) { return false; }
		return is_array($data) ? (count($data) > 0 ? $data[0]->id : false) : false;
	}

    /* Get a note by id */
	function getNote(Int $noteid): False|stdClass {
		$this->init();
		curl_setopt($this->c, CURLOPT_URL, $this->endpoint . '2.0/note/'. $noteid);
		$result = $this->do();
        if ($result === null) { return false; }
        return $result;
	}
}