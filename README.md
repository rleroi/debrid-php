# Debrid Library

A PHP library for interacting with multiple debrid services to download and stream torrent content. This library provides a unified interface for different debrid providers, making it easy to switch between services or support multiple providers in your application.

## 🚀 Features

- **Multiple Debrid Services**: Support for Real-Debrid, AllDebrid, Premiumize, TorBox, and DebridLink
- **Unified API**: Same methods work across different debrid providers
- **Cache Checking**: Check if torrents are already cached before downloading
- **File Selection**: Get specific files from torrents
- **Direct Download Links**: Get unrestricted download links for streaming
- **Error Handling**: Comprehensive error handling with custom exceptions

## 📦 Installation

```bash
composer require rleroi/debrid
```

## 🔧 Supported Services

| Service | Status | API Documentation |
|---------|--------|-------------------|
| **Real-Debrid** | ✅ Supported | [API Docs](https://api.real-debrid.com/) |
| **AllDebrid** | ✅ Supported | [API Docs](https://docs.alldebrid.com/) |
| **Premiumize** | ✅ Supported | [API Docs](https://app.swaggerhub.com/apis-docs/premiumize.me/api/1.7.2) |
| **TorBox** | ✅ Supported | [API Docs](https://www.postman.com/wamy-dev/torbox/folder/zg0dc69/torrents) |
| **DebridLink** | ✅ Supported | [API Docs](https://debrid-link.com/api_doc/v2/introduction) |

## 🎯 Quick Start

### Basic Usage

```php
use RLeroi\Debrid\Client;

// Initialize with your preferred debrid service
$client = (new Client())
    ->setClientRealDebrid('your-real-debrid-token');

// Or use TorBox
$client = (new Client())
    ->setClientTorBox('your-torbox-token');

// Or use AllDebrid
$client = (new Client())
    ->setClientAllDebrid('your-alldebrid-token');

// Or use Premiumize
$client = (new Client())
    ->setClientPremiumize('your-premiumize-token');

// Or use DebridLink
$client = (new Client())
    ->setClientDebridLink('your-debridlink-token');
```

### Check Cached Files

```php
$magnet = 'magnet:?xt=urn:btih:34FF1FAE9661D72152FB1FC31E27C15297072654&dn=example+torrent';

// Get all cached files from a torrent
$files = $client->getCachedFiles($magnet);

// Returns array of file paths
// [
//     '/path/to/file1.mp4',
//     '/path/to/file2.mp4',
//     '/path/to/subtitle.srt'
// ]
```

### Check Specific File

```php
// Check if a specific file is cached
$isCached = $client->isFileCached($magnet, '/path/to/file1.mp4');
// true or false
```

### Get Download Link

```php
// Get direct download link for a specific file
$downloadLink = $client->getLink($magnet, '/path/to/file1.mp4');
// Returns: https://example.com/direct-download-link.mp4
```

### Add New Torrent

```php
// Add a new magnet link to your debrid service
$torrentId = $client->addMagnet($magnet);
// Returns torrent ID for tracking
```

## 🔄 Switching Between Services

You can easily switch between different debrid services:

```php
$client = new Client();

// Use Real-Debrid
$client->setClientRealDebrid('real-debrid-token');
$files = $client->getCachedFiles($magnet);

// Switch to TorBox
$client->setClientTorBox('torbox-token');
$files = $client->getCachedFiles($magnet);

// Switch to AllDebrid
$client->setClientAllDebrid('alldebrid-token');
$files = $client->getCachedFiles($magnet);
```

## ⚠️ Important Notes

### Real-Debrid Exception
**Only Real-Debrid automatically adds magnets** in `getCachedFiles()` and `getLink()` methods **when the torrent is not yet cached**. This is because Real-Debrid removed their cache checking endpoint.

**For all other providers**, you must manually add magnets first if they're not cached:

```php
// Check if cached first
$files = $client->getCachedFiles($magnet);
if (empty($files)) {
    // Add magnet manually
    $torrentId = $client->addMagnet($magnet);
    // Wait for completion, then check again
    $files = $client->getCachedFiles($magnet);
}
```

**Note**: If the torrent is already cached, all providers work the same way - they simply return the cached files or download links.

## 🐛 Error Handling

The library uses custom exceptions for error handling:

```php
use RLeroi\Debrid\Exceptions\DebridException;

try {
    $files = $client->getCachedFiles($magnet);
} catch (DebridException $e) {
    echo "Debrid error: " . $e->getMessage();
} catch (InvalidArgumentException $e) {
    echo "Invalid argument: " . $e->getMessage();
}
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.
