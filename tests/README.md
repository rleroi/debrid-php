# Test Suite Documentation

This directory contains comprehensive tests for the Debrid library.

## Test Structure

### Feature Tests (`tests/Feature/`)
- **`ClientTest.php`** - Tests for the main `Client` class
  - Client configuration (RealDebrid, Premiumize, AllDebrid)
  - Token management
  - All public methods: `getCachedFiles()`, `isFileCached()`, `addMagnet()`, `getLink()`
  - Error handling and validation
  - Method chaining
  - Exception propagation

### Unit Tests (`tests/Unit/`)
- **`DebridFileTest.php`** - Tests for the `DebridFile` DTO
  - Constructor and property access
  - File name extraction
  - File extension detection
  - Human-readable file size formatting
  - Edge cases (no extension, multiple dots, zero size, etc.)

## Running Tests

### Using the Test Runner Script
```bash
php run-tests.php
```

### Using PHPUnit Directly
```bash
# Run all tests
vendor/bin/phpunit

# Run with testdox output
vendor/bin/phpunit --testdox

# Run specific test class
vendor/bin/phpunit tests/Feature/ClientTest.php

# Run specific test method
vendor/bin/phpunit --filter testSetClientRealDebrid
```

### Using Composer (if configured)
```bash
composer test
```

## Test Coverage

The test suite covers:

### Client Class (Feature Tests)
- ✅ All client type configurations
- ✅ Token management and validation
- ✅ All public methods with proper mocking
- ✅ Error scenarios (no client, no token)
- ✅ Method chaining functionality
- ✅ Exception handling and propagation
- ✅ Integration with different client strategies

### DebridFile DTO (Unit Tests)
- ✅ Constructor and property access
- ✅ File name extraction from paths
- ✅ File extension detection
- ✅ Human-readable size formatting (B, KB, MB, GB, TB)
- ✅ Edge cases and boundary conditions

## Test Patterns Used

1. **Mocking Strategy**: Uses `ClientStrategy` interface for mocking instead of concrete classes
2. **Reflection**: Uses reflection to access private properties for testing
3. **Exception Testing**: Tests both expected exceptions and error messages
4. **Method Chaining**: Tests fluent interface patterns
5. **Edge Cases**: Comprehensive coverage of boundary conditions

## Test Data

- Test tokens: `test_token_123`
- Test magnet: `magnet:?xt=urn:btih:test_hash_123`
- Test file path: `test/file.mp4`
- Various file sizes for size formatting tests

## Best Practices Followed

- **PSR-12**: All test code follows PSR-12 coding standards
- **Strict Types**: All test files declare `strict_types=1`
- **Final Classes**: Test classes are marked as `final`
- **Type Safety**: Full type hinting throughout
- **Descriptive Names**: Test method names clearly describe what they test
- **Isolation**: Each test is independent and doesn't rely on others
- **Mocking**: Proper use of mocks to isolate units under test
