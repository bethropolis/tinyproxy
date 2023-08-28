<?php


$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

$server_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Proxy Service</title>
  <style>
    /* Use a serif font for the body and headings */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Georgia, serif;
      line-height: 1.5;
      height: 100vh;
    }

    /* Use Arial font for the headings */
    h1,
    h2,
    h3,
    h4 {
      font-family: Arial, sans-serif;
    }

    /* Use a light blue color for the links and the header background */
    a {
      color: #00a0d2;
    }

    header {
      background-color: #00a0d2;
    }

    /* Center the container and add some padding and margin */
    .container {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }

    .content {
      text-align: center;
      max-width: 600px;
      margin: 20px;
    }

    .content ol {
      text-align: left;
    }

    /* Add some spacing between the elements */
    h1,
    p,
    ol,
    li {
      margin-bottom: 10px;
    }

    /* Add a fade-in animation */
    .fade-in {
      opacity: 0;
      animation-timing-function: ease-in-out;
    }

    @keyframes fadeInAnimation {
      to {
        opacity: 1;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="content">
      <h1>Welcome to the Proxy Service</h1>
      <p>Use this proxy service to fetch and cache resources from the web.</p>
      <h2>How to Use:</h2>
      <ol>
        <li>
          Visit <code><?= $server_url ?>?url=[target_url]</code>
        </li>
        <li>
          Replace <code>[target_url]</code> with the URL you want to fetch and
          cache.
        </li>
        <li>
          The proxy will serve the content or fetch and cache it for future
          use.
        </li>
      </ol>
      <p>
        For example:
        <a href="<?= $server_url ?>?url=https://www.example.com"><?= $server_url ?>?url=https://www.example.com</a>
      </p>
      <p>
        Find the source code on
        <a href="https://github.com/yourusername/your-repo">GitHub</a>.
      </p>
    </div>
  </div>
</body>

</html>