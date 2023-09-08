# BizCuit-Bexio, bexio API en PHP

Cette librairie permet d'accéder à [l'API Bexio](https://docs.bexio.com/) en 
utilisant l'authentifcation par "token". Construire autour de cURL, elle propose
déjà pas mal de fonctionnalités.

Elle créé une abstraction autour des différentes versions de l'API (4 en 4 ans 
c'est beaucoup), de manière à ce que le comportement ait l'air toujours
identique selon les terminaions (endpoint).

La composition est utilisée massivement pour se faire, par exemple :

```php
class BexioContact extends BexioAPI {
	protected $type = 'contact';
	protected $class = 'BizCuit\BXObject\Contact';
	protected $query = 'BizCuit\BXQuery\Contact';


	use tBexioV2Api, tBexioObject, tBexioCollection;
}
```

Encore en développement, elle fournit aussi l'outillage pour éventuellement
construire un système de cache court terme (pour gérer les limitations [Rate Limiting](https://docs.bexio.com/#section/API-basics/Rate-Limiting))
et un cache long terme, en lecture seul, en cache de panne du service.

## Clause de non-responsabilité

Le fait que je développe ce projet ne signifie pas que je soutiens le produit 
Bexio. Je le fais parce que le je le dois, mais, si j'avais le choix,
je n'utiliserais pas bexio. Le support est arrogant et essaie de chasser les 
développeurs comme moi, l'application est tellement lente (pour obtenir 
UN élément via l'API, c'est 500 ms, même depuis l'interface officielle on passe
plus de temps à attendre qu'agir) et le produit est couteux.

## Un court exemple

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