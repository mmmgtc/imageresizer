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
alias sail='[ -f sail ] && bash sail || bash vendor/bin/sail'
./vendor/bin/sail up
```

http://localhost:8080

### To clear some of your docker containers, images, volumes or networks

To clear containers:

```
docker rm -f $(docker ps -a -q)
```

To clear images:

```
docker rmi -f $(docker images -a -q)
```

To clear volumes:

```
docker volume rm $(docker volume ls -q)
```

To clear networks:

```
docker network rm $(docker network ls | tail -n+2 | awk '{if($2 !~ /bridge|none|host/){ print $1 }}')
```

## CDN

It's recommended to run a CDN such as Cloudfront infront of this service to increase both delivery times and reduce load on the server where you are running it.

## Bugsnag

To include Bugsnag reporting, ensure the following variable is set in your .env file

```
BUGSNAG_API_KEY=
```
