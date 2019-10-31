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

## Dependencies:

### File Sideload Module
Install and configure a sideload directory in the module's settings. https://omeka.org/s/modules/FileSideload/

### php-imagick

To install, run the the following commands:

    sudo apt-get update --allow-releaseinfo-change && sudo apt-get install -y imagemagick php-imagick && sudo service php7.2-fpm restart && sudo service nginx restart

Edit the policy to give read|write permissions for PDF files:

    sudo vi /etc/ImageMagick-6/policy.xml 
    
    <policymap>
        <policy domain="coder" rights="read|write" pattern="PDF" />
    </policymap>
    
    sudo service php7.2-fpm restart && sudo service nginx restart

## Extractors:

### pdftotext

Used to extract text from PDF files. Requires [pdftotext](https://linux.die.net/man/1/pdftotext),
a part of the poppler-utils package. To install, run the the following commands:

    sudo apt-get update
    sudo apt-get install poppler-utils
    sudo apt-get install python-poppler
