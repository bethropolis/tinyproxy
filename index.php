<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

require __DIR__ ."/config.php";
require  __DIR__."/proxy.php";


if (isset($_GET["about"])) {
    @include('public/home.php');
    exit;
}

if (isset($_GET[PROXY_URL_QUERY_KEY]) && PROXY_ENABLED) {
    $proxy = new ProxyService();
    $targetUrl = urldecode($_GET[PROXY_URL_QUERY_KEY]);
    $proxy->proxyRequest($targetUrl);
    exit;
}

@include('public/search.php');
exit;
