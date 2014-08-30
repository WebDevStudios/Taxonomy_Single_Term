Taxonomy_Single_Term
==================

Version: 0.2.0

Taxonomies in WordPress are super powerful. The purpose of taxonomies is to create relationships among post types. Unfortunately the UI doesn't effectively enforce limiting to a single term. 

This library helps you remove and replace the built-in taxonomy metabox with a radio or select\* metabox.

_**Read more**: [How To: Replace WordPress Default Taxonomy Metabox with a Radio Select Metabox](http://webdevstudios.com/2013/07/08/replace-wordpress-default-taxonomy-metabox-with-a-radio-select-metabox/)_

Usage
------------

1. Include the `class.taxonomy-single-term.php` file from within your plugin or theme
2. Initialize the class (update the taxonomy slug with your own):  
	`$custom_tax_mb = new Taxonomy_Single_Term( 'custom-tax-slug' );`

#### Optional
1. Second parameter is an array of post\_types and the third parameter is either 'radio', or 'select' (defaulting to radio)\*. To use a `select` type on the `foo` post\_type:
	`$custom_tax_mb = new Taxonomy_Single_Term( 'custom-tax-slug', array( 'foo' ), 'select' );`
2. Update optional class properties like:
```php
// Priority of the metabox placement.
$custom_tax_mb->priority = 'low';

// 'normal' to move it under the post content.
$custom_tax_mb->context = 'normal';

// Custom title for your metabox
$custom_tax_mb->metabox_title = __( 'Custom Metabox Title', 'yourtheme' );

// Makes a selection required.
$custom_tax_mb->force_selection = true;

// Will keep radio elements from indenting for child-terms.
$custom_tax_mb->indented = false; 

// Allows adding of new terms from the metabox *
$custom_tax_mb->allow_new_terms = true; 
```

_\* Props [@jchristopher](https://github.com/jchristopher)_
