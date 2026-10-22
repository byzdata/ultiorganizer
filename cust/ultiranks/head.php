<?php

require_once __DIR__ . '/../include_only.guard.php';
denyDirectCustomizationAccess(__FILE__);

function logo()
{
    global $include_prefix;
    return "<div><a href='" . $include_prefix . "index.php'><img class='logo' src='" . $include_prefix . "cust/ultiranks/logo-big.png' alt='Ultiranks'/></a></div>";
}

function pageHeader()
{
    global $include_prefix;

    $html = "<a href='" . $include_prefix . "index.php' class='header_text'><img class='header_logo' src='" . $include_prefix . "cust/ultiranks/logo.png' alt=''/><span class='header_wordmark'>Ultiranks</span></a>";
    $html .= "<span class='menu_button_row'><img class='menu_button' onclick='menu_toggle()' src='" . $include_prefix . "images/menu-bars.svg' alt='menu'></span><br/>\n";

    return $html;
}
