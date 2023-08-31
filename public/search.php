<?php

$baseProxyUrl = "?".PROXY_URL_QUERY_KEY."=";

$popularSites = [
    [
        'label' => 'Wikipedia',
        'logo' => 'https%3A%2F%2Fwww.wikipedia.org%2Fportal%2Fwikipedia.org%2Fassets%2Fimg%2FWikipedia-logo-v2.png',
        'url' => 'https://www.wikipedia.org'
    ],
    [
        'label' => 'Wikipedia',
        'logo' => 'https%3A%2F%2Fwww.wikipedia.org%2Fportal%2Fwikipedia.org%2Fassets%2Fimg%2FWikipedia-logo-v2.png',
        'url' => 'https://www.wikipedia.org'
    ],
    [
        'label' => 'Wikipedia',
        'logo' => 'https%3A%2F%2Fwww.wikipedia.org%2Fportal%2Fwikipedia.org%2Fassets%2Fimg%2FWikipedia-logo-v2.png',
        'url' => 'https://www.wikipedia.org'
    ],
    [
        'label' => 'Wikipedia',
        'logo' => 'https%3A%2F%2Fwww.wikipedia.org%2Fportal%2Fwikipedia.org%2Fassets%2Fimg%2FWikipedia-logo-v2.png',
        'url' => 'https://www.wikipedia.org'
    ],
    [
        'label' => 'Wikipedia',
        'logo' => 'https%3A%2F%2Fwww.wikipedia.org%2Fportal%2Fwikipedia.org%2Fassets%2Fimg%2FWikipedia-logo-v2.png',
        'url' => 'https://www.wikipedia.org'
    ],
    [
        'label' => 'Wikipedia',
        'logo' => 'https%3A%2F%2Fwww.wikipedia.org%2Fportal%2Fwikipedia.org%2Fassets%2Fimg%2FWikipedia-logo-v2.png',
        'url' => 'https://www.wikipedia.org'
    ]
];


if(!PROXY_ENABLED){
    echo "<span style='color:red'>Proxy service is disabled.</span>";
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Search Page - Proxy Service</title>
    <link rel="stylesheet" href="public/css/style.css" />
</head>

<body>
    <div class="container">
        <header>
            <nav>
                <ul>
                    <li><a href="?about">About</a></li>
                    <!-- Add more navigation links here -->
                    <li><a href="https://github.com/bethropolis/tinyproxy" target="_blank">GitHub</a></li>
                </ul>
            </nav>
        </header>
        <div class="search">
            <form action="<?= $_SERVER['PHP_SELF'] ?>" method="get">
                <input type="text" name="<?= PROXY_URL_QUERY_KEY?>" id="search" placeholder="Search for URLs..." />
                <button type="submit">Search</button>
            </form>
        </div>

        <!-- Autocomplete results -->
        <div class="autocomplete-results" id="autocomplete-results"></div>
        <div class="popular-sites">
            <?php foreach ($popularSites as $site) : ?>
                <a href="<?= $baseProxyUrl . urlencode($site['url']) ?>" class="card">
                    <img src="<?= $site['logo'] ?>" alt="<?= $site['label'] ?> Logo" />
                    <h2><?= $site['label'] ?></h2>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        // autocomplete.js
        const searchInput = document.getElementById("search");
        const autocompleteResults = document.getElementById("autocomplete-results");

        searchInput.addEventListener("input", (event) => {
            const searchText = event.target.value;
            autocompleteResults.innerHTML = "";

            if (searchText.length > 2) {
                const tlds = ["com", "org", "net", "io"]; // List of TLDs
                const autocompleteItems = tlds.map((tld) => `${searchText}.${tld}`);
                displayAutocompleteItems(autocompleteItems);
            }
        });

        autocompleteResults.addEventListener("click", (event) => {
            if (event.target.classList.contains("autocomplete-item")) {
                const selectedItem = event.target.textContent;
                const correctedUrl = selectedItem.includes("://") ?
                    selectedItem :
                    `https://${selectedItem}`;
                redirectToProxy(correctedUrl);
            }
        });

        function displayAutocompleteItems(items) {
            const fragment = document.createDocumentFragment();
            items.forEach((item) => {
                const autocompleteItem = document.createElement("div");
                autocompleteItem.classList.add("autocomplete-item");
                autocompleteItem.textContent = item;
                fragment.appendChild(autocompleteItem);
            });
            autocompleteResults.appendChild(fragment);
        }

        function redirectToProxy(url) {
            window.location.href = `?<?= PROXY_URL_QUERY_KEY?>=${encodeURIComponent(url)}`;
        }
    </script>
</body>

</html>