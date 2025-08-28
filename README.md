# Debrid Library

A PHP library for interacting with debrid services (Real-Debrid, AllDebrid, Premiumize, TorBox, DebridLink) to download and stream torrent content.

## Installation

```bash
composer require rleroi/debrid
```

## Quick Start

```php
use RLeroi\Debrid\Client;

// Choose your debrid service
$client = (new Client())
    ->setClientPremiumize('your-token');

// Add new torrent if not cached
$torrentId = $client->addMagnet($magnet);

// Check what files are available
$magnet = 'magnet:?xt=urn:btih:34FF1FAE9661D72152FB1FC31E27C15297072654&dn=example+torrent';
$files = $client->getCachedFiles($magnet);

// Get download link for a specific file
$downloadLink = $client->getLink($magnet, $files[0]->path);
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
