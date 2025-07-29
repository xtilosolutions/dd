<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link  http://www.powerfulwp.com
 * @since 1.0.0
 *
 * @package    LDDFW
 * @subpackage LDDFW/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    LDDFW
 * @subpackage LDDFW/public
 * @author     powerfulwp <apowerfulwp@gmail.com>
 */
class LDDFW_Public {


	/**
	 * The ID of this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in LDDFW_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The LDDFW_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in LDDFW_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The LDDFW_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
	}

	/**
	 * Show content in customer order
	 *
	 * @since 1.3.0
	 */
	public function lddfw_action_order_details_before_order_table( $order, $sent_to_admin = '', $plain_text = '', $email = '' ) {

		// Only on "My Account" > "Order View".
		if ( is_wc_endpoint_url( 'view-order' ) ) {

			// Order Status.
			$order_status = $order->get_status();

			// Driver id.
			$lddfw_driver_id = $order->get_meta( 'lddfw_driverid' );

			if ( '' !== $lddfw_driver_id ) {

				if ( lddfw_fs()->is__premium_only() ) {
					if ( lddfw_fs()->is_plan( 'premium', true ) ) {

							// Check if order is out for delivery.
						if ( get_option( 'lddfw_out_for_delivery_status', '' ) === 'wc-' . $order_status ) {
							// Tracking.
							if ( '' !== get_option( 'lddfw_tracking_page', '' ) ) {
								$tracking_url = lddfw_tracking_page_url__premium_only( $order->get_id() );
								if ( '' !== $tracking_url ) {
									?>
											<p>
												<a class="button" href="<?php echo $tracking_url; ?>" ><?php echo esc_html__( 'Track your order', 'lddfw' ); ?></a>
											</p>
										<?php
								}
							}
						}

							$driver = new LDDFW_Driver();
						if ( '' !== $lddfw_driver_id ) {
							echo $driver->get_driver_info__premium_only( $lddfw_driver_id, 'html' );
							echo $driver->get_vehicle_info__premium_only( $lddfw_driver_id, 'html' );
						}
					}
				}

				/* driver note */
				$lddfw_driver_note = $order->get_meta( 'lddfw_driver_note' );
				if ( '' !== $lddfw_driver_note ) {
					echo '<p><b>' . esc_html( __( 'Driver note', 'lddfw' ) ) . ':</b><br> ' . $lddfw_driver_note . '</p>';
				}

				if ( lddfw_fs()->is__premium_only() ) {
					if ( lddfw_fs()->is_plan( 'premium', true ) ) {

						// Signature.
						$lddfw_order_signature = $order->get_meta( 'lddfw_order_last_signature' );
						if ( '' !== $lddfw_order_signature ) {
							echo '<p><b>';
							echo esc_html( __( 'Signature', 'lddfw' ) ) . '</b><br>';
							echo '<a href="' . esc_attr( $lddfw_order_signature ) . '" target="_blank"><img style="width:auto;max-width:100%" src="' . esc_attr( $lddfw_order_signature ) . '"></a>';
							echo '</p>';
						}

						// Photo.
						$lddfw_order_delivery_image = $order->get_meta( 'lddfw_order_last_delivery_image' );
						if ( '' !== $lddfw_order_delivery_image ) {
							echo '<p><b>';
							echo esc_html( __( 'Photo', 'lddfw' ) ) . '</b><br>';
							echo '<a href="' . esc_attr( $lddfw_order_delivery_image ) . '" target="_blank"><img style="width:auto;max-width:100%" src="' . esc_attr( $lddfw_order_delivery_image ) . '"></a>';
							echo '</p>';
						}
					}
				}
			}
		}
	}

	/**
	 * Set the driver page.
	 *
	 * @since 1.0.0
	 */
	public function lddfw_page_template( $page_template ) {
		global $post;
		if ( ! empty( $post ) ) {
			if ( $post->ID === intval( get_option( 'lddfw_delivery_drivers_page', '' ) ) ) {
				$page_template = WP_PLUGIN_DIR . '/' . LDDFW_FOLDER . '/index.php';
			}
			if ( $post->ID === intval( get_option( 'lddfw_tracking_page', '' ) ) ) {
				$page_template = WP_PLUGIN_DIR . '/' . LDDFW_FOLDER . '/tracking.php';
			}
		}
		return $page_template;
	}

}
