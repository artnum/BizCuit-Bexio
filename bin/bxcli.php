<?php

use BizCuit\BexioCTX;

require('../bexio.php');
include('conf.php');
global $BXConfig;

if (php_sapi_name() !== 'cli') { exit; }



function _dump ($object, $id, $depth = 0) {
    ksort($object);

    $keyMaxLen = 0;
    foreach($object as $k => $v) {
        $keyMaxLen = max($keyMaxLen, strlen($k));
    }

    $indent = str_repeat(' ', ($depth + 1) * 4);
    if ($depth === 0) { echo "\e[1m\e[7m--- Item ID $id ---\e[0m\n"; }
    foreach($object as $k => $v) {
        echo $indent . "\e[1m$k\e[0m" . str_repeat(' ', $keyMaxLen - strlen($k)) . ": ";
        if (is_array($v)) {
            echo "\n";
            _dump($v, $id, $depth + 1);
        } elseif (is_object($v)) {
            echo "\n";
            _dump(get_object_vars($v), $id, $depth + 1);
        } else {
            $first = true;
            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            if (is_string($v) && strlen($v) > 80 - ((($depth + 1) * 4) + $keyMaxLen + 2)) {
                $v = wordwrap($v, 80 - ((($depth + 1) * 4) + $keyMaxLen + 2) , "\n", true);
            }
            foreach(preg_split('/\n/', $v) as $line) {
                if (!$first) {
                    echo $indent . str_repeat(' ', $keyMaxLen + 2). "$line\n";
                } else {
                    echo "$line\n";
                }
                $first = false;
            }
        }
    }
}

function dump ($object) {
    $content = get_object_vars($object->toObject());
    _dump($content, $object->getId());
}

function getResource ($BexioCTX, $resource) {
    $resource = implode('', array_map(fn($e) => ucfirst($e), explode('_', $resource)));
    $resource = 'BizCuit\\Bexio' . $resource;
    return new $resource($BexioCTX);
}

function get($ctx, $args) {
    $col = getResource($ctx, array_shift($args));
    $id = array_shift($args);
    dump($col->get($id));
}

function search ($ctx, $args) {
    $col = getResource($ctx, array_shift($args));
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
        dump($item, 1);
        $first = false;
    }
}

function docreate ($ctx, $args) {
    $col = getResource($ctx, array_shift($args));
    $object = $col->new();
    foreach ($object->toObject() as $k => $v) {
        if ($object::ID === $k) { continue; }
        $value = trim(readline("    $k: "));
        if (empty($value)) { continue; }
        $object->{$k} = $value;
    }
    $new = $col->set($object);
    echo "Created new item with ID: " . $new->getId() . "\n";
}


function dolist ($ctx, $args) {
    $col = getResource($ctx, array_shift($args));
    $first = true;
    $limit = 500;
    $offset = 0;
    $i = 0;
    while (true) {
        $items = $col->list(['offset' => $offset, 'limit' => $limit]);
        if (empty($items)) { break; }
        foreach ($items as $item) {
            if (!$first) { echo "\n"; }
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
            case 'exit':
            case 'quit': $quit = true; break;
            case 'get': get($BexioCTX, $args); break;
            case 'search': search($BexioCTX, $args); break;
            case 'list': dolist($BexioCTX, $args); break;
            case 'create': docreate($BexioCTX, $args); break;
            default: echo "ERROR: Unknown command: $command\n";
        }
    } catch (Exception|Error $e) {
        
        printf(
            "ERROR: %s (%s:%d)\n",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
    }

}