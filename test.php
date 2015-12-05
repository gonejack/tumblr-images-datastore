<?php
/**
 * Created by PhpStorm.
 * User: Youi
 * Date: 2015-12-05
 * Time: 20:18
 */

require_once('vendor/autoload.php');
require_once('config.php');

$tumblr_schema = (new GDS\Schema('tumblrPack'))
    ->addString('postDomain')
    ->addString('postId')
    ->addString('postUrl')
    ->addString('postType')
    ->addString('postData')
    ->addString('time');

$tumblr_store = new GDS\Store($tumblr_schema);

$record_data = array(
    'postDomain'=> 'abc',
    'postId' => rand(20, 100),
    'postUrl' => 'http://tumblr.com',
    'postType' => 'photo',
    'postData' => array('abc','def'),
    'time' => new DateTime(),
);

$tumblr_entity = $tumblr_store->createEntity($record_data);

$tumblr_store->upsert($tumblr_entity);

