# TinyProxy

tinyproxy is a lightweight PHP proxy service with caching for fetching and serving web resources.

screenshots:
![Screenshot 2023-09-03 00 00 25](https://github.com/bethropolis/tinyproxy/assets/66518866/7acd4764-25a7-407e-967e-d193d8165672)

![Screenshot 2023-09-03 00 06 26](https://github.com/bethropolis/tinyproxy/assets/66518866/1fef1996-e96e-41f1-95dd-7f7eae89ce1c)


## Installation

1. Clone this repository to your web server:

```sh
git clone https://github.com/bethropolis/tinyproxy.git

cd tinyproxy
```

2. Install the required dependencies using Composer:

```
composer install
```
3. Run the TinyProxy service:
```
php -S localhost:8080
```
> I recommend using an `apache` server or `nginx` server.

using docker:
```
docker build -t tinyproxy .
docker run -p 8080:80 --name tinyproxy-container tinyproxy
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


> for more customization edit the `config.php` file.


## Contributing

Contributions are welcome! If you find a bug or have an enhancement idea, please open an issue or submit a pull request.

## License

TinyProxy is released under the MIT License. See [LICENSE](LICENSE) for details.


Happy proxying! 💜
