# TinyProxy

TinyProxy is a fast, secure, and modern PHP proxy service with advanced caching, SSRF protection, rate limiting, and a sleek web UI.

## Features

- **High-performance caching:** File-based caching with automatic GZIP compression.
- **Enterprise Security:** Built-in SSRF prevention, private IP blocking, and request rate limiting.
- **Smart Modifications:** Automatic URL rewriting, ad blocking, and asset transformations on the fly.
- **Admin Dashboard:** Built-in statistics, metrics, and cache management interface.
- **Docker Ready:** Easy deployment using Docker and Docker Compose.

## Installation

### Using Docker (Recommended)

1. Clone this repository:
```sh
git clone https://github.com/bethropolis/tinyproxy.git
cd tinyproxy
```

2. Start the service using Docker Compose:
```sh
docker-compose up -d --build
```
Your proxy will be available at `http://localhost:8080`.

### Manual Installation (PHP 8.3+)

1. Clone this repository:
```sh
git clone https://github.com/bethropolis/tinyproxy.git
cd tinyproxy
```

2. Install dependencies using Composer:
```sh
composer install
```

3. Run the TinyProxy development server:
```sh
php -S localhost:8080 -t public
```

> For production, it's recommended to point an Apache or Nginx virtual host directly to the `public/` directory.

## Usage

To use TinyProxy, simply visit the homepage or make requests to the proxy URL with the `url` parameter:

```
http://localhost:8080/?url=https://www.example.com
```

Via cURL:
```bash
$ curl "http://localhost:8080/?url=https://www.example.com"
```

### Admin Dashboard

You can monitor proxy performance, view statistics, and clear the cache by visiting the admin dashboard:
```
http://localhost:8080/admin
```

### Configuration

TinyProxy uses a dot-notation configuration system. You can easily customize the application by creating a `.env` file in the project root:

```env
APP_ENV=production
APP_DEBUG=false

# Cache settings
CACHE_ENABLED=true
CACHE_DEFAULT_TTL=3600

# Security
SECURITY_BLOCK_PRIVATE_IPS=true
SECURITY_RATE_LIMIT_ENABLED=true
```

## Contributing

Contributions are welcome! If you find a bug or have an enhancement idea, please open an issue or submit a pull request. Ensure tests pass by running `vendor/bin/phpunit` before submitting.

## License

TinyProxy is released under the MIT License. See [LICENSE](LICENSE) for details.

Happy proxying!
