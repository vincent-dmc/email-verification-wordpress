<?php


if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


/************************** CREATE A PACKAGE CLASS *****************************
 *******************************************************************************
 * Create a new list table package that extends the core WP_List_Table class.
 * WP_List_Table contains most of the framework for generating the table, but we
 * need to define and override some methods so that our data can be displayed
 * exactly the way we need it to be.
 *
 * To display this example on a page, you will first need to instantiate the class,
 * then call $yourInstance->prepare_items() to handle any data manipulation, then
 * finally call $yourInstance->display() to render the table to the page.
 *
 */
class HardBounceCleaner_List_Table extends WP_List_Table {


	/** ************************************************************************
	 * REQUIRED. Set up a constructor that references the parent constructor. We
	 * use the parent reference to set some default configs.
	 ***************************************************************************/
	function __construct() {
		global $status, $page;

		//Set parent defaults
		parent::__construct( array(
			'singular' => 'email',     //singular name of the listed records
			'plural'   => 'emails',    //plural name of the listed records
			'ajax'     => false        //does this table support ajax?
		) );

	}

	/** ************************************************************************
	 * Recommended. This method is called when the parent class can't find a method
	 * specifically build for a given column. Generally, it's recommended to include
	 * one method for each column you want to render, keeping your package class
	 * neat and organized. For example, if the class needs to process a column
	 * named 'title', it would first see if a method named $this->column_title()
	 * exists - if it does, that method will be used. If it doesn't, this one will
	 * be used. Generally, you should try to use custom column methods as much as
	 * possible.
	 *
	 * Since we have defined a column_title() method later on, this method doesn't
	 * need to concern itself with any column with a name of 'title'. Instead, it
	 * needs to handle everything else.
	 *
	 * For more detailed insight into how columns are handled, take a look at
	 * WP_List_Table::single_row_columns()
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 * @param string $column_name The name/slug of the column to be processed
	 *
	 * @return string Text or HTML to be placed inside the column <td>
	 **************************************************************************/
	function column_default( $item, $column_name ) {

		$api_key       = get_option( EVH_PLUGIN_PREFIX . '_api_key', null );
		$api_key_valid = get_option( EVH_PLUGIN_PREFIX . '_api_key_valid', null );

		$hbc_connection = false;
		if ( $api_key !== null && $api_key_valid === 1 ) {
			$hbc_connection = true;
		}

		switch ( $column_name ) {
			case 'email':

				$color = 'purple';
				if ( $hbc_connection ) {

				}
				if ( $item['mx'] == 1 ) {
					$color = 'red';
				}
				if ( $item['role'] === null) {
					$color = 'blue';
				}

				return '<span style="color: ' . $color . '" title="' . $item[ $column_name ] . '">' . $item[ $column_name ] . '</span>';
			case 'created_at':
				if ( $item[ $column_name ] !== null ) {
					return date( get_option( 'date_format', "F j, Y" ), strtotime( $item[ $column_name ] ) );
				}

				return '';
			case 'role':
				if ( $item[ $column_name ] ) {
					return __('yes');
				}

				return '';
			case 'free':
				if ( $item[ $column_name ] ) {
					return __('yes');
				}

				return '';
			case 'disposable':
				if ( $item[ $column_name ] ) {
					return __('yes');
				}

				return '';
			case 'mx':
				if ( $item[ $column_name ] ) {
					return __('error');
				}

				return '';
			case 'risky':
				if ( $hbc_connection === false ) {
					return '<span class="label label-info">'.__('HardBouncerCleaner needed').'</span>';
				}
				if ( $item[ $column_name ] ) {
					return __('yes');
				}

				return '';
			case 'status':
				if ( $hbc_connection === false ) {
					return '<span class="label label-info">'.__('HardBouncerCleaner needed').'</span>';
				}

				return $item[ $column_name ];
			default:
				return $item[ $column_name ];
		}
	}

	/** ************************************************************************
	 * Recommended. This is a custom column method and is responsible for what
	 * is rendered in any column with a name/slug of 'title'. Every time the class
	 * needs to render a column, it first looks for a method named
	 * column_{$column_title} - if it exists, that method is run. If it doesn't
	 * exist, column_default() is called instead.
	 *
	 * This example also illustrates how to implement rollover actions. Actions
	 * should be an associative array formatted as 'slug'=>'link html' - and you
	 * will need to generate the URLs yourself. You could even ensure the links
	 *
	 *
	 * @see WP_List_Table::::single_row_columns()
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 *
	 * @return string Text to be placed inside the column <td> (movie title only)
	 **************************************************************************/
	function column_title( $item ) {

	}

	/** ************************************************************************
	 * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
	 * is given special treatment when columns are processed. It ALWAYS needs to
	 * have it's own method.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 *
	 * @return string Text to be placed inside the column <td> (movie title only)
	 **************************************************************************/
	function column_cb( $item ) {

	}

	/** ************************************************************************
	 * REQUIRED! This is where you prepare your data for display. This method will
	 * usually be used to query the database, sort and filter the data, and generally
	 * get it ready to be displayed. At a minimum, we should set $this->items and
	 * $this->set_pagination_args(), although the following properties and methods
	 * are frequently interacted with here...
	 *
	 * @global WPDB $wpdb
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	function prepare_items() {

		/**
		 * First, lets decide how many records per page to show
		 */
		$per_page = 10;

		/**
		 * REQUIRED. Now we need to define our column headers. This includes a complete
		 * array of columns to be displayed (slugs & titles), a list of columns
		 * to keep hidden, and a list of columns that are sortable. Each of these
		 * can be defined in another method (as we've done here) before being
		 * used to build the value for our _column_headers property.
		 */
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		/**
		 * REQUIRED. Finally, we build an array to be used by the class for column
		 * headers. The $this->_column_headers property takes an array which contains
		 * 3 other arrays. One for all columns, one for hidden columns, and one
		 * for sortable columns.
		 */
		$this->_column_headers = array( $columns, $hidden, $sortable );

		/**
		 * REQUIRED for pagination. Let's figure out what page the user is currently
		 * looking at. We'll need this later, so you should always include it in
		 * your own package classes.
		 */
		$current_page = $this->get_pagenum();

		/**
		 * REQUIRED for pagination. Let's check how many items are in our data array.
		 * In real-world use, this would be the total number of items in your database,
		 * without filtering. We'll need this later, so you should always include it
		 * in your own package classes.
		 */
		$total_items = self::get_total();

		/**
		 * REQUIRED. Now we can add our *sorted* data to the items property, where
		 * it can be used by the rest of the class.
		 */
		$this->items = self::get_emails( $per_page, $current_page );


		/**
		 * REQUIRED. We also have to register our pagination options & calculations.
		 */
		$this->set_pagination_args( array(
			'total_items' => $total_items,                  //WE have to calculate the total number of items
			'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
			'total_pages' => ceil( $total_items / $per_page )   //WE have to calculate the total number of pages
		) );
	}

	/** ************************************************************************
	 * REQUIRED! This method dictates the table's columns and titles. This should
	 * return an array where the key is the column slug (and class) and the value
	 * is the column's title text. If you need a checkbox for bulk actions, refer
	 * to the $columns array below.
	 *
	 * The 'cb' column is treated differently than the rest. If including a checkbox
	 * column in your table you must create a column_cb() method. If you don't need
	 * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
	 **************************************************************************/
	function get_columns() {
		$columns = array(
			'created_at' => __('Date added'),
			'email'      => __('Email'),
			'role'       => __('Role'),
			'disposable' => __('Disposable'),
			'mx'         => __('Mx'),
			'risky'      => __('Risky'),
			'status'     => __('Email exist')
		);

		return $columns;
	}

	/** ************************************************************************
	 * Optional. If you want one or more columns to be sortable (ASC/DESC toggle),
	 * you will need to register it here. This should return an array where the
	 * key is the column that needs to be sortable, and the value is db column to
	 * sort by. Often, the key and value will be the same, but this is not always
	 * the case (as the value is a column name from the database, not the list table).
	 *
	 * This method merely defines which columns should be sortable and makes them
	 * clickable - it does not handle the actual sorting. You still need to detect
	 * the ORDERBY and ORDER querystring variables within prepare_items() and sort
	 * your data accordingly (usually by modifying your query).
	 *
	 * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
	 **************************************************************************/
	function get_sortable_columns() {
		$sortable_columns = array(
			'created_at' => array( 'created_at', true ),
			'email'      => array( 'email', false ),

			'role'       => array( 'role', false ),

//			'free'  => array('Free',false),
			'disposable' => array( 'disposable', false ),
			'mx'         => array( 'mx', false ),
			'risky'      => array( 'risky', false ),
			'status'     => array( 'status', false ),
		);

		return $sortable_columns;
	}

	/**
	 * @return int
	 */
	public static function get_total() {
		global $wpdb;

		$table_name_list = $wpdb->prefix . "email_verification_by_hardbouncecleaner_list";
		$sql             = "SELECT COUNT(*) AS total FROM $table_name_list ";
		$result          = $wpdb->get_row( $sql, 'ARRAY_A' );

		if ( ! isset( $result['total'] ) ) {
			return 0;
		}

		return (int) $result['total'];
	}

	/**
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return array|null|object
	 */
	public static function get_emails( $per_page = 5, $page_number = 1 ) {
		global $wpdb;

		$table_name_list = $wpdb->prefix . "email_verification_by_hardbouncecleaner_list";
		$sql             = "SELECT * FROM $table_name_list ";

		$orderby = null;
		if ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array( 'email', 'created_at', 'role', 'free', 'disposable', 'risky', 'status', 'mx' ) ) ) {
			$orderby = $_REQUEST['orderby'];
		}
		$order = 'ASC';
		if ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array( 'asc', 'desc' ) ) ) {
			$order = strtoupper( $_REQUEST['order'] );
		}
		if ( $orderby !== null ) {
			$sql .= ' ORDER BY ' . esc_sql( $orderby ) . ' ' . $order;
		} else {
			$sql .= ' ORDER BY created_at DESC ';
		}

		$sql .= " LIMIT $per_page";

		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;

		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}


}



