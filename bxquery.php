<?php
/**
 * @author Etienne Bagnoud <etienne@artnum.ch>
 * @license MIT
 * @copyright 2023 Etienne Bagnoud
 * 
 */
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
	protected bool $anyField = false;
	protected array $allowedField = ['name'];
	protected array $query = [];
	protected array $localFields = [];

	/**
	 * Create a search object
	 * 
	 * @param array $allowedField Fields allowed in the query as per Bexio documentation
	 * @return void 
	 */
	function __construct(array $allowedField = ['name']) {
		$this->allowedField = $allowedField;
	}

	function add(String $field, String $term, String $criteria = '='):bool {
		$field = strtolower($field);
		$criteria = strtolower($criteria);

		if (!in_array($criteria, self::allowedCriteria)) { return false; }
		if (!$this->anyField && !in_array($field, $this->allowedField)) { return false; }

		$q = new stdClass();
		$q->field = $field;
		$q->value = $term;
		$q->criteria = $criteria;

		if ($this->anyField && !in_array($field, $this->allowedField)) { 
			$this->localFields[] = $q;	
		} else {
			$this->query[] = $q;
		}
		return true;
	}

	function remove(String $field):true {
		$this->query = array_filter($this->query, fn ($e) => $e->field !== $field );
		return true;
	}

	function replace(String $field, String $term, String $criteria):bool {
		$this->remove($field);
		return $this->add($field, $term, $criteria);
	}

	function toJson():string {
		return json_encode($this->query);
	}

	function getRawQuery():array {
		return $this->query;
	}

	function getRawQueryLocal():array {
		return $this->localFields;
	}

	function isWithAnyfields():bool {
		return $this->anyField;
	}

	/**
	 * The query will run with any fields. Fields that are note searchable on 
	 * Bexio side will be searched locally.
	 * @return void 
	 */
	function setWithAnyfields():void {
		$this->anyField = true;
	}
	
	/**
	 * The query will run with only fields that are searchable on Bexio side.
	 * @return void 
	 */
	function unsetWithAnyfields():void {
		$this->anyField = false;
	}
}

class ROObject extends BXQuery {
	function add(String $field, String $term, string $criteria = '='):bool {
		$field = strtolower($field);
		$criteria = strtolower($criteria);

		if (!in_array($criteria, self::allowedCriteria)) { return false; }
		
		$q = new stdClass();
		$q->field = $field;
		$q->value = $term;
		$q->criteria = $criteria;

		$this->query[] = $q;
		return true;
	}
}

class ContactSector extends BXQuery { }
class Payment extends BXQuery { }
class Unit extends BXQuery { }
class StockLocation extends BXQuery { }
class BusinessActivity extends BXQuery { }
class CommunicationType extends BXQuery { }
class Currency extends ROObject { }
class ProjectStatus extends ROObject { }
class ProjectType extends ROObject { }
class Title extends ROObject { }
class ClientService extends ROObject { }
class Expense extends ROObject {}
class BankAccount extends ROObject { }
class Taxes extends ROObject {}

class Project extends BXQuery {
	function __construct() {
		parent::__construct([
			'name',
			'contact_id',
			'pr_state_id'
		]);
	}
}

class Files extends BXQuery {
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

class User extends BXQuery {
	function __construct() {
		parent::__construct([
			'salutation_type',
			'firstname',
			'lastname',
			'email',
			'is_superadmin',
			'is_accountant'
		]);
	}
}

class Bills extends BXQuery {
	function __construct() {
		parent::__construct([
			'firstname_suffix',
			'lastname_company',
			'vendor_ref',
			'currency_code',
			'document_no',
			'title',
			'supplier_id',
			'document_no',
			'net_max',
			'net_min',
			'gross_max',
			'gross_min',
			'vendor',
			'pending_amount_max',
			'pending_amount_min',
			'currency_code',
			'title',
			'vendor_ref',
			'due_date_end',
			'due_date_start',
			'bill_date_end',
			'bill_date_start',
			'status'
		]);
	}
}

class ContactGroup extends BXQuery {
	function __construct() {
		parent::__construct([
			'name'
		]);
	}
}

class OutgoingPayment extends BXQuery { 
	function __construct() {
		parent::__construct([
			'bill_id'
		]);
	}
}

class Salutation extends BXQuery { 
	function __construct() {
		parent::__construct([
			'name'
		]);
	}
}