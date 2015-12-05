<?php
/**
 * Created by PhpStorm.
 * User: Youi
 * Date: 2015-12-05
 * Time: 20:18
 */

require_once('vendor/autoload.php');
require_once('config.php');

function get_store() {
    $schema = (new GDS\Schema('tumblr_pack'))
        ->addString('postDomain')
        ->addString('postId')
        ->addString('postUrl')
        ->addString('postType')
        ->addString('postData', false)
        ->addString('time');
    $store = new GDS\Store($schema);

    return $store;
}

function serialize_data($data) {
    foreach ($data as &$value) {
        $value = serialize($value);
    }

    return $data;
}

function write_to_ds($data) {
    $store = get_store();
    $data = serialize_data($data);

    $store->upsert($store->createEntity($data));

    return true;
}

$data = array(
    'postDomain'=> 'abc',
    'postId' => rand(20, 100),
    'postUrl' => 'http://tumblr.com',
    'postType' => 'photo',
    'postData' => array('abc','def'),
    'time' => time(),
);

write_to_ds($data);