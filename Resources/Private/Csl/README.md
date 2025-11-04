# CSL (Citation Style Language) Styles

This directory contains **bundled CSL style files** for citation formatting in production.

---

## 🎯 Production-Ready Bundled Styles

**Status**: ✅ **57 CSL styles bundled** (1.2 MB)

The extension includes a comprehensive collection of pre-downloaded CSL styles to ensure reliable, fast citation rendering in production **without requiring network access**.

### ✅ Bundled Styles (57 Total)

#### Major Academic (5)
- **APA 7th** - American Psychological Association 7th edition
- **APA 6th** - American Psychological Association 6th edition
- **MLA 9th** - Modern Language Association 9th edition
- **MLA 8th** - Modern Language Association 8th edition
- **Chicago** - Chicago Manual of Style 18th edition (author-date)

#### Engineering & Computer Science (5)
- **IEEE** - IEEE standard
- **IEEE (with URL)** - IEEE with URLs
- **ACM SIG** - ACM SIG Proceedings (3 variants)
- **ACM** - Association for Computing Machinery

#### Physics & Chemistry (5)
- **APS** - American Physical Society
- **AIP** - American Institute of Physics 4th edition
- **ACS** - American Chemical Society
- **RSC** - Royal Society of Chemistry
- **Angewandte Chemie** - Angewandte Chemie International

#### Medical & Life Sciences (6)
- **Vancouver** - Vancouver standard
- **Vancouver Superscript** - Vancouver (numbered)
- **AMA** - American Medical Association 11th edition
- **BMJ** - British Medical Journal
- **The Lancet** - The Lancet
- **PLOS** - Public Library of Science

#### Scientific Journals (4)
- **Nature** - Nature
- **Nature (no et al)** - Nature without "et al."
- **Science** - Science
- **Cell** - Cell

#### German Standards & Legal (5)
- **DIN 1505-2** - DIN 1505-2 (Deutsch)
- **ISO-690 (German)** - ISO-690 (author-date, Deutsch)
- **DGPs** - Deutsche Gesellschaft für Psychologie
- **Juristische Zitierweise** - German Legal Citation
- **NJW** - Neue Juristische Wochenschrift (German Legal)

#### ISO Standards (3)
- **ISO-690 (German, Author-Date)** - ISO-690 (Deutsch)
- **ISO-690 (English, Author-Date)** - ISO-690 (English)
- **ISO-690 (Numeric, English)** - ISO-690 (Numeric)

#### Springer Variants (7)
- **Springer Basic** - Springer Basic (author-date)
- **Springer Brackets** - Springer Basic (numeric, brackets)
- **Springer No Et Al** - Springer Basic (no "et al.")
- **Springer Humanities** - Springer Humanities (author-date)
- **Springer LNCS** - Springer Lecture Notes in Computer Science
- **Springer MathPhys** - Springer MathPhys (author-date)
- **Springer SocPsych** - Springer SocPsych (author-date)

#### Harvard Variants (3)
- **Harvard (Cite Them Right)** - UK standard
- **Harvard (Anglia Ruskin)** - Anglia Ruskin University
- **Harvard (Westminster)** - University of Westminster

#### Elsevier Variants (3)
- **Elsevier Harvard** - Elsevier Harvard (with titles)
- **Elsevier (with titles)** - Elsevier numeric (with titles)
- **Elsevier (without titles)** - Elsevier numeric (without titles)

#### Major Publishers (4)
- **Wiley-VCH** - Wiley-VCH books
- **Taylor & Francis** - Taylor & Francis (Chicago)
- **Oxford UP** - Oxford University Press (note)
- **Cambridge UP** - Cambridge University Press (note)

#### Social Sciences (4)
- **ASA** - American Sociological Association
- **APSA** - American Political Science Association
- **AAA** - American Anthropological Association
- **CSE** - Council of Science Editors

#### Linguistics (1)
- **Unified Linguistics** - Unified style sheet for linguistics

#### French/Canadian (2)
- **UQAM** - Université du Québec à Montréal (APA, Français)
- **Laval** - Université Laval (Français - Canada)

#### Additional (1)
- **BibTeX** - BibTeX generic citation style

**Total**: 57 citation styles covering Europe and USA academic requirements

---

## 📦 Adding More Styles

### Option 1: Use Bundling Script (Recommended)

```bash
# Bundle top-50 comprehensive styles (default, 57 styles)
./scripts/bundle_csl_styles.sh

# Or explicitly:
./scripts/bundle_csl_styles.sh --top50

# Bundle minimal essential styles only (~5 styles)
./scripts/bundle_csl_styles.sh --minimal

# Bundle ALL styles from repository (~2,800 styles, ~50MB) - NOT RECOMMENDED
./scripts/bundle_csl_styles.sh --all
```

### Option 2: Manual Download

1. Browse styles at https://github.com/citation-style-language/styles
2. Download the `.csl` file
3. Save to `Resources/Private/Csl/Styles/`
4. Commit to git

### Option 3: Clone Repository

```bash
# Clone full CSL styles repository
cd /tmp
git clone --depth 1 https://github.com/citation-style-language/styles.git

# Copy specific styles
cp styles/modern-language-association.csl \
   <extension>/Resources/Private/Csl/Styles/mla.csl

# Or copy all styles (10,000+)
cp styles/*.csl <extension>/Resources/Private/Csl/Styles/
```

---

## 🔄 Auto-Download Fallback (Development Only)

**For production**: All required styles should be bundled.

**For development**: If a style is missing, `CslRenderingService` can auto-download it from GitHub as a fallback. This is **NOT recommended for production** because:

- ❌ Requires network access
- ❌ Slow (adds latency)
- ❌ Unreliable (network failures, GitHub downtime)
- ❌ Security concerns (external dependencies)

The auto-download feature logs a **WARNING** when used:

```
WARNING: CSL style 'xyz' not found locally, downloading from GitHub.
         For production, bundle styles using: ./scripts/bundle_csl_styles.sh
```

---

## 🎓 Using CSL Styles

### In Research Output Lists

```php
// Automatic CSL style detection and rendering
$publications = $apiService->getResearchOutputs([
    'view' => 'apa',        // Uses bundled apa.csl
    'size' => 20,
    'organizationUuid' => $uuid
]);

// Result: Each item has $item['rendering'] with formatted citation
```

### In Single Item View

```php
$publication = $apiService->getResearchOutput($uuid, [
    'view' => 'vancouver'   // Uses bundled vancouver.csl
]);

// Result: $publication['rendering'] contains formatted citation
```

### Available Style Names

Use these values for the `view` parameter:

```php
'view' => 'apa'                                  // APA 7th Edition
'view' => 'apa-6th-edition'                      // APA 6th Edition
'view' => 'modern-language-association'          // MLA 9th
'view' => 'chicago-author-date'                  // Chicago (Author-Date)
'view' => 'ieee'                                 // IEEE
'view' => 'vancouver'                            // Vancouver
'view' => 'nature'                               // Nature
'view' => 'american-physics-society'             // APS (Physics)
'view' => 'american-chemical-society'            // ACS (Chemistry)
'view' => 'royal-society-of-chemistry'           // RSC (Chemistry)
'view' => 'harvard-cite-them-right'              // Harvard
'view' => 'springer-basic-author-date'           // Springer
'view' => 'springer-lecture-notes-in-computer-science' // Springer LNCS
'view' => 'din-1505-2'                           // DIN 1505-2 (German)
'view' => 'juristische-zitierweise'              // German Legal
'view' => 'neue-juristische-wochenschrift'       // NJW (German Legal)
'view' => 'unified-style-sheet-for-linguistics'  // Linguistics
// ... see table above for all 57 styles
```

---

## 🧪 Testing Styles

### Quick Test Script

```php
<?php
use Univie\UniviePure\Service\CslRenderingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$cslService = GeneralUtility::makeInstance(CslRenderingService::class);

// Test data
$publication = [
    'uuid' => 'test-001',
    'title' => 'Sample Publication',
    'contributors' => [
        ['name' => ['firstName' => 'John', 'lastName' => 'Doe']]
    ],
    'publicationYear' => 2023
];

// Test APA style
$citation = $cslService->renderResearchOutput($publication, 'apa');
echo $citation;  // Output: Doe, J. (2023). Sample Publication.
```

### Verification Script

```bash
# Verify all bundled styles work
php scripts/verify_csl_styles.php
```

---

## 📁 File Structure

```
Resources/Private/Csl/
├── README.md                           ← This file
└── Styles/                             ← Bundled CSL files
    ├── apa.csl                         (82.6 KB)
    ├── chicago-author-date.csl         (148.8 KB)
    ├── ieee.csl                        (17 KB)
    ├── vancouver.csl                   (12.6 KB)
    ├── springer-* (7 files)            (65 KB)
    ├── iso690-* (3 files)              (58 KB)
    └── ... (44 more files)
```

**Total Size**: ~1.2 MB (57 styles)

---

## 🔍 Finding Style Names

### Official CSL Repository
Browse all 10,000+ styles:
https://github.com/citation-style-language/styles

### CSL Style Search
Search and preview styles:
https://www.zotero.org/styles

### Common Styles

| Journal/Format | CSL File Name |
|----------------|---------------|
| APA | `apa.csl` |
| MLA | `modern-language-association.csl` ⚠️ |
| Chicago | `chicago-author-date.csl` |
| Harvard | `harvard-cite-them-right.csl` |
| Nature | `nature.csl` |
| Science | `science.csl` |
| Cell | `cell.csl` |
| PLOS | `plos.csl` |

⚠️ **Note**: MLA is named `modern-language-association.csl` in the repository, not `mla.csl`.

---

## 🔧 Custom Styles

You can create custom CSL styles following the CSL 1.0.2 specification:
https://docs.citationstyles.org/en/stable/specification.html

1. Create a `.csl` file with your custom formatting rules
2. Save to `Resources/Private/Csl/Styles/my-custom-style.csl`
3. Use with `'view' => 'my-custom-style'`
4. Commit to git for production use

### Example Custom Style Template

```xml
<?xml version="1.0" encoding="utf-8"?>
<style xmlns="http://purl.org/net/xbiblio/csl" class="in-text" version="1.0">
  <info>
    <title>My Custom Citation Style</title>
    <id>http://www.example.com/custom-style</id>
  </info>
  <citation>
    <!-- Citation formatting rules -->
  </citation>
  <bibliography>
    <!-- Bibliography formatting rules -->
  </bibliography>
</style>
```

---

## 🚀 Production Checklist

Before deploying to production:

- [x] Bundle required CSL styles using `./scripts/bundle_csl_styles.sh`
- [x] Verify bundled styles: `ls -la Resources/Private/Csl/Styles/*.csl`
- [x] Commit bundled styles to git
- [x] Test citations render correctly
- [ ] Disable or remove auto-download fallback (optional, for maximum security)

---

## 📊 Bundle Size Impact

| Mode | Styles | Size | TER Compatible |
|------|--------|------|----------------|
| **None** | 0 | 0 KB | ❌ Network required |
| **Minimal** | 5 | ~100 KB | ✅ Yes |
| **Top-50** (default) | 57 | ~1.2 MB | ✅ Yes |
| **All** | 2,800+ | ~50 MB | ⚠️ Too large |

**Recommendation**: Use **Top-50** mode (57 styles, 1.2 MB) for TER distribution.

---

## 🔗 References

- **CSL Specification**: https://docs.citationstyles.org/
- **CSL Styles Repository**: https://github.com/citation-style-language/styles
- **Citeproc-PHP**: https://github.com/seboettg/citeproc-php
- **Bundling Script**: `scripts/bundle_csl_styles.sh`

---

**Last Updated**: 2025-11-04
**Bundled Styles**: 57
**Total Size**: 1.2 MB
**Coverage**: Major academic (APA, MLA, Chicago), Engineering (IEEE, ACM), Physics (APS, AIP), Chemistry (ACS, RSC), German (DIN, Legal), Springer (7 variants), Medical, Scientific journals, Linguistics, French/Canadian
**Status**: ✅ Production Ready
