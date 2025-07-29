<?php
/**
 * Admin panel metaboxes
 *
 * @link  http://www.powerfulwp.com
 * @since 1.0.0
 *
 * @package    LDDFW
 * @subpackage LDDFW/includes
 * @author     powerfulwp <apowerfulwp@gmail.com>
 */

/**
 * Admin panel metaboxes
 *
 * @link  http://www.powerfulwp.com
 * @since 1.0.0
 *
 * @package    LDDFW
 * @subpackage LDDFW/includes
 * @author     powerfulwp <apowerfulwp@gmail.com>
 */
function lddfw_metaboxes() {
	add_meta_box(
		'lddfw_metaboxes',
		__( 'Delivery Driver', 'lddfw' ),
		'lddfw_metaboxes_build',
		'shop_order',
		'side',
		'default'
	);
}

add_action( 'add_meta_boxes', 'lddfw_metaboxes' );

/**
 * Building the metabox.
 */
function lddfw_metaboxes_build() {
	global $post;

	echo '<input type="hidden" name="lddfw_metaboxes_key" id="lddfw_metaboxes_key" value="' . esc_attr( wp_create_nonce( 'lddfw-save-order' ) ) . '" />';

	$lddfw_driverid = get_post_meta( $post->ID, 'lddfw_driverid', true );

	echo '<div class="lddfw-driver-box">
	<label>' . esc_html( __( 'Driver', 'lddfw' ) ) . '</label>';
	$drivers = LDDFW_Driver::lddfw_get_drivers();

	echo esc_html( lddfw_driver_drivers_selectbox( $drivers, $lddfw_driverid, $post->ID, '' ) );

	if ( lddfw_fs()->is__premium_only() ) {
		if ( lddfw_fs()->can_use_premium_code() ) {
			// Driver Commission.
			echo '<p>
			<label>' . esc_html( __( 'Driver Commission', 'lddfw' ) ) . '</label>
			<br>' . lddfw_currency_symbol() . ' <input size="5" name="lddfw_driver_commission" id="lddfw_driver_commission" type="text" value="' . esc_attr( get_post_meta( $post->ID, 'lddfw_driver_commission', true ) ) . '">';
			$lddfw_driver_commission_note = get_post_meta( $post->ID, '_lddfw_driver_commission_note', true );
			if ( '' !== $lddfw_driver_commission_note ) {
				echo '<br>' . $lddfw_driver_commission_note;
			}
			echo '</p>';

			// Delivery Note to the Driver.
			$lddfw_delivery_note_to_driver = get_post_meta( $post->ID, '_lddfw_delivery_note_to_driver', true );
			echo '<p>
			<label>' . esc_html( __( 'Note to the driver', 'lddfw' ) ) . '</label>
			<textarea type="text" style="width: 100%;height: 50px;" name="_lddfw_delivery_note_to_driver" id="_lddfw_delivery_note_to_driver" class="input-text" >' . esc_attr( $lddfw_delivery_note_to_driver ) . '</textarea>';
			echo '</p>';

		}
	}

	/* driver note */
	$lddfw_driver_note = get_post_meta( $post->ID, 'lddfw_driver_note', true );
	if ( '' !== $lddfw_driver_note ) {
		echo '<p><label>' . esc_html( __( 'Driver Note', 'lddfw' ) ) . '</label><br>';
			echo $lddfw_driver_note;
			echo '</p>';
	}

	$lddfw_delivered_date = get_post_meta( $post->ID, 'lddfw_delivered_date', true );
	if ( '' !== $lddfw_delivered_date ) {
		echo '<p><label>' . esc_html( __( 'Delivered Date', 'lddfw' ) ) . '</label><br>';
		echo $lddfw_delivered_date;
		echo '</p>';
	}

	$lddfw_failed_attempt_date = get_post_meta( $post->ID, 'lddfw_failed_attempt_date', true );
	if ( '' !== $lddfw_failed_attempt_date ) {
		echo '<p><label>' . esc_html( __( 'Failed Attempt Date', 'lddfw' ) ) . '</label><br>';
		echo $lddfw_failed_attempt_date;
		echo '</p>';
	}

	if ( lddfw_fs()->is__premium_only() ) {
		if ( lddfw_fs()->can_use_premium_code() ) {

			// Signature.
			$lddfw_order_signature = get_post_meta( $post->ID, 'lddfw_order_signature' );
			if ( ! empty( $lddfw_order_signature ) ) {
				echo '<p><label>' . esc_html( __( 'Signature', 'lddfw' ) ) . '</label><br>';
				foreach ( array_reverse( $lddfw_order_signature ) as $value ) {
					echo '<a href="' . esc_attr( $value ) . '" class="lddfw_order_image" target="_blank"><img style="max-width:100%" src="' . esc_attr( $value ) . '"></a>';
				}
				echo '</p>';
			}

			// Photo.
			$lddfw_order_delivery_image = get_post_meta( $post->ID, 'lddfw_order_delivery_image' );
			if ( ! empty( $lddfw_order_delivery_image ) ) {
				echo '<p><label>' . esc_html( __( 'Photo', 'lddfw' ) ) . '</label><br>';
				foreach ( array_reverse( $lddfw_order_delivery_image ) as $value ) {
					echo '<a href="' . esc_attr( $value ) . '" class="lddfw_order_image" target="_blank"><img style="max-width:100%" src="' . esc_attr( $value ) . '"></a>';
				}
				echo '</p>';
			}
		}
	}
	echo '</div> ';
}
/**
 * Drivers selectbox.
 *
 * @param object $drivers drivers object.
 * @param int    $driver_id user id number.
 * @param int    $order_id order number.
 * @param string $type type.
 * @return void
 */
function lddfw_driver_drivers_selectbox( $drivers, $driver_id, $order_id, $type ) {
	if ( 'bulk' === $type ) {
		echo "<select name='lddfw_driverid_" . esc_attr( $order_id ) . "' id='lddfw_driverid_" . esc_attr( $order_id ) . "'>";
	} else {
		echo "<select name='lddfw_driverid' id='lddfw_driverid_" . esc_attr( $order_id ) . "' order='" . esc_attr( $order_id ) . "' class='widefat'>";
	}
	echo "<option value=''>" . esc_html( __( 'Assign a driver', 'lddfw' ) ) . '</option>
    ';
	$last_availability = '';
	foreach ( $drivers as $driver ) {
		$driver_name    = $driver->display_name;
		$availability   = get_user_meta( $driver->ID, 'lddfw_driver_availability', true );
		$driver_account = get_user_meta( $driver->ID, 'lddfw_driver_account', true );
		$availability   = '1' === $availability ? esc_attr( __( 'Available', 'lddfw' ) ) : esc_attr( __( 'Unavailable', 'lddfw' ) );
		$selected       = '';
		if ( intval( $driver_id ) === $driver->ID ) {
			$selected = 'selected';
		}
		if ( $last_availability !== $availability ) {
			if ( '' !== $last_availability ) {
				echo '</optgroup>';
			}
			echo '<optgroup label="' . esc_attr( $availability . ' ' . __( 'drivers', 'lddfw' ) ) . '">';
			$last_availability = $availability;
		}
		if ( '1' === $driver_account || ( '1' !== $driver_account && intval( $driver_id ) === $driver->ID ) ) {
			echo '<option ' . esc_attr( $selected ) . ' value="' . esc_attr( $driver->ID ) . '">' . esc_html( $driver_name ) . '</option>';
		}
	}
	echo '</optgroup></select>';
}



/**
 * Save the Metabox Data
 *
 * @param int    $post_id post number.
 * @param object $post post object.
 */
function lddfw_driver_save_order_details( $post_id, $post ) {

	$driver = new LDDFW_Driver();
	if (
		! isset( $_POST['lddfw_metaboxes_key'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lddfw_metaboxes_key'] ) ), 'lddfw-save-order' )
	) {
		return $post->ID;
	}

	if ( lddfw_fs()->is__premium_only() ) {
		if ( lddfw_fs()->can_use_premium_code() ) {
			// Save the driver commissions.
			if ( isset( $_POST['lddfw_driver_commission'] ) ) {
				$lddfw_driver_commission = sanitize_text_field( wp_unslash( $_POST['lddfw_driver_commission'] ) );
				if ( is_numeric( $lddfw_driver_commission ) ) {
					lddfw_update_post_meta( $post_id, 'lddfw_driver_commission', $lddfw_driver_commission );
				} else {
					lddfw_delete_post_meta( $post_id, 'lddfw_driver_commission' );
				}
			}
			// Save delivery note to driver.
			if ( isset( $_POST['_lddfw_delivery_note_to_driver'] ) ) {
				$lddfw_delivery_note_to_driver = sanitize_text_field( wp_unslash( $_POST['_lddfw_delivery_note_to_driver'] ) );
				update_post_meta( $post_id, '_lddfw_delivery_note_to_driver', $lddfw_delivery_note_to_driver );
			}
		}
	}

	if ( isset( $_POST['lddfw_driverid'] ) ) {
		$lddfw_driverid                            = sanitize_text_field( wp_unslash( $_POST['lddfw_driverid'] ) );
		$lddfw_driver_order_meta['lddfw_driverid'] = $lddfw_driverid;
	}

	foreach ( $lddfw_driver_order_meta as $key => $value ) {
		/**
		 * Cycle through the $thccbd_meta array!
		 */
		if ( 'revision' === $post->post_type ) {
			/**
			 * Don't store custom data twice
			 */
			return;
		}

		$value = implode( ',', (array) $value );

		if ( 'shop_order' === get_post_type( $post ) ) {
			$driver->assign_delivery_driver( $post->ID, $value, 'store' );
		}

		if ( ! $value ) {
			/**
			 * Delete if blank
			 */
			lddfw_delete_post_meta( $post->ID, $key );

		}
	}
}
add_action( 'save_post', 'lddfw_driver_save_order_details', 10, 2 ); // Save the custom fields.
