<?php
/**
 * Framework pre-cached fragment refresh mechanism.
 *
 * Provides the shared `ic_pre_cached_parts` precache / nonce-refresh contract
 * used by the cart-capable consumers: cache detection, the pre-cached container
 * marker class, the footer script that requests fresh fragments for anonymous
 * page-cached visitors, and the AJAX endpoint that returns them.
 *
 * @package impleCode\IC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ic_is_cached_content' ) ) {

	/**
	 * Determines whether the current request is served from cache.
	 *
	 * @return bool
	 */
	function ic_is_cached_content() {
		if ( defined( 'WP_CACHE' ) && WP_CACHE && ! is_ic_ajax() ) {
			return true;
		}

		return false;
	}

}

if ( ! function_exists( 'ic_modify_cached_container_class' ) ) {
	add_filter( 'wpsupercache_buffer', 'ic_modify_cached_container_class' );

	/**
	 * Replaces the pre-cached class in buffered content.
	 *
	 * @param string $content Buffered content.
	 *
	 * @return string
	 */
	function ic_modify_cached_container_class( $content ) {
		$content = str_replace( 'ic-pre-cached', 'ic-cached', $content );

		return $content;
	}
}

if ( ! function_exists( 'ic_pre_cached_class' ) ) {
	/**
	 * Returns the pre-cached container class.
	 *
	 * @return string
	 */
	function ic_pre_cached_class() {
		$class = 'ic-pre-cached';
		if ( ! empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
			if ( 'LiteSpeed' === $_SERVER['SERVER_SOFTWARE'] ) {
				$class = 'ic-cached';
			}
		}

		return apply_filters( 'ic_pre_cached_class', $class );
	}
}

if ( ! function_exists( 'implecode_pre_cached_script' ) ) {
	add_action( 'wp_print_footer_scripts', 'implecode_pre_cached_script' );

	/**
	 * Outputs the pre-cached standalone cart JavaScript.
	 *
	 * @return void
	 */
	function implecode_pre_cached_script() {
		?>
		<!--noptimize-->
		<script data-cfasync="false">
			ic_prepare_pre_cached();

			function ic_prepare_pre_cached() {
				if (document.getElementsByClassName("logged-in").length) {
					return;
				}
				var cached_buttons = document.getElementsByClassName("ic-cached");
				var i;
				var args = {};
				var containers = [];
				for (i = 0; i < cached_buttons.length; i += 1) {
					var this_content = cached_buttons[i];
					var parents = ic_getParents(this_content);
					if (parents.length !== 0) {
						continue
					}
					var action = this_content.getAttribute('data-action');
					var product_id = this_content.getAttribute('data-product_id');
					if (product_id) {
						if (args[action] === undefined) {
							args[action] = {}
						}
						args[action][product_id] = this_content.getAttribute('data-args');
					} else {
						args[action] = this_content.getAttribute('data-args');
					}
					var pass = {};
					pass.action = action;
					pass.content = this_content;
					if (product_id) {
						pass.product_id = product_id;
					}
					containers.push(pass)
				}
				if (containers.length > 0) {
					var request = new XMLHttpRequest();
					var url = "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>";
					window.ic_pre_cached_pending = true;
					request.open("POST", url, true);
					request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;');
					request.onreadystatechange = ic_replace_pre_cached(containers, request);
					request.send("action=ic_pre_cached_parts&args=" + encodeURIComponent(JSON.stringify(args)))
				}
			}


			function ic_replace_pre_cached(containers, request) {
				return function () {
					if (request.readyState !== 4 || request.ic_pre_cached_complete) {
						return;
					}
					request.ic_pre_cached_complete = true;
					try {
						if (request.status !== 200) {
							return;
						}
						var html = {};
						try {
							html = JSON.parse(request.response);
						} catch (e) {
							return;
						}
						if (!html || typeof html !== 'object') {
							html = {};
						}
						if (html && typeof html.ic_product_object_nonces === 'object' && !Array.isArray(html.ic_product_object_nonces) && typeof window.product_object === 'object' && window.product_object !== null) {
							for (var nonce_key in html.ic_product_object_nonces) {
								if (Object.prototype.hasOwnProperty.call(html.ic_product_object_nonces, nonce_key)) {
									window.product_object[nonce_key] = html.ic_product_object_nonces[nonce_key];
								}
							}
						}
						for (let key in containers) {
							var Obj = containers[key].content;
							var action = containers[key].action;
							if (!Obj) {
								continue;
							}
							// ensure the element is still in DOM
							var parent = Obj.parentNode;
							if (!parent) {
								continue;
							}
							if (html[action] !== undefined) {
								var replacement = html[action];
								if (typeof replacement === 'object' && !Array.isArray(replacement) && replacement !== null) {
									if (containers[key].product_id !== undefined && Object.prototype.hasOwnProperty.call(replacement, containers[key].product_id)) {
										replacement = replacement[containers[key].product_id];
									}
								}
								if (Obj.outerHTML) {
									Obj.outerHTML = replacement
								} else {
									var tmpObj = document.createElement("div");
									tmpObj.innerHTML = '<!--THIS DATA SHOULD BE REPLACED-->';
									var ObjParent = parent;
									ObjParent.replaceChild(tmpObj, Obj);
									ObjParent.innerHTML = ObjParent.innerHTML.replace('<div><!--THIS DATA SHOULD BE REPLACED--></div>', replacement)
								}
							}
						}
					} finally {
						window.ic_pre_cached_pending = false;
						const ic_replaced_pre_cached = new CustomEvent("ic_replaced_pre_cached", {
							bubbles: true,
							detail: { containers: containers },
						});
						document.dispatchEvent(ic_replaced_pre_cached);
					}
				}
			}

			function ic_getParents(e) {
				var result = [];
				for (var p = e && e.parentElement; p; p = p.parentElement) {
					if (p.classList.contains("ic-cached")) {
						result.push(p)
					}
				}
				return result
			}
		</script><!--/noptimize-->
		<?php
	}

}

if ( ! function_exists( 'ic_ajax_pre_cached_parts' ) ) {
	add_action( 'wp_ajax_nopriv_ic_pre_cached_parts', 'ic_ajax_pre_cached_parts' );
	add_action( 'wp_ajax_ic_pre_cached_parts', 'ic_ajax_pre_cached_parts' );

	/**
	 * Returns pre-cached HTML parts over AJAX.
	 *
	 * @return void
	 */
	function ic_ajax_pre_cached_parts() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Public cached-fragment endpoint accepts raw JSON for anonymous visitors.
		$args = isset( $_POST['args'] ) ? wp_unslash( $_POST['args'] ) : array();
		if ( ! empty( $args ) && ! is_array( $args ) ) {
			$args = json_decode( $args, true );
			if ( ! empty( $args ) && ! is_array( $args ) ) {
				$args = json_decode( $args, true );
			}
		}
		if ( is_array( $args ) ) {
			foreach ( $args as $key => $value ) {
				if ( is_array( $value ) ) {
					$first_array_value = reset( $value );
					if ( ! is_array( $first_array_value ) && ic_string_contains( $first_array_value, '[' ) ) {
						foreach ( $value as $sub_key => $sub_value ) {
							$args[ $key ][ $sub_key ] = json_decode( $sub_value, true );
						}
					}
				}
				if ( empty( $value ) || is_array( $value ) ) {
					continue;
				}
				$args[ $key ] = json_decode( $value, true );
			}
		}
		$parts                             = apply_filters( 'ic_pre_cached_parts', array(), $args );
		$parts['ic_product_object_nonces'] = apply_filters( 'ic_catalog_product_object_nonces', array() );
		echo wp_json_encode( $parts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		wp_die();
	}
}
