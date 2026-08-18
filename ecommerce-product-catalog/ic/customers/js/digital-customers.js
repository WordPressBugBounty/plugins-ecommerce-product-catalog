/*!
 * Manages customer login and registration
 *
 */

jQuery(document).ready(function () {
    /* globals ic_disable_body, ic_enable_body, ic_disable_container, product_object, ic_digital_customers */
    jQuery('.login_button.not-logged a, .popup_login_form .closer').click(function (event) {
        event.preventDefault();
        digital_customer_toggle_popup_login();
    });
    jQuery('.inside_login.ui-tabs').tabs();
    var login_form_container = jQuery('.ic-login-form-container');
    apply_login_placeholders();

    function show_login_view_after_registration_response(this_login_form_container, response) {
        this_login_form_container.find('.login_form #login_form').prepend(response);
        this_login_form_container.find('.ic-user-register').hide();
        this_login_form_container.find('.login_form').show();
        this_login_form_container.find('.ic-login-form-options').hide();
        this_login_form_container.find('.ic-top-registration-link').show();
    }

    login_form_container.on('click', '.sign-in', function () {
        var this_login_form_container = jQuery(this).closest('.ic-login-form-container');
        this_login_form_container.find('.ic-guest-cancel').trigger('click');
        this_login_form_container.find('.login_form').show();
        this_login_form_container.find('.ic-user-register').hide();
        this_login_form_container.find('.ic-top-registration-link').show();

        this_login_form_container.find('.ic-cancel-login').remove();
        var this_login_submit_container = this_login_form_container.find('.login-submit');
        this_login_submit_container.append(product_object.login_cancel_button);
        // Mobile menu guest login
        var this_guest_button = this_login_form_container.find('.ic-login-form-options .guest-sign-in');
        if (this_guest_button.length) {
            if (this_guest_button.is(':hidden') && this_guest_button.closest('.ic-customer-menu-login-container').length && this_guest_button.closest('.show-popup').length === 0 && (this_guest_button.closest('.show-popup-for-logged-1').length === 0 || this_guest_button.closest('.show-popup-for-logged-1').closest('.logged-in').length > 0 || this_guest_button.closest('.show-popup-for-logged-1').closest('.ic-logged-in').length > 0)) {
                this_login_submit_container.find('.guest-sign-in').remove();
                this_login_submit_container.append(this_guest_button.clone().addClass('ic-secondary-button'));
            }
        }
        // END Mobile menu guest login
        if (jQuery(this).closest('.popup_login').length) {
            jQuery('.ic-overlay').first().show();
        } else {
            this_login_form_container.find('.ic-login-form-options').hide();
        }
        this_login_form_container.on('click', '.ic-cancel-login', function () {
            this_login_form_container.find('.login_form').hide();
            this_login_form_container.find('.ic-top-registration-link').hide();
            this_login_form_container.find('.ic-login-form-options').show();
            jQuery('.ic-overlay').hide();
            remove_alerts();
        });
    });
    if (login_form_container.hasClass('ic-login-form-container-show-login-form')) {
        if (jQuery('.login_form:visible').length === 0) {
            login_form_container.find('.sign-in').trigger('click');
        }
    }
    login_form_container.on('click', '.guest-sign-in', function () {
        var this_login_form_container = jQuery(this).closest('.ic-login-form-container');
        this_login_form_container.find('.login_form').hide();
        this_login_form_container.find('.ic-guest-register').show();
        this_login_form_container.find('.ic-top-registration-link').show();
        if (jQuery(this).closest('.popup_login').length) {
            jQuery('.ic-overlay').first().show();
        } else {
            this_login_form_container.find('.ic-login-form-options').hide();
        }
    });
    login_form_container.on('click', '.ic-guest-cancel', function () {
        login_form_container.find('.ic-guest-register').hide();
        login_form_container.find('.ic-top-registration-link').hide();
        if (jQuery(this).closest('.ic-customer-menu-login-container').length === 0) {
            login_form_container.find('.ic-login-form-options').show();
        }
        login_form_container.find('.warning:visible').remove();
        jQuery('.ic-overlay').hide();
    });
    login_form_container.on('click', '.ic-create-account', function (e) {
        e.preventDefault();
        var this_login_form_container = jQuery(this).closest('.ic-login-form-container');
        this_login_form_container.find('.ic-user-register').show();
        this_login_form_container.find('.ic-guest-register').hide();
        this_login_form_container.find('.login_form').hide();
        if (jQuery(this).closest('.popup_login').length) {
            jQuery('.ic-overlay').first().show();
        } else {
            this_login_form_container.find('.ic-login-form-options').hide();
        }
    });
    login_form_container.on('click', '.ic-user-register-cancel', function () {
        login_form_container.find('.ic-user-register').hide();
        login_form_container.find('.ic-login-form-options').show();
        history.replaceState('', document.title, window.location.pathname + window.location.search);
        jQuery('.ic-overlay').hide();
    });
    login_form_container.on('click', '.ic-guest-submit', function () {
        jQuery(this).closest('.ic-guest-register').find(':submit').click();
    });
    login_form_container.on('click', '.ic-user-register-submit', function () {
        jQuery(this).closest('.ic-user-register').find(':submit').click();
    });
    login_form_container.on('click', '.ic-edit-guest', function () {
        var this_login_form_container = jQuery(this).closest('.ic-login-form-container');
        if (jQuery(this).closest('.ic-customer-menu-login-container').length === 0) {
            jQuery('.ic-saved-guest-container').hide();
            if (this_login_form_container.closest('.ic-customer-menu-login-container').length) {
                //login_form_container.find('.ic-login-form-options').show();
            }
        }
        this_login_form_container.find('.ic-guest-register').show();
        if (jQuery(this).closest('.popup_login').length) {
            jQuery('.ic-overlay').first().show();
        }
    });
    login_form_container.on('submit', '#loginform', function () {
        remove_alerts();
    });
    login_form_container.on('submit', '.ic-guest-register', function (e) {
        e.preventDefault();
        remove_alerts();
        var guest_register_form = jQuery(this);
        var this_login_form_container = guest_register_form.closest('.ic-login-form-container');
        //guest_register_form.find('.ic-form-inside').css('opacity', '0.5');
        ic_disable_container(guest_register_form.find('.ic-form-inside'));
        var email = guest_register_form.find('[name=ic-guest-register-email]').val();
        var phone = guest_register_form.find('[name=ic-guest-register-phone]').val();
        var name = guest_register_form.find('[name=ic-guest-register-name]').val();
        var nonce = guest_register_form.find('[name=ic-guest-register-nonce]').val();
        var settings = guest_register_form.find('[name=ic-guest-register-settings]').val();
        var data = {
            'action': 'ic_guest_register',
            'email': email,
            'phone': phone,
            'name': name,
            'nonce': nonce,
            'settings': settings
        };
        jQuery.post(product_object.ajaxurl, data, function (response) {
            login_form_container.find('.ic-saved-guest-container').remove();
            guest_register_form.replaceWith(response);
            var saved_guest_container = jQuery('.ic-saved-guest-container');
            saved_guest_container.show();
            if (saved_guest_container.closest('.ic-cart-fast-checkout-container').length === 0) {
                if (response.includes('ic-saved-guest')) {
                    ic_disable_body();
                    location.reload(true);
                } else {
                    ic_enable_body();
                    //jQuery('body').css('opacity', '');
                    this_login_form_container.find('#login_form > .warning').remove();
                    this_login_form_container.find('.warning:visible').each(function () {
                        this_login_form_container.find('#login_form').prepend(this);
                    });
                    this_login_form_container.find('.sign-in').trigger('click');
                    var input_email = jQuery('.ic-guest-register-email input').val();
                    if (input_email) {
                        this_login_form_container.find('.login_form input#user_login').val(input_email);
                    }
                    //login_form_container.find('.login_form').show();
                }
            } else {
                jQuery.ic.doAction('ic_guest_registered');
            }
        });
        return true;
    });
    login_form_container.on('submit', '.ic-user-register', function (e) {
        e.preventDefault();
        var this_login_form_container = jQuery(this).closest('.ic-login-form-container');
        remove_alerts();
        jQuery('.ic-user-register-submit').prop('disabled', true);
        jQuery('.ic-user-register .ic-form-inside').css('opacity', '0.5');
        var user_register_form = jQuery(this);
        var email = user_register_form.find('[name=ic-user-register-email]').val();
        var phone = user_register_form.find('[name=ic-user-register-phone]').val();
        var name = user_register_form.find('[name=ic-user-register-name]').val();
        var new_account_confirm = user_register_form.find('[name=ic_new_account_confirm]').val();
        jQuery('.ic_ajax_register_response').remove();
		var data = {
			'action': 'ic_user_register',
			'ic_nonce': product_object.registration_nonce,
            'email': email,
            'phone': phone,
            'name': name,
            'ic_new_account_confirm': new_account_confirm
        };
        jQuery.post(product_object.ajaxurl, data, function (response) {
            //login_form_container.prepend(response);
            if (response) {
                if (!response.includes('warning') && !response.includes('ic-modal-container-container')) {
                    show_login_view_after_registration_response(this_login_form_container, response);
                } else {
                    user_register_form.prepend(response);
                }
            }
            jQuery('.ic-user-register-submit').prop('disabled', false);
            jQuery('.ic-user-register .ic-form-inside').css('opacity', '');
            login_form_container = jQuery('.ic-login-form-container');
        });
        return true;

    });
    /*
    var menu_login_button = jQuery('.ic-trigger-menu-login');

    menu_login_button.hover(
        function () {
            jQuery(this).next('.ic-customer-menu-login-container').show();
        },
        function () {
            jQuery(this).next('.ic-customer-menu-login-container').hide();
        }
    );
    jQuery('.ic-customer-menu-login-container').hover(
        function () {
            jQuery(this).show();
        },
        function () {
            jQuery(this).hide();
        }
    );

*/
    jQuery('.ic-customer-menu-login-container').on('click', '.button, a', function () {
        jQuery('.ic-customer-menu-login-container').closest('.sub-menu').addClass('ic-permanent-show');
        jQuery('.ic-customer-menu-login-container').on('click', '.ic-guest-cancel, .ic-cancel-login, .ic-user-register-cancel, #ic_overlay', function () {
            jQuery('.ic-customer-menu-login-container').closest('.sub-menu').removeClass('ic-permanent-show');
            jQuery('.ic-customer-menu-login-container').off('click', '.ic-guest-cancel, .ic-cancel-login, .ic-user-register-cancel, #ic_overlay');
        });

        jQuery('body').on('click', '#ic_overlay', function () {
            jQuery('.ic-customer-menu-login-container').closest('.sub-menu').removeClass('ic-permanent-show');
            jQuery('body').off('click', '#ic_overlay');
        });
    });


    var location_hash = window.location.hash.substr(1);
    manage_login_hash(location_hash);
    jQuery('body').on('click', '.ic-trigger-menu-login', function (e) {
        if (manage_login_hash(jQuery(this).attr('href').substr(1))) {
            e.preventDefault();
        }
    });
    var save_form_checkbox = jQuery('[name="quote_cart_ic_save_form[]"]');
    if (save_form_checkbox.length) {
        save_form_checkbox.closest('.row').addClass('ic-always-hidden');
        jQuery('.ic-form form .row').on('change', function () {
            if (!jQuery(this).hasClass('payment-options') && jQuery(this).find('[name="quote_cart_ic_save_form[]"]').length === 0) {
                save_form_checkbox.closest('.row').removeClass('ic-always-hidden');
                if (!save_form_checkbox.attr('data-already_checked')) {
                    save_form_checkbox.prop('checked', true);
                    save_form_checkbox.attr('data-already_checked', '1');
                }
            }
        });
    }

    function manage_login_hash(location_hash) {
        var this_login_form_container = jQuery('.entry-content .ic-login-form-container');
        if (this_login_form_container.length === 0 || this_login_form_container.is(':hidden')) {
            this_login_form_container = login_form_container;
        }
        //this_login_form_container = login_form_container;
        var return_var = false;
        if (location_hash === 'create_account') {
            this_login_form_container.find('.ic-create-account').first().trigger('click');
            if (this_login_form_container.closest('.ic-cart-fast-checkout-container').length === 0 && this_login_form_container.closest('.ic-customer-menu-login-container').length === 0) {
                setTimeout(
                    function () {
                        jQuery([document.documentElement, document.body]).animate({
                            scrollTop: this_login_form_container.offset().top
                        }, 2000);
                    }, 100
                );
            }
            return_var = true;
        } else if (location_hash === 'customer_login') {
            this_login_form_container.find('.sign-in').first().trigger('click');
            if (this_login_form_container.is(':hidden') && jQuery('.ic-cart-fast-checkout').length === 0) {
                this_login_form_container.find('.ic-cancel-login').trigger('click');
                login_form_container.find('.sign-in').first().trigger('click');
            }
            return_var = true;
        }
        return return_var;
    }

    function remove_alerts() {
        jQuery('.login-success-message').hide();
        jQuery('.login-failed-message').hide();
        jQuery('.login-locked-message').hide();
        login_form_container.find('.warning:visible').remove();
        login_form_container.find('.success:visible').remove();
    }

    function digital_customer_toggle_popup_login() {
        var login_form = jQuery('.ic-guest-register:visible');
        if (login_form.length === 0 || login_form.is(':hidden')) {
            login_form = jQuery('.ic-user-register:visible');
        }
        if (login_form.length === 0 || login_form.is(':hidden')) {
            login_form = jQuery('.ic-saved-guest-container:visible');
        }
        if (login_form.length === 0 || login_form.is(':hidden')) {
            login_form = jQuery('.popup_login_form:visible');
        }
        if (login_form.length === 0 || login_form.is(':hidden')) {
            login_form = jQuery('.popup_login_form');
        }
        login_form.toggle('slide', {'direction': 'up'}, function () {
            if (login_form.is(':hidden')) {
                jQuery('.ic-overlay').first().hide();
            } else {
                jQuery('.ic-overlay').first().show();
            }
        });
    }

    function apply_login_placeholders() {
        if (typeof ic_digital_customers === 'undefined') {
            return;
        }
        if (typeof ic_digital_customers.username_placeholder !== 'undefined') {
            jQuery('.popup_login #user_login').attr('placeholder', ic_digital_customers.username_placeholder);
        }
        if (typeof ic_digital_customers.pass_placeholder !== 'undefined') {
            jQuery('.popup_login #user_pass').attr('placeholder', ic_digital_customers.pass_placeholder);
        }
    }
});
