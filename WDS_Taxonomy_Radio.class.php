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
 * @version  0.1.3
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
			jQuery(document).ready(function($){
				$('.editinline').on( 'click', function( evt ) {
					var $this = $(this);
					setTimeout( function() {
						var $editRow = $this.parents( 'tr' ).next();
						var $taxListInputs = $editRow.find( '.<?php echo $this->slug; ?>-checklist li input' );
						if ( $taxListInputs.length ) {
							// loop and switch input types
							$taxListInputs.each( function() {
								$(this).attr( 'type', 'radio' );
							});
						}
					}, 50 );
				});
			});
		</script>
		<?php
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
