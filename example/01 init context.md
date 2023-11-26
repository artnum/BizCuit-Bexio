# Init context

To initialize context, you need an [authentication token](https://docs.bexio.com/#section/Authentication/API-Tokens).

Then instanciate a new BexioCTX with that token and done.

```php
require('vendor/autload.php');

use BizCuit\BexioCTX;

$ctx = new BexioCTX($token); 

```

## Owner ID and user ID

At creation or modification, some endpoint requires an `user_id` and an `owner_id`
to be set. You can pass those values with the body or set them globally in the 
context.

```php
$ctx->user_id = 1;
$ctx->owner_id = 1;
```

Doing so will set `user_id` and `owner_id` if not present in the body.

## Errors

Errors are thrown as exceptions. The exception code matches the HTTP error code
from Bexio documentation, as the message. Raw data of the body is set as a
previous exception.

```php

use BizCuit\BexioSalutation;

/* ... */

$salutation = new BizCuit\BexioSalutation($ctx);

$newSalutation = $salutation->new();
$newSalutation->non_existent_field = 'x';
try {
    $salutation->set($newSalutation);
} catch (Exception $e) {
    echo $e->getCode() . ' :: ' . $e->getMessage() . PHP_EOL; 
    $e = $e->getPrevious();
    echo $e->getMessage() . PHP_EOL;
}
```
Results with :
```
422 :: Could not save the entity
{"error_code":422,"message":"The form could not be saved due to the following errors:","errors":["name: Pflichtfeld","global: Unexpected extra form field named \"non_existent_field\"."]}
```