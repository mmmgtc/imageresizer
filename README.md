# An image resizer in Laravel 9

Uses Imagick / Intervention (version 3 that supports animated gifs) to take the URL of an image and resizes the image based on certain parameters, e.g.

```
/images?width=300&height=400&url=https://ipfs.io/ipfs/bafkreic4pd3wdjrf3quan6gympn3scwuavcu3w5n7uzgpdf56ncscymx4i
```

To maintain the image aspect ratio, only pass in either width or height. If both width and height are passed in, the image is cropped to those dimensions.

Caches the original image on the server, applies the resizing and caches the result.

## To start up development

```
composer install
make up
```

http://localhost:8080

## CDN

It's recommended to run a CDN such as Cloudfront infront of this service to increase both delivery times and reduce load on the server where you are running it.

## Bugsnag

To include Bugsnag reporting, ensure the following variable is set in your .env file

```
BUGSNAG_API_KEY=
```
