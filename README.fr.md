# BizCuit-Bexio

Permet d'accéder à [la nouvelle API de Bexio simplement](https://docs.bexio.com/#section/New-API-Portal). Nécessite juste de configurer le [Token d'authentification](https://docs.bexio.com/#section/Authentication/API-Tokens).

Utilise cURL et nécessite PHP >= 8.0 (utilisation de la [déclaration de type](https://www.php.net/manual/en/language.types.declarations.php).

Le fonctionnement se veut assez simple et direct.


```php
use BizCuit\BexioContact;
use BizCuit\BexioCountry;
use BizCuit\BXQuery;

$bx = new BizCuit\BexioCTX('... [secret token] ...');

$country = new BexioCountry($bx);
$contact = new BexioContact($bx);

$query = new BXQuery\Country();
$query->add('name_short', 'CH', 'like');
$results = $country->search($query);

$offset = 0;
$limit = 500;
do {
    $result = $contact->list($offset, $limit);
    $qty = count($result);
    $offset += $limit;
    foreach ($result as $c) {
        echo 'EDIT CONTACT ' . $c->getId() . ' - ' .  $c->name_1 . "\n";
        $c->country_id = $results[0]->id;
        $contact->update($c)->toJson();
        echo '    DONE' . "\n";
    }
} while($qty === $limit);

```

