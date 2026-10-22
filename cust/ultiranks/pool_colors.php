<?php

require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

/*
 * Ultiranks brand palette for pool colors, ordered so the first pools of a
 * division get navy, teal and gray-blue (the brand data-series order), followed
 * by tints and shades of the same hues for divisions with more pools.
 */
return [
    '00466F', // navy
    '4CAECF', // teal
    '849DAA', // gray-blue
    '2B6D94',
    '9AD3E6',
    '3E5560',
    '6C8EA0',
    '0B2E44',
];
