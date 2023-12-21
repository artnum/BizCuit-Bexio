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

$BexioCTX = new BexioCTX($BXConfig['token']);

$quit = false;
while (!$quit) {
    $line = strtolower(trim(readline('bxcli> ')));
    readline_add_history($line);
    $args = explode(' ', $line);
    $command = array_shift($args);
    try {
        switch ($command) {
            case 'quit': $quit = true; break;
            case 'get': get($BexioCTX, $args); break;
        }
    } catch (Exception|Error $e) {
        echo 'ERROR: ' . $e->getMessage() . "\n";
    }

}