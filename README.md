## Example usage

See the docs for more information.

```php
$client = (new \RLeroi\Debrid\Client())
    ->setClientTorBox()
    ->setToken('my-api-token');

$client->getCachedFiles('some-torrent-infohash');
/*
[
    'some-torrent-infohash' => [
        ['name' => 'cached-filename.txt', 'size' => 1234567],
        ['name' => 'another-cached-file.txt', 'size' => 7654321],
    ],
]
*/

$client->isFileCached('some-torrent-infohash', 'cached-filename.txt');
// true
```
