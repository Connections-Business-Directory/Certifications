<?php
/**
 * An extension for the Connections Business Directory plugin which adds the ability to add and assign certifications to your business directory entries.
 *
 * @package   Connections Business Directory Extension - Certifications
 * @category  Extension
 * @author    Steven A. Zahm
 * @license   GPL-2.0+
 * @link      https://connections-pro.com
 * @copyright 2021 Steven A. Zahm
 *
 * @wordpress-plugin
 * Plugin Name:       Connections Business Directory Extension - Certifications
 * Plugin URI:        https://connections-pro.com/documentation/certifications/
 * Description:       An extension for the Connections Business Directory plugin which adds the ability to add and assign certifications to your business directory entries.
 * Version:           1.4.1
 * Author:            Steven A. Zahm
 * Author URI:        https://connections-pro.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       connections_certifications
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Connections_Certifications' ) ) {

	final class Connections_Certifications {

		const VERSION = '1.4.1';

		/**
		 * @var string The absolute path this this file.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $file = '';

		/**
		 * @var string The URL to the plugin's folder.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $url = '';

		/**
		 * @var string The absolute path to this plugin's folder.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $path = '';

		/**
		 * @var string The basename of the plugin.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $basename = '';

		public function __construct() {

			self::$file       = __FILE__;
			self::$url        = plugin_dir_url( self::$file );
			self::$path       = plugin_dir_path( self::$file );
			self::$basename   = plugin_basename( self::$file );

			self::loadDependencies();

			/**
			 * This should run on the `plugins_loaded` action hook. Since the extension loads on the
			 * `plugins_loaded` action hook, load immediately.
			 */
			cnText_Domain::register(
				'connections_certifications',
				self::$basename,
				'load'
			);

			// Add to Connections menu.
			add_filter( 'cn_submenu', array( __CLASS__, 'addMenu' ) );

			// Remove the "View" link from the "Certifications" taxonomy admin page.
			add_filter( 'cn_certification_row_actions', array( __CLASS__, 'removeViewAction' ) );

			// Register the metabox.
			add_action( 'cn_metabox', array( __CLASS__, 'registerMetabox') );

			// Attach certifications to entry when saving an entry.
			add_action( 'cn_process_taxonomy-category', array( __CLASS__, 'attachCertifications' ), 9, 2 );

			// Add support for CSV Import.
			add_filter( 'cncsv_map_import_fields', array( __CLASS__, 'import_field_option' ) );
			add_action( 'cncsv_import_fields', array( __CLASS__, 'import_field' ), 10, 3 );

			// Add support for CSV Export.
			add_filter( 'cn_csv_export_fields_config', array( __CLASS__, 'export_field_config' ) );
			add_filter( 'cn_csv_export_fields', array( __CLASS__, 'export_field_header' ) );
			add_filter( 'cn_export_header-certifications', array( __CLASS__, 'export_header' ), 10, 3 );
			add_filter( 'cn_export_field-certifications', array( __CLASS__, 'export_data' ), 10, 4 );

			// Add the "Certifications" option to the admin settings page.
			// This is also required so it'll be rendered by $entry->getContentBlock( 'certifications' ).
			add_filter( 'cn_content_blocks', array( __CLASS__, 'settingsOption') );

			// Add the action that'll be run when calling $entry->getContentBlock( 'certifications' ) from within a template.
			add_action( 'cn_entry_output_content-certifications', array( __CLASS__, 'block' ), 10, 3 );

			// Register the widget.
			add_action( 'widgets_init', array( 'CN_Certifications_Widget', 'register' ) );
		}

		/**
		 * The widget.
		 *
		 * @access private
		 * @since  1.0
		 * @static
		 * @return void
		 */
		private static function loadDependencies() {

			require_once( self::$path . 'includes/class.widgets.php' );
		}

		/**
		 * Register the taxonomy with the Gravity Forms Connector.
		 *
		 * @since 1.3
		 *
		 * @param array $taxonomy
		 *
		 * @return array
		 */
		public static function registerCertificationsTaxonomy( $taxonomy ) {

			$taxonomy['certification'] = array(
				'labels' => array(
					'name'          => _x(
						'Certifications',
						'Taxonomy field plural name.',
						'connections_gravity_forms'
					),
					'all_items'     => __( 'All Certifications', 'connections_gravity_forms' ),
					'select_items'  => __( 'Select Certifications', 'connections_gravity_forms' ),
					'singular_name' => _x(
						'Certification',
						'Taxonomy field singular name.',
						'connections_gravity_forms'
					),
					'field_label'   => __( 'Entry Certifications', 'connections_gravity_forms' ),
				),
			);

			return $taxonomy;
		}

		public static function addMenu( $menu ) {

			$menu[66]  = array(
				'hook'       => 'certifications',
				'page_title' => 'Connections : ' . __( 'Certifications', 'connections_certifications' ),
				'menu_title' => __( 'Certifications', 'connections_certifications' ),
				'capability' => 'connections_edit_categories',
				'menu_slug'  => 'connections_certifications',
				'function'   => array( __CLASS__, 'showPage' ),
			);

			return $menu;
		}

		public static function showPage() {

			// Grab an instance of the Connections object.
			$instance = Connections_Directory();

			if ( $instance->dbUpgrade ) {

				include_once CN_PATH . 'includes/inc.upgrade.php';
				connectionsShowUpgradePage();
				return;
			}

			switch ( $_GET['page'] ) {

				case 'connections_certifications':
					include_once self::$path . 'includes/admin/pages/certifications.php';
					connectionsShowCertificationsPage();
					break;
			}
		}

		public static function removeViewAction( $actions ) {

			unset( $actions['view'] );

			return $actions;
		}

		/**
		 * Registered the custom metabox.
		 *
		 * @access private
		 * @since  1.0
		 * @static
		 */
		public static function registerMetabox() {

			$atts = array(
				'id'       => 'certifications',
				'title'    => __( 'Certifications', 'connections_certifications' ),
				//'pages'    => $pages,
				'context'  => 'side',
				'priority' => 'core',
				'callback' => array( __CLASS__, 'metabox' ),
			);

			cnMetaboxAPI::add( $atts );
		}

		/**
		 * The certifications metabox.
		 *
		 * @access public
		 * @since  1.0
		 *
		 * @param  cnEntry $entry   An instance of the cnEntry object.
		 * @param  array   $metabox The metabox options array from self::register().
		 */
		public static function metabox( $entry, $metabox ) {

			echo '<div class="certificationdiv" id="taxonomy-certification">';

			$style = <<<HEREDOC
<style type="text/css" scoped>
	.certificationdiv div.tabs-panel {
		min-height: 42px;
		max-height: 200px;
		overflow: auto;
		padding: 0 0.9em;
		border: solid 1px #ddd;
		background-color: #fdfdfd;
	}
	.certificationdiv ul.certificationchecklist ul {
		margin-left: 18px;
	}
</style>
HEREDOC;
			echo $style;

				echo '<div id="certification-all" class="tabs-panel">';

				cnTemplatePart::walker(
					'term-checklist',
					array(
						'name'     => 'entry_certification',
						'taxonomy' => 'certification',
						'selected' => cnTerm::getRelationships( $entry->getID(), 'certification', array( 'fields' => 'ids' ) ),
					)
				);

				echo '</div>';
			echo '</div>';
		}

		/**
		 * Add, update or delete the entry certifications.
		 *
		 * @access public
		 * @since  1.0
		 * @static
		 *
		 * @param  string $action The action to being performed to an entry.
		 * @param  int    $id     The entry ID.
		 */
		public static function attachCertifications( $action, $id ) {

			// Grab an instance of the Connections object.
			$instance = Connections_Directory();

			if ( isset( $_POST['entry_certification'] ) && ! empty( $_POST['entry_certification'] ) ) {

				$instance->term->setTermRelationships( $id, $_POST['entry_certification'], 'certification' );

			} else {

				$instance->term->setTermRelationships( $id, array(), 'certification' );
			}
		}

		/**
		 * Add the field to the choices available to map to a CSV file field.
		 *
		 * @param array $fields
		 *
		 * @return array
		 */
		public static function import_field_option( $fields ) {

			$fields['certifications'] = 'Certifications';

			return $fields;
		}

		/**
		 * Import certifications and attach them to the entry.
		 *
		 * @param int         $id    The entry ID.
		 * @param array       $row   The parsed data from the CSV file.
		 * @param cnCSV_Entry $entry An instance of the cnCSV_Entry object.
		 */
		public static function import_field( $id, $row, $entry ) {

			$termIDs = array();

			$parsed = $entry->arrayPull( $row, 'certifications', $termIDs );

			if ( ! empty( $parsed ) ) {

				/*
				 * Convert the supplied certifications to an array and sanitize.
				 * Apply the same filters added to the core WP default filters for `pre_term_name` so the certification name
				 * will return a match if it exists.
				 */
				$certifications = explode( ',', $parsed );
				$certifications = array_map( 'sanitize_text_field', $certifications );
				$certifications = array_map( 'wp_filter_kses', $certifications );
				$certifications = array_map( '_wp_specialchars', $certifications );

				foreach ( $certifications as $certification ) {

					// Query the db for the term to be added.
					$term = cnTerm::getBy( 'name', $certification, 'certification' );

					if ( $term instanceof cnTerm_Object ) {

						$termIDs[] = $term->term_id;

					} else {

						// Add the new certification.
						$insert_result = cnTerm::insert( $certification, 'certification', array( 'slug' => '', 'parent' => '0', 'description' => '' ) );

						if ( ! is_wp_error( $insert_result ) ) {

							$termIDs[] = $insert_result['term_id'];
						}
					}
				}

			}

			// Do not set certification relationships if $termIDs is empty because if updating, it will delete existing relationships.
			if ( ! empty( $termIDs ) ) Connections_Directory()->term->setTermRelationships( $id, $termIDs, 'certification' );
		}

		/**
		 * Callback for the `cn_csv_export_fields_config` filter.
		 *
		 * Add the certifications export configurations option to the export config.
		 *
		 * @since 1.2
		 *
		 * @param array $fields
		 *
		 * @return array
		 */
		public static function export_field_config( $fields ) {

			$fields[] = array(
				'field'  => 'certifications',
				'type'   => 'certifications',
				//'fields' => '',
				//'table'  => CN_ENTRY_TABLE_META,
				//'types'  => NULL,
			);

			return $fields;
		}

		/**
		 * Callback for the `cn_csv_export_fields` filter.
		 *
		 * Set the column header name.
		 *
		 * @since 1.2
		 *
		 * @param array $fields
		 *
		 * @return array
		 */
		public static function export_field_header( $fields ) {

			$fields['certifications'] = 'Certifications';

			return $fields;
		}

		/**
		 * Callback for the `cn_export_header-certifications` filter.
		 *
		 * Returns the CSV file header name for the Certifications column.
		 *
		 * @since 1.2
		 *
		 * @param string                 $header
		 * @param array                  $atts
		 * @param cnCSV_Batch_Export_All $export
		 *
		 * @return string
		 */
		public static function export_header( $header, $atts, $export ) {

			return 'Certifications';
		}

		/**
		 * Callback for the `cn_export_field-certifications` filter.
		 *
		 * Export the data.
		 *
		 * @since 1.2
		 *
		 * @param string                 $data
		 * @param object                 $entry
		 * @param array                  $field
		 * @param cnCSV_Batch_Export_All $export
		 *
		 * @return string
		 */
		public static function export_data( $data, $entry, $field, $export ) {

			$data = '';

			// Process terms table and list all certifications in a single cell...
			$names = array();

			$terms = $export->getTerms( $entry->id, 'certification' );

			foreach ( $terms as $term ) {

				$names[] = $term->name;
			}

			if ( ! empty( $names ) ) {

				$data = $export->escapeAndQuote( implode( ',', $names ) );
			}

			return $data;
		}

		/**
		 * Add the custom meta as an option in the content block settings in the admin.
		 * This is required for the output to be rendered by $entry->getContentBlock().
		 *
		 * @access private
		 * @since  1.0
		 * @param  array  $blocks An associative array containing the registered content block settings options.
		 * @return array
		 */
		public static function settingsOption( $blocks ) {

			$blocks['certifications'] = __( 'Certifications', 'connections_certifications' );

			return $blocks;
		}

		/**
		 * Callback for the `cn_entry_output_content-{id}` action.
		 * @see cnOutput::getContentBlock()
		 *
		 * Renders the Certifications content block.
		 *
		 * @internal
		 * @since 1.0
		 *
		 * @param cnEntry    $object
		 * @param array      $atts     The shortcode atts array passed from the calling action.
		 * @param cnTemplate $template
		 */
		public static function block( $object, $atts, $template ) {

			global $wp_rewrite;

			$defaults = array(
				'container_tag'    => 'div',
				//'label_tag'        => 'span',
				'item_tag'         => 'span',
				'type'             => 'list',
				'list'             => 'unordered',
				//'label'            => __( 'Certifications:', 'connections_certifications' ) . ' ',
				'separator'        => ', ',
				'parent_separator' => ' &raquo; ',
				'before'           => '',
				'after'            => '',
				'link'             => FALSE,
				'parents'          => FALSE,
				//'child_of'         => 0,
				//'return'           => FALSE,
			);

			/**
			 * Allow extensions to filter the method default and supplied args.
			 *
			 * @since 1.0
			 */
			$atts = cnSanitize::args(
				apply_filters( 'cn_output_atts_certification', $atts ),
				apply_filters( 'cn_output_default_atts_certification', $defaults )
			);

			$terms = cnRetrieve::entryTerms( $object->getId(), 'certification' );

			if ( empty( $terms ) ) {

				return;
			}

			$count = count( $terms );
			$html  = '';
			$label = '';
			$items = array();

			if ( 'list' == $atts['type'] ) {

				$atts['item_tag'] = 'li';
			}

			$i = 1;

			foreach ( $terms as $term ) {

				$text = '';
				//$text .= esc_html( $term->name );

				if ( $atts['parents'] ) {

					// If the term is a root parent, skip.
					if ( 0 !== $term->parent ) {

						$text .= self::getTermParents(
							$term->parent,
							'certification',
							array(
								'link'       => $atts['link'],
								'separator'  => $atts['parent_separator'],
								'force_home' => $object->directoryHome['force_home'],
								'home_id'    => $object->directoryHome['page_id'],
							)
						);
					}
				}

				$atts['link'] = FALSE;
				if ( $atts['link'] ) {

					$rel = is_object( $wp_rewrite ) && $wp_rewrite->using_permalinks() ? 'rel="category tag"' : 'rel="category"';

					$url = cnTerm::permalink(
						$term,
						'category',
						array(
							'force_home' => $object->directoryHome['force_home'],
							'home_id'    => $object->directoryHome['page_id'],
						)
					);

					$text .= '<a href="' . $url . '" ' . $rel . '>' . esc_html( $term->name ) . '</a>';

				} else {

					$text .= esc_html( $term->name );
				}

				/**
				 * @since 1.0
				 */
				$items[] = apply_filters(
					'cn_entry_output_certification_item',
					sprintf(
						'<%1$s class="cn-certification-name cn-certification-%2$d">%3$s%4$s</%1$s>',
						$atts['item_tag'],
						$term->term_id,
						$text,
						$count > $i && 'list' !== $atts['type'] ? esc_html( $atts['separator'] ) : ''
					),
					$term,
					$count,
					$i,
					$atts
				);

				$i++; // Increment here so the correct value is passed to the filter.
			}

			/*
			 * Remove NULL, FALSE and empty strings (""), but leave values of 0 (zero).
			 * Filter our these in case someone hooks into the `cn_entry_output_category_item` filter and removes a category
			 * by returning an empty value.
			 */
			$items = array_filter( $items, 'strlen' );

			/**
			 * @since 1.0
			 */
			$items = apply_filters( 'cn_entry_output_certification_items', $items );

			if ( 'list' == $atts['type'] ) {

				$html .= sprintf(
					'<%1$s class="cn-certification-list">%2$s</%1$s>',
					'unordered' === $atts['list'] ? 'ul' : 'ol',
					implode( '', $items )
				);

			} else {

				$html .= implode( '', $items );
			}

			/**
			 * @since 1.0
			 */
			$html = apply_filters(
				'cn_entry_output_certification_container',
				sprintf(
					'<%1$s class="cn-certifications">%2$s</%1$s>' . PHP_EOL,
					$atts['container_tag'],
					$atts['before'] . $label . $html . $atts['after']
				),
				$atts
			);

			echo $html;
		}

		/**
		 * Retrieve category parents with separator.
		 *
		 * NOTE: This is the Connections equivalent of @see get_category_parents() in WordPress core ../wp-includes/category-template.php
		 *
		 * @access public
		 * @since  8.5.18
		 * @static
		 *
		 * @param int    $id        Term ID.
		 * @param string $taxonomy  Term taxonomy.
		 * @param array  $atts      The attributes array. {
		 *
		 *     @type bool   $link       Whether to format as link or as a string.
		 *                              Default: FALSE
		 *     @type string $separator  How to separate categories.
		 *                              Default: '/'
		 *     @type bool   $nicename   Whether to use nice name for display.
		 *                              Default: FALSE
		 *     @type array  $visited    Already linked to categories to prevent duplicates.
		 *                              Default: array()
		 *     @type bool   $force_home Default: FALSE
		 *     @type int    $home_id    Default: The page set as the directory home page.
		 * }
		 *
		 * @return string|WP_Error A list of category parents on success, WP_Error on failure.
		 */
		public static function getTermParents( $id, $taxonomy = 'certification', $atts = array() ) {

			$defaults = array(
				'link'       => FALSE,
				'separator'  => '/',
				'nicename'   => FALSE,
				'visited'    => array(),
				'force_home' => FALSE,
				'home_id'    => cnSettingsAPI::get( 'connections', 'connections_home_page', 'page_id' ),
			);

			$atts = cnSanitize::args( $atts, $defaults );

			$chain  = '';
			$parent = cnTerm::get( $id, $taxonomy );

			if ( is_wp_error( $parent ) ) {

				return $parent;
			}

			if ( $atts['nicename'] ) {

				$name = $parent->slug;

			} else {

				$name = $parent->name;
			}

			if ( $parent->parent && ( $parent->parent != $parent->term_id ) && ! in_array( $parent->parent,  $atts['visited'] ) ) {

				$atts['visited'][] = $parent->parent;

				$chain .= self::getTermParents( $parent->parent, 'certification', $atts );
			}

			if ( $atts['link'] ) {

				$chain .= '<span class="cn-category-breadcrumb-item" id="cn-category-breadcrumb-item-' . esc_attr( $parent->term_id ) . '">' . '<a href="' . esc_url( cnTerm::permalink( $parent->term_id, 'category', $atts ) ) . '">' . $name . '</a>' . $atts['separator'] . '</span>';

			} else {

				$chain .= $name . esc_html( $atts['separator'] );
			}

			return $chain;
		}
	}

	/**
	 * Start up the extension.
	 *
	 * @access public
	 * @since 1.0
	 *
	 * @return Connections_Certifications|false
	 */
	function Connections_Certifications() {

		if ( class_exists( 'connectionsLoad' ) ) {

			return new Connections_Certifications();

		} else {

			add_action(
				'admin_notices',
				function() {
					echo '<div id="message" class="error"><p><strong>ERROR:</strong> Connections must be installed and active in order use Connections Certifications.</p></div>';
				}
			);

			return false;
		}
	}

	/**
	 * Since Connections loads at default priority 10, and this extension is dependent on Connections,
	 * we'll load with priority 11 so we know Connections will be loaded and ready first.
	 */
	add_action( 'plugins_loaded', 'Connections_Certifications', 11 );

	// Support the Gravity Form Connector, register Disciplines Taxonomy.
	// NOTE: Must add filter before the `plugins_loaded` action.
	add_filter( 'Connections_Directory\Connector\Gravity_Forms\Register_Taxonomy_Fields', array( 'Connections_Certifications', 'registerCertificationsTaxonomy' ) );

}
