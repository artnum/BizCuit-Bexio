<?php
/* (c) 2023 Etienne Bagnoud */
namespace BizCuit\BXQuery;

use stdClass;

abstract class BXQuery {
	const allowedCriteria = [
		'=',
		'equal',
		'!=',
		'not_equal',
		'>',
		'greater_than',
		'<',
		'less_than',
		'>=',
		'greater_equal',
		'<=',
		'less_equal',
		'like',
		'not_like',
		'is_null',
		'not_null',
		'in',
		'not_in'
	];
	protected $allowedField = ['name'];
	protected $query = [];

	function __construct(Array $allowedField = ['name']) {
		$this->allowedField = $allowedField;
	}

	function add(String $field, String $term, String $criteria = '=') {
		$field = strtolower($field);
		$criteria = strtolower($criteria);

		if (!in_array($criteria, $this::allowedCriteria)) { return false; }
		if (!in_array($field, $this->allowedField)) { return false; }
		
		$q = new stdClass();
		$q->field = $field;
		$q->value = $term;
		$q->criteria = $criteria;

		$this->query[] = $q;
		return true;
	}

	function remove(String $field) {
		$this->query = array_filter($this->query, fn ($e) => $e->field !== $field );
	}

	function replace(String $field, String $term, String $criteria) {
		$this->remove($field);
		return $this->add($field, $term, $criteria);
	}

	function toJson() {
		return json_encode($this->query);
	}
}

class ContactGroup extends BXQuery { }
class ContactSector extends BXQuery { }
class Salutation extends BXQuery { }
class Title extends BXQuery { }
class Payment extends BXQuery { }
class Unit extends BXQuery { }
class StockLocation extends BXQuery { }
class BusinessActivity extends BXQuery { }
class CommunicationType extends BXQuery { }

class Project extends BXQuery {
	function __construct() {
		parent::__construct([
			'name',
			'contact_id',
			'pr_state_id'
		]);
	}
}

class File extends BXQuery {
	function __construct() {
		parent::__construct([
			'id',
			'uuid',
			'created_at',
			'name',
			'extension',
			'size_in_bytes',
			'mime_type',
			'user_id',
			'is_archived',
			'source_id'
		]);
	}
}

class Contact extends BXQuery {
	function __construct() {
		parent::__construct([
			'id',
			'name_1',
			'name_2',
			'nr',
			'address',
			'mail',
			'mail_second',
			'postcode',
			'city',
			'country_id',
			'contact_group_ids',
			'contact_type_id',
			'updated_at',
			'user_id',
			'phone_fixed',
			'phone_mobile',
			'fax'
		]);
	}
}

class ContactRelation extends BXQuery {
	function __construct() {
		parent::__construct([
			'contact_id',
			'contact_sub_id',
			'updated_at'
		]);
	}
}

class AdditionalAddress extends BXQuery {
	function __construct() {
		parent::__construct([
			'name',
			'address',
			'postcode',
			'city',
			'country_id',
			'subject',
			'email'
		]);
	}
}

class Quote extends BXQuery {
	function __construct() {
		parent::__construct([
			'id',
			'kb_item_status_id',
			'document_nr',
			'title',
			'contact_id',
			'contact_sub_id',
			'user_id',
			'currency_id',
			'total_gross',
			'total_net',
			'total',
			'is_valid_from',
			'is_valid_to',
			'is_valid_until',
			'updated_at'
		]);
	}
}

class Order extends BXQuery {
	function __construct() {
		parent::__construct([
			'id',
			'kb_item_status_id',
			'document_nr',
			'title',
			'contact_id',
			'contact_sub_id',
			'user_id',
			'currency_id',
			'total_gross',
			'total_net',
			'total',
			'is_valid_from',
			'is_valid_to',
			'updated_at'
		]);
	}
}

class Invoice extends BXQuery {
	function __construct() {
		parent::__construct([
			'id',
			'kb_item_status_id',
			'document_nr',
			'title',
			'api_reference',
			'contact_id',
			'contact_sub_id',
			'user_id',
			'currency_id',
			'total_gross',
			'total_net',
			'total',
			'is_valid_from',
			'is_valid_to',
			'updated_at'
		]);
	}
}

class InvoiceReminder extends BXQuery {
	function __construct() {
		parent::__construct([
			'title',
			'reminder_level',
			'is_sent',
			'is_valid_from',
			'is_valid_to',
		]);
	}
}

class Account extends BXQuery {
	function __construct() {
		parent::__construct([
			'account_no',
			'fibu_account_group_id',
			'name',
			'account_type'
		]);
	}
}

class Item extends BXQuery {
	function __construct() {
		parent::__construct([
			'intern_name',
			'intern_code'
		]);
	}
}

class StockArea extends BXQuery {
	function __construct() {
		parent::__construct([
			'name',
			'stock_id'
		]);
	}
}

class Timesheet extends BXQuery {
	function __construct() {
		parent::__construct([
			'id',
			'client_service_id',
			'contact_id',
			'user_id',
			'pr_project_id',
			'status_id'
		]);
	}
}

class Country extends BXQuery {
	function __construct() {
		parent::__construct([
			'name',
			'name_short'
		]);
	}
}

class Language extends BXQuery {
	function __construct() {
		parent::__construct([
			'name',
			'iso_639_1'
		]);
	}
}

class Note extends BXQuery {
	function __construct() {
		parent::__construct([
			'event_start',
			'contact_id',
			'user_id',
			'subject',
			'module_id',
			'entry_id'
		]);
	}
}

class Task extends BXQuery {
	function __construct() {
		parent::__construct([
			'subject',
			'updated_at',
			'user_id',
			'contact_id',
			'todo_status_id',
			'module_id',
			'entry_id'
		]);
	}
}