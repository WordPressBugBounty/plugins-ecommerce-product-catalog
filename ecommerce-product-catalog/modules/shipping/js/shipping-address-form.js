jQuery(
	function () {
		function getSelectedValue(form, fieldName) {
			var selectedField = form.find('input[type="radio"][name="' + fieldName + '"]:checked').first();

			if (!selectedField.length) {
				selectedField = form.find('input[type="hidden"][name="' + fieldName + '"]').first();
			}

			return selectedField.val() || '';
		}

		function getScope(context) {
			if (context && context.jquery) {
				return context;
			}

			if (context && context.nodeType) {
				return jQuery(context);
			}

			return jQuery(document.body);
		}

		function getAjaxUrl() {
			if (typeof product_object !== 'undefined' && product_object.ajaxurl) {
				return product_object.ajaxurl;
			}

			if (typeof ajaxurl !== 'undefined') {
				return ajaxurl;
			}

			return '';
		}

		function getFieldValue(field, container) {
			var fieldName = field.attr('name');
			var fieldValue = '';

			if (field.is(':checkbox')) {
				fieldValue = [];
				container.find('input[type="checkbox"][name="' + fieldName + '"]:checked').each(
					function () {
						fieldValue.push(jQuery(this).val());
					}
				);
			} else if (field.is(':radio')) {
				fieldValue = container.find('input[type="radio"][name="' + fieldName + '"]:checked').val() || '';
			} else {
				fieldValue = field.val();
			}

			return fieldValue;
		}

		function saveField(field) {
			var ajaxUrl = getAjaxUrl();
			var fieldName = field.attr('name');
			var container = field.closest('.shipping-address-form-fields');
			var preName = container.data('pre_name');

			if (!ajaxUrl || !fieldName || !preName || field.prop('disabled') || (field[0] && !field[0].checkValidity())) {
				return;
			}

			jQuery.post(
				ajaxUrl,
				{
					action: 'ic_formbuilder_save_field',
					name: fieldName,
					value: getFieldValue(field, container),
					pre_name: preName
				}
			);
		}

		function updateGroup(group) {
			var fieldName = group.data('ic-shipping-field');
			var form = group.closest('form');
			var selectedValue = '';

			if (!fieldName || !form.length) {
				return;
			}

			selectedValue = getSelectedValue(form, fieldName);
			group.find('.ic-shipping-address-option').each(
				function () {
					var option = jQuery(this);
					var isActive = option.data('ic-shipping-option-value') === selectedValue;
					var fields = option.find('input, select, textarea, button');

					option.toggle(isActive);
					option.toggleClass('active', isActive);
					fields.prop('disabled', !isActive);
					fields.filter('select').trigger('chosen:updated');
				}
			);
		}

		function updateAllGroups(context) {
			var scope = getScope(context);

			scope.find('.ic-shipping-address-group').each(
				function () {
					updateGroup(jQuery(this));
				}
			);
		}

		updateAllGroups();

		jQuery(document.body).on(
			'change',
			'input[type="radio"][name="shipping"], input[type="radio"][name^="shipping_"]',
			function () {
				updateAllGroups(jQuery(this).closest('form'));
			}
		);

		jQuery(document.body).on(
			'change',
			'.shipping-address-form-fields input, .shipping-address-form-fields select, .shipping-address-form-fields textarea',
			function () {
				saveField(jQuery(this));
			}
		);

		if (jQuery.ic && typeof jQuery.ic.addAction === 'function') {
			jQuery.ic.addAction(
				'ic_checkout_table_updated',
				function () {
					updateAllGroups();
				}
			);
		}
	}
);
