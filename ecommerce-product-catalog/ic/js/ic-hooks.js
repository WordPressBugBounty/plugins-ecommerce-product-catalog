/**
 * JavaScript hooks registry.
 *
 * @package impleCode\IC
 * (c) 2026 Norbert Dreszer - https://implecode.com
 */

if (typeof implecode === 'undefined') {
	var implecode = [];
}
if (implecode.ic === undefined) {
	implecode.ic = {
		/**
		 * Implement a WordPress-link Hook System for Javascript
		 */
		hooks: {action: {}, filter: {}},
		addAction: function (action, callable, tag) {
			implecode.ic.addHook( 'action', action, callable, tag );
		},
		addFilter: function (action, callable, tag) {
			implecode.ic.addHook( 'filter', action, callable, tag );
		},
		doAction: function (action, args) {

			implecode.ic.doHook( 'action', action, null, args );
		},
		applyFilters: function (action, value, args) {
			return implecode.ic.doHook( 'filter', action, value, args );
		},
		removeAction: function (action, tag) {
			implecode.ic.removeHook( 'action', action, tag );
		},
		removeFilter: function (action, tag) {
			implecode.ic.removeHook( 'filter', action, tag );
		},
		addHook: function (hookType, action, callable, tag) {
			if (undefined == implecode.ic.hooks[hookType][action]) {
				implecode.ic.hooks[hookType][action] = [];
			}
			var hooks = implecode.ic.hooks[hookType][action];
			if (undefined == tag) {
				tag = action + '_' + hooks.length;
			}
			implecode.ic.hooks[hookType][action].push( {tag: tag, callable: callable} );
		},
		doHook: function (hookType, action, value, args) {
			if (undefined != implecode.ic.hooks[hookType][action]) {
				var hooks       = implecode.ic.hooks[hookType][action];
				var hooksLength = hooks.length;
				for (var i = 0; i < hooksLength; i++) {
					if ('action' == hookType) {
						hooks[i].callable( args );
					} else {
						value = hooks[i].callable( value, args );
					}
				}
			}
			if ('filter' == hookType) {
				return value;
			}
		},
		removeHook: function (hookType, action, tag) {
			if (undefined != implecode.ic.hooks[hookType][action]) {
				var hooks = implecode.ic.hooks[hookType][action];
				for (var i = hooks.length - 1; i >= 0; i--) {
					if (undefined == tag || tag == hooks[i].tag) {
						hooks.splice( i, 1 );
					}
				}
			}
		}
	};
}
