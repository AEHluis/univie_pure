# Univie Pure - T3LUH FIS TYPO3 Extension

## Overview
**Univie Pure** (T3LUH FIS) is a TYPO3 Extension designed to integrate with the Elsevier Pure Web Service, allowing seamless display of **Publications, Datasets, Equipment, and Projects** on TYPO3-powered websites. It is fully compatible with **Pure Web Service version 524** and is based on the **Vienna Pure Extension**.

## Features
- Fetch and display **publications**, **projects**, **datasets**, and **equipment** from Elsevier Pure Web Service.
- Provides configurable TYPO3 plugins for embedding **Pure API data**.
- Supports **caching** for improved performance.
- Compatible with TYPO3 versions supporting **Extbase & Fluid**.
- Designed to work with **Pure/FIS API v524**.

## Installation
### 1. Install via Composer
```sh
composer require univie/univie-pure
```

### 2. Manual Installation
1. Download the extension and place it in your TYPO3 `/typo3conf/ext/` directory.
2. Activate the extension in the TYPO3 backend under **Admin Tools → Extensions**.
3. Clear TYPO3 cache.

### 3. Configure Environment Variables
Set up your `.env` file in the TYPO3 Root-Directory with the following credentials:
```
PURE_URI=https://your-pure-instance/ws/api/524/
PURE_APIKEY=your-api-key
PURE_ENDPOINT=/ws/api/524/
```

## Usage
### 1. Adding a Plugin to a Page
1. In TYPO3 backend, go to a page where you want to display Pure data.
2. Add a **new content element** and select the **Univie Pure plugin**.
3. Configure the plugin settings (e.g., **Publication List, Project List, Dataset List, Equipment List**).
4. Save and preview the page.


## API Documentation of the Pure Endpoint
Public API documentation is available at:
- [FIS API Docs](https://www.fis.uni-hannover.de/ws/api/524/api-docs/index.html) or
- [TU/e Pure API Docs](https://pure.tue.nl/ws/api/524/api-docs/index.html)

## Development
### Running PHPUnit Tests
```sh
.Build/vendor/phpunit/phpunit/phpunit --configuration Tests/phpunit.xml
```


## Contributing
1. Fork the repository.
2. Create a feature branch (`git checkout -b feature-xyz`).
3. Commit your changes (`git commit -m 'Add feature xyz'`).
4. Push to the branch (`git push origin feature-xyz`).
5. Open a **Pull Request**.

## License
This extension is licensed under the **GNU GENERAL PUBLIC LICENSE Version 3**.

## Maintainers
- **Alex Ebeling-Hoppe** - [ebeling-hoppe@luis.uni-hannover.de](mailto:ebeling-hoppe@luis.uni-hannover.de)
- **Organization/Institution** - University of Vienna / LUIS - Leibniz University Hannover

## Support
For questions or issues, please open a ticket on [GitHub Issues](https://github.com/AEHluis/univie_pure/issues).

