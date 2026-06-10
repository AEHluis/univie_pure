# Bundled PHP Libraries

This directory contains third-party PHP libraries bundled with the extension.

**Location**: `Libraries/` (Extension root level)

This is the conventional location for bundled third-party libraries in TYPO3 extensions.

---

## Current Libraries

### citeproc-php v2.7.0

**Purpose**: Citation Style Language (CSL) 1.0.2 processor
**Version**: v2.7.0 (bundled)
**License**: MIT
**Repository**: https://github.com/seboettg/citeproc-php

Used for generating academically correct citations in various styles (APA, MLA, Chicago, IEEE, etc.)

---

## Installation

### Option 1: Use Bundling Script (Easiest)

```bash
# From extension root directory
./scripts/bundle_citeproc.sh
```

This automatically:
- Downloads citeproc-php v2.7.0
- Downloads dependencies
- Copies to `Libraries/citeproc-php/`
- Cleans unnecessary files

### Option 2: Manual Installation

See `BUNDLING_CITEPROC.md` in the extension root for detailed instructions.

### Option 3: Already Bundled (TER Installations)

If you installed via TYPO3 Extension Manager, the library is already included. No action needed.

---

## Directory Structure

```
Libraries/                      ← Extension root level
└── citeproc-php/              ← v2.7.0 bundled
    ├── src/                   ← Library source code
    │   ├── CiteProc.php       ← Main class
    │   ├── Style/
    │   ├── Rendering/
    │   └── ...
    ├── vendor/                ← Dependencies
    │   └── seboettg/
    │       └── collection/    ← Required dependency
    ├── composer.json          ← Library metadata
    └── LICENSE.txt            ← MIT License
```

## Verification

Check if the library is loaded:

```bash
# TYPO3 CLI
vendor/bin/typo3 pure:check-libraries

# Or check TYPO3 System Log for:
# "Loaded citeproc-php from bundled version"
```

## Updates

To update the bundled library:

```bash
./scripts/bundle_citeproc.sh --update
```

Or manually follow instructions in `BUNDLING_CITEPROC.md`.

## Licensing

All bundled libraries retain their original licenses. See each library's LICENSE file.

**citeproc-php**: MIT License
- Copyright (c) Sebastian Böttger
- Full license: `citeproc-php/LICENSE`

## Size

Approximate bundled size:
- Full bundle: ~500-800 KB
- Minimal bundle: ~300-500 KB

## Support

- **Bundling issues**: See `BUNDLING_CITEPROC.md`
- **Library bugs**: Report to respective library repository
- **Extension issues**: Create issue in extension repository

---

**Note**: This directory should be committed to version control when distributing via TER.
For development with Composer, the Composer-installed version takes precedence.
