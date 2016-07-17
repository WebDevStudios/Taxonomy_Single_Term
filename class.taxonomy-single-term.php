<?php

if ( ! class_exists( 'Taxonomy_Single_Term' ) ) :
/**
 * Removes and replaces the built-in taxonomy metabox with <select> or series of <input type="radio" />
 *
 * Usage:
 *
 * $custom_tax_mb = new Taxonomy_Single_Term( 'custom-tax-slug', array( 'post_type' ), 'type' ); // 'type' can be 'radio' or 'select' (default: radio)
 *
 * Update optional properties:
 *
 * $custom_tax_mb->set( 'priority', 'low' );
 * $custom_tax_mb->set( 'context', 'normal' );
 * $custom_tax_mb->set( 'metabox_title', __( 'Custom Metabox Title', 'yourtheme' ) );
 * $custom_tax_mb->set( 'force_selection', true );
 * $custom_tax_mb->set( 'indented', false );
 * $custom_tax_mb->set( 'allow_new_terms', true );
 *
 * @link  http://codex.wordpress.org/Function_Reference/add_meta_box#Parameters
 * @link  https://github.com/WebDevStudios/Taxonomy_Single_Term/blob/master/README.md
 * @version  0.2.1
 */
class Taxonomy_Single_Term {

	/**
	 * Post types where metabox should be replaced (defaults to all post_types associated with taxonomy)
	 * @since 0.1.0
	 * @var array
	 */
	protected $post_types = array();

	/**
	 * Taxonomy slug
	 * @since 0.1.0
	 * @var string
	 */
	protected $slug = '';

	/**
	 * Taxonomy object
	 * @since 0.1.0
	 * @var object
	 */
	protected $taxonomy = false;

	/**
	 * Taxonomy_Single_Term_Walker object
	 * @since 0.1.0
	 * @var object
	 */
	protected $walker = false;

	/**
	 * New metabox title. Defaults to Taxonomy name
	 * @since 0.1.0
	 * @var string
	 */
	protected $metabox_title = '';

	/**
	 * Metabox priority. (vertical placement)
	 * 'high', 'core', 'default' or 'low'
	 * @since 0.1.0
	 * @var string
	 */
	protected $priority = 'high';

	/**
	 * Metabox position. (column placement)
	 * 'normal', 'advanced', or 'side'
	 * @since 0.1.0
	 * @var string
	 */
	protected $context = 'side';

	/**
	 * Set to true to hide "None" option & force a term selection
	 * @since 0.1.1
	 * @var boolean
	 */
	protected $force_selection = false;

	/**
	 * Whether hierarchical taxonomy inputs should be indented to represent hierarchy
	 * @since 0.1.2
	 * @var boolean
	 */
	protected $indented = true;

	/**
	 * Checks if there is a bulk-edit term to set
	 * @var boolean|term object
	 */
	protected $to_set = false;

	/**
	 * Array of post ids whose terms have been reset from bulk-edit. (prevents recursion)
	 * @var array
	 */
	protected $single_term_set = array();

	/**
	 * What input element to use in the taxonomy meta box (radio or select)
	 * @var array
	 */
	protected $input_element = 'radio';

	/**
	 * Whether adding new terms via the metabox is permitted
	 * @since 0.2.0
	 * @var boolean
	 */
	protected $allow_new_terms = false;

	/**
	 * Initiates our metabox action
	 * @since 0.1.0
	 * @param string $tax_slug      Taxonomy slug
	 * @param array  $post_types    post-types to display custom metabox
	 */
	public function __construct( $tax_slug, $post_types = array(), $type = 'radio' ) {

		$this->slug = $tax_slug;
		$this->post_types = is_array( $post_types ) ? $post_types : array( $post_types );
		$this->input_element = in_array( (string) $type, array( 'radio', 'select' ) ) ? $type : $this->input_element;

		add_action( 'add_meta_boxes', array( $this, 'add_input_element' ) );
		add_action( 'admin_footer', array( $this, 'js_checkbox_transform' ) );
		add_action( 'wp_ajax_taxonomy_single_term_add', array( $this, 'ajax_add_term' ) );

		// Handle bulk-editing
		if ( isset( $_REQUEST['bulk_edit'] ) && 'Update' == $_REQUEST['bulk_edit'] ) {
			$this->bulk_edit_handler();
		}
	}

	/**
	 * Removes and replaces the built-in taxonomy metabox with our own.
	 * @since 0.1.0
	 */
	public function add_input_element() {

		// test the taxonomy slug construtor is an actual taxonomy
		if ( ! $this->taxonomy() ) {
			return;
		}

		foreach ( $this->post_types() as $key => $cpt ) {
			// remove default category type metabox
			remove_meta_box( $this->slug . 'div', $cpt, 'side' );
			// remove default tag type metabox
			remove_meta_box( 'tagsdiv-' . $this->slug, $cpt, 'side' );
			// add our custom radio box
			add_meta_box( $this->slug . '_input_element', $this->metabox_title(), array( $this, 'input_element' ), $cpt, $this->context, $this->priority );
		}
	}

	/**
	 * Displays our taxonomy input metabox
	 * @since 0.1.0
	 * @todo Abstract inline javascript to it's own file and localize it
	 */
	public function input_element() {

		// uses same noncename as default box so no save_post hook needed
		wp_nonce_field( 'taxonomy_'. $this->slug, 'taxonomy_noncename' );

		$class       = $this->indented ? 'taxonomydiv' : 'not-indented';
		$class      .= 'category' !== $this->slug ? ' ' . $this->slug . 'div' : '';
		$class      .= ' tabs-panel';

		$this->namefield    = 'category' == $this->slug ? 'post_category' : 'tax_input[' . $this->slug . ']';
		$this->namefield    = $this->taxonomy()->hierarchical ? $this->namefield . '[]' : $this->namefield;

		$el_open_cb  = $this->input_element . '_open';
		$el_close_cb = $this->input_element . '_close';

		?>
		<div id="taxonomy-<?php echo $this->slug; ?>" class="<?php echo $class; ?>">
			<?php $this->{$el_open_cb}() ?>
			<?php $this->term_fields_list(); ?>
			<?php $this->{$el_close_cb}() ?>
			<?php if ( $this->allow_new_terms ) {
				$this->terms_adder_button();
			} ?>
			<div style="clear:both;"></div>
		</div>
		<?php
	}

	/**
	 * Select wrapper open
	 * @since  0.2.0
	 */
	public function select_open() {
		?>
		<select style="display:block;width:100%;margin-top:12px;" name="<?php echo $this->namefield; ?>" id="<?php echo $this->slug; ?>checklist" class="form-no-clear">
			<?php if ( ! $this->force_selection ) : ?>
				<option value="0"><?php echo esc_html( apply_filters( 'taxonomy_single_term_select_none', __( 'None' ) ) ); ?></option>
			<?php endif;
	}

	/**
	 * Radio wrapper open
	 * @since  0.2.0
	 */
	public function radio_open() {
		?>
		<ul id="<?php echo $this->slug; ?>checklist" data-wp-lists="list:<?php echo $this->slug; ?>" class="categorychecklist form-no-clear">
			<?php if ( ! $this->force_selection ) : ?>
				<li style="display:none;">
					<input id="taxonomy-<?php echo $this->slug; ?>-clear" type="radio" name="<?php echo $this->namefield; ?>" value="0" />
				</li>
			<?php endif;
	}

	/**
	 * Select wrapper close
	 * @since  0.2.0
	 */
	public function select_close() {
		?>
		</select>
		<?php
	}

	/**
	 * Radio wrapper close
	 * @since  0.2.0
	 */
	public function radio_close() {
		?>
		</ul>
		<p style="margin-bottom:0;float:left;width:50%;">
			<a class="button" id="taxonomy-<?php echo $this->slug; ?>-trigger-clear" href="#"><?php _e( 'Clear' ); ?></a>
		</p>
		<script type="text/javascript">
			jQuery(document).ready(function($){
				$('#taxonomy-<?php echo $this->slug; ?>-trigger-clear').click(function(){
					$('#taxonomy-<?php echo $this->slug; ?> input:checked').prop( 'checked', false );
					$('#taxonomy-<?php echo $this->slug; ?>-clear').prop( 'checked', true );
					return false;
				});
			});
		</script>
		<?php
	}

	/**
	 * wp_terms_checklist wrapper which outputs the terms list
	 * @since  0.2.0
	 */
	public function term_fields_list() {
		wp_terms_checklist( get_the_ID(), array(
			'taxonomy'      => $this->slug,
			'selected_cats' => false,
			'popular_cats'  => false,
			'checked_ontop' => false,
			'walker'        => $this->walker(),
		) );
	}

	/**
	 * Adds button (and associated JS) for adding new terms
	 * @since 0.2.0
	 */
	public function terms_adder_button() {
		?>
		<p style="margin-bottom:0;float:right;width:50%;text-align:right;">
			<a class="button-secondary" id="taxonomy-<?php echo $this->slug; ?>-new" href="#"<?php if ( 'radio' == $this->input_element ) : ?> style="display:inline-block;margin-top:0.4em;"<?php endif; ?>><?php _e( 'Add New' ); ?></a>
		</p>
		<script type="text/javascript">
			jQuery(document).ready(function($){
				$('#taxonomy-<?php echo $this->slug; ?>-new').click(function(e){
					e.preventDefault();

					var termName = prompt( "Add New <?php echo esc_attr( $this->taxonomy()->labels->singular_name ); ?>", "New <?php echo esc_attr( $this->taxonomy()->labels->singular_name ); ?>" );

					if( ! termName ) {
						return;
					}
					if(termName != null) {
						var data = {
							'action'    : 'taxonomy_single_term_add',
							'term_name' : termName,
							'taxonomy'  : '<?php echo $this->slug; ?>',
							'nonce'     : '<?php echo wp_create_nonce( 'taxonomy_'. $this->slug, '_add_term' ); ?>'
						};
						$.post( ajaxurl, data, function(response) {
							window.console.log( 'response', response );
							if( response.success ){
								<?php if ( 'radio' == $this->input_element ) : ?>
									$('#taxonomy-<?php echo $this->slug; ?> input:checked').prop( 'checked', false );
								<?php else : ?>
									$('#taxonomy-<?php echo $this->slug; ?> option').prop( 'selected', false );
								<?php endif; ?>
								$('#<?php echo $this->slug; ?>checklist').append( response.data );
							} else {
								window.alert( '<?php printf( __( 'There was a problem adding a new %s' ), esc_attr( $this->taxonomy()->labels->singular_name ) ); ?>: ' + "\n" + response.data );
							}
						});
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * AJAX callback to add terms inline
	 * @since 0.2.0
	 */
	function ajax_add_term() {
		$nonce     = isset( $_POST['nonce'] ) ? sanitize_text_field( $_POST['nonce'] ) : '';
		$term_name = isset( $_POST['term_name'] ) ? sanitize_text_field( $_POST['term_name'] ) : false;
		$taxonomy  = isset( $_POST['taxonomy'] ) ? sanitize_text_field( $_POST['taxonomy'] ) : false;
		
		$friendly_taxonomy = $this->taxonomy()->labels->singular_name;
		
		// Ensure user is allowed to add new terms
		if( !$this->allow_new_terms ) {
			wp_send_json_error( __( "New $friendly_taxonomy terms are not allowed" ) );
		}

		if( !taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( __( "Taxonomy $friendly_taxonomy does not exist. Cannot add term" ) );
		}

		if( !wp_verify_nonce( $nonce, 'taxonomy_' . $taxonomy, '_add_term' ) ) {
			wp_send_json_error( __( "Cheatin' Huh? Could not verify security token" ) );
		}

		if( term_exists( $term_name, $taxonomy ) ) {
			wp_send_json_error( __( "The term '$term_name' already exists in $friendly_taxonomy" ) );
		}

		$result = wp_insert_term( $term_name, $taxonomy );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$term = get_term_by( 'id', $result['term_id'], $taxonomy );

		if ( ! isset( $term->term_id ) ) {
			wp_send_json_error();
		}


		$field_name = $taxonomy == 'category'
			? 'post_category'
			: 'tax_input[' . $taxonomy . ']';

		$field_name = $this->taxonomy()->hierarchical
			? $field_name . '[]'
			: $field_name;

		$args = array(
			'id'            => $taxonomy . '-' . $term->term_id,
			'name'          => $field_name,
			'value'         => $this->taxonomy()->hierarchical ? $term->term_id : $term->slug,
			'checked'       => ' checked="checked"',
			'selected'      => ' selected="selected"',
			'disabled'      => '',
			'label'         => esc_html( apply_filters( 'the_category', $term->name ) ),
		);

		$output = '';
		$output .= 'radio' == $this->input_element
			? $this->walker()->start_el_radio( $args )
			: $this->walker()->start_el_select( $args );

		// $output is handled by reference
		$this->walker()->end_el( $output, $term );

		wp_send_json_success( $output );

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
						var $editRow = $this.parents( 'tr' ).next().next();
						changeToRadio( $editRow );
					}, 50 );
				});

			});
		</script>
		<?php
	}

	/**
	 * Handles checking if object terms need to be set when bulk-editing posts
	 * @since  0.2.1
	 */
	public function bulk_edit_handler() {
		// Get wp tax name designation
		$name = $this->slug;

		if ( 'category' == $name ) {
			$name = 'post_category';
		}

		if ( 'tag' == $name ) {
			$name = 'post_tag';
		}

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
	 * @since 0.1.0
	 * @return object Taxonomy object
	 */
	public function taxonomy() {
		$this->taxonomy = $this->taxonomy ? $this->taxonomy : get_taxonomy( $this->slug );
		return $this->taxonomy;
	}

	/**
	 * Gets the taxonomy's associated post_types
	 * @since 0.1.0
	 * @return array Taxonomy's associated post_types
	 */
	public function post_types() {
		$this->post_types = !empty( $this->post_types ) ? $this->post_types : $this->taxonomy()->object_type;
		return $this->post_types;
	}

	/**
	 * Gets the metabox title from the taxonomy object's labels (or uses the passed in title)
	 * @since 0.1.0
	 * @return string Metabox title
	 */
	public function metabox_title() {
		$this->metabox_title = !empty( $this->metabox_title ) ? $this->metabox_title : $this->taxonomy()->labels->name;
		return $this->metabox_title;
	}

	/**
	 * Gets the Taxonomy_Single_Term_Walker object for use in term_fields_list and ajax_add_term
	 * @since 0.2.0
	 * @return object Taxonomy_Single_Term_Walker object
	 */
	public function walker() {
		if ( $this->walker ) {
			return $this->walker;
		}
		require_once( 'walker.taxonomy-single-term.php' );
		$this->walker = new Taxonomy_Single_Term_Walker( $this->taxonomy()->hierarchical, $this->input_element );

		return $this->walker;
	}

	/**
	 * Set the object properties.
	 *
	 * @since 0.2.1
	 *
	 * @param string $property  Property in object.  Must be set in object.
	 * @param mixed  $value     Value of property.
	 *
	 * @return Taxonomy_Single_Term  Returns Taxonomy_Single_Term object, allows for chaining.
	 */
	public function set( $property, $value ) {

		if ( property_exists( $this, $property ) ) {
			$this->$property = $value;
		}

		return $this;
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since  0.2.1
	 *
	 * @param  string    Property in object to retrieve.
	 * @throws Exception Throws an exception if the field is invalid.
	 *
	 * @return mixed     Property requested.
	 */
	public function __get( $property ) {
		if ( property_exists( $this, $value ) ) {
			return $this->{$property};
		} else {
			throw new Exception( 'Invalid '. __CLASS__ .' property: ' . $field );
		}
	}

}

endif; // class_exists check
