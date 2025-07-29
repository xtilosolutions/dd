<?php
/**
 * Fired during plugin activation
 *
 * @link  http://www.powerfulwp.com
 * @since 1.0.0
 *
 * @package    LDDFW
 * @subpackage LDDFW/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    LDDFW
 * @subpackage LDDFW/includes
 * @author     powerfulwp <apowerfulwp@gmail.com>
 */
class LDDFW_Driver {
	/**
	 * Drivers query
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function lddfw_get_drivers() {
		$args = array(
			'role'           => 'driver',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'lddfw_driver_availability',
					'compare' => 'NOT EXISTS',
					'value'   => '',
				),
				array(
					'key'     => 'lddfw_driver_availability',
					'compare' => 'EXISTS',
				),
			),
			'orderby'        => 'meta_value ASC,display_name ASC',
			'posts_per_page' => -1,
		);
		return get_users( $args );
	}

	 /**
	  *  Get driver driving mode
	  *
	  * @param int    $driver_id The driver ID.
	  * @param string $type mode type.
	  * @return string
	  */
	public static function get_driver_driving_mode( $driver_id, $type ) {
		$driving_mode = 'DRIVING';
		if ( lddfw_fs()->is__premium_only() ) {
			if ( lddfw_fs()->is_plan( 'premium', true ) ) {
				$lddfw_driver_travel_mode = get_user_meta( $driver_id, 'lddfw_driver_travel_mode', true );
				if ( '' !== $lddfw_driver_travel_mode && false !== $lddfw_driver_travel_mode ) {
					$driving_mode = $lddfw_driver_travel_mode;
				}
			}
		}

		$driving_mode = 'lowercase' === $type ? strtolower( $driving_mode ) : $driving_mode;
		return $driving_mode;
	}

	/**
	 *  Assign delivery order
	 *
	 * @param int    $order_id The order ID.
	 * @param int    $driver_id The driver ID.
	 * @param string $operator The type.
	 * @return void
	 */
	public static function assign_delivery_driver( $order_id, $driver_id, $operator ) {

		$order = wc_get_order( $order_id );
		if ( false !== $order ) {

			$order_driverid = get_post_meta( $order_id, 'lddfw_driverid', true );

			// Delete driver cache.
			lddfw_delete_cache( 'driver', $order_driverid );

			// Delete orders cache.
			lddfw_delete_cache( 'orders', '' );

			$driver = get_userdata( $driver_id );
			if ( ! empty( $driver ) && $driver_id !== $order_driverid && '-1' !== $driver_id && '' !== $driver_id ) {

				// Delete driver cache.
				lddfw_delete_cache( 'driver', $driver_id );

				$driver_name = $driver->display_name;
				$note        = __( 'Delivery driver has been assigned to order.', 'lddfw' );

				$user_note = '';
				if ( lddfw_fs()->is__premium_only() ) {
					if ( lddfw_fs()->is_plan( 'premium', true ) ) {
						$current_user = wp_get_current_user();
						if ( $current_user->exists() ) {
							/* translators: %s: driver name */
							$user_note = sprintf( ' ' . __( 'by %s', 'lddfw' ), $current_user->display_name );
						}
						/* translators: %s: driver name */
						$note = sprintf( __( 'Delivery driver %1$s has been assigned to order %2$s', 'lddfw' ), $driver_name, $user_note );
					}
				}

				// Update order driver.
				lddfw_update_post_meta( $order_id, 'lddfw_driverid', $driver_id );

				// Update assigned date.
				update_user_meta( $driver_id, 'lddfw_assigned_date', date_i18n( 'Y-m-d H:i:s' ) );

				/**
				 * Update order status to driver assigned.
				*/
				$lddfw_driver_assigned_status = get_option( 'lddfw_driver_assigned_status', '' );
				$lddfw_processing_status      = get_option( 'lddfw_processing_status', '' );
				$current_order_status         = 'wc-' . $order->get_status();

				if ( '' !== $lddfw_driver_assigned_status && $current_order_status === $lddfw_processing_status ) {
					$order->update_status( $lddfw_driver_assigned_status, '' );
				}
				$order->save();

				if ( lddfw_fs()->is__premium_only() ) {
					if ( lddfw_fs()->is_plan( 'premium', true ) ) {

						/* Email driver. */
						WC_Emails::instance();
						do_action( 'lddfw_assigned_order_email_driver_notification', $order_id );

						/* Email admin when driver has been claimed order. */
						if ( 'driver' === $operator ) {
							do_action( 'lddfw_assigned_order_email_admin_notification', $order_id );
						}

						/* Email vendor. */
						$store     = new LDDFW_Store();
						$seller_id = $store->lddfw_order_seller( $order );
						if ( '' !== $seller_id ) {
							if ( lddfw_fs()->is__premium_only() ) {
								if ( lddfw_fs()->is_plan( 'premium', true ) ) {
									do_action( 'lddfw_assigned_order_email_vendor_notification', $order_id, $order, $seller_id );
								}
							}
						}

						/* Send whatsapp to driver */
						$lddfw_whatsapp_assign_to_driver = get_option( 'lddfw_whatsapp_assign_to_driver', '' );
						if ( '1' === $lddfw_whatsapp_assign_to_driver && 'store' === $operator ) {
							$whatsapp = new LDDFW_WHATSAPP();
							$result   = $whatsapp->lddfw_send_whatsapp_to_driver__premium_only( $order_id, $order, $driver_id );
							$note    .= ', ' . $result[1];
						}

						/* Send sms to driver */
						$lddfw_sms_assign_to_driver = get_option( 'lddfw_sms_assign_to_driver', '' );
						if ( '1' === $lddfw_sms_assign_to_driver && 'store' === $operator ) {
							$sms    = new LDDFW_SMS();
							$result = $sms->lddfw_send_sms_to_driver__premium_only( $order_id, $order, $driver_id );
							$note  .= ', ' . $result[1];
						}
					}
				}

				$order->add_order_note( $note );
			}
		}
	}

	/**
	 * Assign delivery permission
	 *
	 * @param int $order_seller_id seller id.
	 * @param int $driver_seller_id seller id.
	 * @return statement
	 */
	public function assign_driver_permission__premium_only( $order_seller_id, $driver_seller_id ) {
		$result = true;
		if ( has_filter( 'lddfw_assign_driver_permission' ) ) {
			$result = apply_filters( 'lddfw_assign_driver_permission', $order_seller_id, $driver_seller_id );
		}
		return $result;
	}

	/**
	 * Auto assign delivery orders
	 *
	 * @param int $order_id The order id.
	 * @return void
	 */
	public function auto_assign_delivery_drivers__premium_only( $order_id ) {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				' select mt.user_id, IFNULL( mt4.meta_value, "" ) as seller from ' . $wpdb->base_prefix . 'users u
				inner join ' . $wpdb->base_prefix . 'usermeta mt on mt.user_id = u.id and mt.meta_key = \'' . $wpdb->prefix . 'capabilities\'
				inner join ' . $wpdb->base_prefix . 'usermeta mt1 on mt1.user_id = u.id and mt1.meta_key = \'lddfw_driver_availability\'
				inner join ' . $wpdb->base_prefix . 'usermeta mt2 on mt2.user_id = u.id and mt2.meta_key = \'lddfw_driver_account\'
				left join ' . $wpdb->base_prefix . 'usermeta mt3 on mt3.user_id = u.id and mt3.meta_key = \'lddfw_assigned_date\'
				left join ' . $wpdb->base_prefix . 'usermeta mt4 on mt4.user_id = u.id and mt4.meta_key = \'ddfwm_vendor\'
				left join (
					select mt.meta_value as driver_id ,p.ID as orders
					from ' . $wpdb->prefix . 'posts p
					inner join ' . $wpdb->prefix . 'postmeta mt on mt.post_id = p.ID
					where post_type = \'shop_order\' and
					post_status in (%s,%s,%s,%s)
					and mt.meta_key = \'lddfw_driverid\'
					and mt.meta_value <> \'\' and mt.meta_value <> \'-1\'
				) t on t.driver_id = mt.user_id
				where
				mt.meta_value like %s and mt1.meta_value = \'1\' and mt2.meta_value = \'1\'
				group by mt.user_id
				order by count(t.orders) , mt3.meta_value
				',
				array(
					get_option( 'lddfw_driver_assigned_status', '' ),
					get_option( 'lddfw_processing_status', '' ),
					get_option( 'lddfw_out_for_delivery_status', '' ),
					get_option( 'lddfw_failed_attempt_status', '' ),
					'%\"driver\"%',
				)
			)
		); // db call ok; no-cache ok.

		if ( ! empty( $result ) ) {

			$enable_virtual_items     = get_option( 'lddfw_enable_virtual_items', '' );
			$assign_by_less_orders    = true;
			$lddfw_auto_assign_method = get_option( 'lddfw_auto_assign_method', '' );

			// Auto-assign methods that block assign by less orders.
			if ( in_array( $lddfw_auto_assign_method, array( '3', '5', '6' ) ) ) {
				$assign_by_less_orders = false;
			}

			// Get the order shipping address.
			$order        = wc_get_order( $order_id );
			$store        = new LDDFW_Store();
			$order_parent = $order->get_parent_id();

			$billing_city     = $order->get_billing_city();
			$billing_postcode = $order->get_billing_postcode();
			$billing_country  = $order->get_billing_country();
			$billing_state    = LDDFW_Order::lddfw_states( $billing_country, $order->get_billing_state() );
			if ( '' !== $billing_country ) {
				$billing_country = WC()->countries->countries[ $billing_country ];
			}
			$shipping_city     = $order->get_shipping_city();
			$shipping_postcode = $order->get_shipping_postcode();
			$shipping_country  = $order->get_shipping_country();
			$shipping_state    = LDDFW_Order::lddfw_states( $shipping_country, $order->get_shipping_state() );
			if ( '' !== $shipping_country ) {
				$shipping_country = WC()->countries->countries[ $shipping_country ];
			}

			// If shipping info is missing if set the billing info.
			if ( '' === $shipping_city ) {
				$shipping_city     = $billing_city;
				$shipping_state    = $billing_state;
				$shipping_postcode = $billing_postcode;
				$shipping_country  = $billing_country;
			}

			$order_seller_id      = $store->lddfw_order_seller( $order );
			$pickup_address_array = $store->lddfw_pickup_address( 'array', $order, $order_seller_id );

			// Set default value.
			$order_allow       = true;
			$pickup_city       = '';
			$order_assigned_to = '';

			// Set pickup address.
			if ( ! empty( $pickup_address_array ) ) {

				// Set pickup city.
				$pickup_city = $pickup_address_array['city'];

				// If auto assign by pickup address we set the pickup address.
				if ( '4' === $lddfw_auto_assign_method ) {
					$shipping_city     = $pickup_address_array['city'];
					$shipping_postcode = $pickup_address_array['zip'];
					$shipping_country  = $pickup_address_array['country'];
					$shipping_state    = $pickup_address_array['state'];
					$shipping_state    = LDDFW_Order::lddfw_states( $shipping_country, $shipping_state );
					if ( '' !== $shipping_country ) {
						$shipping_country = WC()->countries->countries[ $shipping_country ];
					}
				}
			}

			// Check if the seller is the admin.
			if ( '' !== $order_seller_id ) {
				$user = new WP_User( $order_seller_id, '', get_current_blog_id() );
				if ( in_array( 'administrator', (array) $user->roles, true ) ) {
					$order_seller_id = '';
				}
			}

			// Check if the order has a local pickup or that the shipping method has been disabled for auto assign drivers.
			foreach ( $order->get_items( 'shipping' ) as $item_id => $line_item ) {
				$shipping_data      = $line_item->get_data();
				$shipping_method_id = $shipping_data['method_id'];
				$instance_id        = absint( $shipping_data['instance_id'] );
				$shipping_method    = WC_Shipping_Zones::get_shipping_method( $instance_id );

				$lddfw_shipping_disable_auto_assign = '';
				if ( false !== $shipping_method ) {
					if ( $shipping_method->has_settings() ) {
						$instance_settings = $shipping_method->instance_settings;
						if ( ! empty( $instance_settings['lddfw_shipping_disable_auto_assign'] ) ) {
							$lddfw_shipping_disable_auto_assign = $instance_settings['lddfw_shipping_disable_auto_assign'];
						}
					}
				}

				if ( 'local_pickup' === $shipping_method_id || 'yes' === $lddfw_shipping_disable_auto_assign ) {
						$order_allow = false;
						break;
				}
			}

			// Check if the order has virtual products.
			foreach ( $order->get_items() as $order_item ) {
				$item = wc_get_product( $order_item->get_product_id() );
				if ( false !== $item ) {
					if ( $item->is_virtual() && '1' !== $enable_virtual_items ) {
						$order_allow = false;
						break;
					}
				}
			}

			// Check suborder permission.
			$auto_assign_suborders = get_option( 'lddfw_auto_assign_suborders' );
			if ( 0 !== $order_parent && '1' !== $auto_assign_suborders && false !== $auto_assign_suborders ) {
				$order_allow = false;
			}

			if ( true === $order_allow ) {

				 // Assign by shipping or pickup address.
				if ( '2' === $lddfw_auto_assign_method || '4' === $lddfw_auto_assign_method ) {

					// Assign order by zipcode.
					foreach ( $result as $driver ) {
						$driver_postcode = get_user_meta( $driver->user_id, 'billing_postcode', true );
						if ( $driver_postcode === $shipping_postcode && '' !== $driver_postcode ) {
							if ( $this->assign_driver_permission__premium_only( $order_seller_id, $driver->seller ) ) {
								$order_assigned_to = $driver->user_id;
								break;
							}
						}
					}

					// Assign order by city.
					if ( '' === $order_assigned_to ) {
						foreach ( $result as $driver ) {
							$driver_city = get_user_meta( $driver->user_id, 'billing_city', true );
							if ( $driver_city === $shipping_city && '' !== $driver_city ) {
								if ( $this->assign_driver_permission__premium_only( $order_seller_id, $driver->seller ) ) {
									$order_assigned_to = $driver->user_id;
									break;
								}
							}
						}
					}

					// Assign order by state.
					if ( '' === $order_assigned_to ) {
						foreach ( $result as $driver ) {
							$driver_state = get_user_meta( $driver->user_id, 'billing_state', true );
							if ( $driver_state === $shipping_state && '' !== $driver_state ) {
								if ( $this->assign_driver_permission__premium_only( $order_seller_id, $driver->seller ) ) {
									$order_assigned_to = $driver->user_id;
									break;
								}
							}
						}
					}

					// Assign order by country.
					if ( '' === $order_assigned_to ) {
						foreach ( $result as $driver ) {
							$driver_country = get_user_meta( $driver->user_id, 'billing_country', true );
							if ( $driver_country === $shipping_country && '' !== $driver_country ) {
								if ( $this->assign_driver_permission__premium_only( $order_seller_id, $driver->seller ) ) {
									$order_assigned_to = $driver->user_id;
									break;
								}
							}
						}
					}
				}

				// Assign order by shipping city.
				if ( '' === $order_assigned_to ) {
					if ( '3' === $lddfw_auto_assign_method || '6' === $lddfw_auto_assign_method ) {
						foreach ( $result as $driver ) {
							$driver_city = get_user_meta( $driver->user_id, 'billing_city', true );
							if ( $driver_city === $shipping_city && '' !== $driver_city ) {
								if ( $this->assign_driver_permission__premium_only( $order_seller_id, $driver->seller ) ) {
									$order_assigned_to = $driver->user_id;
									break;
								}
							}
						}
					}
				}

				// Assign order by pickup city.
				if ( '' === $order_assigned_to ) {
					if ( '5' === $lddfw_auto_assign_method || '6' === $lddfw_auto_assign_method ) {
						foreach ( $result as $driver ) {
							$driver_city = get_user_meta( $driver->user_id, 'billing_city', true );
							if ( $driver_city === $pickup_city && '' !== $driver_city ) {
								if ( $this->assign_driver_permission__premium_only( $order_seller_id, $driver->seller ) ) {
									$order_assigned_to = $driver->user_id;
									break;
								}
							}
						}
					}
				}

				// Assign by less orders.
				if ( '' === $order_assigned_to && true === $assign_by_less_orders ) {
					foreach ( $result as $driver ) {
						if ( $this->assign_driver_permission__premium_only( $order_seller_id, $driver->seller ) ) {
							$order_assigned_to = $driver->user_id;
							break;
						}
					}
				}

				if ( '' !== $order_assigned_to ) {
					$this->assign_delivery_driver( $order_id, $order_assigned_to, 'store' );
				}
			}
		}
	}

	 /**
	  * Edit driver form
	  *
	  * @since 1.5.0
	  * @param int $driver_id The driver_id.
	  * @return array
	  */
	public function lddfw_edit_driver_form( $driver_id ) {
		global $lddfw_wpnonce;
		$user_meta       = get_userdata( $driver_id );
		$first_name      = $user_meta->first_name;
		$last_name       = $user_meta->last_name;
		$email           = $user_meta->user_email;
		$billing_country = $user_meta->billing_country;
		$phone           = $user_meta->billing_phone;
		$city            = $user_meta->billing_city;
		$company         = $user_meta->billing_company;
		$address_1       = $user_meta->billing_address_1;
		$address_2       = $user_meta->billing_address_2;
		$postcode        = $user_meta->billing_postcode;
		$billing_state   = $user_meta->billing_state;

		$html              = '<form service="lddfw_edit_driver" class="lddfw_form" id="driver_form" ><div class="container">
			<div class="row">
				<div class="col-12">';
				$driver_id = $user_meta->ID;

					   $html .= '
									<input type="hidden" name="lddfw_driverid" value="' . $driver_id . '">
									<input type="hidden" name="lddfw_wpnonce" id="lddfw_wpnonce" value="' . $lddfw_wpnonce . '">
									<div class="lddfw_alert_wrap"></div>
									<div class="lddfw_wrap">
								';

						$html .= '<div class="lddfw_box">
								  <h3 class="lddfw_title">' . esc_html( __( 'Delivery Settings', 'lddfw' ) ) . '</h3>';

								// Availability.
						$html                             .= '<div class=" form-group row   availability">
								<label class="col-9 availability-text col-form-label">' . esc_html( __( 'I am', 'lddfw' ) );
								$lddfw_driver_availability = get_user_meta( $driver_id, 'lddfw_driver_availability', true );
		if ( '1' === $lddfw_driver_availability ) {
			$html .= '
										<span id="lddfw_availability_status" available="' . esc_attr( __( 'Available', 'lddfw' ) ) . '" unavailable="' . esc_attr( __( 'Unavailable', 'lddfw' ) ) . '">' . esc_html( __( 'Available', 'lddfw' ) ) . '</span>
										</label>
										<div class="col-3 text-right">
											<a id="lddfw_availability" class="lddfw_active" title="' . esc_attr( __( 'Availability status', 'lddfw' ) ) . '" href="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">
											<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="toggle-on" class="svg-inline--fa fa-toggle-on fa-w-18" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M384 64H192C86 64 0 150 0 256s86 192 192 192h192c106 0 192-86 192-192S490 64 384 64zm0 320c-70.8 0-128-57.3-128-128 0-70.8 57.3-128 128-128 70.8 0 128 57.3 128 128 0 70.8-57.3 128-128 128z"></path></svg></a>
										</div>
										';
		} else {
			$html .= '
										<span id="lddfw_availability_status" available="' . esc_attr( __( 'Available', 'lddfw' ) ) . '" unavailable="' . esc_attr( __( 'Unavailable', 'lddfw' ) ) . '">' . esc_html( __( 'Unavailable', 'lddfw' ) ) . '</span>
										</label>
										<div class="col-3 text-right">
											<a id="lddfw_availability" class="" title="' . esc_attr( __( 'Availability status', 'lddfw' ) ) . '" href="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">
											<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="toggle-off" class="svg-inline--fa fa-toggle-off fa-w-18" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M384 64H192C85.961 64 0 149.961 0 256s85.961 192 192 192h192c106.039 0 192-85.961 192-192S490.039 64 384 64zM64 256c0-70.741 57.249-128 128-128 70.741 0 128 57.249 128 128 0 70.741-57.249 128-128 128-70.741 0-128-57.249-128-128zm320 128h-48.905c65.217-72.858 65.236-183.12 0-256H384c70.741 0 128 57.249 128 128 0 70.74-57.249 128-128 128z"></path></svg></a>
										</div>';
		}

						$html .= '</div>';
		if ( lddfw_fs()->is__premium_only() ) {
			if ( lddfw_fs()->can_use_premium_code() ) {
					global $lddfw_drivers_tracking_timing;
				if ( '1' === $lddfw_drivers_tracking_timing ) {
									// Tracking permission.
									$html             .= '
												<div class="form-group row">
														<label class="col-9 col-form-label">' . esc_html( __( 'Track Me', 'lddfw' ) ) . '
														</label>';
												$html .= '
															<div class="col-3 text-right">
																<a id="lddfw_trackme" title="' . esc_attr( __( 'Tracking status', 'lddfw' ) ) . '" href="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '">
																	<span class="lddfw_trackme_on" style="display:none;" >
																		<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="toggle-on" class="svg-inline--fa fa-toggle-on fa-w-18" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M384 64H192C86 64 0 150 0 256s86 192 192 192h192c106 0 192-86 192-192S490 64 384 64zm0 320c-70.8 0-128-57.3-128-128 0-70.8 57.3-128 128-128 70.8 0 128 57.3 128 128 0 70.8-57.3 128-128 128z"></path></svg>
																	</span>
																	<span class="lddfw_trackme_off" style="display:none;" >
																		<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="toggle-off" class="svg-inline--fa fa-toggle-off fa-w-18" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M384 64H192C85.961 64 0 149.961 0 256s85.961 192 192 192h192c106.039 0 192-85.961 192-192S490.039 64 384 64zM64 256c0-70.741 57.249-128 128-128 70.741 0 128 57.249 128 128 0 70.741-57.249 128-128 128-70.741 0-128-57.249-128-128zm320 128h-48.905c65.217-72.858 65.236-183.12 0-256H384c70.741 0 128 57.249 128 128 0 70.74-57.249 128-128 128z"></path></svg>
																	</span>
																</a>
															</div>
														';
												$html .= '
														<div class="col-12"><div id="tracking_alert"></div></div>
											 </div>
										 ';
				}

										// Panel mode.
										// Get user app mode.
										$lddfw_app_mode = get_user_meta( $driver_id, 'lddfw_driver_app_mode', true );
										// If empty get admin setting app mode.
										$lddfw_app_mode = '' === $lddfw_app_mode ? get_option( 'lddfw_app_mode', '' ) : $lddfw_app_mode;

										$html .= '<div class="form-group row">
										<label class="col-sm-2 col-form-label" for="lddfw_driver_app_mode"> ' . esc_html( __( 'Panel Theme', 'lddfw' ) ) . '</label>
										<div class="col-sm-10">
											<select name="lddfw_driver_app_mode" class="form-control small-select valid">
												<option value="light" ' . selected( esc_attr( $lddfw_app_mode ), 'light', false ) . '>' . esc_html( __( 'Light Mode' ) ) . '</option>
												<option value="dark" ' . selected( esc_attr( $lddfw_app_mode ), 'dark', false ) . '>' . esc_html( __( 'Dark Mode' ) ) . '</option>
											</select>
										</div>
										</div>
										';

										// Travel mode.
										$lddfw_driver_travel_mode = $this->get_driver_driving_mode( $driver_id, '' );
										$html                    .= '<div class="form-group row">
								<label class="col-sm-2 col-form-label" for="lddfw_driver_travel_mode"> ' . esc_html( __( 'Transportation Mode', 'lddfw' ) ) . '</label>
								<div class="col-sm-10">
									<select class="form-control small-select" name="lddfw_driver_travel_mode" id="lddfw_driver_travel_mode">
										<option ' . selected( 'DRIVING', $lddfw_driver_travel_mode, false ) . ' value="DRIVING"> ' . esc_html( __( 'Driving', 'lddfw' ) ) . ' </option>
										<option ' . selected( 'WALKING', $lddfw_driver_travel_mode, false ) . ' value="WALKING"> ' . esc_html( __( 'Walking', 'lddfw' ) ) . ' </option>
										<option ' . selected( 'BICYCLING', $lddfw_driver_travel_mode, false ) . ' value="BICYCLING"> ' . esc_html( __( 'Bicycling', 'lddfw' ) ) . ' </option>
										<option ' . selected( 'TRANSIT', $lddfw_driver_travel_mode, false ) . ' value="TRANSIT"> ' . esc_html( __( 'Transit', 'lddfw' ) ) . ' </option>
									</select>
									<small id="emailHelp" class="form-text text-muted">' . esc_html( __( 'When you calculate directions, you may specify the transportation mode to use.', 'lddfw' ) ) . ' </small>
								</div>
								</div>
								';

										// Navigation APP.
										$lddfw_driver_navigation_app = get_user_meta( $driver_id, 'lddfw_driver_navigation_app', true );
										$lddfw_navigation_app        = ( '' !== $lddfw_driver_navigation_app ) ? $lddfw_driver_navigation_app : get_option( 'lddfw_navigation_app', '' );
										$html                       .= '
								<div class="form-group row">
								<label class="col-sm-2 col-form-label" for="lddfw_navigation_app"> ' . esc_html( __( 'Navigation APP', 'lddfw' ) ) . '</label>
								<div class="col-sm-10">
									<select class="form-control small-select" name="lddfw_navigation_app" id="lddfw_navigation_app">
										<option ' . selected( 'wase', $lddfw_navigation_app, false ) . ' value="wase"> ' . esc_html( __( 'Waze', 'lddfw' ) ) . ' </option>
										<option ' . selected( 'apple_maps', $lddfw_navigation_app, false ) . ' value="apple_maps"> ' . esc_html( __( 'Apple Maps', 'lddfw' ) ) . ' </option>
										<option ' . selected( 'google_maps', $lddfw_navigation_app, false ) . ' value="google_maps"> ' . esc_html( __( 'Google Maps', 'lddfw' ) ) . ' </option>
									</select>
								</div>
								</div>
								';

										// Vehicle.
										$lddfw_driver_vehicle = get_user_meta( $driver_id, 'lddfw_driver_vehicle', true );
										$html                .= '<div class="form-group row">
											<label class="col-sm-2 col-form-label" for="lddfw_driver_vehicle">' . esc_html( __( 'Vehicle type', 'lddfw' ) ) . '</label>
											<div class="col-sm-10">
												<input type="text" name="lddfw_driver_vehicle" value="' . $lddfw_driver_vehicle . '" class="form-control reqi" id="lddfw_driver_vehicle" placeholder="' . esc_html( __( 'Vehicle type', 'lddfw' ) ) . '">
												<small id="emailHelp" class="form-text text-muted">' . esc_html( __( 'Write the driver vehicle type / model.', 'lddfw' ) ) . ' </small>
											</div>
										 </div>';

										// License Plate.
										$lddfw_driver_licence_plate = get_user_meta( $driver_id, 'lddfw_driver_licence_plate', true );
										$html                      .= '<div class="form-group row">
											<label class="col-sm-2 col-form-label" for="lddfw_driver_licence_plate">' . esc_html( __( 'License Plate', 'lddfw' ) ) . '</label>
											<div class="col-sm-10">
												<input type="text" name="lddfw_driver_licence_plate" value="' . $lddfw_driver_licence_plate . '" class="form-control reqi" id="lddfw_driver_licence_plate" placeholder="' . esc_html( __( 'License Plate', 'lddfw' ) ) . '">
											</div>
										 </div>';
			}
		}

						$html .= '</div>';

						$html .= '<div class="lddfw_box">
						<h3 class="lddfw_title">' . esc_html( __( 'Contact Info', 'lddfw' ) ) . '</h3>';

		if ( lddfw_fs()->is__premium_only() ) {
			if ( lddfw_fs()->can_use_premium_code() ) {
				$html .= '<div class="form-group row upload_image_form">
								<label class="col-sm-2 col-form-label" for="upload_image_wrap">' . esc_html( __( 'Photo', 'lddfw' ) ) . '</label>
								<div class="col-sm-10">
								<div class="upload_image_wrap"><span class="lddfw_helper"></span>';
				/* driver photo */
				$image    = '';
				$image_id = get_user_meta( $driver_id, 'lddfw_driver_image', true );
				if ( intval( $image_id ) > 0 ) {
					$image = wp_get_attachment_image_src( $image_id, 'medium' )[0];
					if ( '' !== $image ) {
						$html .= '<img src="' . $image . '">';
					}
				}
				if ( '' === $image ) {
					$html .= '<img src="' . plugins_url() . '/' . LDDFW_FOLDER . '/public/images/user.png?ver=' . LDDFW_VERSION . '">';
				}
				$html .= '

								</div>
								<div class="custom-file photo_upload" >
									<input type="hidden" class="lddfw_image_input" name="lddfw_image_input" value="" >
									<input type="file" class="custom-file-input lddfw_upload_image"  >
									<label class="custom-file-label" for="upload_image">
									<svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="camera" class="svg-inline--fa fa-camera fa-w-16" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="currentColor" d="M512 144v288c0 26.5-21.5 48-48 48H48c-26.5 0-48-21.5-48-48V144c0-26.5 21.5-48 48-48h88l12.3-32.9c7-18.7 24.9-31.1 44.9-31.1h125.5c20 0 37.9 12.4 44.9 31.1L376 96h88c26.5 0 48 21.5 48 48zM376 288c0-66.2-53.8-120-120-120s-120 53.8-120 120 53.8 120 120 120 120-53.8 120-120zm-32 0c0 48.5-39.5 88-88 88s-88-39.5-88-88 39.5-88 88-88 88 39.5 88 88z"></path></svg>
									</label>
								</div>
								</div></div> ';
			}
		}

					$html .= '<div class="form-group row">
							<label  class="col-sm-2 col-form-label" for="lddfw_first_name">' . esc_html( __( 'First name', 'lddfw' ) ) . '</label>
							<div class="col-sm-10">
							<input type="text" name="lddfw_first_name" value="' . $first_name . '" class="form-control reqi" id="lddfw_first_name"  placeholder="' . esc_html( __( 'First name', 'lddfw' ) ) . '">
							</div>
						</div>
						<div class="form-group row">
							<label  class="col-sm-2 col-form-label" for="lddfw_last_name">' . esc_html( __( 'Last name', 'lddfw' ) ) . '</label>
							<div class="col-sm-10">
							<input type="text" name="lddfw_last_name" value="' . $last_name . '" class="form-control" id="lddfw_last_name"  placeholder="' . esc_html( __( 'Last name', 'lddfw' ) ) . '">
							</div>
						</div>
						<div class="form-group row">
							<label  class="col-sm-2 col-form-label" for="lddfw_company">' . esc_html( __( 'Company', 'lddfw' ) ) . '</label>
							<div class="col-sm-10">
							<input type="text" name="lddfw_company" value="' . $company . '" class="form-control" id="lddfw_company"  placeholder="' . esc_html( __( 'Company', 'lddfw' ) ) . '">
							</div>
						</div>
						<div class="form-group row">
							<label  class="col-sm-2 col-form-label" for="lddfw_address_1">' . esc_html( __( 'Address line 1', 'lddfw' ) ) . '</label>
							<div class="col-sm-10">
							<input type="text" name="lddfw_address_1" value="' . $address_1 . '" class="form-control" id="lddfw_address_1"  placeholder="' . esc_html( __( 'Address line 1', 'lddfw' ) ) . '">
							</div>
						</div>
						<div class="form-group row">
							<label  class="col-sm-2 col-form-label" for="lddfw_address_2">' . esc_html( __( 'Address line 2', 'lddfw' ) ) . '</label>
							<div class="col-sm-10">
							<input type="text" name="lddfw_address_2" value="' . $address_2 . '" class="form-control" id="lddfw_address_2"  placeholder="' . esc_html( __( 'Address line 2', 'lddfw' ) ) . '">
							</div>
						</div>
						<div class="form-group row">
						<label  class="col-sm-2 col-form-label" for="lddfw_city">' . esc_html( __( 'City', 'lddfw' ) ) . '</label>
						<div class="col-sm-10">
						<input type="text" name="lddfw_city" value="' . $city . '" class="form-control" id="lddfw_city"  placeholder="' . esc_html( __( 'City', 'lddfw' ) ) . '">
						</div>
					</div>
					<div class="form-group row">
						<label  class="col-sm-2 col-form-label" for="lddfw_postcode">' . esc_html( __( 'Postcode / ZIP', 'lddfw' ) ) . '</label>
						<div class="col-sm-10">
						<input type="text" name="lddfw_postcode" value="' . $postcode . '" class="form-control" id="lddfw_postcode"  placeholder="' . esc_html( __( 'Postcode / ZIP', 'lddfw' ) ) . '">
						</div>
					</div>

						';

						global $woocommerce;
						$countries_obj = new WC_Countries();
						$countries     = $countries_obj->__get( 'countries' );

						$default_country       = $countries_obj->get_base_country();
						$default_county_states = $countries_obj->get_states( 'US' );

						$html .= '<div class="form-group row">
						<label  class="col-sm-2 col-form-label" for="lddfw_country">' . esc_html( __( 'Country / Region', 'lddfw' ) ) . '</label>';
						$html .= '<div class="col-sm-10"><select id="billing_country" name="lddfw_country" class="form-control">
							<option value="">' . esc_html( __( 'Select Country / Region', 'lddfw' ) ) . '</option>';
		foreach ( $countries as $key => $country ) {
			 $html .= '<option value="' . $key . '" ' . selected( $billing_country, $key, false ) . ' >' . $country . '</option>';
		}
						$html .= '</select>';
						$html .= '</div></div>';

						$html .= '<div class="form-group row">
								  <label class="col-sm-2 col-form-label" for="billing_state_select">' . esc_html( __( 'State / County', 'lddfw' ) ) . '</label>';
						$html .= '<div class="col-sm-10"><select style="display:none" id="billing_state_select" name="billing_state_select" class="form-control">
									<option value="">' . esc_html( __( 'Select State / County', 'lddfw' ) ) . '</option>';
		foreach ( $default_county_states as $key => $state ) {
			$html .= '<option value="' . $key . '" ' . selected( $billing_state, $key, false ) . ' >' . $state . '</option>';
		}
						$html .= '</select>
								  <input type="text" style="display:none" class="form-control" id="billing_state_input"  placeholder="' . esc_html( __( 'State / County', 'lddfw' ) ) . '" value="' . esc_attr( $billing_state ) . '" name="billing_state">';
						$html .= '</div></div>';

						$html .= '<div class="form-group row">
						<label  class="col-sm-2 col-form-label" for="lddfw_phone">' . esc_html( __( 'Phone number', 'lddfw' ) ) . '</label>
						<div class="col-sm-10">
						<input type="text" name="lddfw_phone" value="' . $phone . '" class="form-control" id="lddfw_phone" placeholder="' . esc_html( __( 'Phone number', 'lddfw' ) ) . '">
						</div>
					</div>
					</div>
					';

					$html .= '<div class="lddfw_box">
					<h3 class="lddfw_title">' . esc_html( __( 'Account', 'lddfw' ) ) . '</h3>';

					// Email.
					$html .= '<div class="form-group row">
							<label class="col-sm-2 col-form-label"  for="lddfw_email">' . esc_html( __( 'Email address', 'lddfw' ) ) . '</label>
							<div class="col-sm-10">
							<input type="email" name="lddfw_email"  value="' . $email . '"  class="form-control" id="lddfw_email" placeholder="' . esc_html( __( 'Enter email', 'lddfw' ) ) . '">
							</div>
						</div>';

					// Password.
					$html .= '<div class="form-group row">
							<label class="col-sm-2 col-form-label"  for="lddfw_password">' . esc_html( __( 'Password', 'lddfw' ) ) . '</label>
							<div class="col-sm-10">
								<button type="button" id="new_password_button" class="btn btn-secondary">' . esc_html( __( 'Set New Password', 'lddfw' ) ) . '</button>
								<div class = "row" id = "lddfw_password_holder" style = "display:none" >
									<div class="col-6">
										<input type="text" name="lddfw_password" id="lddfw_password"  value="" class="form-control" id="lddfw_password" placeholder="' . esc_html( __( 'Enter password', 'lddfw' ) ) . '">
									</div>
									<div class="col-6">
										<button type="button" id="cancel_password_button" class="btn btn-secondary">' . esc_html( __( 'Cancel', 'lddfw' ) ) . '</button>
									</div>
								</div>
							</div>
						</div>';

					$html .= '</div>';

						$html .= '
						</div></div></div></div>';

							// Buttons.
							$html .= '<div class="lddfw_footer_buttons">
							<div class="container">
								<div class="row">
									<div class="col-12">
							<button class="lddfw_submit_btn btn btn-lg btn-primary btn-block" type="submit">
							' . esc_html( __( 'Update', 'lddfw' ) ) . '
							</button>
							<button style="display:none" class="lddfw_loading_btn btn-lg btn btn-block btn-primary" type="button" disabled>
							<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
							' . esc_html( __( 'Loading', 'lddfw' ) ) . '
							</button>
							</div>
							</div>
							</div>
							</div>
					</form>';

			$html .= '
		 ';
		return $html;
	}

	/**
	 * Edit driver
	 *
	 * @since 1.5.0
	 * @return array
	 */
	public function lddfw_edit_driver_service() {
		$error     = '';
		$result    = '0';
		$new_nonce = '';

		// Security check.
		if ( isset( $_POST['lddfw_wpnonce'] ) ) {

				$driver_id  = ( isset( $_POST['lddfw_driverid'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_driverid'] ) ) : '';
				$email      = ( isset( $_POST['lddfw_email'] ) ) ? sanitize_email( wp_unslash( $_POST['lddfw_email'] ) ) : '';
				$first_name = ( isset( $_POST['lddfw_first_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_first_name'] ) ) : '';
				$last_name  = ( isset( $_POST['lddfw_last_name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_last_name'] ) ) : '';
				$phone      = ( isset( $_POST['lddfw_phone'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_phone'] ) ) : '';
				$country    = ( isset( $_POST['lddfw_country'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_country'] ) ) : '';
				$company    = ( isset( $_POST['lddfw_company'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_company'] ) ) : '';
				$address_1  = ( isset( $_POST['lddfw_address_1'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_address_1'] ) ) : '';
				$address_2  = ( isset( $_POST['lddfw_address_2'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_address_2'] ) ) : '';
				$city       = ( isset( $_POST['lddfw_city'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_city'] ) ) : '';
				$postcode   = ( isset( $_POST['lddfw_postcode'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_postcode'] ) ) : '';
				$password   = ( isset( $_POST['lddfw_password'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_password'] ) ) : '';
				$state      = ( isset( $_POST['billing_state'] ) ) ? sanitize_text_field( wp_unslash( $_POST['billing_state'] ) ) : '';

			if ( lddfw_fs()->is__premium_only() ) {
				if ( lddfw_fs()->can_use_premium_code() ) {
					$app_mode       = ( isset( $_POST['lddfw_driver_app_mode'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_driver_app_mode'] ) ) : '';
					$image          = ( isset( $_POST['lddfw_image_input'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_image_input'] ) ) : '';
					$travel_mode    = ( isset( $_POST['lddfw_driver_travel_mode'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_driver_travel_mode'] ) ) : '';
					$vehicle        = ( isset( $_POST['lddfw_driver_vehicle'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_driver_vehicle'] ) ) : '';
					$licence_plate  = ( isset( $_POST['lddfw_driver_licence_plate'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_driver_licence_plate'] ) ) : '';
					$navigation_app = ( isset( $_POST['lddfw_navigation_app'] ) ) ? sanitize_text_field( wp_unslash( $_POST['lddfw_navigation_app'] ) ) : '';
				}
			}
			if ( '' === $driver_id ) {
				// No driver.
				$error = __( 'Driver number is empty.', 'lddfw' );
			} else {
				// Check for empty fields.
				if ( '' === $email ) {
					// No email.
					$error = __( 'The email field is empty.', 'lddfw' );
				} else {
					if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
						// Invalid Email.
						$error = __( 'The email is invalid.', 'lddfw' );
					} else {
						// Email exist for another user.
						$user    = get_user_by( 'email', $email );
						$user_id = $user->data->ID;
						if ( $user && $user_id !== $driver_id ) {
							$error = __( 'Email exist for another user.', 'lddfw' );
						} else {
							if ( '' === $first_name ) {
								$error = __( 'First name is empty.', 'lddfw' );
							} else {
								if ( '' === $last_name ) {
									$error = __( 'Last name is empty.', 'lddfw' );
								} else {
									if ( '' === $phone ) {
										$error = __( 'Phone is empty.', 'lddfw' );
									} else {
										if ( '' === $address_1 ) {
											$error = __( 'Address 1 is empty.', 'lddfw' );
										} else {
											if ( '' === $city ) {
												$error = __( 'City is empty.', 'lddfw' );
											} else {
												if ( '' === $country ) {
													$error = __( 'Country is empty.', 'lddfw' );
												} else {
														wp_update_user(
															array(
																'ID' => $driver_id,
																'first_name' => $first_name,
																'last_name' => $last_name,
																'user_email' => $email,
																'nickname' => $first_name . ' ' . $last_name,
															)
														);
														update_user_meta( $driver_id, 'billing_first_name', $first_name );
														update_user_meta( $driver_id, 'billing_last_name', $last_name );
														update_user_meta( $driver_id, 'billing_company', $company );
														update_user_meta( $driver_id, 'billing_address_1', $address_1 );
														update_user_meta( $driver_id, 'billing_address_2', $address_2 );
														update_user_meta( $driver_id, 'billing_postcode', $postcode );
														update_user_meta( $driver_id, 'billing_city', $city );
														update_user_meta( $driver_id, 'billing_state', $state );
														update_user_meta( $driver_id, 'billing_phone', $phone );
														update_user_meta( $driver_id, 'billing_country', $country );

														wp_update_user(
															array(
																'ID' => $driver_id,
																'display_name' => "$first_name $last_name",
															)
														);

													if ( '' !== $password ) {
														// Change password.
														wp_set_password( $password, $driver_id );
														// Log user again.
														LDDFW_Login::lddfw_user_login( $user, $password );

														$_set_cookies = true; // for the closures.

														// Set the (secure) auth cookie immediately.
														add_action(
															'set_auth_cookie',
															function( $auth_cookie, $a, $b, $c, $scheme ) use ( $_set_cookies ) {
																if ( $_set_cookies ) {
																	$_COOKIE[ 'secure_auth' === $scheme ? SECURE_AUTH_COOKIE : AUTH_COOKIE ] = $auth_cookie;
																}
															},
															10,
															5
														);

														// Set the logged-in cookie immediately.
														add_action(
															'set_logged_in_cookie',
															function( $logged_in_cookie ) use ( $_set_cookies ) {
																if ( $_set_cookies ) {
																	$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;
																}
															}
														);

														// Set cookies.
														wp_set_auth_cookie( $driver_id );
														$_set_cookies = false;

														// Create nounce.
														$new_nonce = wp_create_nonce( 'lddfw-nonce' );
													}

													if ( lddfw_fs()->is__premium_only() ) {
														if ( lddfw_fs()->can_use_premium_code() ) {
															update_user_meta( $driver_id, 'lddfw_driver_app_mode', $app_mode );
															update_user_meta( $driver_id, 'lddfw_driver_travel_mode', $travel_mode );
															update_user_meta( $driver_id, 'lddfw_driver_vehicle', $vehicle );
															update_user_meta( $driver_id, 'lddfw_driver_licence_plate', $licence_plate );
															update_user_meta( $driver_id, 'lddfw_driver_navigation_app', $navigation_app );

															if ( '' !== $image ) {
																$media    = new LDDFW_Media();
																$image_id = $media->lddfw_add_image_to_media( $image, 'driver' );
																update_user_meta( $driver_id, 'lddfw_driver_image', $image_id );
															}
														}
													}

														$result = 1;
														$error  = __( 'Successfully updated.', 'lddfw' );
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
		return "{\"result\":\"$result\",\"error\":\"$error\",\"nonce\":\"$new_nonce\"}";
	}


	/**
	 *  Set driver commission
	 *
	 * @param object $order order object.
	 * @param int    $order_id order number.
	 */
	public function set_order_commission__premium_only( $order, $order_id ) {
		// Set driver commission.
		$commission       = 0;
		$commission_note  = '';
		$order_commission = $order->get_meta( 'lddfw_driver_commission' );
		if ( '' === $order_commission ) {

			$lddfw_driver_commission_value        = get_option( 'lddfw_driver_commission_value', '' );
			$lddfw_driver_commission_second_value = get_option( 'lddfw_driver_commission_second_value', '' );
			$lddfw_driver_commission_type         = get_option( 'lddfw_driver_commission_type', '' );
			if ( '' !== $lddfw_driver_commission_value && '' !== $lddfw_driver_commission_type ) {

				// Fixed price.
				if ( 'fixed' === $lddfw_driver_commission_type ) {
					$commission = $lddfw_driver_commission_value;
				}

				// Delivery percentage.
				if ( 'delivery_percentage' === $lddfw_driver_commission_type ) {
					$commission = $order->get_shipping_total() * $lddfw_driver_commission_value / 100;
				}

				// Order percentage.
				if ( 'order_percentage' === $lddfw_driver_commission_type ) {
					$order_total = $order->get_total();
					$refund      = $order->get_total_refunded();
					if ( '' !== $refund ) {
						$order_total = $order_total - $refund;
					}
					$commission = $order_total * $lddfw_driver_commission_value / 100;
				}

				// Get distance and duration.
				if ( 'distance_time' === $lddfw_driver_commission_type || 'time' === $lddfw_driver_commission_type || 'distance' === $lddfw_driver_commission_type ) {
					// Set distance from origin.
					$driver_id = $order->get_meta( 'lddfw_driverid' );
					$route     = new LDDFW_Route();
					$route->lddfw_distancematrix( $order, $order_id, $driver_id, true );
					$origin_distance = get_post_meta( $order_id, '_lddfw_origin_distance', true );
					if ( ! empty( $origin_distance ) ) {
						if ( isset( $origin_distance['duration_text'] ) && isset( $origin_distance['distance_text'] ) ) {
							// Set commission note.
							$commission_note = $origin_distance['distance_text'] . ' (' . $origin_distance['duration_text'] . ') ' . esc_html( __( 'from pickup address.', 'lddfw' ) );
						}
					}
				}

				// Distance.
				if ( 'distance_time' === $lddfw_driver_commission_type || 'distance' === $lddfw_driver_commission_type ) {
					if ( ! empty( $origin_distance ) ) {
						if ( isset( $origin_distance['distance_text'] ) ) {
							$distance = floatval( $origin_distance['distance_value'] );
							if ( 0 < $distance ) {
								// Calculate distance price.
								$store       = new LDDFW_Store();
								$unit_system = $store->lddfw_country_unit_system__premium_only();
								if ( 'imperial' === $unit_system ) {
									$calculate_commission = $distance / 1609 * $lddfw_driver_commission_value;
								} else {
									$calculate_commission = $distance / 1000 * $lddfw_driver_commission_value;
								}
								$commission += round( $calculate_commission );
							}
						}
					}
				}

				// Time.
				if ( 'distance_time' === $lddfw_driver_commission_type || 'time' === $lddfw_driver_commission_type ) {
					if ( ! empty( $origin_distance ) ) {
						if ( isset( $origin_distance['duration_text'] ) ) {
							$duration = floatval( $origin_distance['duration_value'] );
							if ( 0 < $duration ) {
								// Calculate duration price.
								if ( 'distance_time' === $lddfw_driver_commission_type ) {
									$calculate_commission = $duration / 60 * $lddfw_driver_commission_second_value;
								} else {
									$calculate_commission = $duration / 60 * $lddfw_driver_commission_value;
								}
								$commission += round( $calculate_commission );
							}
						}
					}
				}
			}
		}

		if ( has_filter( 'lddfw_set_order_commission' ) ) {
			$commission = apply_filters( 'lddfw_set_order_commission', $commission, $order );
		}

		if ( is_numeric( $commission ) ) {
			if ( 0 < $commission ) {

				lddfw_update_post_meta( $order_id, 'lddfw_driver_commission', $commission );
				delete_post_meta( $order_id, '_lddfw_driver_commission_note' );
				if ( '' !== $commission_note ) {
					update_post_meta( $order_id, '_lddfw_driver_commission_note', $commission_note );
				}
				remove_action( 'save_post', 'lddfw_driver_save_order_details', 10, 2 );
			}
		}
	}


	/**
	 * Get driver vehicle info for customers
	 *
	 * @param int    $driver_id The driver ID.
	 * @param string $format text format.
	 */
	public function get_vehicle_info__premium_only( $driver_id, $format ) {
		/* vehicle details */
		$result                     = '';
		$lddfw_driver_vehicle       = get_user_meta( $driver_id, 'lddfw_driver_vehicle', true );
		$lddfw_driver_licence_plate = get_user_meta( $driver_id, 'lddfw_driver_licence_plate', true );
		if ( '' !== $lddfw_driver_vehicle || '' !== $lddfw_driver_licence_plate ) {
			if ( 'html' === $format ) {
				$result .= '<p><b>' . esc_html( __( 'Vehicle', 'lddfw' ) ) . ':</b><br>';
				$result .= esc_html( $lddfw_driver_vehicle ) . '<br>';
				$result .= esc_html( $lddfw_driver_licence_plate ) . '</p>';
			} else {
				$result .= esc_html( __( 'Vehicle', 'lddfw' ) ) . "\n";
				$result .= esc_html( $lddfw_driver_vehicle ) . "\n";
				$result .= esc_html( $lddfw_driver_licence_plate ) . "\n";
			}
		}
		return $result;
	}

	/**
	 * Get driver info for customers
	 *
	 * @param int    $driver_id The driver ID.
	 * @param string $format text format.
	 */
	public function get_driver_info__premium_only( $driver_id, $format, $permission = false ) {
		$result = '';
		// Get permissions to show the info.
		$lddfw_driver_photo_permission = get_option( 'lddfw_driver_photo_permission', false );
		$lddfw_driver_name_permission  = get_option( 'lddfw_driver_name_permission', false );
		$lddfw_driver_phone_permission = get_option( 'lddfw_driver_phone_permission', false );
		$photo_permission              = false === $lddfw_driver_photo_permission || '1' === $lddfw_driver_photo_permission ? true : false;
		$name_permission               = false === $lddfw_driver_name_permission || '1' === $lddfw_driver_name_permission ? true : false;
		$phone_permission              = false === $lddfw_driver_phone_permission || '1' === $lddfw_driver_phone_permission ? true : false;
		$driver                        = get_user_by( 'id', $driver_id );
		$driver_name                   = ( ! empty( $driver ) ) ? $driver->display_name : '';
		$lddfw_driver_billing_phone    = get_user_meta( $driver_id, 'billing_phone', true );

		if ( true === $permission || true === $photo_permission || true === $name_permission || true === $phone_permission ) {
			if ( 'html' === $format ) {
				$result .= '<h2 class="woocommerce-column__title"><b>' . esc_html( __( 'Driver details', 'lddfw' ) ) . '</b></h2>';
			} else {
				$result .= esc_html( __( 'Driver details', 'lddfw' ) ) . "\n";
			}
		}

		if ( 'html' === $format ) {

			if ( true === $permission || true === $photo_permission ) {
				/* driver photo */
				$image_id = get_user_meta( $driver_id, 'lddfw_driver_image', true );
				if ( intval( $image_id ) > 0 ) {
					$image = wp_get_attachment_image_src( $image_id, 'medium' )[0];
					if ( '' !== $image ) {
						$result .= '<img style="width:auto;max-width:100px" src="' . $image . '"><br>';
					}
				}
			}

			if ( true === $permission || true === $name_permission ) {
				/* driver name */
				if ( '' !== $driver_name ) {
					$result .= '<b>' . esc_html( $driver_name ) . '</b><br>';
				}
			}

			if ( true === $permission || true === $phone_permission ) {
				/* driver phone */
				if ( '' !== $lddfw_driver_billing_phone ) {
					$result .= '<a href="tel:' . $lddfw_driver_billing_phone . '">' . __( 'Call driver', 'lddfw' ) . ': ' . $lddfw_driver_billing_phone . '</a>';
				}
			}
		} else {

			if ( true === $permission || true === $name_permission ) {
				/* driver name */
				if ( '' !== $driver_name ) {
					$result .= esc_html( $driver_name ) . "\n";
				}
			}

			if ( true === $permission || true === $phone_permission ) {
				/* driver phone */
				if ( '' !== $lddfw_driver_billing_phone ) {
					$result .= '<a href="tel:' . $lddfw_driver_billing_phone . '">' . __( 'Call driver', 'lddfw' ) . ': ' . $lddfw_driver_billing_phone . '</a>' . "\n";
				}
			}
		}
		return $result;
	}



	/**
	 *  Get driver address
	 *
	 * @param int $driver_id The driver ID.
	 * @return array
	 */
	public function get_driver_address__premium_only( $driver_id ) {
		$user_meta = get_userdata( $driver_id );
		$country   = $user_meta->billing_country;
		$city      = $user_meta->billing_city;
		$address_1 = $user_meta->billing_address_1;
		$address_2 = $user_meta->billing_address_2;
		$postcode  = $user_meta->billing_postcode;
		$state     = $user_meta->billing_state;
		if ( '' !== $country ) {
			$country = WC()->countries->countries[ $country ];
		}
		$array = array(
			'street_1' => $address_1,
			'street_2' => $address_2,
			'city'     => $city,
			'zip'      => $postcode,
			'country'  => $country,
			'state'    => $state,
		);

		return array( lddfw_format_address( 'map_address', $array ), lddfw_format_address( 'address_line', $array ) );
	}

}
