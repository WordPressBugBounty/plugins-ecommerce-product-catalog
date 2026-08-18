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

		function markRequiredFields(fields) {
			fields.each(
				function () {
					var field = jQuery(this);

					if (field.is('[required]') || field.prop('required')) {
						field.data('ic-shipping-address-required', true);
					}
				}
			);
		}

		function updateRequiredFields(fields, isRequired) {
			fields.each(
				function () {
					var field = jQuery(this);

					if (field.data('ic-shipping-address-required')) {
						if (isRequired) {
							field.attr('required', 'required');
							field.prop('required', true);
						} else {
							field.removeAttr('required');
							field.prop('required', false);
						}
					}
				}
			);
		}

		function updateSameAsBilling(option) {
			var toggle = option.find('.ic-shipping-address-same-as-billing-toggle').first();
			var container = option.find('.shipping-address-form-fields-container').first();
			var formFields;
			var fields;
			var isSame;

			if (!toggle.length || !container.length) {
				return;
			}

			formFields = container.find('input, select, textarea');
			fields = formFields.add(container.find('button'));
			isSame = toggle.is(':checked');

			markRequiredFields(formFields);
			container.toggle(!isSame);
			fields.prop('disabled', isSame);
			updateRequiredFields(formFields, !isSame);
			fields.filter('select').trigger('chosen:updated');
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
			var container = field.closest('.shipping-address-form-row');
			var preName = container.find('.shipping-address-form-fields').first().data('pre_name');

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
					if (isActive) {
						updateSameAsBilling(option);
					} else {
						option.find('.shipping-address-form-fields-container').show();
					}
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
			'.ic-shipping-address-same-as-billing-toggle, .shipping-address-form-fields input, .shipping-address-form-fields select, .shipping-address-form-fields textarea',
			function () {
				var field = jQuery(this);
				if (field.hasClass('ic-shipping-address-same-as-billing-toggle')) {
					updateSameAsBilling(field.closest('.ic-shipping-address-option'));
				}
				saveField(field);
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
