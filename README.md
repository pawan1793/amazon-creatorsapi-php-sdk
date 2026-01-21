# Creators API PHP SDK Example

## Prerequisites

### PHP Version Support
- **Supported**: To run the SDK you need PHP version 8.1 or higher.

## Installation

Install via Composer:

```bash
composer require pawanmore/amazon-creatorsapi-php-sdk
```

## Quick Start

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Amazon\CreatorsAPI\v1\Configuration;
use Amazon\CreatorsAPI\v1\com\amazon\creators\api\DefaultApi;
use Amazon\CreatorsAPI\v1\com\amazon\creators\model\GetItemsRequestContent;
use Amazon\CreatorsAPI\v1\com\amazon\creators\model\GetItemsResource;

$config = new Configuration();
$config->setCredentialId('<YOUR CREDENTIAL ID>');
$config->setCredentialSecret('<YOUR CREDENTIAL SECRET>');
$config->setVersion('<YOUR CREDENTIAL VERSION>'); // e.g., 2.1, 2.2, 2.3

$api = new DefaultApi(null, $config);

$request = new GetItemsRequestContent();
$request->setPartnerTag('<YOUR PARTNER TAG>');
$request->setItemIds(['B0DLFMFBJW']);
$request->setResources([GetItemsResource::ITEM_INFO_TITLE]);

$response = $api->getItems('<YOUR MARKETPLACE>', $request);
print_r($response);
```

## Setup Instructions

### 1. Install and Configure PHP

For PHP installation, you can download it from the official website: https://www.php.net/downloads

```bash
# Check PHP version
php --version
```

### 2. Install Dependencies
```bash
cd {path_to_dir}/creatorsapi-php-sdk
composer install
```

### 3. Run Sample Code
Navigate to the examples directory to run the samples.

```bash
cd examples
```

Before running the samples, you'll need to configure your API credentials in the sample files by replacing the following placeholders:

- `<YOUR CREDENTIAL ID>` - Your API credential ID
- `<YOUR CREDENTIAL SECRET>` - Your API credential secret  
- `<YOUR CREDENTIAL VERSION>` - Your credential version (e.g., "2.1" for NA, "2.2" for EU, "2.3" for FE region)
- `<YOUR MARKETPLACE>` - Your marketplace (e.g., "www.amazon.com" for US marketplace)
- `<YOUR PARTNER TAG>` - Add valid Partner Tag for the requested marketplace in applicable sample code snippet files

Run the following commands to run the sample files:

**Get detailed product information:**
```bash
php SampleGetItems.php
```

**Search for products:**
```bash
php SampleSearchItems.php
```

#### Other Samples
Check the `examples` directory for additional sample files with various API operations.
