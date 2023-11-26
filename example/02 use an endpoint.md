# Using endpoints

Each endpoint are defined as a class with a name like `BizCuit\Bexio[...]`.

They have extends from their own `BizCuit\BXObject\` and `BizCuit\BXQuery\`. If
they extend `BizCuit\BXObject\ROObject` and `BizCuit\BXQuery\ROObject`, it means
that using them might work but may not. Bexio API has bugs and quirks so in
order to get it right, some testing is needed. Extending `ROObject` means no
test done.

## Accessing fields

Each endpoint has its fields accessing directly with `$object->field_name` where
`field_name` matches the name given in Bexio documentation.

The documentation might be buggy, for example, the country endpoint don't have
the right field name, so this library will try to map the name correctly, as
you can see in BexioCountry :

```php
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
```

## Listing

Listing is straightforward, you just call `->list()`, with offset and limit as
parameters. Each endpoint has its own limitation (I have seen 500 and 1000 most
of the time).

```php
/* list all entry in an endpoint */

use BizCuit\BexioInvoice;

/* ... */

$invoice = new BizCuit\BexioInvoice($ctx);
$offset = 0;
do {
    $results = $invoice->list($offset, 200);
    foreach($results as $result) {
        echo $result->id . PHP_EOL;
    }
    $offset += 200;
} while(!empty($results));

```

## Searching

To search you need need to create a BXQuery object first with `->newQuery()` and
then add paramters to it. Then call `->search($query)`. A second parameter is 
an array with ordering, offet and limit.

```php
$invoiceQuery = $invoice->newQuery();
$invoiceQuery->add('kb_item_status_id', 8);

$pendings = $invoice->search($invoiceQuery, ['order' => 'total_gross']);
foreach($pendings as $pending) {
    var_dump($pending);
}
```

The syntax of order follows the documentation

## Creating and updating

Both create and update use the function `->set()`. If the body contains the id, 
the function does an overwrite else it create a new object.

There is an `->update()` that is designed to modify only some attributes but, as
of now, the Bexio backend don't seems to work as documented so don't use it.

You need to create an object with `->new()`

```php

$newInvoice = $invoice->new();
$newInvoice->contact_id = 1;
$newInvoice->title = 'My invoice';

$savedInvoice = $invoice->set($newInvoice);
```

In theory, you could modify `$savedInvoice` and send it back to the server but
this doesn't always work as the server respond with an object filled with fields
that are not allowed. Also on update, some fields that were optional on create
become required on update. It's a guessing game.

## Deleting

Deleting is simply to call `->delete()` with the id. To get the id, just use `->getId()` on the object.

Some endpoint don't allow delete but they have `->archive()`.