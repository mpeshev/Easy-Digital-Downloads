<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if( !class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class EDD_Gateway_Error_Log_Table extends WP_List_Table {

	var $per_page = 30;

	function __construct(){
		global $status, $page;

		//Set parent defaults
		parent::__construct( array(
			'singular'  => edd_get_label_singular(),    // singular name of the listed records
			'plural'    => edd_get_label_plural(),    	// plural name of the listed records
			'ajax'      => false             			// does this table support ajax?
		) );

	}


	function column_default( $item, $column_name ) {
		switch( $column_name ){

			case 'error' :
				return get_the_title( $item['ID'] ) ? get_the_title( $item['ID'] ) : __( 'Payment Error', 'edd' );
			case 'gateway' :
				return edd_get_payment_gateway( $item['payment_id'] );
			case 'buyer' :
				$user = ! empty( $item['user_id'] ) ? $item['user_id'] : $item['buyer'];
				return '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-payment-history&user=' ) . urlencode( $user ) . '">' . $item['buyer'] . '</a>';
			default:
				return $item[ $column_name ];

		}
	}

	function column_message( $item ) {
?>
		<a href="#TB_inline?width=640&amp;inlineId=log-message-<?php echo $item['ID']; ?>" class="thickbox" title="<?php _e( 'View Log Message', 'edd' ); ?> "><?php _e( 'View Log Message', 'edd' ); ?></a>
		<div id="log-message-<?php echo $item['ID']; ?>" style="display:none;">
			<?php

			$log_message = get_post_field( 'post_content', $item['ID'] );
			$serialized = strpos( $log_message, '{"' );

			// check to see if the log message contains serialized information
			if( $serialized !== false ) {

				$length  = strlen( $log_message ) - $serialized;
				$intro   = substr( $log_message, 0, - $length );
				$data    = substr( $log_message, $serialized, strlen( $log_message ) - 1 );

				echo wpautop( $intro );

				echo wpautop( __( '<strong>Log data:</strong>') );

				echo '<div style="word-wrap: break-word;">' . wpautop( $data ) . '</div>';

			} else {
				// no serialized data found
				echo wpautop( $log_message );
			}
			?>
		</div>
<?php
	}


	function get_columns() {
		$columns = array(
			'ID'         => __( 'Log ID', 'edd' ),
			'payment_id' => __( 'Payment ID', 'edd' ),
			'error'   => __( 'Error', 'edd' ),
			'message' => __( 'Error Message', 'edd' ),
			'gateway' => __( 'Gateway', 'edd' ),
			'buyer'   => __( 'Buyer', 'edd' ),
			'date'    => __( 'Date', 'edd' )
		);
		return $columns;
	}


	function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}

	function bulk_actions() {
		// these aren't really bulk actions but this outputs the markup in the right place
		edd_log_views();
	}

	function logs_data() {

		global $edd_logs;

		$logs_data = array();
		$paged     = $this->get_paged();
		$log_query = array(
			'log_type'    => 'gateway_error',
			'paged'       => $paged
		);

		$logs = $edd_logs->get_connected_logs( $log_query );

		if( $logs ) {

			foreach( $logs as $log ) {

				$user_info  = edd_get_payment_meta_user_info( $log->post_parent );
				$user_id 	= isset( $user_info['id']) ? $user_info['id'] : 0;
				$user_data 	= get_userdata( $user_id );

				$logs_data[] = array(
					'ID'         => $log->ID,
					'payment_id' => $log->post_parent,
					'error'      => 'error',
					'gateway'    => 'gateway',
					'user_id'    => $user_id,
					'buyer'	     => $user_data ? $user_data->display_name : $user_info['email'],
					'date'	     => $log->post_date
				);
			}
		}

		return $logs_data;
	}


	/** ************************************************************************
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/

	function prepare_items() {

		global $edd_logs;

		/**
		 * First, lets decide how many records per page to show
		 */
		$per_page = $this->per_page;

		$columns = $this->get_columns();

		$hidden = array(); // no hidden columns

		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$current_page = $this->get_pagenum();

		$this->items = $this->logs_data();

		$total_items = $edd_logs->get_log_count( 0, 'gateway_error' );

		$this->set_pagination_args( array(
				'total_items' => $total_items,                  	// WE have to calculate the total number of items
				'per_page'    => $per_page,                     	// WE have to determine how many items to show on a page
				'total_pages' => ceil( $total_items / $per_page )   // WE have to calculate the total number of pages
			)
		);
	}

}