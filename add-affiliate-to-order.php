<?php
/**
Plugin Name: Add Affiliate to Order
Description: Add afifliate info to $order variable and admin edit order screen.
Author: Josh Buchanan
Version: 0.0.9
*/

// Add a new checkout field
function affid_filter_checkout_fields($fields){
    $affid = '';
	if ( isset($_COOKIE['affwp_ref']) ) {
		$affid = $_COOKIE['affwp_ref'];
	}
	$fields['affiliate_fields'] = array(
            'affid_field' => array(
                'type' => 'text',
                'value' => __( $affid )
                ),
            );

    return $fields;
}
//add_filter( 'woocommerce_checkout_fields', 'affid_filter_checkout_fields' );

// display the extra field on the checkout form
function affid_extra_checkout_fields(){ 

    $checkout = WC()->checkout(); ?>

    <div class="extra-fields">

    <?php 
    // because of this foreach, everything added to the array in the previous function will display automagically
    foreach ( $checkout->checkout_fields['affiliate_fields'] as $key => $field ) : ?>

            <?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>

        <?php endforeach; ?>
    </div>

<?php }
//add_action( 'woocommerce_checkout_after_customer_details' ,'affid_extra_checkout_fields' );


// save the extra field when checkout is processed
function affid_save_extra_checkout_fields( $order_id, $posted ){
    // don't forget appropriate sanitization if you are using a different field type
    $affid = '';
	if ( isset($_COOKIE['affwp_ref']) ) {
		$affid = $_COOKIE['affwp_ref'];
	}
    update_post_meta( $order_id, '_affiliate_id', sanitize_text_field( $affid ) );
//    if( isset( $posted['affid_field'] ) ) {
//        update_post_meta( $order_id, '_affid_field', sanitize_text_field( $posted['affid_field'] ) );
//    }
}
add_action( 'woocommerce_checkout_update_order_meta', 'affid_save_extra_checkout_fields', 10, 2 );


// display the extra data on order recieved page and my-account order review
function kia_display_order_data( $order_id ){  ?>
    <h2><?php _e( 'Additional Info' ); ?></h2>
    <table class="shop_table shop_table_responsive additional_info">
        <tbody>
            <tr>
                <th><?php _e( 'Some Field:' ); ?></th>
                <td><?php echo get_post_meta( $order_id, '_some_field', true ); ?></td>
            </tr>
            <tr>
                <th><?php _e( 'Another Field:' ); ?></th>
                <td><?php echo get_post_meta( $order_id, '_another_field', true ); ?></td>
            </tr>
        </tbody>
    </table>
<?php }
//add_action( 'woocommerce_thankyou', 'kia_display_order_data', 20 );
//add_action( 'woocommerce_view_order', 'kia_display_order_data', 20 );


// display the extra data in the order admin panel
function affid_display_order_data_in_admin( $order ){ 
	$orderObj = wc_get_order( $order->get_order_number() );
	$affiliateObject = new Affiliate_WP_DB_Affiliates();
	$affiliates = $affiliateObject->get_affiliates(array('number' => 0,'order' => 'ASC'));
	$optionsArray = array('' => 'No Affiliate (Deletes Existing Referrals on save)');
	
	foreach ( $affiliates as $affiliate ) {
		$optionsArray[$affiliate->affiliate_id] = __( $affiliate->affiliate_id.' - '.affwp_get_affiliate_name($affiliate->affiliate_id), 'woocommerce' ); 
	}
	
	$value = '';
	if ( !empty(get_post_meta( $order->get_order_number(), '_affiliate_id', true ))) {
		$value = get_post_meta( $order->get_order_number(), '_affiliate_id', true );
	}
	
	$customer_id = $order->get_customer_id();
	$lifetime = '';
	if ( $customer_id > 0 ) {
		$lifetime_id = get_user_meta($customer_id, 'affwp_lc_affiliate_id', true);
		$lifetime_name = affwp_get_affiliate_name($lifetime_id);
		$lifetime = "(Lifetime affiliate: ".$lifetime_id." - ".$lifetime_name.")";
	}
	woocommerce_wp_select( array(
		'id' => '_affiliate_id',
		'label' => __( 'Attached Affiliate: '.$lifetime, 'woocommerce' ),
		'wrapper_class' => 'form-field-wide',
		'value'       => $value,
		'options' => $optionsArray
		)
	);		
	$reff = new Affiliate_WP_Referrals_DB();
	$refAgrs = array(
		'number' 	   => 0,
		'reference'    => $order->get_order_number(),
		'orderby'      => 'date',
	);	 
	$referrals = $reff->get_referrals( $refAgrs );
	if ( $referrals ) {
	?>
	<p>Existing Referrals:</p>
		<table style="width:100%;text-align:center;">
			<tr>
				<th>Referral</th>
				<th>Affiliate</th>
				<th>Rate</th>
				<th>Amount</th>
				<th>Status</th>
			</tr>
	<?php
		foreach ( $referrals as $referral ) {
			echo "<tr>";
			echo "<td><a href='/wp-admin/admin.php?page=affiliate-wp-referrals&referral_id=".$referral->referral_id."&action=edit_referral'>#".$referral->referral_id."</a></td>";
			echo "<td><a href='/wp-admin/admin.php?page=affiliate-wp-referrals&affiliate_id=".$referral->affiliate_id."'>".affwp_get_affiliate_name($referral->affiliate_id)."</a></td>";
			echo "<td>".round($referral->amount/( $order->get_total() - $order->get_total_tax() - $order->get_total_shipping() - $order->get_shipping_tax() )*100,1)."%</td>";
			echo "<td>".$referral->amount."</td>";
			echo "<td>".$referral->status."</td>";
			echo "</tr>";
			//print_r($order);
		}
	?>
		</table>
	<?php
	}
	

// JOSH Output referral Informaiton here ******************************************************************************************************************************************
}

add_action( 'woocommerce_admin_order_data_after_order_details', 'affid_display_order_data_in_admin' );

function affid_save_extra_details( $post_id, $post ){
    update_post_meta( $post_id, '_affiliate_id', wc_clean( $_POST[ '_affiliate_id' ] ) );
	
	$order = new WC_Order($post_id);
	$admin = wp_get_current_user();
	$admin_name = $admin->display_name;
	
	$order_date = date_i18n( 'Y-m-d H:i:s', strtotime( $order->get_date_paid() ) );
	if ( $order->has_status( 'completed' ) ) {
		$referral_status = 'unpaid';
	} else {
		$referral_status = 'pending';
	}

	if ( !empty($_POST[ '_affiliate_id' ]) ) {
		$total = $order->get_total() - $order->get_total_tax() - $order->get_total_shipping() - $order->get_shipping_tax() - $order->get_total_refunded();
		$refAmt = affwp_calc_referral_amount($total, $_POST[ '_affiliate_id' ], $_POST[ 'post_ID' ], '', '');
		$refAdd = array(
			'affiliate_id' => absint( $_POST[ '_affiliate_id' ] ),
			'amount'       => $refAmt,
			'description'  => 'Added from Order #'.$_POST[ 'post_ID' ].' edit page by '.$admin_name.' on '.date("Y-m-d h:i:sa"),
			'reference'    => ! empty( $_POST[ 'post_ID' ] )   ? sanitize_text_field( $_POST[ 'post_ID' ] )   : '',
			'context'      => 'woocommerce',
			'status'       => $referral_status,
			'date'		   => $order_date,
		);
	}
	$reff = new Affiliate_WP_Referrals_DB();
	$refAgrs = array(
		'number' 	   => 0,
		'reference'    => $_POST[ 'post_ID' ],
		'orderby'      => 'amount',
	);													 
	$referrals = $reff->get_referrals( $refAgrs );
	if ( $referrals ) {
		$old_aff_id = $referrals[0]->affiliate_id;
		$new_aff_id = absint( $_POST[ '_affiliate_id' ] );
		if ( $new_aff_id != $old_aff_id ) {
			foreach ( $referrals as $referral ) {
				if ( $referral->status != 'paid' ) {
					affwp_delete_referral( $referral->referral_id );
					$order->add_order_note($admin_name.' removed referral #'.$referral->referral_id.' for $'.$referral->amount.' from '.affwp_get_affiliate_name($referral->affiliate_id));
				}
			}
			if ( isset($refAdd) ) {
				$new_referral = affwp_add_referral($refAdd);
				$order->add_order_note('Direct referral #'.$new_referral.' for $'.$refAmt.' recorded for '.affwp_get_affiliate_name($_POST[ '_affiliate_id' ]).' by '.$admin_name);
			}
		}
	} else {
		if ( isset($refAdd) ) {
			$new_referral = affwp_add_referral($refAdd);
			$order->add_order_note('Direct referral #'.$new_referral.' for $'.$refAmt.' recorded for '.affwp_get_affiliate_name($_POST[ '_affiliate_id' ]).' by '.$admin_name);
		}
	}
}
add_action( 'woocommerce_process_shop_order_meta', 'affid_save_extra_details', 45, 2 );
