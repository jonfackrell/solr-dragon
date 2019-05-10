# SolrDragon

This module is currently in development and should not be used.

Extract OCR text coordinates from files to make them searchable and usable as Labels for highlighting text in OpenSeadragon.

Once installed and active, this module has the following features:

- The module extracts text location information from PDF's that have OCR information
- The module uses Google's Vision API to OCR images and obtain text location information
- The module provides a search adapter for Solr. All of the extracted text information is stored in Solr. 
- The module integrates OpenSeadragon for highlighting search results within media.

## Supported file formats:

- PDF
- TIFF
- PNG
- JPG

## Extractors:

### pdftotext

Used to extract text from PDF files. Requires [pdftotext](https://linux.die.net/man/1/pdftotext),
a part of the poppler-utils package.