<?php
header('Access-Control-Allow-Origin: *');
// content type default plin text



require 'proxy.php';
if (!isset($_GET['url'])) {
    @include('public/home.php');
    exit;
}

$proxy = new ProxyService();
$proxy->proxyRequest($_GET['url']);
?>

