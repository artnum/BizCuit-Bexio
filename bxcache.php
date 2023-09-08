<?php

/**
 * @author Etienne Bagnoud <etienne@artnum.ch>
 * @license MIT
 * @copyright 2023 Etienne Bagnoud
 * @todo Still in heavy developpment, not ready for usage as is.
 * 
 *  Manage caching of external bexio data into two caches :
 *  - Short time cache to avoid hiting ratelimit (put,get,delete)
 *  - Long time cache in case bexio is down (store,load,remove)
 */
class BexioCache {
    protected $cache; // short time cache
    protected $path; // long time cache
    protected $duration = 30;
    
    function __construct (Memcached $memcache, string $path, Int $duration = 30) {
        $this->cache = $memcache;
        $this->path = realpath($path);
        $this->duration = $duration;

        if (!is_writable($this->path)) {
            throw new Exception ('Cache is not writable');
        }
    }

    function content_hash (string $content):string {
        return hash('xxh3', $content);
    }

    function ref_hash (string $reference):string {
        return hash('xxh3', $reference) . '.' . hash('crc32c', $reference);
    }

    function ref_to_path (string $reference):string {
        $base = $this->ref_hash($reference);
        $dir = $this->path . '/' . substr($base, 0, 2) . '/' . substr($base, 2, 2) . '/' . $base . '/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        return $dir;
    }

    function cmp_content (string $reference, string $content) {
        $dirname = $this->ref_to_path($reference);
        if (!is_file($dirname . '/hash')) { return false; }

        $hash = $this->content_hash($content);
        $storedHash = file_get_contents($dirname . '/hash');

        if ($storedHash === $hash) { return true; }

        return false;
    }

    /**
     * When set into collection, this will be for iterating only so performance
     * penalty is not a concern when reading back. This is to be used in some 
     * specific emergency case where slowness is not a problem.
     */
    function set_to_collection (string $reference, string $path):bool {
        $parts = explode('/', $reference);
        $collection = array_shift($parts);
        $item = array_shift($parts);
        if (str_starts_with($item, '#')) { return true; } // query or listing, don't structure into collection/item
        if (!is_dir($this->path . '/' . $collection)) {
            mkdir($this->path . '/' . $collection);
        }
        $file = basename($path);
        if (!is_link($this->path . '/' . $collection . '/' . $file)) {
         return symlink($path, $this->path . '/' . $collection . '/' . $file);
        }
        return true;
    }

    function get_age (string $reference):int {
        $dirname = $this->ref_to_path($reference);
        if (!is_file($dirname . '/hash')) { return -1; }
        $mtime = filemtime($dirname . '/hash');
        if ($mtime === false) { return -1; }
        return time() - $mtime;
    }

    function iterate_collection (string $collection):Generator {
        $dh = opendir($this->path . '/' . $collection);
        if (!$dh) { return; }
        while(($file = readdir($dh)) !== false) {
            if (is_file($this->path . '/' . $collection . '/' . $file . '/deleted')) { continue; }
            if (!is_file($this->path . '/' . $collection . '/' . $file . '/content')) { continue; }
            yield file_get_contents($this->path . '/' . $collection . '/' . $file . '/content');
        }
        closedir($dh);
    }

    function store (string $reference, string $content):bool {
        $dirname = $this->ref_to_path($reference);
        $this->set_to_collection($reference, $dirname);
        if (file_put_contents($dirname . '/content', $content)) {
            return file_put_contents($dirname . '/hash', $this->content_hash($content)) !== false;   
        }
        return false;
    }

    function load (string $reference):string|false {
        $dirname = $this->ref_to_path($reference);
        return file_get_contents($dirname . '/content');
    }

    function remove (string $reference):bool {
        $dirname = $this->ref_to_path($reference);
        return file_put_contents($dirname . '/deleted', strval(time())) !== false;
    }

    function put (string $reference, string $content):bool {
        $reference = $this->ref_hash($reference);
        return $this->cache->set($reference, $content, $this->duration);
    }

    function get (string $reference):string|false {
        $reference = $this->ref_hash($reference);
        return $this->cache->get($reference);
    }

    function delete (string $reference):bool {
        $reference = $this->ref_hash($reference);
        return $this->cache->delete($reference);
    }
} 

/* Trait to compose a class where you can search in cache like it would be the
 * actual Bexio API.
 */
trait BexioJSONCache {
    protected $bxcache;

    function read_cache (string $reference):Array {
        if ($this->bxcache->get_age($reference) <= -1) { return [0, false]; }
        $content = $this->bxcache->load($reference);
        if ($content === false) { return [0, false]; }
        [$count, $content] = explode("\n", $content, 2);
        return [intval($count), $content];
    }

    function search_cache ($collection, $reference):Generator {
        if ($this->bxcache->get_age($reference) <= -1) { return; }
        $content = $this->bxcache->load($reference);
        if ($content === false) { return; }
        [$count, $content] = explode("\n", $content, 2);
        $content = json_decode($content);
        foreach($content as $id) {
            $object = $this->read_cache($collection . '/' . $id);
            if ($object[0] <= 0) { continue; }
            if ($object[0] > 1) {
                $items = json_decode($object[1]);
                foreach($items as $item) { yield json_encode($item); }
                continue;
            }
            yield $object[1];
        }
    }

    function _cmp_value ($op, $v1, $v2 = '') {
        $strv1 = strval($v1);
        $strv2 = strval($v2);
        switch ($op) {
            case '=':
            case 'equal':
                return strcasecmp($strv1, $strv2) === 0;
            case '!=':
            case 'not_equal':
                return strcasecmp($strv1, $strv2) !== 0;
            case '>':
            case 'greater_than':
                return strcasecmp($strv1, $strv2) === 1;
            case '<':
            case 'less_than':
                return strcasecmp($strv1, $strv2) === -1;
            case '>=':
            case 'greater_equal':
                $x = strcasecmp($strv1, $strv2);
                return $x >= 0;
            case '<=':
            case 'less_equal':
                $x = strcasecmp($strv1, $strv2);
                return $x <= 0;
            case 'like':
                return stristr($strv1, $strv2) !== false;
            case 'not_like':
                return stristr($strv1, $strv2) === false;
            case 'is_null':
                return is_null($v1);
            case 'not_null':
                return !is_null($v1);
            case 'in':
                $v2 = json_decode($v2);
                foreach ($v2 as $item) {
                    if ($this->_cmp_value($strv1, strval($item))) {
                        return true;
                    }
                }
                return false;
            case 'not_in':
                $v2 = json_decode($v2);
                foreach ($v2 as $item) {
                    if ($this->_cmp_value($strv1, strval($item))) {
                        return false;
                    }
                }
                return true;

        }
    }

    /**
     * Search cache a bit like it would be done on real server
     */
    function query_cache (string $collection, array $query, bool $use_or = false):Generator {
        foreach($this->bxcache->iterate_collection($collection) as $object) {
            [$count, $content] = explode("\n", $object);
            if ($count <= 0) { continue; }
            if ($count > 1) {
                $items = json_decode($content);
                foreach($items as $item) { 
                    $filter = $use_or;
                    foreach ($query as $q) {
                        if ($use_or) {
                            if ($this->_cmp_value($q['criteria'], $item->{$q['field']}, $q['value'])) {
                                $filter = false;
                                break;
                            }         
                            continue;
                        }
                        if (!$this->_cmp_value($q['criteria'], $item->{$q['field']}, $q['value'])) {
                            $filter = true;
                            break;
                        }
                    }
                    if ($filter) { continue; }
                    yield json_encode($item); 
                }
                continue;
            }
            $object = json_decode($content);
            $filter = $use_or;
            foreach ($query as $q) {
                if ($use_or) {
                    if ($this->_cmp_value($q['criteria'], $object->{$q['field']}, $q['value'])) {
                        $filter = false;
                        break;
                    }
                    continue;
                }
                if (!$this->_cmp_value($q['criteria'], $object->{$q['field']}, $q['value'])) {
                    $filter = true;
                    break;
                }
            }
            if ($filter) { continue; }
            yield $content;
        }
    }

    function store_cache (string $reference, string $content, int $items) {
        $content = $items . "\n" . $content;
        if (!$this->bxcache->cmp_content($reference, $content)) {
            $this->bxcache->store($reference, $content);
        }
    }
}