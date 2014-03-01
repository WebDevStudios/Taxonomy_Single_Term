<?php

if ( !class_exists( 'WDS_Taxonomy_Radio' ) ) {
	 /**
		* Removes and replaces the built-in taxonomy metabox with our radio-select metabox.
		* @link  http://codex.wordpress.org/Function_Reference/add_meta_box#Parameters
		*/
	 class WDS_Taxonomy_Radio {

			// Post types where metabox should be replaced (defaults to all post_types associated with taxonomy)
			public $post_types = array();
			// Taxonomy slug
			public $slug = '';
			// Taxonomy object
			public $taxonomy = false;
			// New metabox title. Defaults to Taxonomy name
			public $metabox_title = '';
			// Metabox priority. (vertical placement)
			// 'high', 'core', 'default' or 'low'
			public $priority = 'high';
			// Metabox position. (column placement)
			// 'normal', 'advanced', or 'side'
			public $context = 'side';
			// Set to true to hide "None" option & force a term selection
			public $force_selection = false;


			/**
			 * Initiates our metabox action
			 * @param string $tax_slug      Taxonomy slug
			 * @param array  $post_types    post-types to display custom metabox
			 */
			public function __construct( $tax_slug, $post_types = array() ) {

				 $this->slug = $tax_slug;
				 $this->post_types = is_array( $post_types ) ? $post_types : array( $post_types );

				 add_action( 'add_meta_boxes', array( $this, 'add_radio_box' ) );
			}

			/**
			 * Removes and replaces the built-in taxonomy metabox with our own.
			 */
			public function add_radio_box() {
				 //test the taxonomy slug construtor is an actual taxonomy
				 if ( !$this->taxonomy() ) return;
			
				 foreach ( $this->post_types() as $key => $cpt ) {
						// remove default category type metabox
						remove_meta_box( $this->slug .'div', $cpt, 'side' );
						// remove default tag type metabox
						remove_meta_box( 'tagsdiv-'.$this->slug, $cpt, 'side' );
						// add our custom radio box
						add_meta_box( $this->slug .'_radio', $this->metabox_title(), array( $this, 'radio_box' ), $cpt, $this->context, $this->priority );
				 }
			}

			/**
			 * Displays our taxonomy radio box metabox
			 */
			public function radio_box() {

				 // uses same noncename as default box so no save_post hook needed
				 wp_nonce_field( 'taxonomy_'. $this->slug, 'taxonomy_noncename' );

				 // get terms associated with this post
				 $names = wp_get_object_terms( get_the_ID(), $this->slug );
				 // get all terms in this taxonomy
				 $terms = (array) get_terms( $this->slug, 'hide_empty=0' );
				 // filter the ids out of the terms
				 $existing = ( !is_wp_error( $names ) && !empty( $names ) )
						? (array) wp_list_pluck( $names, 'term_id' )
						: array();
				 // Check if taxonomy is hierarchical
				 // Terms are saved differently between types
				 $h = $this->taxonomy()->hierarchical;

				 // default value
				 $default_val = $h ? 0 : '';
				 // input name
				 $name = $h ? 'tax_input['. $this->slug .'][]' : 'tax_input['. $this->slug .']';

				 echo '<div style="margin-bottom: 5px;">
				 <ul id="'. $this->slug .'_taxradiolist" data-wp-lists="list:'. $this->slug .'_tax" class="categorychecklist form-no-clear">';

						// If 'category,' force a selection, or force_selection is true
						if ( $this->slug != 'category' && !$this->force_selection ) {
							 // our radio for selecting none
							 echo '<li id="'. $this->slug .'_tax-0"><label><input value="'. $default_val .'" type="radio" name="'. $name .'" id="in-'. $this->slug .'_tax-0" ';
							 checked( empty( $existing ) );
							 echo '> '. sprintf( __( 'No %s', 'wds' ), $this->taxonomy()->labels->singular_name ) .'</label></li>';
						}

				 // loop our terms and check if they're associated with this post
				 foreach ( $terms as $term ) {

						$val = $h ? $term->term_id : $term->slug;

						echo '<li id="'. $this->slug .'_tax-'. $term->term_id .'"><label><input value="'. $val .'" type="radio" name="'. $name .'" id="in-'. $this->slug .'_tax-'. $term->term_id .'" ';
						// if so, they get "checked"
						checked( !empty( $existing ) && in_array( $term->term_id, $existing ) );
						echo '> '. $term->name .'</label></li>';
				 }
				 echo '</ul></div>';

			}

			/**
			 * Gets the taxonomy object from the slug
			 * @return object Taxonomy object
			 */
			public function taxonomy() {
				 $this->taxonomy = $this->taxonomy ? $this->taxonomy : get_taxonomy( $this->slug );
				 return $this->taxonomy;
			}

			/**
			 * Gets the taxonomy's associated post_types
			 * @return array Taxonomy's associated post_types
			 */
			public function post_types() {
				 $this->post_types = !empty( $this->post_types ) ? $this->post_types : $this->taxonomy()->object_type;
				 return $this->post_types;
			}

			/**
			 * Gets the metabox title from the taxonomy object's labels (or uses the passed in title)
			 * @return string Metabox title
			 */
			public function metabox_title() {
				 $this->metabox_title = !empty( $this->metabox_title ) ? $this->metabox_title : $this->taxonomy()->labels->name;
				 return $this->metabox_title;
			}


	 }

	 // Usage:

	 // $custom_tax_mb = new WDS_Taxonomy_Radio( 'custom-tax-slug' );

	 // Update optional properties

	 // $custom_tax_mb->priority = 'low';
	 // $custom_tax_mb->context = 'normal';
	 // $custom_tax_mb->metabox_title = __( 'Custom Metabox Title', 'yourtheme' );
	 // $custom_tax_mb->force_selection = true;

}