# TinyProxy

tinyproxy is a lightweight PHP proxy service with caching for fetching and serving web resources.

## Installation

1. Clone this repository to your web server:

```
git clone https://github.com/bethropolis/tinyproxy.git
```

2. Install the required dependencies using Composer:

```
composer install
```
3. Run the TinyProxy service:
```
php -S localhost:8080
```

## Usage

To use TinyProxy, simply make requests to the proxy URL with the `url` parameter:

```
http://localhost:8080/?url=https://www.example.com
```
curl:
```bash
$ curl http://localhost:8080/?url=https://www.example.com
```
> replace `localhost:8080` with  your web server's address.

#### parameters
  | parameter | description   | type  |
  | --------- | ------------- |-------|
  | `url` | The URL to proxy. |string |
  | `cache` | The cache directory. |bool|

## Contributing

Contributions are welcome! If you find a bug or have an enhancement idea, please open an issue or submit a pull request.

## License

TinyProxy is released under the MIT License. See [LICENSE](LICENSE) for details.


Happy proxying! 💜
