# BizCuit-Bexio

Permet d'accéder à [la nouvelle API de Bexio simplement](https://docs.bexio.com/#section/New-API-Portal). Nécessite juste de configurer le [Token d'authentification](https://docs.bexio.com/#section/Authentication/API-Tokens).

Utilise cURL et nécessite PHP >= 8.0 (utilisation de la [déclaration de type](https://www.php.net/manual/en/language.types.declarations.php).

Le fonctionnement se veut assez simple et direct.


```php

$bxAPI = new BizCuit\BexioAPI('... [secret token] ...');

$note = $bxAPI->initNote(); // retourn un tableau avec tous les attributs
$note['subject'] = 'test';
$noteid = $bxAPI->createNote($note);

print_r($bxAPI->getNote($noteid));

```

