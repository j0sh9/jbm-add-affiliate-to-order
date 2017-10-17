<?php
/**
*Plugin Name: _Add Affiliate to Order
*Description: Add Referral info admin edit order screen.
*Author: Josh Buchanan
*Version: 2.0.0
*/

// display the extra data in the order admin panel
add_action( 'woocommerce_admin_order_data_after_order_details', 'affid_display_order_data_in_admin' );
function affid_display_order_data_in_admin( $order ){ 
	$order_id = $order->get_id();
	$orderObj = wc_get_order( $order_id );
	$affiliateObject = new Affiliate_WP_DB_Affiliates();
	$affiliates = $affiliateObject->get_affiliates(array('number' => 0,'order' => 'ASC'));
	$optionsArray = array('' => 'No Change', 'delete' => 'Delete Pending/Unpaid Referrals');
	
	foreach ( $affiliates as $affiliate ) {
		$optionsArray[$affiliate->affiliate_id] = __( $affiliate->affiliate_id.' - '.affwp_get_affiliate_name($affiliate->affiliate_id), 'woocommerce' ); 
	}
	
	$value = '';
	$attached_affiliate = '';
	/*
	if ( !empty(get_post_meta( $order_id, '_affiliate_id', true ))) {
		$value = get_post_meta( $order_id, '_affiliate_id', true );
		$attached_affiliate_name = affwp_get_affiliate_name($value);
		$attached_affiliate = 'Attached Affiliate: '.$value.' - '. $attached_affiliate_name;
	}
	*/
	
	$customer_id = $order->get_customer_id();
	$lifetime = '';
	if ( $customer_id > 0 ) {
		$lifetime_id = get_user_meta($customer_id, 'affwp_lc_affiliate_id', true);
		if ( !empty($lifetime_id) ) {
			$lifetime_name = affwp_get_affiliate_name($lifetime_id);
			$lifetime = $lifetime_id." - ".$lifetime_name."<br>";
		}
	}
	woocommerce_wp_select( array(
		'id' => '_affiliate_id_select',
		//'label' => __( 'Affiliate Info:<br>'.$lifetime.$attached_affiliate, 'woocommerce' ),
		'label' => __( '<h3>Referral Info:</h3>Lifetime affiliate: '.$lifetime.'<p><strong>Assign Commission:</strong>', 'woocommerce' ),
		'wrapper_class' => 'form-field-wide',
		'value'       => $value,
		'options' => $optionsArray
		)
	);	
	?>
<div  id="_jbm_update_lifetime_affiliate_label" style="display:none"><label><input type="checkbox" name="_jbm_update_lifetime_affiliate" id="_jbm_update_lifetime_affiliate" value="update" disabled /> Update Lifetime Affiliate Association?</label></div>

<div  id="_jbm_update_referral_add" style="display:none">
	<p><button type="submit" name="jbm_add_referral" value="1" class="button">Add Referral</button>&nbsp;&nbsp;<button type="submit" name="jbm_replace_referrals" value="1" class="button">Replace Pending/Unpaid Referrals</button></p></div>
	
<div  id="_jbm_update_referral_delete" style="display:none">
	<p><button type="submit" name="jbm_delete_referral" value="1" class="button">Delete Pending/Unpaid Referrals</button></p></div>
	
	</p>
	<script>jQuery('#_affiliate_id_select').change(function() {
		if ( jQuery('#_affiliate_id_select').val() == '' ) {
			jQuery('#_jbm_update_lifetime_affiliate').attr('disabled', true);
			jQuery('#_jbm_update_lifetime_affiliate').attr('checked', false);
			jQuery('#_jbm_update_lifetime_affiliate_label').hide(250);
			jQuery('#_jbm_update_referral_delete').hide(250);
			jQuery('#_jbm_update_referral_add').hide(250);
		} else {
			jQuery('#_jbm_update_lifetime_affiliate').attr('disabled', false);
			jQuery('#_jbm_update_lifetime_affiliate_label').slideDown(250);
		}
		if ( jQuery('#_affiliate_id_select').val() == 'delete' ) {
			jQuery('#_jbm_update_referral_add').hide();
			jQuery('#_jbm_update_referral_delete').slideDown(250);
		}
			
		if ( jQuery('#_affiliate_id_select').val() != '' && jQuery('#_affiliate_id_select').val() != 'delete' ) {
			jQuery('#_jbm_update_referral_delete').hide();
			jQuery('#_jbm_update_referral_add').slideDown(250);
		}
	});
</script>
	<?php
	$reff = new Affiliate_WP_Referrals_DB();
	$refAgrs = array(
		'number' 	   => 0,
		'reference'    => $order_id,
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
function jbm_order_edit_get_referrals($post_id) {
	$reff = new Affiliate_WP_Referrals_DB();
	$refAgrs = array(
		'number' 	   => 0,
		'reference'    => $post_id,
		'orderby'      => 'amount',
		'status'	   => array('pending','unpaid'),
	);													 
	return $reff->get_referrals( $refAgrs );
}


add_action( 'woocommerce_process_shop_order_meta', 'affid_save_extra_details', 45, 2 );
function affid_save_extra_details( $post_id, $post ){
	
	$affiliate_select_id = $_POST[ '_affiliate_id_select' ];
	//No Change... Abort
	if ( empty($affiliate_select_id) || $affiliate_select_id == '' )
		return; // We're done here
	
	$affiliate_update_lifetime = ( isset($_POST[ '_jbm_update_lifetime_affiliate' ]) )?1:0;
	
	$admin = wp_get_current_user();
	$admin_name = $admin->display_name;
	
	$order = new WC_Order($post_id);
	$customer_id = $order->get_customer_id();
	
	//Just deleting pending/unpaid referrals
	if ( $affiliate_select_id == 'delete' ) {
		$referrals = jbm_order_edit_get_referrals($post_id);
		foreach ( $referrals as $referral ) {
			affwp_delete_referral( $referral->referral_id );
			$order->add_order_note($admin_name.' removed referral #'.$referral->referral_id.' for $'.$referral->amount.' from '.affwp_get_affiliate_name($referral->affiliate_id));
			update_post_meta($post_id, '_affiliate_id', '');
			if( $affiliate_update_lifetime )
				update_user_meta($customer_id, 'affwp_lc_affiliate_id', '');
		}
		return; //We're done here
	}
	
	//Adding Referrals... Continue
	
	//No Change... Abort
    $current_affid = get_post_meta( $post_id, '_affiliate_id', true );
	if ( $current_affid == $affiliate_select_id )
		return; //We're done here
	
	$add_replace = ( isset($_POST['jbm_replace_referrals']) )?'replace':'add';

	$order_date = date_i18n( 'Y-m-d H:i:s', strtotime( $order->get_date_paid() ) );
	if ( $order->has_status( 'completed' ) ) {
		$referral_status = 'unpaid';
	} else {
		$referral_status = 'pending';
	}

	$total = $order->get_total() - $order->get_total_tax() - $order->get_total_shipping() - $order->get_shipping_tax() - $order->get_total_refunded();
	$refAmt = affwp_calc_referral_amount($total, $affiliate_select_id, $post_id, '', '');
	$refAdd = array(
		'affiliate_id' => absint( $affiliate_select_id ),
		'amount'       => $refAmt,
		'description'  => 'Added from Order #'.$post_id.' edit page by '.$admin_name.' on '.date("Y-m-d h:i:sa"),
		'reference'    => ! empty( $post_id )   ? sanitize_text_field( $post_id )   : '',
		'context'      => 'woocommerce',
		'status'       => $referral_status,
		'date'		   => $order_date,
	);
	
	//Just add a referral
	if ( $add_replace == 'add' ) {
		$new_referral = affwp_add_referral($refAdd);
		$order->add_order_note('Direct referral #'.$new_referral.' for $'.$refAmt.' recorded for '.affwp_get_affiliate_name($affiliate_select_id).' by '.$admin_name);
		update_post_meta($post_id, '_affiliate_id', $affiliate_select_id);
		if( $affiliate_update_lifetime )
			update_user_meta($customer_id, 'affwp_lc_affiliate_id', $affiliate_select_id);
		return; //We're done here
	}
	
	//Replacing pending/unpaid referrals
	$referrals = jbm_order_edit_get_referrals($post_id);
		
	if ( $referrals ) {
		foreach ( $referrals as $referral ) {
			affwp_delete_referral( $referral->referral_id );
			$order->add_order_note($admin_name.' removed referral #'.$referral->referral_id.' for $'.$referral->amount.' from '.affwp_get_affiliate_name($referral->affiliate_id));
		}
	}
	
	$new_referral = affwp_add_referral($refAdd);
	$order->add_order_note('Direct referral #'.$new_referral.' for $'.$refAmt.' recorded for '.affwp_get_affiliate_name($affiliate_select_id).' by '.$admin_name);
	update_post_meta($post_id, '_affiliate_id', $affiliate_select_id);
	if( $affiliate_update_lifetime )
		update_user_meta($customer_id, 'affwp_lc_affiliate_id', $affiliate_select_id);
	return;
}
