<?php
/**
 * @author Etienne Bagnoud <etienne@artnum.ch>
 * @license MIT
 * @copyright 2023 Etienne Bagnoud
 * 
 */

namespace BizCuit\BXObject;

use stdClass;

abstract class BXObject {
    const ID = 'id';
    const NR = 'nr';
    const createProperties = [];
    const nullableProperties = [];
    const mapProperties = [];
    const readonly = false;
    protected $changes = [];
	protected $content;

	function __construct(stdClass $object = new stdClass()) {
        $this->content = new stdClass();
        foreach($this::createProperties as $prop) {
            $this->{$prop} = null;
        }
        foreach ($object as $prop => $value) {
            $this->{$prop} = $value;
        }
        $this->changes = [];
	}

	function getId() {
        if (!isset($this->content->{$this::ID})) { return null; }
		return $this->content->{$this::ID};
	}

    function getType ():string {
        $parts = explode('\\', get_class($this));
        return array_pop($parts);
    }

	function getNumber() {
        if ($this::NR === null) { return null; }
		return $this->content->{$this::NR};
	}

	function toJson() {
		$outClass = clone $this->content;
        foreach ($outClass as $k => $v) {
            /* when requesting data, it appears that null properties are set to 0 and fail to write back */
            if (in_array($k, $this::nullableProperties) && $outClass->{$k} === 0) { $outClass->{$k} = null; } 
        }
		return json_encode($outClass);
	}

    function changesToJson() {
        $outClass = new stdClass();
        foreach($this->changes as $key) {
            $outClass->{$key} = $this->{$key};
        }
        return json_encode($outClass);
    }

	function __get($name) {
        if (isset($this::mapProperties[$name])) {
            $name = $this::mapProperties[$name];
        }
		if (property_exists($this->content, $name))  { return $this->content->{$name}; }
		return false;
	}

	function __set($name, $value) {
        if (isset($this::mapProperties[$name])) {
            $name = $this::mapProperties[$name];
        }
		$this->content->{$name} = $value;
        if (!in_array($name, $this->changes)) { $this->changes[] = $name; }
		return $this->content->{$name};
	}
}

class ROObject extends BXObject {
    const NR = null;
    const readonly = true;

	function toJson() {
		return json_encode(clone $this->content);
	}

    function changesToJson() {
        return json_encode(new stdClass());
    }
}


class File extends ROObject {}
class ProjectType extends ROObject {}
class ProjectStatus extends ROObject {}
class Title extends ROObject {}
class Salutation extends ROObject {}
class ClientService extends ROObject {}
class Currency extends ROObject {}
class Expense extends ROObject {}
class ContactGroup extends ROObject { }
class ContactSector extends ROObject { }
class Payment extends ROObject { }
class Unit extends ROObject { }
class StockLocation extends ROObject { }
class BusinessActivity extends ROObject { }
class CommunicationType extends ROObject { }
class BankAccount extends ROObject { }
class OutgoingPayment extends ROObject { }

class Country extends BXObject {
    const NR = null;
    const createProperties = [
        'name',
        'name_short',
        'iso3166_alpha2'
    ];

    /* API BUG when requesting a country, iso_3166_alpha2 is set with the value
     * you expect to be in iso3166_alpha2 (which is what the documentation 
     * describe). If you create a country with the attribute iso_3166_alpha2,
     * the server return an error, so you have to map that property
     */
    const mapProperties = [
        'iso_3166_alpha2' => 'iso3166_alpha2'
    ];
}

class Contact extends BXObject {
    const NR = null;
    const createProperties = [
        'nr',
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

    /* API BUG when requesting a contact, if no salutation are set, the result
     * will have salutation_id = 0, but when sending back to update, edit or
     * replace the contact, the server complains with this value not being to
     * "null", so this is to fix that.
     */
    const nullableProperties = [
        'salutation_id'
    ];
}

class ContactRelation extends BXObject {
    const NR = null;
    const createProperties = [
        'contact_id',
        'contact_sub_id',
        'description'
    ];
}

class AdditionalAddress extends BXObject {
    const NR = null;
    const createProperties = [
        'name',
        'address',
        'postcode',
        'city',
        'country_id',
        'subject',
        'description'
    ];
}

class Note extends BXObject {
    const NR = null;
    const createProperties = [
        'user_id',
        'event_start',
        'subject',
        'info',
        'contact_id',
        'pr_project_id',
        'entry_id',
        'module_id'
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

class User extends BXObject {
    const NR = null;
    const createProperties = [
        'salutation_type',
        'firstname',
        'lastname',
        'email',
        'is_superadmin',
        'is_accountant'
    ];
}

class Bills extends BXObject {
    const NR = null;
    const createProperties = [
        'supplier_id',
        'vendor_ref',
        'title',
        'contact_partner_id',
        'bill_date',
        'due_date',
        'amount_man',
        'amount_calc',
        'manual_amount',
        'currency_code',
        'exchange_rate',
        'base_currency_amount',
        'item_net',
        'purchase_order_id',
        'qr_bill_information',
        'attachment_ids',
        'address',
        'line_items',
        'discounts',
        'payment'
    ];
}


class PDF extends BXObject {
    const createProperties = [
        'name',
        'size',
        'mime',
        'content'
    ];

    function __construct(stdClass $object = new stdClass()) {
        $this->content = new stdClass();
        foreach($this::createProperties as $prop) {
            $this->{$prop} = null;
        }
        foreach ($object as $prop => $value) {
            if ($prop === 'content') {
                $this->{$prop} = base64_decode($value);
                continue;
            }
            $this->{$prop} = $value;
        }
        $this->changes = [];
    }

    function toJson() {
		$outClass = new stdClass();
        foreach ($this->content as $k => $v) {
            if ($k === 'content') {
                $outClass->{$k} = base64_encode($v);
                continue;
            }
            $outClass->{$k} = $v;
        }
        foreach ($outClass as $k => $v) {
            if (!in_array($k, $this::createProperties)) { unset($outClass->{$k}); }
            /* when requesting data, it appears that null properties are set to 0 and fail to write back */
            if (in_array($k, $this::nullableProperties) && $outClass->{$k} === 0) { $outClass->{$k} = null; } 
        }
        
		return json_encode($outClass);
	}

    function changesToJson() {
        $outClass = new stdClass();
        foreach($this->changes as $key) {
            if ($key === 'content') {
                $outClass->{$key} = base64_encode($this->{$key});
            }
            $outClass->{$key} = $this->{$key};
        }
        return json_encode($outClass);
    }
}