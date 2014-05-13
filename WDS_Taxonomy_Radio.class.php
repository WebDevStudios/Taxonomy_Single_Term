<?php

if ( ! class_exists( 'WDS_Taxonomy_Radio' ) ) :
/**
 * Removes and replaces the built-in taxonomy metabox with our radio-select metabox.
 *
 * Usage:
 *
 * $custom_tax_mb = new WDS_Taxonomy_Radio( 'custom-tax-slug' );
 *
 * Update optional properties:
 *
 * $custom_tax_mb->priority = 'low';
 * $custom_tax_mb->context = 'normal';
 * $custom_tax_mb->metabox_title = __( 'Custom Metabox Title', 'yourtheme' );
 * $custom_tax_mb->force_selection = true;
 * $custom_tax_mb->indented = false;
 *
 * @link  http://codex.wordpress.org/Function_Reference/add_meta_box#Parameters
 * @version  0.1.4
 */
class WDS_Taxonomy_Radio {

	/**
	 * Post types where metabox should be replaced (defaults to all post_types associated with taxonomy)
	 * @var array
	 * @since 0.1.0
	 */
	public $post_types = array();

	/**
	 * Taxonomy slug
	 * @var string
	 * @since 0.1.0
	 */
	public $slug = '';

	/**
	 * Taxonomy object
	 * @var object
	 * @since 0.1.0
	 */
	public $taxonomy = false;

	/**
	 * New metabox title. Defaults to Taxonomy name
	 * @var string
	 * @since 0.1.0
	 */
	public $metabox_title = '';

	/**
	 * Metabox priority. (vertical placement)
	 * 'high', 'core', 'default' or 'low'
	 * @var string
	 * @since 0.1.0
	 */
	public $priority = 'high';

	/**
	 * Metabox position. (column placement)
	 * 'normal', 'advanced', or 'side'
	 * @var string
	 * @since 0.1.0
	 */
	public $context = 'side';

	/**
	 * Set to true to hide "None" option & force a term selection
	 * @var boolean
	 * @since 0.1.1
	 */
	public $force_selection = false;

	/**
	 * Whether hierarchical taxonomy inputs should be indented to represent hierarchy
	 * @var boolean
	 * @since 0.1.2
	 */
	public $indented = true;

	/**
	 * Checks if there is a bulk-edit term to set
	 * @var boolean|term object
	 */
	public $to_set = false;

	/**
	 * Array of post ids whose terms have been reset from bulk-edit. (prevents recursion)
	 * @var array
	 */
	public $single_term_set = array();

	/**
	 * Initiates our metabox action
	 * @param string $tax_slug      Taxonomy slug
	 * @param array  $post_types    post-types to display custom metabox
	 * @since 0.1.0
	 */
	public function __construct( $tax_slug, $post_types = array() ) {

		$this->slug = $tax_slug;
		$this->post_types = is_array( $post_types ) ? $post_types : array( $post_types );

		add_action( 'add_meta_boxes', array( $this, 'add_radio_box' ) );
		add_action( 'admin_footer', array( $this, 'js_checkbox_transform' ) );

		// Handle bulk-editing
		if ( isset( $_REQUEST['bulk_edit'] ) && 'Update' == $_REQUEST['bulk_edit'] ) {

			// Get wp tax name designation
			$name = $this->slug;
			if ( 'category' == $name )
				$name = 'post_category';
			if ( 'tag' == $name )
				$name = 'post_tag';

			// If this tax name exists in the query arg
			if ( isset( $_REQUEST[ $name ] ) && is_array( $_REQUEST[ $name ] ) ) {
				$this->to_set = end( $_REQUEST[ $name ] );
			} elseif ( isset( $_REQUEST['tax_input'][ $name ] ) && is_array( $_REQUEST['tax_input'][ $name ] ) ) {
				$this->to_set = end( $_REQUEST['tax_input'][ $name ] );
			}

			// Then get it's term object
			if ( $this->to_set ) {
				$this->to_set = get_term( $this->to_set, $this->slug );
				// And hook in our re-save action
				add_action( 'set_object_terms', array( $this, 'maybe_resave_terms' ), 10, 5 );
			}
		}
	}

	/**
	 * Removes and replaces the built-in taxonomy metabox with our own.
	 * @since 0.1.0
	 */
	public function add_radio_box() {
		// test the taxonomy slug construtor is an actual taxonomy
		if ( ! $this->taxonomy() )
			return;

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
	 * @since 0.1.0
	 */
	public function radio_box() {

		// uses same noncename as default box so no save_post hook needed
		wp_nonce_field( 'taxonomy_'. $this->slug, 'taxonomy_noncename' );

		require_once( 'WDS_Taxonomy_Radio_Walker.php' );

		$class = $this->indented ? 'taxonomydiv' : 'not-indented';
		$class .= 'category' !== $this->slug ? ' '. $this->slug .'div' : '';
		$class .= ' tabs-panel';
		?>
		<div id="taxonomy-<?php echo $this->slug; ?>" class="<?php echo $class; ?>" style="margin-bottom: 5px;">
			<ul id="<?php echo $this->slug; ?>checklist" data-wp-lists="list:<?php echo $this->slug?>" class="categorychecklist form-no-clear">
				<?php wp_terms_checklist( get_the_ID(), array(
					'taxonomy'      => $this->slug,
					'checked_ontop' => false,
					'walker'        => new WDS_Taxonomy_Radio_Walker( $this->taxonomy()->hierarchical ),
				) ) ?>
			</ul>
		</div>
		<?php

	}

	/**
	 * Add some JS to the post listing page to transform the quickedit inputs
	 * @since  0.1.3
	 */
	public function js_checkbox_transform() {
		$screen = get_current_screen();
		$taxonomy = $this->taxonomy();

		if (
			empty( $taxonomy ) || empty( $screen )
			|| ! isset( $taxonomy->object_type )
			|| ! isset( $screen->post_type )
			|| ! in_array( $screen->post_type, $taxonomy->object_type )
		)
			return;

		?>
		<script type="text/javascript">
			// Handles changing input types to radios for WDS_Taxonomy_Radio
			jQuery(document).ready(function($){
				var $postsFilter = $('#posts-filter');
				var $theList = $postsFilter.find('#the-list');

				// Handles changing the input type attributes
				var changeToRadio = function( $context ) {
					$context = $context ? $context : $theList;
					var $taxListInputs = $context.find( '.<?php echo $this->slug; ?>-checklist li input' );
					if ( $taxListInputs.length ) {
						// loop and switch input types
						$taxListInputs.each( function() {
							$(this).attr( 'type', 'radio' ).addClass('transformed-to-radio');
						});
					}
				};

				$postsFilter
					// Handle converting radios in bulk-edit row
					.on( 'click', '#doaction, #doaction2', function(){
						var name = $(this).attr('id').substr(2);
						if ( 'edit' === $( 'select[name="' + name + '"]' ).val() ) {
							setTimeout( function() {
								changeToRadio( $theList.find('#bulk-edit') );
							}, 50 );
						}
					})
					// when clicking new radio inputs, be sure to uncheck all but the one clicked
					.on( 'change', '.transformed-to-radio', function() {
						var $this = $(this);
						$siblings = $this.parents( '.<?php echo $this->slug; ?>-checklist' ).find( 'li .transformed-to-radio' ).prop( 'checked', false );
						$this.prop( 'checked', true );
					});

				// Handle converting radios in inline-edit rows
				$theList.find('.editinline').on( 'click', function() {
					var $this = $(this);
					setTimeout( function() {
						var $editRow = $this.parents( 'tr' ).next();
						changeToRadio( $editRow );
					}, 50 );
				});
			});
		</script>
		<?php
	}

	/**
	 * Handles resaving terms to post when bulk-editing so that only one term will be applied
	 * @since  0.1.4
	 * @param  int    $object_id  Object ID.
	 * @param  array  $terms      An array of object terms.
	 * @param  array  $tt_ids     An array of term taxonomy IDs.
	 * @param  string $taxonomy   Taxonomy slug.
	 * @param  bool   $append     Whether to append new terms to the old terms.
	 * @param  array  $old_tt_ids Old array of term taxonomy IDs.
	 */
	public function maybe_resave_terms( $object_id, $terms, $tt_ids, $taxonomy, $append ) {
		if (
			// if the terms being edited are not this taxonomy
			$taxonomy != $this->slug
			// or we already did our magic
			|| in_array( $object_id, $this->single_term_set, true )
		) {
			// Then bail
			return;
		}

		// Prevent recursion
		$this->single_term_set[] = $object_id;
		// Replace terms with the one term
		wp_set_object_terms( $object_id, $this->to_set->slug, $taxonomy, $append );
	}

	/**
	 * Gets the taxonomy object from the slug
	 * @return object Taxonomy object
	 * @since 0.1.0
	 */
	public function taxonomy() {
		$this->taxonomy = $this->taxonomy ? $this->taxonomy : get_taxonomy( $this->slug );
		return $this->taxonomy;
	}

	/**
	 * Gets the taxonomy's associated post_types
	 * @return array Taxonomy's associated post_types
	 * @since 0.1.0
	 */
	public function post_types() {
		$this->post_types = !empty( $this->post_types ) ? $this->post_types : $this->taxonomy()->object_type;
		return $this->post_types;
	}

	/**
	 * Gets the metabox title from the taxonomy object's labels (or uses the passed in title)
	 * @return string Metabox title
	 * @since 0.1.0
	 */
	public function metabox_title() {
		$this->metabox_title = !empty( $this->metabox_title ) ? $this->metabox_title : $this->taxonomy()->labels->name;
		return $this->metabox_title;
	}


}

endif; // class_exists check
