<?php
/* (c) 2023 Etienne Bagnoud */

namespace BizCuit\BXObject;

use BizCuit\BXQuery\BXQuery;
use stdClass;

abstract class BXObject {
	protected $content;
    const ID = 'id';
    const NR = 'nr';
    const createProperties = [];
    const nullableProperties = [];

	function __construct(stdClass $object) {
		$this->content = $object;		
        foreach ($this::createProperties as $prop) {
            if (!property_exists($this->content, $prop)) { $this->content->{$prop} = null; }
        }
	}

	function getId() {
		return $this->content->{$this::ID};
	}

	function getNumber() {
        if ($this::NR === null) { return null; }
		return $this->content->{$this::NR};
	}

	function toJson() {
		$outClass = clone $this->content;
        foreach ($outClass as $k => $v) {
            if (!in_array($k, $this::createProperties)) { unset($outClass->{$k}); }
            /* when requesting data, it appears that null properties are set to 0 and fail to write back */
            if (in_array($k, $this::nullableProperties) && $outClass->{$k} === 0) { $outClass->{$k} = null; } 
        }
		return json_encode($outClass);
	}

	function __get($name) {
		if (property_exists($this->content, $name))  { return $this->content->{$name}; }
		return false;
	}

	function __set($name, $value) {
		$this->content->{$name} = $value;
		return $this->content->{$name};
	}
}

class Country extends BXObject {
    const NR = null;
    const createProperties = [
        'name',
        'name_short',
        'iso_3166_alpha2'
    ];
}

class Contact extends BXObject {
    const NR = null;
    const createProperties = [
        'contact_type_id',
        'name_1',
        'name_2',
        'salutation_id',
        'salutation_form',
        'titel_id',
        'birthday',
        'address',
        'postcode',
        'city',
        'country_id',
        'mail',
        'mail_second',
        'phone_fixed',
        'phone_fixed_second',
        'phone_mobile',
        'fax',
        'url',
        'skype_name',
        'remarks',
        'language_id',
        'contact_group_ids',
        'contact_branch_ids',
        'user_id',
        'owner_id'
    ];

    const nullableProperties = [
        'salutation_id'
    ];
}

class Project extends BXObject {
    const NR = null;
    const createProperties = [
        'name',
        'start_date',
        'end_date',
        'comment',
        'pr_state_id',
        'pr_project_type_id',
        'contact_id',
        'contact_sub_id',
        'pr_invoice_type_id',
        'pr_invoice_type_amount',
        'pr_budget_type_id',
        'pr_budget_type_amount',
        'user_id'
    ];
}

class Order extends BXObject {
    const NR = 'document_nr';
    const createProperties = [
        'title',
        'contact_id',
        'contact_sub_id',
        'user_id',
        'project_id',
        'language_id',
        'bank_account_id',
        'currency_id',
        'payment_type_id',
        'header',
        'footer',
        'mwst_type',
        'mwst_is_net',
        'show_position_taxes',
        'is_valid_from',
        'delivery_address_type',
        'api_reference',
        'template_slug',
        'positions'
    ];
}

class Invoice extends BXObject {
    const NR = 'document_nr';
    const createProperties = [
        'title',
        'contact_id',
        'contact_sub_id',
        'user_id',
        'project_id',
        'language_id',
        'bank_account_id',
        'currency_id',
        'payment_type_id',
        'header',
        'footer',
        'mwst_type',
        'mwst_is_net',
        'show_position_taxes',
        'is_valid_from',
        'is_valid_until',
        'reference',
        'api_reference',
        'template_slug',
        'positions'
    ];
}

class Quote extends BXObject {
    const NR = 'document_nr';
    const createProperties = [
        'title',
        'contact_id',
        'contact_sub_id',
        'user_id',
        'project_id',
        'language_id',
        'bank_account_id',
        'currency_id',
        'payment_type_id',
        'header',
        'footer',
        'mwst_type',
        'mwst_is_net',
        'show_position_taxes',
        'is_valid_from',
        'is_valid_until',
        'delivery_address_type',
        'api_reference',
        'viewed_by_client_at',
        'kb_terms_of_payment_template_id',
        'template_slug',
        'positions'
    ];
}