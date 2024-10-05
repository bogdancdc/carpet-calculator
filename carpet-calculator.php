<?php
/*
		Plugin Name: Калькулятор стоимости стирки ковров
		Description: Плагин для расчета стоимости стирки ковров
		Version: 1.0
		Author: cdc
		License: GPL2
		Requires: contact-form-7
*/


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

	function carpet_calculator_admin_styles() {
		$screen = get_current_screen();
		if ( $screen->id === 'toplevel_page_carpet_calculator_settings' ) {
			wp_enqueue_style( 'carpet-calculator-admin-style', plugins_url( 'css/admin-style.css', __FILE__ ) );
		}
	}
	add_action( 'admin_enqueue_scripts', 'carpet_calculator_admin_styles' );
    add_filter('wpcf7_autop_or_not', '__return_false');


	// Замена инпута на кнопку
	add_filter( 'wpcf7_form_elements', 'custom_form_submit' );
	function custom_form_submit( $form ) {
		$form = preg_replace_callback(
			'/<input(?=[^>]+type="submit")([^>]*)value="([^"]+)"([^>]*)>/',
			function( $matches ) {
				$value = esc_attr( $matches[2] );
				return sprintf( '<button type="submit"%1$s>%2$s</button>', $matches[1] . $matches[3], $value );
			},
			$form
		);
		$form = preg_replace( '/<\/input>/', '</button>', $form );
		return $form;
	}

	function carpet_calculator_shortcode() {
		
		ob_start();
		
		$tariffs = get_option('carpet_calculator_tariffs', array());
		$default_tariff = get_option('carpet_calculator_default_tariff', 0);
		$services = get_option('carpet_calculator_services', array());
		$all_discounts = get_option('carpet_calculator_options', array());
		$discounts = array();
		$discounts_human_readable = array();

		foreach ($all_discounts as $key => $value) {
				foreach ($value as $key_1 => $discount) {
					if ( isset($discount['status']) && $discount['status'] == "1" ) {
						$discounts[$key][$key_1] = $discount;
						$discounts_human_readable[$key]['description'] = $all_discounts[$key . '_description'];
						
						if ($key == "n_plus_one_discount") {
							$discounts[$key] = $value;
							$discounts_human_readable[$key]['name'] = "Скидка " . $discount['qty'] .  "+1";
							$discounts_human_readable[$key]['values'][$key_1] = $discount['qty'];
						}
						if ( isset($discount['value']) ) {
							if ( $discount['type'] == "percent" ) {
								$discounts_human_readable[$key]['values'][$key_1] = $discount['value'] . "%";
							} elseif ( $discount['type'] == "fixed" ) {
								$discounts_human_readable[$key]['values'][$key_1] = $discount['value'] . "₽";
							}
							if ( $key == "quantity_discount" ) {
								$discounts_human_readable[$key]['name'] = "От количества ковров";
							} elseif ( $key == "order_amount_discount" ) {
								$discounts_human_readable[$key]['name'] = "От суммы заказа";
							} elseif ( $key == "category_discount" ) {
								$discounts_human_readable[$key]['name'] = "Для определенных категорий клиентов";
							}
						}
					}
				}
				if ($key == "first_order_discount" && isset($value['status'])) {
					$discounts[$key] = $value;
					$discounts_human_readable[$key]['name'] = "Скидка на первый заказ";
					$discounts_human_readable[$key]['status'] = $value['status'];
					$discounts_human_readable[$key]['description'] = $all_discounts[$key . '_description'];
					if ( $value['type'] == "percent" ) {
						$discounts_human_readable[$key]['values'][0] = $value['value'] . "%";
					} elseif ( $discount['type'] == "fixed" ) {
						$discounts_human_readable[$key]['values'][0] = $value['value'] . "₽";
					}
				}
		}
		?>
			<div class="uk-grid calc_height" uk-grid style="position: relative;">
			  <div class="uk-width-1-1 uk-width-2-3@m">
				
				<button class="uk-button uk-button-default" id="double_btn" type="button">+ Добавить ковер</button>
			</div>
			<div class="uk-width-1-1 uk-width-1-3@m">
				<div class="uk-card uk-card-default uk-card-body uk-position-z-index" uk-sticky="media: 640; end: !.calc_height; offset: 80" style="padding: 30px;">
					<h3>Данные заказа</h3>
					<div id="t_sum_0"></div>
					<div id="t_end"></div>
					<!-- select discount -->
					<?php if(count($discounts_human_readable)) { ?>
						<div class="uk-margin uk-card uk-card-default uk-card-body uk-padding-small">
							<label class="uk-form-label uk-text-small" for="select_discount">Выберите скидку, которую хотите применить <span uk-icon="icon: question; ratio: 1" id="sdDiscount" uk-tooltip="К заказу можно применить только 1 скидку"></span></label>
							<div class="uk-form-controls uk-margin">
								<select class="uk-select" id="select_discount" name="carpets[discount]">
									<option value="Нет" data-value="0">Нет</option>
									<?php
										foreach ($discounts_human_readable as $key => $value) {
											echo '<option value="'.$value["name"].'" data-value="'.$key.'">'.$value["name"].'</option>';
										}
									?>
								</select>
							</div>
							<div class="uk-form-controls uk-margin" id="category_discount" style="display: none; margin-bottom: 16px;">
								<label class="uk-form-label uk-text-small" for="category_discount">Выберите категорию</label>
								<div class="uk-form-controls">
									<select class="uk-select" id="category_discount_select" name="carpets[category_discount]">
										<option value="Выбрать">Выбрать</option>
										<?php
											$categories = $discounts['category_discount'];
											foreach ( $categories as $category ) {
												$cd_type = $category['type'] == 'percent' ? '%' : '₽';
												echo '<option value="' . $category['name'] . '" data-value="' . $category['category'] . '|' . $category['type'] . '|' . $category['value'] . '">' . $category['name'] . ' (' . $category['value'] . $cd_type . ')' . '</option>';
											}
										?>
									</select>
								</div>
							</div>
						</div>
					<?php }?>
					<!-- total -->
					<div class="uk-grid uk-flex-middle" style="row-gap: 12px; margin-top: 30px">
						<div class="uk-width-1-3">
							<label class="uk-form-label uk-text-small" for="form-stacked-select">Подытог</label>
						</div>
						<div class="uk-width-2-3 uk-text-right uk-text-small" id="s_subtotal"></div>
						<div class="uk-width-1-3" styl>
							<label class="uk-form-label uk-text-small" for="form-stacked-select">Скидка</label>
						</div>
						<div class="uk-width-2-3 uk-text-right uk-text-small"id="s_discount"></div>
					</div>
					<div class="uk-margin uk-grid uk-flex-middle">
						<div class="uk-width-1-3">
							<label class="uk-form-label uk-text-bold uk-text-lead" for="form-stacked-select" style="font-style: initial">Итого</label>
						</div>
						<div class="uk-width-2-3">
							<input class="uk-input uk-form-blank uk-text-bold uk-text-lead uk-text-right" type="text" id="total" name="carpets[total]" value="0" readonly style="font-style: initial">
						</div>
					</div>
				<?php foreach ($discounts_human_readable as $key => $value) { 
					if (isset($value['description']) && $value['description'] != '') {
				?>	
					<div class="ds_attention <?php echo $key; ?> uk-margin" id="<?php echo $key; ?>_description" style="display: none">
						<?php
							echo $value['description'];
						?>
					</div>
				<?php 
					}
				}
				?>
				</div>
			</div>
		</div>

<!--		<script src="https://code.jquery.com/jquery-3.6.3.min.js" integrity="sha256-pvPw+upLPUjgMXY0G+8O0xUf+/Im1MZjXxxgOcBQBXU=" crossorigin="anonymous"></script> -->
		<script>
			jQuery(document).ready(function($){

				<?php
					// create  json of tariffs and services for js 
					echo 'var tariffs = ' . json_encode($tariffs) . ';';
					echo 'var default_tariff = ' . json_encode($default_tariff) . ';';
					echo 'var services = ' . json_encode($services) . ';';
					echo 'var discounts = ' . json_encode($discounts_human_readable) . ';';

				?>

				var your_message_val = {};
				var val_string = '';
				var your_message_services = '';

				// tariffs convet to array
				var tariffs_array = [];
				for (var key in tariffs) {
					tariffs_array.push(tariffs[key]);
				}

				// services convet to array
				var services_array = [];
				for (var key in services) {
					services_array.push(services[key]);
				}

				var i_id = 0;

				function create_carpet_html (index_id) {

					var tariffs_html = '';
					var services_html = '';

					tariffs_array.forEach(element => {

						tariffs_html += '<div class="uk-width-1-1 uk-width-1-2@s uk-width-1-3@xl uk-grid-margin uk-first-column">'+
						'<label class="uk-flex uk-button uk-button-default uk-flex-between uk-flex-middle" style="text-align: left; padding: 12px 16px; font-size: 0; height: 100%">'+
						'<input '+
						'class="uk-radio tariff_price" '+
						'type="radio" '+
						'name="carpets[' + index_id + '][Тариф]" '+
						'id="tariff_price_' + index_id + '" '+
						'value="' + element.name + ' (' + element.price + ' + ₽/м²)" '+
						'data-price="' + element.price + '" '+
						'data-name="' + element.name + '"';
						if (default_tariff == element.name) {
							tariffs_html += 'checked="checked" '
						}
						
						tariffs_html += 'style="margin-top: 0"> '+
						'<span class="uk-form-label uk-margin-auto-right  uk-margin-small-left" style="font-size: 13px; line-height: 1.3; ">' + element.name + '</span> '+
						'<span class="uk-form-label uk-text-right uk-text-nowrap" style="font-size: 13px; line-height: 1.3; ">' + element.price + ' ₽/м²</span> '+
						'</label> '+
						'</div>'
					});


					var service_unit = 'м²';
					services_array.forEach(element => {
						service_unit = element.unit == 'pcs' ? 'шт' : 'м²';
						services_html += '<div class="uk-width-1-1 uk-width-1-2@s uk-grid-margin uk-first-column">'+
						'<label class="uk-flex uk-button uk-button-default uk-flex-between uk-flex-middle" style="text-align: left; padding: 12px 16px; font-size: 0; height: 100%">'+
						'<input '+
						'class="uk-checkbox service_price '+  element.unit + '" ' +
						'type="checkbox" '+
						'name="carpets[' + index_id + '][Доп услуги][]" '+
						'id="service_price_' + index_id + '" '+
						'value="' + element.name + ' (' + element.price + ' + ₽/' + service_unit + ')" '+
						'data-price="' + element.price + '" '+
						'data-name="' + element.name + '" '+
						'data-unitname="' + service_unit + '"' +
						'data-unitvalue="' + element.unit + '"' +
						'style="margin-top: 0"> '+
						'<span class="uk-form-label uk-margin-auto-right  uk-margin-small-left" style="font-size: 13px; line-height: 1.3; ">' + element.name + '</span>'+
						'<span class="uk-form-label uk-text-right uk-text-nowrap" style="font-size: 13px; line-height: 1.3; ">' + element.price + ' ₽/' + service_unit + '</span>'+
						'</label>'+
						'</div>'	
					});

					var carpet_html = '<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom double d_' + index_id + '" data-id="' + index_id + '">'+
					'<div class="uk-position-top-right uk-position-small uk-hidden-hover delete_button" ' +
					(index_id == 0 ? ' style="display: none"' : '') + '>'+
					'<a class="uk-link-reset uk-icon-button uk-button-danger" uk-icon="icon: close; ratio: 1" uk-tooltip="Удалить ковер"></a>'+
					'</div>'+
					'<h3 style="margin-top: 0">Размеры ковра:</h3>'+
					'<div class="uk-form-controls uk-form-controls-text uk-grid-medium" uk-grid>'+
					'<div class="uk-width-1-1 uk-width-1-2@s uk-width-1-3@xl uk-grid-margin uk-first-column">'+
					'<label class="uk-form-label uk-margin-auto-right  uk-margin-small-left">Длина (м)*</label>'+
					'<input class="uk-input wy"  min="1" type="text" placeholder="_.__" name="carpets[' + index_id + '][Длина]" data-inputmask="\'mask\': \'9.99\', \'max\': \'9.99\'">'+
					'</div>'+
					'<div class="uk-width-1-1 uk-width-1-2@s uk-width-1-3@xl uk-grid-margin uk-first-column">'+
					'<label class="uk-form-label uk-margin-auto-right  uk-margin-small-left">Ширина (м)*</label>'+
					'<input class="uk-input wx"  min="1" type="text" placeholder="_.__" name="carpets[' + index_id + '][Ширина]" data-inputmask="\'mask\': \'9.99\', \'max\': \'9.99\'">'+
					'</div>'+
					'</div>'+
					'<h3>Тарифы:</h3>'+
					'<div class="uk-form-controls uk-form-controls-text uk-grid-medium" uk-grid>'+
					tariffs_html +
					'</div>'+
					'<h3>Дополнительные услуги:</h3>'+
					'<div class="uk-form-controls uk-form-controls-text uk-grid-medium" uk-grid>'+
					services_html +
					'</div>'+
					'</div>';
					
					return carpet_html;
				}

				function insert_carpet () {
					var carpet_html = create_carpet_html(i_id);
					$('#double_btn').before(carpet_html);
					if (i_id > 0) {
						$([document.documentElement, document.body]).animate({
							scrollTop: $(".d_"+i_id).offset().top - 90
						}, 1000);
					}
					i_id++;
					Inputmask().mask(document.querySelectorAll("input"));
					const wys = document.querySelectorAll(".wy");
					const wxs = document.querySelectorAll(".wx");
					
					wys.forEach(e => {
						e.addEventListener("keydown", (e) => {
							// simulate decimal number input behaveour on arrow up and down press in text input with the max value of 9.99 with step of 1
							if (e.key === "ArrowUp") {
								// if value is empty, replace it with 0.00
								if (e.target.value === "") {
									e.target.value = "0.00";
								}
								// replace all the underscore signs with 0
								e.target.value = e.target.value.replaceAll('_', '0');
								e.target.value = Math.min(9.99, +parseFloat(e.target.value) + 1);
							}
							if (e.key === "ArrowDown") {
								e.target.value = e.target.value.replaceAll('_', '0');
								if (e.target.value !== "" && parseInt(e.target.value) > 1) {
									e.target.value = Math.max(0, + parseFloat(e.target.value) - 1);
								} else {
									e.target.value = "1.00";
								}
							}
							changeHandler({target: e.target});
						})
						e.addEventListener("keyup", (e) => {
							if (e.key === "ArrowUp" || e.key === "ArrowDown") {
								e.target.value = e.target.value.replaceAll('_', '0');
							}
							changeHandler({target: e.target});
						})
					})
					
					wxs.forEach(e => {
						e.addEventListener("keydown", (e) => {
							// simulate decimal number input behaveour on arrow up and down press in text input with the max value of 9.99 with step of 1
							if (e.key === "ArrowUp") {
								// if value is empty, replace it with 0.00
								if (e.target.value === "") {
									e.target.value = "0.00";
								}
								// replace all the underscore signs with 0
								e.target.value = e.target.value.replaceAll('_', '0');
								e.target.value = Math.min(9.99, +parseFloat(e.target.value) + 1);
							}
							if (e.key === "ArrowDown") {
								e.target.value = e.target.value.replaceAll('_', '0');
								if (e.target.value !== "" && parseInt(e.target.value) > 1) {
									e.target.value = Math.max(0, + parseFloat(e.target.value) - 1);
								} else {
									e.target.value = "1.00";
								}
							}
							changeHandler({target: e.target});
						})
						e.addEventListener("keyup", (e) => {
							if (e.key === "ArrowUp" || e.key === "ArrowDown") {
								e.target.value = e.target.value.replaceAll('_', '0');
							}
							changeHandler({target: e.target});
						})
					})
				}

				insert_carpet();


				function brew_summary_table (nominal_index, ct_index, wx, wy, ct_tariff_sum, ct_tariff_name, ct_services, is_html) {
					var your_message_carpet = '';
					var count_table = '';
					var sub_total = 0;

					function ct_services_html () {
						your_message_services = '';
						var ct_services_html_text = '';
						for (var i = 0; i < ct_services.length; i++) {
							var sum = ct_services[i].price;
							if (ct_services[i].unit.value == 'sqm') {
								sum = ct_services[i].price * wx * wy;
							}

							sub_total += sum;

							ct_services_html_text += '<tr class="table_services uk-text-small">'+
							'<td>'+ ct_services[i].name + ' <span class="uk-text-nowrap">(' + ct_services[i].price + '/' + ct_services[i].unit['name'] + ')</td>'+
							'<td class="uk-text-nowrap uk-text-right" style="vertical-align: bottom;">'+ sum  + ' ₽</td>'+
							'</tr>';

							your_message_services += ct_services[i].name + ' (' + ct_services[i].price + '/' + ct_services[i].unit['name'] + ') - ' + sum + ' ₽\n';
						}
						return ct_services_html_text;
					}

					sub_total += ct_tariff_sum * wx * wy;

					count_table += '<table class="uk-table uk-table-small uk-table-hover uk-table-divider double_table dt_' + ct_index + ' uk-text-small">' +
						'<thead>'+
						'<th colspan="2" class="table_th">'+
						'Ковер #'  + nominal_index + 
						'</th>'+
						'</thead>'+
						'<tbody>'+
						'<tr class="table_tariff">'+
						'<td class="uk-table-expand">'+
						'<b>Тариф</b> <br>'+
						'<span class="tariff">' + ct_tariff_name + ' (' + ct_tariff_sum + '/м²)</span>'+
						'</td>'+
						'<td class="uk-table-shrink" style="white-space: nowrap; text-align: right; vertical-align: bottom"><span class="tariff_sum">' + parseInt(ct_tariff_sum * wx * wy) + '</span> ₽</td>'+
						'</tr>'+
						ct_services_html() +
						'<tr class="table_subtotal">'+
						'<td><b>Итого</b></td>'+
						'<td style="white-space: nowrap"><span class="sub_total">' + parseInt(sub_total) + '</span> ₽</td>'+
						'</tr>'+
						'</tbody>'+
						'</table>';
					
					your_message_carpet +=
						'Ковер #' + nominal_index + '\n' +
						'Тариф: ' + ct_tariff_name + ' (' + ct_tariff_sum + '/м²)\n' +
						'Ширина: ' + wx + ' м\n' +
						'Длина: ' + wy + ' м\n' +
						your_message_services +
						'\nИтого: ' + parseInt(sub_total) + ' ₽\n'+
						'------------------\n\n';
					
					return is_html ? count_table : your_message_carpet;
				}

				const handle_discount = (total, type, value) => {
					var s_total = 0;
					if(type == 'percent'){
						s_total = '~' + parseInt(total - (total * (1 - parseInt(value)/100)));
						total = parseInt(total * (1 - parseInt(value)/100));
					} else {
						total = total - parseInt(value);
						s_total = parseInt(value);
					}

					if (value == 0) {
						$('#s_discount').html('0 ₽');
					} else {
						$('#s_discount').html(s_total + ' ₽');
					}
					return total;
				}

				const discountHandler = (e) => {
					var discount = $("#select_discount").find(":selected").data('value');
					var total = 0;
					var type = 'fixed';
					var value = 0;

					$('.sub_total').each(function(){
						total += parseInt($(this).text());
					});
					$('.ds_attention').hide();


					if (discount != 'category_discount') {
						$('#category_discount').hide();
						$('#category_discount_select').prop('selectedIndex', 0);
						
					}

					switch(discount){
						case 'category_discount':
							$('#category_discount').show();
							$('.ds_attention.category_discount').show();
							type = $('#category_discount_select').find(":selected").data('value').split('|')[1];
							value = $('#category_discount_select').find(":selected").data('value').split('|')[2];
							break;
						case 'quantity_discount':
							$('.ds_attention.quantity_discount').show();
							const ds_qty_amount_type = [
								<?php foreach($discounts['quantity_discount'] as $quantity_discount){ ?>
									{
										<?php echo $quantity_discount['qty']; ?>: {
											type: '<?php echo $quantity_discount['type']; ?>',
											value: '<?php echo $quantity_discount['value']; ?>'
										},
									},
								<?php } ?>
							]
							ds_qty_amount_type.forEach((item, index) => {
								if($('.double').length >= Object.keys(item)[0]){
									type = item[Object.keys(item)[0]].type;
									value = item[Object.keys(item)[0]].value;
								}
							});
							break;
						case 'order_amount_discount':
							$('.ds_attention.order_amount_discount').show();
							const ds_order_amount_type = [
								<?php foreach($discounts['order_amount_discount'] as $order_amount_discount){ ?>
									{
										<?php echo $order_amount_discount['amount']; ?>: {
											type: '<?php echo $order_amount_discount['type']; ?>',
											value: '<?php echo $order_amount_discount['value']; ?>'
										},
									},
								<?php } ?>
							]
							ds_order_amount_type.forEach((item, index) => {
								if(total >= Object.keys(item)[0]){
									type = item[Object.keys(item)[0]].type;
									value = item[Object.keys(item)[0]].value;
								}
							});
							break;
						case 'n_plus_one_discount':
							$('.ds_attention.n_plus_one_discount').show();
							const ds_n_plus_one_type = [
								<?php foreach($discounts['n_plus_one_discount'] as $key => $n_plus_one_discount){ ?>
									{
										qty: '<?php echo $n_plus_one_discount['qty']; ?>'
									},
								<?php } ?>
							]
							ds_n_plus_one_type.forEach((item, index) => {
								const cheapest_carpet = $('.sub_total').sort(function(a, b){
									return $(a).text() - $(b).text();
								}).first();
								if($('.double').length - 1 == parseInt(item.qty)){
									type = 'fixed';
									value = cheapest_carpet.text();
								}
							});
							//find the cheapest carpet and discount it
							break;
						<?php
							if (isset($discounts['first_order_discount']['status'])) {
								echo "case 'first_order_discount':\n";
								echo "$('.ds_attention.first_order_discount').show();\n";
								echo "type = ";
									$discount = $discounts['first_order_discount'];
									if($discount['type'] == 'percent'){
										echo "'percent'\n";
									} else {
										echo "'fixed'\n";
									}
								echo "value =";
									$discount = $discounts['first_order_discount'];
									echo $discount['value'] . "\n";
								echo "break;\n";
							}
							?>
					}
					if (total > 0) {
						total = handle_discount(total, type, value);
					}

					$('#total').val(total + ' ₽');
					your_message_val['s_discount'] = 'Скидка: ' + value + (type == 'fixed' ? ' ₽' : '%') + '\n';
					your_message_val['total'] = 'Итого: ' + total + ' ₽';
					
					val_string = Object.values(your_message_val).join('');
					
					$('[name="your-message"]').val(val_string);
				}

				const changeHandler = (e, ct_index) => {
					var targetParent = $(e.target).parents('.double');
					var targetTable = $('.dt_' + $(targetParent).data('id'));
					var wy = parseFloat($(targetParent).find('.wy').val() == '' ? 1 : $(targetParent).find('.wy').val());
					var wx = parseFloat($(targetParent).find('.wx').val() == '' ? 1 : $(targetParent).find('.wx').val());

					if ($(targetParent).find('.tariff_price:checked') == null) {
						$(targetParent).find('.tariff_price').first().prop('checked', true);
					}

					var tariff = parseInt($(targetParent).find('.tariff_price:checked').data('price'));
					var tariffName = $(targetParent).find('.tariff_price:checked').data('name');

					var ct_services = [];

					$(targetParent).find('.service_price:checked').map(function(){
						ct_services.push(
							{
								"name": $(this).data('name'),
								"price": $(this).data('price'),
								"unit": {
									name: $(this).data('unitname'),
									value: $(this).data('unitvalue')
								}
							});
					});

					if ($('#t_sum_'+ targetParent.data('id')).length == 0) {
						$('#t_end').before('<div id="t_sum_'+ targetParent.data('id') +'"></div>');
					}
					$('#t_sum_'+ targetParent.data('id')).html(brew_summary_table(targetParent.index() + 1, targetParent.data('id'), wx, wy, tariff, tariffName, ct_services, true));
					
					your_message_val[targetParent.data('id')] = brew_summary_table(targetParent.index() + 1, targetParent.data('id'), wx, wy, tariff, tariffName, ct_services, false);

					var total = 0;
					$('.sub_total').each(function(){
						total += parseInt($(this).text());
					});

					$('#total').val(total + ' ₽');

					$('#s_subtotal').text(total + ' ₽');

					your_message_val['a_sum'] = 'Подытог: ' + total + ' ₽\n';

					discountHandler();

				}
				$('#double_btn').click(function(){
					insert_carpet();
					var inserted_carpet = $('.double').last();
					changeHandler({target: $(inserted_carpet).find('input')[0]});
				});

				$(document).on('click', '.delete_button', function(){
					// i_id--;
					$('#t_sum_' + $(this).parents('.double').data('id')).remove();
					$(this).parents('.double').remove();

					changeHandler({target: $('.double input')[0]});
				});

				$(document).on('change', '.double input' ,function () {
					changeHandler({target: this});
				});

				$('#select_discount').on('change', discountHandler);
				$('#category_discount_select').on('change', discountHandler);
				changeHandler({target: $('.double input')[0]});

				function reset_all_carpets () {
					// remove all carpets
					$('.double').remove();
					// reset i_id
					i_id = 0;
					// reset tables
					$('#t_sum_0').nextUntil('#t_end').remove();
					// insert one carpet
					insert_carpet();
					// run changeHandler
					changeHandler({target: $('.double input')[0]});
				}

				document.addEventListener( 'wpcf7mailsent', function( event ) {
					reset_all_carpets()
				}, false );
			});
		</script>
		<?php return ob_get_clean();
	}

	add_action('admin_menu', 'carpet_calculator_settings_page');
	add_action( 'admin_init', 'carpet_calculator_register_settings' );
	add_action( 'wpcf7_before_send_mail', 'my_cf7_mail_body' );

	function my_cf7_mail_body( $contact_form ) {
		$submission = WPCF7_Submission::get_instance();

		if ( $submission ) {

			$posted_data = $_POST;

			$body = '<table>';
			// add name and phone to the body
			foreach ($posted_data['carpets'] as $key => $value) {
				if (is_array($value) && $key != 'sender') {
					$body .= '<tr><td colspan="2"><p style="margin: 15px 0 0;font-size:20px;font-weight:bold;">Ковер #' . $key + 1 . '</p></td></tr>';
					foreach ($value as $inner_key => $inner_value) {
						if (is_array($inner_value)) {
							$body .= '<tr><td colspan="2"><p style="margin: 0 0 0 15px"><strong>' . $inner_key . '</strong></p></td></tr>';
							foreach ($inner_value as $k => $v) {
								if ($k == $v) {
									$body .= '<tr><td colspan="2"><p style="margin: 0 0 0 15px">' . $k . '</p></td></tr>';
								} else {
									$body .= '<tr><td><p style="margin: 0 0 0 15px">' . $k . ' </p></td><td><p style="margin: 0">' . $v . '</p></td></tr>';
								}
							}
						} else {
							$body .= '<tr><td><p style="margin: 0 0 0 15px">' . $inner_key . ' </p></td><td><p style="margin: 0">' . $inner_value . '</p></td></tr>';
						}
					}
				} else {
					if ($key == 'total') {
						$key = 'Итого';
					} else if ($key == 'discount') {
						$key = 'Скидка';
					} else if ($key == 'category_discount') {
						$key = 'Категория';
					}

					if ($value != 'Выбрать') {
						$body .= '<tr><td><p style="margin: 0;font-size:24px;font-weight:bold;"><strong>' . $key . '</strong></p></td><td><p style="margin: 0;">' . $value . '</p></td></tr>';
					}
				}
			}
			$body .= '</table>';
			
			$mail = $contact_form->prop( 'mail' );
			$mail['body'] .= $body;

			$contact_form->set_properties( array( 'mail' => $mail ) );
		}
	}
	add_filter( 'wp_mail_content_type', function( $content_type ) {
		return 'text/html';
	});


	function my_plugin_register_shortcodes() {
		add_shortcode( 'carpet_calculator', 'carpet_calculator_shortcode' );
	}
	add_action( 'wpcf7_init', 'my_plugin_register_shortcodes', 20 );



function carpet_calculator_settings_page() {
	add_menu_page(
        'Настройки калькулятора ковров',
        'Калькулятор',
        'manage_options',
        'carpet_calculator_settings',
        'carpet_calculator_settings_page_callback',
        'dashicons-calculator',
	);
}

function carpet_calculator_settings_page_callback() {
	
	// получаем текущие значения настроек
	$options = get_option('carpet_calculator_options', array());
	$discount = get_option('carpet_calculator_discount', 0);
	$category_discount = isset($options['category_discount']) ? $options['category_discount'] : array();

	if (isset($_POST['save_settings'])) {
		// сохраняем значения настроек
		$_POST['tariffs'] = array_filter($_POST['tariffs'], function($tariff) {
			return !empty($tariff['name']) && !empty($tariff['price']);
		});
		$_POST['services'] = array_filter($_POST['services'], function($service) {
			return !empty($service['name']) && !empty($service['price']);
		});
		$_POST['carpet_calculator_options']['category_discount'] = array_filter($_POST['carpet_calculator_options']['category_discount'], function($category_discount) {
			return $category_discount['category'] != 'none' && $category_discount['type'] != 'none' && !empty($category_discount['value']);
		});
		$_POST['carpet_calculator_options']['quantity_discount'] = array_filter($_POST['carpet_calculator_options']['quantity_discount'], function($quantity_discount) {
			return !empty($quantity_discount['qty']) && $quantity_discount['type'] != 'none' && !empty($quantity_discount['value']);
		});
		$_POST['carpet_calculator_options']['order_amount_discount'] = array_filter($_POST['carpet_calculator_options']['order_amount_discount'], function($order_amount_discount) {
			return !empty($order_amount_discount['amount']) && $order_amount_discount['type'] != 'none' && !empty($order_amount_discount['value']);
		});
		$_POST['carpet_calculator_options']['n_plus_one_discount'] = array_filter($_POST['carpet_calculator_options']['n_plus_one_discount'], function($n_plus_one_discount) {
			return !empty($n_plus_one_discount['qty']);
		});

		update_option('carpet_calculator_tariffs', $_POST['tariffs']);
		update_option('carpet_calculator_services', $_POST['services']);
		update_option('carpet_calculator_discount', $_POST['discount']);
		update_option('carpet_calculator_default_tariff', $_POST['default_tariff']);
		update_option('carpet_calculator_options', $_POST['carpet_calculator_options']);
	}
	?>
	<div class="wrap carpet-calculator-settings">
		<h1>Настройки калькулятора</h1>
		
		<form method="post">
			<input type="hidden" name="save_settings" value="1">


			<?php settings_fields( 'carpet_calculator_options' ); ?>
    		<?php do_settings_sections( 'carpet_calculator_settings' ); ?>

			<?php submit_button(); ?>
		
		</form>
	</div>
	<script>
		jQuery(document).ready(function($) {
			$('#add-tariff').click(function() {
				var index = $('#tarrifsTable tbody tr').length + 1;
				var html = '<tr><td><input type="text" name="tariffs['+index+'][name]" value="" placeholder="Название тарифа" ></td>';
				html += '<td><input type="number" name="tariffs['+index+'][price]" value="" placeholder="Цена за кв. м:"></td></tr>';
				$('#tarrifsTable tbody').append(html);
			});
			$('#add-service').click(function() {
				var index = $('#additionalsTable tbody tr').length + 1;
				var html = '<tr><td><input type="text" name="services['+index+'][name]" value="" placeholder="Название услуги" style="width: 300px"></td>';
				html += '<td><input type="number" name="services['+index+'][price]" value="" placeholder="Цена"></td>';
				html += '<td><select name="services['+index+'][unit]" style="width: 75px">';
				html += '<option value="sqm">м2</option>';
				html += '<option value="pcs">шт</option>';
				html += '</select></td></tr>';
				$('#additionalsTable tbody').append(html);
			});
			$('#add-category').click(function() {
				var index = $('#categoriesTable tbody tr').length + 1;
				var html = '<tr>'+
						'<td>'+
							'<select name="carpet_calculator_options[category_discount][' + index + '][category]">'+
								'<option value="none">Не выбрано</option>'+
								'<option value="pensioners">Пенсионерам</option>'+
								'<option value="large_families">Многодетным семьям</option>'+
								'<option value="disabled">Инвалидам</option>'+
								'<option value="veterans">Ветеранам</option>'+
								'<option value="regular">Постоянным клиентам</option>'+
							'</select>'+
						'</td>'+
						'<td>'+
							'<select name="carpet_calculator_options[category_discount][' + index + '][type]">'+
								'<option value="none">Не выбрано</option>'+
								'<option value="percent">Процент</option>'+
								'<option value="fixed">Фиксированная сумма</option>'+
							'</select>'+
						'</td>'+
						'<td>'+
							'<input type="number" name="carpet_calculator_options[category_discount][' + index + '][value]" value="">'+
						'</td>'+
						'<td>'+
							'<input type="checkbox" id="category_discount_status" name="carpet_calculator_options[category_discount][' + index + '][status]" value="1"><br>'+
						'</td>'+
					'</tr>';
				$('#categoriesTable tbody').append(html);
			});
			$('#add-quantity').click(function() {
				var index = $('#quantityTable tbody tr').length + 1;
				var html = '<tr>'+
						'<td>'+
							'<input type="number" name="carpet_calculator_options[quantity_discount][' + index + '][qty]" value="">'+
						'</td>'+
						'<td>' +
							'<select name="carpet_calculator_options[quantity_discount][' + index + '][type]">' +
								'<option value="none">Не выбрано</option>' +
								'<option value="percent">Процент</option>' +
								'<option value="fixed">Фиксированная сумма</option>' +
							'</select>' +
						'</td>' +
						'<td>'+
							'<input type="number" name="carpet_calculator_options[quantity_discount][' + index + '][value]" value="">'+
						'</td>'+
						'<td>'+
							'<input type="checkbox" id="quantity_discount_status" name="carpet_calculator_options[quantity_discount][' + index + '][status]" value="1"><br>'+
						'</td>'+
					'</tr>';
				$('#quantityTable tbody').append(html);
			});
			$('#add-order-amount').click(function() {
				var index = $('#orderAmountTable tbody tr').length + 1;
				var html = '<tr>'+
						'<td>'+
							'<input type="number" name="carpet_calculator_options[order_amount_discount][' + index + '][amount]" value="">'+
						'</td>'+
						'<td>' +
							'<select name="carpet_calculator_options[order_amount_discount][' + index + '][type]">' +
								'<option value="none">Не выбрано</option>' +
								'<option value="percent">Процент</option>' +
								'<option value="fixed">Фиксированная сумма</option>' +
							'</select>' +
						'</td>' +
						'<td>'+
							'<input type="number" name="carpet_calculator_options[order_amount_discount][' + index + '][value]" min="0" max="100" value="">'+
						'</td>'+
						'<td>'+
							'<input type="checkbox" id="order_amount_discount_status" name="carpet_calculator_options[order_amount_discount][' + index + '][status]" value="1"><br>'+
						'</td>'+
					'</tr>';
				$('#orderAmountTable tbody').append(html);
			});
			$('#add-n-plus-one').click(function() {
				var index = $('#nPlusOneTable tbody tr').length + 1;
				var html = '<tr>'+
						'<td>'+
							'<input type="number" name="carpet_calculator_options[n_plus_one_discount][' + index + '][qty]" value="">'+
						'</td>'+
						'<td>'+
							'<input type="checkbox" id="n_plus_one_discount_status" name="carpet_calculator_options[n_plus_one_discount][' + index + '][status]" value="1"><br>'+
						'</td>'+
					'</tr>';
				$('#nPlusOneTable tbody').append(html);
			});
		});
	</script>
	<?php
}


// Настройки скидок
// Тип скидки
  // по количеству ковров
	// поля: количество ковров, скидка
  // n + 1 (n - количество ковров, 1 - количество бесплатных ковров (самый дешевый))
	// поля: количество ковров, количество бесплатных ковров
  // процент от суммы заказа в зависимости от количества ковров (повторяемое)
	// поля: количество ковров, процент скидки
  // по сумме заказа (повторяемое)
	// поля: сумма заказа, скидка
  // по социальному статусу (повторяемое)
	// поля: социальный статус, скидка
  // по колчиеству заказов одного клиента
	// поля: количество заказов, скидка

function carpet_calculator_customer_category_discount_callback() {
    $options = get_option( 'carpet_calculator_options' );
    $category_discount = isset( $options['category_discount'] ) ? $options['category_discount'] : array();
    ?>
	<table id="categoriesTable">
		<thead>
			<tr>
				<th>Категория</th>
				<th>Тип скидки</th>
				<th>Размер скидки</th>
				<th>Статус</th>
			</tr>
		</thead>
		<tbody>
			<?php
			if (!empty($category_discount)) {
				foreach ($category_discount as $key => $discount) {
					?>
					<tr>
						<td>
							<select name="carpet_calculator_options[category_discount][<?php echo $key; ?>][category]">
								<option value="none" <?php selected( $discount['category'], 'none' ); ?>>Не выбрано</option>
								<option value="pensioners" <?php selected( $discount['category'], 'pensioners' ); ?>>Пенсионерам</option>
								<option value="large_families" <?php selected( $discount['category'], 'large_families' ); ?>>Многодетным семьям</option>
								<option value="disabled" <?php selected( $discount['category'], 'disabled' ); ?>>Инвалидам</option>
								<option value="veterans" <?php selected( $discount['category'], 'veterans' ); ?>>Ветеранам</option>
								<option value="regular" <?php selected( $discount['category'], 'regular' ); ?>>Постоянным клиентам</option>
							</select>
							<?php
								// set discount name depending on discount category
								$discount_name = '';
								switch ($discount['category']) {
									case 'pensioners':
										$discount_name = 'Пенсионерам';
										break;
									case 'large_families':
										$discount_name = 'Многодетным семьям';
										break;
									case 'disabled':
										$discount_name = 'Инвалидам';
										break;
									case 'veterans':
										$discount_name = 'Ветеранам';
										break;
									case 'regular':
										$discount_name = 'Постоянным клиентам';
										break;
									default:
										$discount_name = 'Не выбрано';
										break;
								}
								echo '<input type="hidden" name="carpet_calculator_options[category_discount][' . $key . '][name]" value="' . $discount_name . '">';
							?>
						</td>
						<td>
							<select name="carpet_calculator_options[category_discount][<?php echo $key; ?>][type]">
								<option value="none" <?php selected( $discount['type'], 'none' ); ?>>Не выбрано</option>
								<option value="percent" <?php selected( $discount['type'], 'percent' ); ?>>Процент</option>
								<option value="fixed" <?php selected( $discount['type'], 'fixed' ); ?>>Фиксированная сумма</option>
							</select>
						</td>
						<td>
							<input type="number" name="carpet_calculator_options[category_discount][<?php echo $key; ?>][value]" value="<?php echo esc_attr( $discount['value'] ); ?>">
						</td>
						<td>
							<input
								type="checkbox"
								id="category_discount_status"
								name="carpet_calculator_options[category_discount][<?php echo $key; ?>][status]"
								value="1"
								<?php checked( $discount['status'], 1 ); ?>
							>
						</td>
					</tr>
					<?php
				}
			}
			
			if (isset($_POST['add_category_discount'])) {
				$category_discount[] = array(
					'name' => '',
					'status' => '',
					'category' => '',
					'type' => '',
					'value' => ''
				);
			}
			?>
		</tbody>
	</table>
	<div><button type="button" id="add-category">Добавить</button></div>
	<br>
	<!-- add textarea for discount description -->
	<label for="category_discount_description">Описание скидки</label>
	<textarea name="carpet_calculator_options[category_discount_description]" rows="10" cols="50"><?php echo $options['category_discount_description']; ?></textarea>
	<br><br><hr><br>
    <?php

}

function carpet_calculator_quantity_discount_callback() {
    $options = get_option( 'carpet_calculator_options' );
    $quantity_discount = isset( $options['quantity_discount'] ) ? $options['quantity_discount'] : array();
    ?>
			<table id="quantityTable">
				<thead>
					<tr>
						<th>Количество ковров</th>
						<th>Тип скидки</th>
						<th>Скидка</th>
						<th>Статус</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($quantity_discount)) : ?>
						<?php foreach ($quantity_discount as $key => $discount) : ?>
							<tr>
								<td>
									<input type="number" id="quantity_discount_qty_<?php echo $key; ?>" name="carpet_calculator_options[quantity_discount][<?php echo $key; ?>][qty]" min="0" value="<?php echo esc_attr( $discount['qty'] ); ?>">
								</td>
								<td>
									<select name="carpet_calculator_options[quantity_discount][<?php echo $key; ?>][type]">
										<option value="none" <?php selected( $discount['type'], 'none' ); ?>>Не выбрано</option>
										<option value="percent" <?php selected( $discount['type'], 'percent' ); ?>>Процент</option>
										<option value="fixed" <?php selected( $discount['type'], 'fixed' ); ?>>Фиксированная сумма</option>
									</select>
								</td>
								<td>
									<input type="number" id="quantity_discount_percent_<?php echo $key; ?>" name="carpet_calculator_options[quantity_discount][<?php echo $key; ?>][value]" min="0" max="100" step="1" value="<?php echo esc_attr( $discount['value'] ); ?>">
								</td>
								<td>
									<input
										type="checkbox"
										id="quantity_discount_status_<?php echo $key; ?>"
										name="carpet_calculator_options[quantity_discount][<?php echo $key; ?>][status]"
										value="1"
										<?php checked( $discount['status'], 1 ); ?>
									>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			<div><button type="button" id="add-quantity">Добавить</button></div>
			<br>
			<!-- add textarea for discount description -->
			<label>Описание скидки</label>
			<textarea name="carpet_calculator_options[quantity_discount_description]" rows="10" cols="50"><?php echo $options['quantity_discount_description']; ?></textarea>
			<br><br><hr><br>
    <?php
}

function carpet_calculator_first_order_discount_callback () {
	//non iterable, non user assignable
	$options = get_option( 'carpet_calculator_options' );
	$first_order_discount = isset( $options['first_order_discount'] ) ? $options['first_order_discount']['value'] : 0;
	$first_order_discount_type = isset( $options['first_order_discount'] ) ? $options['first_order_discount']['type'] : 'none';
	$first_order_discount_status = isset( $options['first_order_discount'] ) ? $options['first_order_discount']['status'] : '';
	?>
	<table id="first_order_discount">
		<thead>
			<tr>
				<th>Тип скидки</th>
				<th>Скидка</th>
				<th>Статус</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<select name="carpet_calculator_options[first_order_discount][type]">
						<option value="none" <?php selected( $first_order_discount_type, 'none' ); ?>>Не выбрано</option>
						<option value="percent" <?php selected( $first_order_discount_type, 'percent' ); ?>>Процент</option>
						<option value="fixed" <?php selected( $first_order_discount_type, 'fixed' ); ?>>Фиксированная сумма</option>
					</select>
				</td>
				<td>
					<input type="number" name="carpet_calculator_options[first_order_discount][value]" value="<?php echo esc_attr( $first_order_discount ); ?>">
				</td>
				<td>
					<input
						type="checkbox"
						id="order_amount_discount_status"
						name="carpet_calculator_options[first_order_discount][status]"
						value="1"
						<?php checked( $first_order_discount_status, 1 ); ?>
					>
				</td>
			</tr>
		</tbody>
	</table>
	<br>
	<!-- add textarea for discount description -->
	<label>Описание скидки</label>
	<textarea name="carpet_calculator_options[first_order_discount_description]" rows="10" cols="50"><?php echo $options['first_order_discount_description']; ?></textarea>
	<br><br><hr><br>
	<?php
}

function carpet_calculator_order_amount_discount_callback() {
	$options = get_option( 'carpet_calculator_options' );
	$order_amount_discount = isset( $options['order_amount_discount'] ) ? $options['order_amount_discount'] : array();
	?>
		<table id="orderAmountTable">
			<thead>
				<tr>
					<th>Сумма заказа</th>
					<th>Тип скидки</th>
					<th>Скидка</th>
					<th>Статус</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($order_amount_discount)) : ?>
					<?php foreach ($order_amount_discount as $key => $discount) : ?>
						<tr>
							<td>
								<input type="number" id="order_amount_discount_amount" name="carpet_calculator_options[order_amount_discount][<?php echo $key; ?>][amount]" min="0" value="<?php echo esc_attr( $discount['amount'] ); ?>">
							</td>
							<td>
								<select name="carpet_calculator_options[order_amount_discount][<?php echo $key; ?>][type]">
									<option value="none" <?php selected( $discount['type'], 'none' ); ?>>Не выбрано</option>
									<option value="percent" <?php selected( $discount['type'], 'percent' ); ?>>Процент</option>
									<option value="fixed" <?php selected( $discount['type'], 'fixed' ); ?>>Фиксированная сумма</option>
								</select>
							</td>
							<td>
								<input type="number" id="order_amount_discount_percent" name="carpet_calculator_options[order_amount_discount][<?php echo $key; ?>][value]" min="0" max="100" step="1" value="<?php echo esc_attr( $discount['value'] ); ?>">
							</td>
							<td>
								<input
									type="checkbox"
									id="order_amount_discount_status"
									name="carpet_calculator_options[order_amount_discount][<?php echo $key; ?>][status]"
									value="1"
									<?php checked( $discount['status'], 1 ); ?>
								>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<div><button type="button" id="add-order-amount">Добавить</button></div>
		<br>
		<!-- add textarea for discount description -->
		<label>Описание скидки</label>
		<textarea name="carpet_calculator_options[order_amount_discount_description]" rows="10" cols="50"><?php echo $options['order_amount_discount_description']; ?></textarea>
		<br><br><hr><br>
	<?php
}

function carpet_calculator_n_plus_one_discount_callback() {
	$options = get_option( 'carpet_calculator_options' );
	$n_plus_one_discount = isset( $options['n_plus_one_discount'] ) ? $options['n_plus_one_discount'] : array();
	?>
		<table id="nPlusOneTable">
			<thead>
				<tr>
					<th>Количество ковров</th>
					<th>Статус</th>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($n_plus_one_discount)) : ?>
					<?php foreach ($n_plus_one_discount as $key => $discount) : ?>
						<tr>
							<td>
								<input type="number" id="n_plus_one_discount_qty" name="carpet_calculator_options[n_plus_one_discount][<?php echo $key; ?>][qty]" min="0" value="<?php echo esc_attr( $discount['qty'] ); ?>">
							</td>
							<td>
								<input
									type="checkbox"
									id="n_plus_one_discount_status"
									name="carpet_calculator_options[n_plus_one_discount][<?php echo $key; ?>][status]"
									value="1"
									<?php checked( $discount['status'], 1 ); ?>
								>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<div><button type="button" id="add-n-plus-one">Добавить</button></div>
		<br>
		<!-- add textarea for discount description -->
		<label>Описание скидки</label>
		<textarea name="carpet_calculator_options[n_plus_one_discount_description]" rows="10" cols="50"><?php echo $options['n_plus_one_discount_description']; ?></textarea>
		<br><br><hr><br>
	<?php
}


function carpet_calculator_services_callback () {
	$services = get_option('carpet_calculator_services', array());
	?>
	
			<table id="additionalsTable">
				<thead>
					<tr>
						<th>Название доп. услуги</th>
						<th>Стоимость</th>
						<th>Ед. измерения</th>
					</tr>
				</thead>
				<tbody>
				<?php
					if (!empty($services)) {
						foreach ($services as $key => $service) {
							echo '<tr>';
							echo '<td><input type="text" name="services['.$key.'][name]" value="'.$service['name'].'" style="width: 300px"></td>';
							echo '<td><input type="number" name="services['.$key.'][price]" value="'.$service['price'].'"></td>';
							echo '<td><select name="services['.$key.'][unit]" style="width: 75px">';
							echo '<option value="sqm" '.selected($service['unit'], 'sqm', false).'>м2</option>';
							echo '<option value="pcs" '.selected($service['unit'], 'pcs', false).'>шт</option>';
							echo '</select></td>';
							echo '</tr>';
						}
					}
					if (isset($_POST['add_service'])) {
						$services[] = array(
							'name' => '',
							'price' => '',
							'unit' => ''
						);
					}
				?>
				</tbody>
			</table>
			<div><button type="button" id="add-service">Добавить услугу</button></div>
			<br><br><hr><br>
	<?php
}

function carpet_calculator_tariffs_callback () {
	$tariffs = get_option('carpet_calculator_tariffs', array());
	$default_tariff = get_option('carpet_calculator_default_tariff', '');
	?>
			<table id="tarrifsTable">
				<thead>
					<tr>
						<th>Название тарифа</th>
						<th>Стоимость</th>
					</tr>
				</thead>
				<tbody>
					<?php
						if (!empty($tariffs)) {
							foreach ($tariffs as $key => $tariff) {
								echo '<tr>';
								echo '<td><input type="text" name="tariffs['.$key.'][name]" value="'.$tariff['name'].'"></td>';
								echo '<td><input type="number" name="tariffs['.$key.'][price]" value="'.$tariff['price'].'"></td>';
								echo '</tr>';
							}
						}
						if (isset($_POST['add_tariff'])) {
							$tariffs[] = array(
								'name' => '',
								'price' => ''
							);
						}
					?>
				</tbody>
			</table>
			<div><button type="button" id="add-tariff">Добавить тариф</button></div>

			
			<h2>Тариф по умолчанию</h2>
			<div>
				<select name="default_tariff">
					<?php
						if (!empty($tariffs)) {
							foreach ($tariffs as $key => $tariff) {
								echo '<option value="'.$tariff['name'].'" '.selected($tariff['name'], $default_tariff, false).'>'.$tariff['name'].'</option>';
							}
						}
					?>
				</select>
			</div>

			<br><br><hr><br>
	<?php
}



function carpet_calculator_sanitize_options( $options ) {
    if ( isset( $options['quantity_discount'] ) ) {
        if ( $options['quantity_discount']['type'] === 'quantity' ) {
            $options['quantity_discount']['qty'] = absint( $options['quantity_discount']['qty'] );
            $options['quantity_discount']['percent'] = absint( $options['quantity_discount']['percent'] );
        } else {
            unset( $options['quantity_discount']['qty'] );
            unset( $options['quantity_discount']['percent'] );
        }
    }

    return $options;
}


function carpet_calculator_register_settings() {
    register_setting( 'carpet_calculator_options', 'carpet_calculator_options', 'carpet_calculator_sanitize_options' );


	add_settings_section( 'carpet_calculator_tariffs_section', 'Тарифы', 'carpet_calculator_tariffs_callback', 'carpet_calculator_settings' );
	add_settings_section( 'carpet_calculator_services_section', 'Дополнительные услуги', 'carpet_calculator_services_callback', 'carpet_calculator_settings' );
    add_settings_section( 'carpet_calculator_discounts_section', 'Скидка для определенных категорий клиентов', 'carpet_calculator_customer_category_discount_callback', 'carpet_calculator_settings' );
	add_settings_section( 'quantity_discount', 'Скидка от количества ковров', 'carpet_calculator_quantity_discount_callback', 'carpet_calculator_settings', 'carpet_calculator_discounts_section' );
	add_settings_section( 'carpet_calculator_order_amount_discount', 'Скидка от суммы заказа', 'carpet_calculator_order_amount_discount_callback', 'carpet_calculator_settings', 'carpet_calculator_discounts_section' );
	add_settings_section( 'carpet_calculator_n_plus_one_discount', 'Скидка N+1', 'carpet_calculator_n_plus_one_discount_callback', 'carpet_calculator_settings', 'carpet_calculator_discounts_section' );
	add_settings_section( 'carpet_calculator_first_order_discount', 'Скидка для новых клиентов', 'carpet_calculator_first_order_discount_callback', 'carpet_calculator_settings', 'carpet_calculator_discounts_section' );

	add_settings_field( 'carpet_calculator_order_discount', 'Скидка на заказ стирки более чем одного ковра', 'carpet_calculator_order_discount_callback', 'carpet-calculator-settings', 'carpet_calculator_discounts_section' );
	add_settings_field( 'carpet_calculator_client_discount', 'Скидка для определенных категорий клиентов', 'carpet_calculator_customer_category_discount_callback', 'carpet-calculator-settings', 'carpet_calculator_discounts_section' );
	add_settings_field( 'carpet_calculator_returning_customer_discount', 'Скидка для постоянных клиентов', 'carpet_calculator_returning_customer_discount_callback', 'carpet-calculator-settings', 'carpet_calculator_discounts_section' );
	add_settings_field( 'carpet_calculator_general_discount', 'Общая скидка', 'carpet_calculator_general_discount_callback', 'carpet-calculator-settings', 'carpet_calculator_discounts_section' );

}