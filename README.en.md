# BizCuit-Bexio, bexio API in PHP

If you are looking to move away from [Bexio](https://bexio.com), there is
another project [bx-sync](https://github.com/artnum/bx-sync) which aims to 
do just that.

This library allow to access [Bexio API](https://docs.bexio.com/). Constructed
arround cURL, it uses token authentication and provide a great deal of features.

It abastracts between the differents API version (like 4 in 4 years is quite
a lot) to make every endpoint look the same. It relies on class composition to
achieve that, example :

```php
class BexioContact extends BexioAPI {
	protected $type = 'contact';
	protected $class = 'BizCuit\BXObject\Contact';
	protected $query = 'BizCuit\BXQuery\Contact';


	use tBexioV2Api, tBexioObject, tBexioCollection;
}
```

Still in active developpement, it also provide tooling to build a data cache
short term (to mitigate [Rate Limiting](https://docs.bexio.com/#section/API-basics/Rate-Limiting))
and long term to keep working (read-only) if the upstream service goes down.

## Endorsement

The fact that I am developping this doesn't mean that I endorse Bexio product. 
I do it because I need it, but if I were to have the choice, I wouldn't use bexio.
The support is quite arrogant and try as hard as possible to drive away dev like
me, the application is soooooo slow (to get ONE item through the API, you wait
500ms, even from the official frontend you spend more time waiting than 
working) and it is expensive.

## Install

Via composer `composer require "artnum/bizcuit-bexio @dev"`

## Documentation

Documentation available at [https://bizcuit.ch](https://bizcuit.ch/bexio/documentation/)

## Example of usage

```php

$context = new BizCuit\BexioCTX($bexio_api_token);

$bills = new BizCuit\BexioBills($context);
$files = new BizCuit\BexioFile($context);

$billQuery = $bills->newQuery();
$billQuery->add('status', 'DRAFT');

$billInDraft = $bills->search($billQuery);
foreach($billInDraft as $bill) {
    foreach($bill->attachment_ids as $fileUUID) {
        $fileWithContent = $file->get($fileUUID);
    }
}
```

## Available Classes

Each class represents an endpoint, adding more classes is quite easy, I just 
didn't take the time and will add them as I use them.

 - BexioCountry
 - BexioQuote
 - BexioInvoice
 - BexioOrder
 - BexioContact
 - BexioProject
 - BexioContactRelation
 - BexioAdditionalAddress
 - BexioNote
 - BexioUser
 - BexioBusinessActivity
 - BexioSalutation
 - BexioTitle
 - BexioProjectType
 - BexioProjectStatus
 - BexioBills
 - BexioFile

## Create new class for endpoint

To add a read-only class, you can define it with ROObject like, for example,
BexioProjectStatus :

```php
class BexioProjectStatus extends BexioAPI {
	protected $type = 'pr_project_state';
	protected $class = 'BizCuit\BXObject\ROObject';
	protected $query = 'BizCuit\BXQuery\ROObject';

	use tBexioV2Api, tBexioObject, tBexioCollection;
}
```
In order to create a full class, you would need to define an object class and 
a query class (but you can still have a query class ROObject with an object 
class of the full type), like BexioContact (choosen because this hits a bug in
the API and, as the last phone call I had with the company : "the API is not
strategic", meaning that they don't really care about that).

```php
/* file bxobject.php */
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

/* file bxquery.php */
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

/* file bexio.php */
class BexioContact extends BexioAPI {
	protected $type = 'contact';
	protected $class = 'BizCuit\BXObject\Contact';
	protected $query = 'BizCuit\BXQuery\Contact';


	use tBexioV2Api, tBexioObject, tBexioCollection;
}
```

## Available trait for class composition

### tBexioObject

 - function new ():BXObject (create a new object of this type)
 - function delete(Int|String|BXObject $id): Bool (delete object)
 - function get (Int|String|BXObject $id, array $options = []):BXObject (get the object)
 - function set (BXObject $content):BXObject|false (save an object)
 - function update (BXObject $content):BXObject|false (update an object)

### tBexioCollection 
 
 - function getIdName ():string (get the name of the property used as id for this collection)
 - function newQuery ():BXquery (prepare a query object for this collection)
 - function search (BXQuery $query, Int $offset = 0, Int $limit = 500):array (execute a search on the collection)
 - function list (Int $offset = 0, Int $limit = 500):array (list collection)

### tBexioV2Api, tBexioV3Api and tBexioV4Api

Used only to set the right API number

### tBexioArchiveable
 - function archive (BXObject $content):bool (archive an object)
 - function unarchive (BXObject $content):bool (unarchive an object)

### tBexioNumberObject
 - function getByNumber (Int|String $id):BXObject (get an object by its number instead of id)

### tBexioPDFObject
 - 	function getPDF(Int|BXObject $id):BXObject (get a PDF of the object)

### tBexioProjectObject
 - function listByProject (Int|BXObject $projectId): Array (List by project id)
