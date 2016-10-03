<?php

/** 
 * Auth via uLogin.ru
 * @package SMF
 * @subpackage uLogin Package
 * @author uLogin team@ulogin.ru https://ulogin.ru/
 * @license GPL3 
 */

$smcFunc['db_query']('', 'CREATE TABLE IF NOT EXISTS {db_prefix}ulogin (
  `id` int(10) unsigned NOT NULL auto_increment,
  `userid` int(10) NOT NULL,
  `identity` text,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM', array()
);

?>
