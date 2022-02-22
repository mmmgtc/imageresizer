# A simple image resizer in Laravel 9

Uses Imagick / Intervention (version 3 that supports animated gifs) to take an IPFS id and resizes the image based on certain parameters, e.g.

```
/images/{ipfsid}/width=300&height=400&quality=30
```

Stores the original image on the server, applies the resizing and caches the result.
