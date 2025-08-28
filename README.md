# Debrid Library

A PHP library for interacting with debrid services (Real-Debrid, AllDebrid, Premiumize, TorBox, DebridLink) to download and stream torrent content.

## Installation

```bash
composer require rleroi/debrid
```

## Quick Start

```php
use RLeroi\Debrid\Client;

function getLink(Client $client, string $magnet): ?string
{
  // 1. add magnet
  $client->addMagnet($magnet);

  // 2. get all files
  $files = $client->getCachedFiles($magnet);

  if (!count($files)) {
    return null;
  }

  // 3. get stream/download link for a specific file
  return $client->getLink($magnet, $files[0]->path);
}

$client = (new Client())
    ->setClientPremiumize('your-token');
$magnet = 'magnet:?xt=urn:btih:34FF1FAE9661D72152FB1FC31E27C15297072654&dn=example+torrent';

getLink($client, $magnet);
```

## Supported Services

```php
// Real-Debrid -- Note: Real-Debrid requires addMagnet() before calling getCachedFiles()
$client->setClientRealDebrid('token');

// AllDebrid  
$client->setClientAllDebrid('token');

// Premiumize
$client->setClientPremiumize('token');

// TorBox (TODO)
// $client->setClientTorBox('token');

// DebridLink (TODO)
//$client->setClientDebridLink('token');
```
