<?php


if (isset($_GET['key'])) {
    $cacheKey = $_GET['key'];
    $cacheFile = '../cache/' . $cacheKey;
    if (file_exists($cacheFile)) {
        $json_content = file_get_contents($cacheFile);
        $content = json_decode($json_content);
    }
}

// read files in cache dir
$files = scandir('../cache');
$files = array_diff($files, array('.', '..'));


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>offline browse</title>
    <link rel="stylesheet" href="./css/offline.css">
</head>

<body id="ofbody">
    <nav id="ofnav">
        <h3>cached files</h3>
        <ul>
            <?php
            foreach ($files as $file) {
                echo "<a href='offline.php?key=$file'><li>$file</li></a>";
            }
            ?>
        </ul>
    </nav>
    <main id="ofmain">

        <div id="content">
            <?php
            if (isset($content)) {
                switch ($content->content_type) {
                    case 'text/html':
                        echo $content->content;
                        break;
                    case 'image/jpeg':
                        echo "<img src='data:image/jpeg;base64," . $content->content . "' />";
                        break;
                    case 'image/png':
                        echo "<img src='data:image/png;base64," . $content->content . "' />";
                        break;
                    case 'image/gif':
                        echo "<img src='data:image/gif;base64," . $content->content . "' />";
                        break;
                    case 'text/css':
                        echo "<pre><code>$content->content</code></pre>";
                        break;
                    case 'application/json':
                        echo "<pre><code>$content->content</code></pre>";
                        break;
                    case 'text/plain':
                        echo "<pre><code>$content->content</code></pre>";
                        break;
                    case 'application/pdf':
                        echo "<embed src='data:application/pdf;base64," . $content->content . "' />";
                        break;
                    default:
                        echo "content type not supported $content->content_type";
                        break;
                }
            }
            ?></div>
    </main>
</body>

</html>