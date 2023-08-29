<?php
header('Access-Control-Allow-Origin: *');

require 'proxy.php';


if (isset($_GET["search"])) {
    @include('public/search.php');
    exit;
}

if (isset($_GET['url'])) {
    $proxy = new ProxyService();
    $targetUrl = urldecode($_GET['url']);
    $proxy->proxyRequest($targetUrl);
    exit;
}



@include('public/home.php');
exit;
