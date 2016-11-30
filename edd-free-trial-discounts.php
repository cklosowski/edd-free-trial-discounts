<?php
/*
Plugin Name: Easy Digital Downloads - Free Trial Discounts
Plugin URL: https://chrisk.io
Description: Adds support for discount codes to apply a free trial to the item
Version: 1.0
Author: Chris Klosowski
Author URI: https://chrisk.io
Text Domain: edds
Domain Path: languages
*/

class EDD_Free_Trial_Discounts {
	private static $instance;

	private function __construct() {}

	public static function instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDD_Free_Trial_Discounts ) ) {
			self::$instance = new EDD_Free_Trial_Discounts;
			self::$instance->hooks();
			self::$instance->filters();
		}

		return self::$instance;
	}

	private function hooks() {
		add_action( 'edd_add_discount_form_before_use_once', array( $this, 'add_discount' ) );
		add_action( 'edd_edit_discount_form_before_use_once', array( $this, 'edit_discount' ), 10, 2 );

		add_action( 'edd_post_update_discount', array( $this, 'save_discount' ), 10, 2 );
		add_action( 'edd_post_insert_discount', array( $this, 'save_discount' ), 10, 2 );

		add_filter( 'edd_cart_contents', array( $this, 'maybe_apply_trial' ), 10, 1 );
		add_filter( 'edd_recurring_download_has_free_trial', array( $this, 'maybe_has_trial' ), 10, 2 );

		add_filter( 'edd_get_cart_discount_html', array( $this, 'discount_cart_row' ), 10, 4 );
	}

	private function filters() {

	}

	/**
	 * Add our discount option to the add discount screen
	 */
	public function add_discount() {
		$this->add_edit_discount( 0 );
	}

	/**
	 * Add our discount option to the edit discount screen
	 * @param  int   $discount_id The discount ID
	 * @param  array $discount    The discount options
	 * @return void
	 */
	public function edit_discount( $discount_id, $discount ) {
		$this->add_edit_discount( $discount_id );
	}

	private function add_edit_discount( $discount_id ) {
		$applies_trial = get_post_meta( $discount_id, '_edd_trial_discount', true );
		$checkbox_args  = array(
			'id'       => 'applies_free_trial',
			'name'     => 'applies_free_trial',
			'current'  => empty( $applies_trial ) ? 0 : 1,
			'class'    => 'edd-checkbox',
		);

		?>
		<tr>
			<th scope="row" valign="top">
				<label for="applies_free_trial">Discount Applies Free Trial</label>
			</th>
			<td>
				<?php echo EDD()->html->checkbox( $checkbox_args ); ?>
				<span class="description">When checked, adding this discount will convert the purchase to a free trial, even if the product doesn't have the option enabled..</span>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the discount options
	 * @param  array $discount_details Discount code details
	 * @param  int   $discount_id      The discount ID being modified
	 * @return void
	 */
	public function save_discount( $discount_details, $discount_id ) {
		$enabled = ( ! empty( $_POST['applies_free_trial'] ) ) ? 1 : 0;
		update_post_meta( $discount_id, '_edd_trial_discount', $enabled );
	}

	public function is_trial_discount_applied() {
		$return = false;
		$discounts = edd_get_cart_discounts();

		if ( empty( $discounts ) ) {
			return $return;
		}

		foreach ( $discounts as $discount ) {
			$trial_enabled = $this->is_trial_discount( $discount );
			if ( $trial_enabled ) {
				$return = true;
			}

		}

		return $return;
	}

	public function is_trial_discount( $discount ) {
		$return = false;

		$discount_id    = edd_get_discount_id_by_code( $discount );

		if ( false === $discount_id && is_numeric( $discount ) ) {
			$discount_id = $discount;
		}

		$trial_enabled = get_post_meta( $discount_id, '_edd_trial_discount', true );
		if ( ! empty( $trial_enabled ) ) {
			$return = true;
		}

		return $return;
	}

	public function maybe_apply_trial( $cart ) {
		if ( ! $this->is_trial_discount_applied() ) {
			return $cart;
		}

		foreach ( $cart as &$item ) {

			if ( function_exists( 'EDD_Recurring' ) ) {
				$has_variable_pricing = edd_has_variable_prices( $item['id'] );
				if ( $has_variable_pricing ) {
					$is_recurring = EDD_Recurring()->is_price_recurring( $item['id'], $item['options']['price_id'] );
				} else {
					$is_recurring = EDD_Recurring()->is_recurring( $item['id'] );
				}

				if ( $is_recurring ) {
					if ( $has_variable_pricing ) {
						$period = EDD_Recurring()->get_period( $item['options']['price_id'], $item['id'] );
					} else {
						$period = EDD_Recurring()->get_period_single( $item['id'] );
					}

					$item['options']['recurring']['trial_period']['quantity'] = 1;
					$item['options']['recurring']['trial_period']['unit']   = $period;
				}
			}

		}

		return $cart;
	}

	public function maybe_has_trial( $has_trial, $download_id ) {
		if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || false === DOING_AJAX ) ) {
			return $has_trial;
		}

		return $this->is_trial_discount_applied();
	}

	public function discount_cart_row( $discount_html, $discount, $rate, $remove_url ) {
		if ( ! $this->is_trial_discount( $discount ) ) { return $discount; }

		$discount_html = '';
		$discount_html .= "<span class=\"edd_discount\">\n";
			$discount_html .= "<span class=\"edd_discount_rate\">$discount</span>\n";
			$discount_html .= "<a href=\"$remove_url\" data-code=\"$discount\" class=\"edd_discount_remove\"></a>\n";
		$discount_html .= "</span>\n";

		return $discount_html;
	}


}

EDD_Free_Trial_Discounts::instance();