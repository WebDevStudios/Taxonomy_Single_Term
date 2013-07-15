WDS_Taxonomy_Radio
==================

Removes and replaces the built-in taxonomy metabox with our radio-select metabox. http://webdevstudios.com/2013/07/08/replace-wordpress-default-taxonomy-metabox-with-a-radio-select-metabox/

Usage
------------

1. Include the `WDS_Taxonomy_Radio.class.php` file from within your plugin or theme
2. Initialize the class (update the taxonomy slug with your own): `$custom_tax_mb = new WDS_Taxonomy_Radio( 'custom-tax-slug' );`
3. Update optional class properties like:
  * `$custom_tax_mb->priority = 'low';`
  * `$custom_tax_mb->context = 'normal';`
  * `$custom_tax_mb->metabox_title = __( 'Custom Metabox Title', 'yourtheme' );`
  * `$custom_tax_mb->force_selection = true;`
