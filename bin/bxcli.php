<?php

use BizCuit\BexioCTX;

require('../bexio.php');
include('conf.php');
global $BXConfig;

function _dump ($object, $depth = 0) {
    $indent = str_repeat(' ', ($depth + 1) * 4);
    foreach($object as $k => $v) {
        echo $indent . "\e[1m" .  $k .  "\e[0m => ";
        if (is_array($v)) {
            echo "\n";
            _dump($v, $depth + 1);
        } elseif (is_object($v)) {
            echo "\n";
            _dump($v, $depth + 1);
        } else {
            $first = true;
            foreach(preg_split('/\n/', $v) as $line) {
                if (!$first) {
                    echo $indent . str_repeat(' ', strlen($k . ' => ')). $line . "\n";
                } else {
                    echo $line . "\n";
                }
                $first = false;
            }
        }
    }
}

function dump ($object) {
    $content = $object->toObject();
    _dump($content);
}

function get($ctx, $args) {
    $resource = 'BizCuit\\Bexio' . ucfirst(array_shift($args));
    $id = array_shift($args);
    $col = new $resource($ctx);
    dump($col->get($id));
}

function search ($ctx, $args) {
    $resource = 'BizCuit\\Bexio' . ucfirst(array_shift($args));
    $col = new $resource($ctx);
    $query = $col->newQuery();
    $query->setWithAnyFields();
    foreach ($args as $arg) {
        $arg = str_replace('"', '', $arg);
        $parts = explode(' ', $arg);
        $query->add($parts[0], $parts[2], $parts[1]);
    }
    $i = 0;
    $first = true;
    foreach ($col->search($query) as $item) {
        if (!$first) { echo "\n"; }
        echo "\e[1mITEM(" . $i++ . "\e[0m)\n";
        dump($item, 1);
        $first = false;
    }
}

function dolist ($ctx, $args) {
    $resource = 'BizCuit\\Bexio' . ucfirst(array_shift($args));
    $col = new $resource($ctx);
    $first = true;
    $limit = 500;
    $offset = 0;
    $i = 0;
    while (true) {
        $items = $col->list(['offset' => $offset, 'limit' => $limit]);
        if (empty($items)) { break; }
        foreach ($items as $item) {
            if (!$first) { echo "\n"; }
            echo "\e[1mITEM(" . $i++ . "\e[0m)\n";
            dump($item, 1);
            $first = false;
        }
        $offset += $limit;
    }
}

$BexioCTX = new BexioCTX($BXConfig['token']);

$quit = false;
while (!$quit) {
    $line = strtolower(trim(readline('bxcli> ')));
    readline_add_history($line);

    $args = preg_split('/\s+(?=([^"]*"[^"]*")*[^"]*$)/', $line);
    $command = array_shift($args);
    try {
        switch ($command) {
            case 'quit': $quit = true; break;
            case 'get': get($BexioCTX, $args); break;
            case 'search': search($BexioCTX, $args); break;
            case 'list': dolist($BexioCTX, $args); break;
        }
    } catch (Exception|Error $e) {
        echo 'ERROR: ' . $e->getMessage() . "\n";
    }

}