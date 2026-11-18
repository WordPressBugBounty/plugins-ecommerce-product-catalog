/* global jQuery, icEmailDesign, wp */
(function ($) {
    'use strict';
    $(function () {
        $(document).on('click', '.ic-email-design-notice .notice-dismiss', function () {
            $.post(icEmailDesign.ajaxUrl, {action: 'ic_email_design_dismiss', _wpnonce: icEmailDesign.nonce});
        });
        var form = $('#ic-email-design-form');
        if (!form.length) { return; }
        function updateHeaderFields() {
            var header = form.find('[name="design[header]"]:checked').val();
            $('#ic-email-header-logo-fields').prop('hidden', header !== 'logo');
            $('#ic-email-header-text-fields').prop('hidden', header !== 'text');
        }
        updateHeaderFields();
        form.on('change', '[name="design[header]"]', updateHeaderFields);
        var revision = 0, cache = {}, serverHtml = previewHtml();
        function previewHtml() { return $('#ic-email-preview').attr('srcdoc') || ''; }
        var preview = $('#ic-email-preview'), viewport = preview.parent();
        preview.addClass('ic-email-preview-frame');
        var buffer = $('<iframe>', {title: preview.attr('title'), sandbox: 'allow-same-origin','class': 'ic-email-preview-frame ic-email-preview-buffer', 'aria-hidden': 'true', tabindex: '-1'}).appendTo(viewport);
        buffer[0].inert = true;
        var activeHtml = serverHtml, stagedHtml = null, queuedHtml = null, queuedDoc = null, refreshTimer = null, fitFrame = null;
        // The shown preview document, once its srcdoc has loaded (frames run no scripts).
        function loadedPreview() {
            try {
                var live = preview[0].contentDocument;
                return live && live.URL === 'about:srcdoc' && live.readyState === 'complete' && live.body ? live : null;
            } catch (e) { return null; }
        }
        // In-place sync of an editor draft into the shown preview: only text nodes, the run spans and the
        // paragraph/line-break structure are created; only the highlight style and run index attributes change.
        // Nothing is parsed from the typed text. Any other difference falls back to a srcdoc rebuild.
        var syncTags = /^(P|BR|SPAN)$/, syncAttributes = /^(style|data-ic-run)$/;
        function syncAttrs(live, target, apply) {
            var names = {}, ok = true;
            Array.prototype.forEach.call(live.attributes, function (a) { names[a.name] = true; });
            Array.prototype.forEach.call(target.attributes, function (a) { names[a.name] = true; });
            Object.keys(names).forEach(function (name) {
                var value = target.getAttribute(name);
                if (live.getAttribute(name) === value) { return; }
                if (!syncAttributes.test(name)) { ok = false; return; }
                if (apply) { if (value === null) { live.removeAttribute(name); } else { live.setAttribute(name, value); } }
            });
            return ok;
        }
        function createLike(doc, target) {
            if (target.nodeType === 3) { return doc.createTextNode(target.nodeValue); }
            if (target.nodeType !== 1 || !syncTags.test(target.nodeName)) { return null; }
            var node = doc.createElement(target.localName);
            if (!syncAttrs(node, target, true)) { return null; }
            for (var child = target.firstChild; child; child = child.nextSibling) {
                var copy = createLike(doc, child);
                if (!copy) { return null; }
                node.appendChild(copy);
            }
            return node;
        }
        function syncNode(live, target, apply) {
            if (live.isEqualNode(target)) { return true; }
            if (live.nodeType !== 1) {
                if (apply) { live.nodeValue = target.nodeValue; }
                return true;
            }
            return syncAttrs(live, target, apply) && syncChildren(live, target, apply);
        }
        function syncChildren(live, target, apply) {
            var a = Array.prototype.slice.call(live.childNodes), b = Array.prototype.slice.call(target.childNodes), start = 0, end = 0;
            while (start < a.length && start < b.length && a[start].isEqualNode(b[start])) { start++; }
            while (end < a.length - start && end < b.length - start && a[a.length - 1 - end].isEqualNode(b[b.length - 1 - end])) { end++; }
            var before = end ? a[a.length - end] : null, count = Math.max(a.length - start - end, b.length - start - end);
            for (var i = 0; i < count; i++) {
                var x = start + i < a.length - end ? a[start + i] : null, y = start + i < b.length - end ? b[start + i] : null;
                if (x && y && x.nodeType === y.nodeType && x.nodeName === y.nodeName) {
                    if (!syncNode(x, y, apply)) { return false; }
                    continue;
                }
                if (y) {
                    var copy = createLike(live.ownerDocument, y);
                    if (!copy) { return false; }
                    if (apply) { live.insertBefore(copy, x || before); }
                }
                if (x && apply) { live.removeChild(x); }
            }
            return true;
        }
        function applyInPlace(html, doc) {
            var live = loadedPreview();
            if (!live) { return false; }
            var heads = [live.head, doc.head].map(function (head) { return Array.prototype.filter.call(head.childNodes, function (node) { return node.nodeName !== 'TITLE'; }); });
            if (heads[0].length !== heads[1].length || heads[0].some(function (node, i) { return !node.isEqualNode(heads[1][i]); })) { return false; }
            if (!syncAttrs(live.body, doc.body, false) || !syncChildren(live.body, doc.body, false)) { return false; }
            syncAttrs(live.body, doc.body, true);
            syncChildren(live.body, doc.body, true);
            if (live.title !== doc.title) { live.title = doc.title; }
            activeHtml = html;
            if (fitFrame === null) { fitFrame = window.requestAnimationFrame(function () { fitFrame = null; fitPreview(); }); }
            return true;
        }
        // doc: an editor draft document that may be synced in place instead of reloading the frame.
        function stagePreview(html, doc) {
            if (doc && stagedHtml === null && applyInPlace(html, doc)) { queuedHtml = null; queuedDoc = null; return; }
            queuedHtml = html; queuedDoc = doc || null;
            if (stagedHtml !== null) { return; }
            if (queuedHtml === activeHtml) { queuedHtml = null; queuedDoc = null; return; }
            stagedHtml = queuedHtml; queuedHtml = null; queuedDoc = null;
            buffer.one('load', function () {
                window.requestAnimationFrame(function () {
                    var old = preview;
                    preview = buffer; buffer = old;
                    activeHtml = stagedHtml;
                    fitPreview();
                    buffer.removeAttr('id').addClass('ic-email-preview-buffer').attr({'aria-hidden': 'true', tabindex: '-1'});
                    buffer[0].inert = true;
                    preview.attr('id', 'ic-email-preview').removeClass('ic-email-preview-buffer').removeAttr('aria-hidden tabindex');
                    preview[0].inert = false;
                    stagedHtml = null;
                    // A newer draft is synced in the same frame as the swap, so older text is never painted.
                    if (queuedHtml !== null) { var next = queuedDoc; queuedDoc = null; stagePreview(queuedHtml, next); }
                });
            }).attr('srcdoc', stagedHtml);
        }
        // Measure an inert, sanitized layout copy: the preview itself stays sandboxed (no scripts).
        var measureHost = document.createElement('div');
        measureHost.setAttribute('aria-hidden', 'true');
        measureHost.inert = true;
        measureHost.style.cssText = 'position:absolute;left:-100000px;top:0;visibility:hidden;pointer-events:none;';
        document.body.appendChild(measureHost);
        var measureRoot = measureHost.attachShadow({mode: 'closed'});
        var measureBody = document.createElement('body');
        measureBody.setAttribute('data-ic-measure-body', '');
        measureRoot.appendChild(measureBody);
        var measureStyle = document.createElement('style');
        measureRoot.appendChild(measureStyle);
        var measureTags = /^(table|tbody|thead|tfoot|tr|td|th|caption|col|colgroup|div|span|p|a|h[1-6]|ul|ol|li|dl|dt|dd|br|wbr|b|strong|i|em|u|s|strike|del|ins|small|big|sub|sup|blockquote|q|cite|pre|code|kbd|samp|var|tt|abbr|acronym|dfn|bdi|bdo|ruby|rt|rp|mark|time|data|hr|img|picture|address|article|aside|section|nav|main|header|footer|hgroup|figure|figcaption|center|font|details|summary|video|audio|canvas|progress|meter)$/;
        var measureExcludedTags = /^(script|style|iframe|object|embed|link|meta|base|template|noscript|source|track)$/;
        var measureStyles = /^(width|min-width|max-width|height|min-height|max-height|margin(?:-(?:top|right|bottom|left))?|padding(?:-(?:top|right|bottom|left))?|border(?:-(?:top|right|bottom|left))?(?:-(?:width|style|spacing|collapse))?|box-sizing|display|font(?:-[a-z-]+)?|line-height|letter-spacing|word-spacing|text-align|text-indent|text-transform|text-wrap(?:-[a-z-]+)?|white-space(?:-[a-z-]+)?|word-break|word-wrap|overflow-wrap|tab-size|direction|unicode-bidi|vertical-align|list-style(?:-type|-position)?|table-layout|float|clear|gap|row-gap|column-gap|flex(?:-[a-z-]+)?|grid(?:-[a-z-]+)?|align-(?:items|content|self)|justify-(?:items|content|self)|order|column-count|column-width)$/;
        function measureDeclarations(source, target) {
            Array.prototype.forEach.call(source, function (property) {
                var value = source.getPropertyValue(property);
                if (measureStyles.test(property) && !/url\s*\(|expression\s*\(|var\s*\(|@/i.test(value)) { target.setProperty(property, value); }
            });
        }
        function measureRules(doc) {
            var rules = [];
            if (!window.CSSStyleSheet || !CSSStyleSheet.prototype.replaceSync) { return ''; }
            doc.querySelectorAll('style').forEach(function (source) {
                var sheet = new CSSStyleSheet();
                try { sheet.replaceSync(source.textContent); } catch (e) { return; }
                Array.prototype.forEach.call(sheet.cssRules, function (rule) {
                    if (rule.type !== 1) { return; }
                    var selectors = rule.selectorText.split(',').map(function (selector) { return selector.trim(); }).filter(function (selector) {
                        return /^[a-z0-9\s>+~-]+$/i.test(selector) && selector.split(/[\s>+~]+/).every(function (tag) { return /^(html|body)$/.test(tag) || measureTags.test(tag); });
                    });
                    if (!selectors.length) { return; }
                    var safe = document.createElement('span');
                    measureDeclarations(rule.style, safe.style);
                    if (safe.style.cssText) { rules.push(selectors.join(',').replace(/\b(?:html|body)\b/g, '[data-ic-measure-body]') + '{' + safe.style.cssText + '}'); }
                });
            });
            return rules.join('\n');
        }
        function measureNode(source) {
            if (source.nodeType === 3) { return document.createTextNode(source.textContent); }
            if (source.nodeType !== 1 || measureExcludedTags.test(source.localName)) { return document.createTextNode(''); }
            // Unknown passive wrappers keep their descendants instead of losing custom text.
            var tag = measureTags.test(source.localName) ? source.localName : 'span';
            var node = document.createElement(tag === 'img' ? 'span' : tag);
            measureDeclarations(source.style, node.style);
            ['width', 'height', 'colspan', 'rowspan', 'cellpadding', 'cellspacing', 'border', 'align', 'valign', 'open', 'hidden', 'dir', 'lang', 'start', 'reversed', 'type', 'span', 'size', 'face', 'controls', 'min', 'max', 'value', 'low', 'high', 'optimum'].forEach(function (attribute) {
                if (source.hasAttribute(attribute)) { node.setAttribute(attribute, source.getAttribute(attribute)); }
            });
            if (source.localName === 'img') {
                var logo = $('#ic-email-logo-preview img')[0];
                var width = parseFloat(source.getAttribute('width') || source.style.width) || (logo && logo.naturalWidth) || 200;
                var height = parseFloat(source.getAttribute('height') || source.style.height) || (logo && logo.naturalHeight) || 60;
                var ratio = Math.min(1, (parseFloat(source.style.maxWidth) || width) / width, (parseFloat(source.style.maxHeight) || height) / height);
                node.style.display = source.style.display || 'inline-block';
                node.style.width = (width * ratio) + 'px'; node.style.height = (height * ratio) + 'px';
            } else {
                Array.prototype.forEach.call(source.childNodes, function (child) { node.appendChild(measureNode(child)); });
            }
            return node;
        }
        function fitPreview() {
            var width = viewport.width() - 2;
            if (width <= 0) { return; }
            var doc = new DOMParser().parseFromString(activeHtml, 'text/html');
            measureHost.style.width = width + 'px';
            measureBody.style.cssText = 'display:flow-root;margin:8px;padding:0;font:16px \"Times New Roman\";';
            measureDeclarations(doc.body.style, measureBody.style);
            measureStyle.textContent = measureRules(doc);
            measureBody.replaceChildren();
            Array.prototype.forEach.call(doc.body.childNodes, function (child) { measureBody.appendChild(measureNode(child)); });
            var height = Math.max(1, Math.ceil(measureHost.getBoundingClientRect().height + 2));
            viewport.find('.ic-email-preview-frame').css({width: '100%', height: height + 'px', transform: 'none'});
            viewport.css('height', height + 'px');
        }
        preview.on('load', fitPreview);
        $(window).on('resize', fitPreview);
        if (window.ResizeObserver) {
            new window.ResizeObserver(fitPreview).observe(viewport[0]);
        }
        fitPreview();
        function accentForeground(color) {
            var channels = color.slice(1).match(/../g).map(function (hex) {
                var value = parseInt(hex, 16) / 255;
                return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
            });
            return 1.05 / (0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2] + 0.05) >= 4.5 ? '#ffffff' : '#000000';
        }
        function accentTint(color, amount) {
            if (typeof amount === 'undefined') { amount = 0.08; }
            return '#' + color.slice(1).match(/../g).map(function (hex) { return Math.round(parseInt(hex, 16) * amount + 255 * (1 - amount)).toString(16).padStart(2, '0'); }).join('');
        }
        function contrast() {
            var hex = $('#ic-email-accent').val().replace('#', '');
            if (hex.length === 3) { hex = hex.split('').map(function (x) { return x + x; }).join(''); }
            var valid = /^[0-9a-f]{6}$/i.test(hex);
            var rgb = valid ? hex.match(/../g).map(function (x) {
                var v = parseInt(x, 16) / 255;
                return v <= 0.04045 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            }) : [0, 0, 0];
            var low = valid && 1.05 / (0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2] + 0.05) < 4.5;
            $('#ic-email-contrast-warning').text(icEmailDesign.contrastWarning).prop('hidden', !low);
        }
        function currentKey() { return $('#ic-email-preview-select').val() + ':' + form.find('[name="design[layout]"]:checked').val() + ':' + $('#ic-email-style').val(); }
        function safeUrl(value) {
            try { var url = new URL(value); return /^(https?:)$/.test(url.protocol) ? url.href : ''; } catch (e) { return ''; }
        }
        function footerText(doc, node, value, color, underline) {
            node.textContent = '';
            value.split(/(\[site_name\]|\[site_url\]|\n)/).forEach(function (part) {
                if (part === '[site_name]') {
                    var anchor = doc.createElement('a'); anchor.href = icEmailDesign.homeUrl; anchor.style.color = color; if (underline) { anchor.style.textDecoration = 'underline'; } anchor.textContent = icEmailDesign.siteName; node.appendChild(anchor);
                } else if (part === '\n') { node.appendChild(doc.createElement('br')); }
                else { node.appendChild(doc.createTextNode(part === '[site_url]' ? icEmailDesign.siteUrl : part)); }
            });
        }
        function refresh() {
            if (refreshTimer !== null) { return; }
            refreshTimer = window.setTimeout(function () { refreshTimer = null; renderPreview(); }, 100);
        }
        function renderPreview() {
            var layout = form.find('[name="design[layout]"]:checked').val();
            $('#ic-email-modern-fields').toggleClass('ic-email-dim', layout === 'classic');
            var selected = $('#ic-email-preview-select option:selected'), previewLayout = selected.attr('data-layout') || layout;
            $('.ic-email-preview-title b').text('Preview: ' + (previewLayout === 'classic' ? 'Classic' : 'Modern') + ' layout');
            $('#ic-email-preview-help').text(selected.attr('data-preview-help') || '').prop('hidden', !selected.attr('data-preview-help'));
            contrast();
            updateCustomCount();
            // Inside the editor the preview always shows the fields (draft renders only), never the saved render.
            if (!serverHtml || editing) { return; }
            var doc = new DOMParser().parseFromString(serverHtml, 'text/html');
            if (serverHtml.indexOf('<!--ic-email-modern-->') === -1) { stagePreview(serverHtml); return; }
            var style = $('#ic-email-style').val(), footerColor = style === 'elegant' ? '#8a7f72' : (style === 'bold' ? '#626975' : '#6b7680');
            var color = $('#ic-email-accent').val();
            if (/^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(color)) {
                if (color.length === 4) { color = '#' + color.slice(1).split('').map(function (value) { return value + value; }).join(''); }
                var foreground = accentForeground(color), tint = accentTint(color);
                doc.querySelectorAll('[data-ic-email="accent"]').forEach(function (node) { node.style.backgroundColor = color; });
                doc.querySelectorAll('.ic-email-button, a.remove-plain').forEach(function (node) { node.style.backgroundColor = color; node.style.color = foreground; });
                if (style === 'bold') {
                    doc.querySelectorAll('[data-ic-email="canvas"]').forEach(function (node) { node.style.backgroundColor = accentTint(color, 0.05); });
                    doc.querySelectorAll('[data-ic-email="item"]').forEach(function (node) { node.style.borderBottomColor = accentTint(color, 0.20); });
                    doc.querySelectorAll('[data-ic-email="header"]').forEach(function (node) { node.style.backgroundColor = color; node.style.color = foreground; node.querySelectorAll('h1').forEach(function (heading) { heading.style.color = foreground; }); });
                    doc.querySelectorAll('.ic-email-box').forEach(function (node) { node.style.backgroundColor = tint; node.style.color = accentForeground(tint); });
                }
                doc.querySelectorAll('.ic-email-link').forEach(function (node) { node.style.color = color; });
            }
            var header = doc.querySelector('[data-ic-email="header"]');
            if (header && !header.hasAttribute('data-ic-fixed-brand')) {
                var heading = header.querySelector('h1');
                if (heading) { heading.remove(); }
                header.textContent = icEmailDesign.siteName;
                var checkedHeader = form.find('[name="design[header]"]:checked'), headerMode = checkedHeader.length && !checkedHeader.prop('disabled') ? checkedHeader.val() : 'name';
                if (headerMode === 'text') { header.textContent = ($('#ic-email-header-text').val() || '').trim() || icEmailDesign.siteName; }
                if (headerMode === 'logo') {
                    var image = $('#ic-email-logo-preview img'), source = safeUrl(image.attr('src') || '');
                    if (source) { var logo = doc.createElement('img'); logo.src = source; logo.alt = icEmailDesign.siteName; logo.style.cssText = 'display:block;max-width:200px;max-height:60px;width:auto;height:auto;border:0;' + (style === 'elegant' ? 'margin:0 auto;' : ''); header.textContent = ''; header.appendChild(logo); }
                }
                if (heading) { header.appendChild(heading); }
            }
            var footer = doc.querySelector('[data-ic-email="footer-text"]');
            if (footer && !footer.hasAttribute('data-ic-fixed-brand') && !$('#ic-email-footer').prop('disabled')) { footerText(doc, footer, $('#ic-email-footer').val(), footerColor, style === 'bold'); }
            var link = doc.querySelector('[data-ic-email="footer-link"]');
            if (link && !link.hasAttribute('data-ic-fixed-brand') && !$('#ic-email-footer-label').prop('disabled')) {
                link.textContent = '';
                var label = $('#ic-email-footer-label').val().trim(), address = safeUrl(form.find('[name="design[footer_url]"]').val().trim());
                if (label && address) { var anchor = doc.createElement('a'); anchor.href = address; anchor.style.color = footerColor; if (style === 'bold') { anchor.style.textDecoration = 'underline'; } anchor.textContent = label; link.appendChild(doc.createElement('br')); link.appendChild(anchor); }
            }
            stagePreview('<!doctype html>' + doc.documentElement.outerHTML);
        }
        function serverRefresh(force) {
            var key = currentKey(), current = ++revision;
            if (!force && cache[key]) { serverHtml = cache[key]; refresh(); return; }
            var data = form.serializeArray().filter(function (field) { return field.name !== 'action'; });
            data.push({name: 'action', value: 'ic_email_design_preview'});
            $.post(icEmailDesign.ajaxUrl, data).done(function (response) {
                // The saved preview of this key stays valid even when a newer request superseded it.
                if (response.success && response.data.html) { cache[key] = response.data.html; }
                if (current !== revision || editing) { return; }
                if (response.success && response.data.html) { serverHtml = response.data.html; refresh(); $('#ic-email-preview-status').text(''); }
                else { $('#ic-email-preview-status').text(response.data || ''); }
            });
        }
        var testButton = form.find('.ic-email-design-test'), sending = false, clickedSubmitter = null;
        form.on('click', ':submit', function () { clickedSubmitter = this; });
        form.on('submit', function (event) {
            var submitter = event.originalEvent && event.originalEvent.submitter || clickedSubmitter;
            clickedSubmitter = null;
            if (submitter !== testButton[0]) { return; }
            event.preventDefault();
            if (sending) { return; }
            sending = true;
            var originalHtml = testButton.html(), spinner = form.find('.ic-email-design-actions .spinner'), result = $('#ic-email-test-result');
            var data = form.serializeArray().filter(function (field) { return field.name !== 'action'; });
            data.push({name: 'action', value: 'ic_email_design_test'});
            testButton.prop('disabled', true).attr('aria-busy', 'true').text(icEmailDesign.sending);
            spinner.addClass('is-active'); result.prop('hidden', true).text('');
            function showResult(response) {
                var message = response && response.data && response.data.message;
                result.text(typeof message === 'string' ? message : icEmailDesign.mailFailure).prop('hidden', false);
            }
            $.post(icEmailDesign.ajaxUrl, data).done(showResult).fail(function (xhr) {
                showResult(xhr.responseJSON);
            }).always(function () {
                sending = false;
                testButton.prop('disabled', false).removeAttr('aria-busy').html(originalHtml);
                spinner.removeClass('is-active');
            });
        });
        cache[currentKey()] = serverHtml;
        form.on('input change', 'input, textarea, #ic-email-style', function (event) {
            if (this.name === 'design[layout]' || this.name === 'design[style]') { if (event.type === 'change') { updateCustomCount(); refresh(); serverRefresh(false); } } else { refresh(); }
        });
        $('#ic-email-preview-select').on('change', function () { serverRefresh(false); });
        function selectSample(key) { $('#ic-email-preview-select').val(key).trigger('change'); }
        function templateRequest(type, key, extra) {
            return $.post(icEmailDesign.ajaxUrl, $.extend({action: 'ic_email_design_template', _wpnonce: icEmailDesign.nonce, operation_type: type, sample: key}, extra || {}));
        }
        function updateCustomCount() {
            var count = $('.ic-email-custom-row').filter(function () { return !this.hidden; }).length;
            $('#ic-email-customized').prop('hidden', !count || form.find('[name="design[layout]"]:checked').val() !== 'modern');
            $('.ic-email-custom-count').text(count + (count === 1 ? ' email uses your own text, so it keeps it in the Modern layout' : ' emails use your own text, so they keep it in the Modern layout'));
        }
        var pending, dialog = document.getElementById('ic-email-template-dialog');
        $('.ic-email-custom-preview').on('click', function () { selectSample($(this).closest('[data-sample]').data('sample')); });
        $('.ic-email-use-default').on('click', function () {
            var key = $(this).closest('[data-sample]').data('sample');
            templateRequest('prepare', key).done(function (response) {
                if (!response.success) { $('#ic-email-template-status').text(response.data || ''); return; }
                pending = {key: key, hash: response.data.hash};
                $('#ic-email-before').text(response.data.before); $('#ic-email-after').text(response.data.after); dialog.showModal();
            });
        });
        $('#ic-email-cancel-default').on('click', function () { dialog.close(); });
        $('#ic-email-confirm-default').on('click', function () {
            if (!pending) { return; }
            var operation = pending;
            templateRequest('use', operation.key, {hash: operation.hash}).done(function (response) {
                if (!response.success) { $('#ic-email-template-status').text(response.data || ''); return; }
                dialog.close(); cache = {}; serverRefresh(true);
                var row = $('.ic-email-custom-row').filter(function () { return $(this).data('sample') === operation.key; }); row.prop('hidden', true);
                updateCustomCount();
                var status = $('#ic-email-template-status').text('Modern default text applied. ');
                $('<button>', {type: 'button', 'class': 'button-link', text: icEmailDesign.undoText}).appendTo(status).on('click', function () {
                    templateRequest('undo', operation.key, {operation: response.data.operation}).done(function (undo) {
                        status.text(undo.success ? 'Your text was restored.' : undo.data || '');
                        if (undo.success) { row.prop('hidden', false); updateCustomCount(); cache = {}; serverRefresh(true); }
                    });
                });
            });
        });
        if ($.fn.wpColorPicker) {
            $('#ic-email-accent').wpColorPicker({change: function (event, ui) {
                $('#ic-email-accent').val(ui.color.toString()); refresh();
            }, clear: refresh});
        }
        $('#ic-email-accent-default').on('click', function () {
            $('#ic-email-accent').wpColorPicker('color', '#2f6b9a'); refresh();
        });
        $('#ic-email-choose-logo').on('click', function () {
            var frame = wp.media({title: $(this).text(), library: {type: ['image/png', 'image/jpeg']}, multiple: false});
            frame.on('select', function () {
                var image = frame.state().get('selection').first().toJSON();
                if (image.mime !== 'image/png' && image.mime !== 'image/jpeg') { return; }
                $('#ic-email-logo-id').val(image.id);
                $('#ic-email-logo-preview').empty().append($('<img>', {src: image.url, alt: image.alt || ''}));
                form.find('[name="design[header]"][value="logo"]').prop('checked', true);
                updateHeaderFields();
                refresh();
            });
            frame.open();
        });
        $('#ic-email-remove-logo').on('click', function () {
            $('#ic-email-logo-id').val(0); $('#ic-email-logo-preview').text(icEmailDesign.noLogo); refresh();
        });
        // Edit content: popup over the settings column; the preview stays visible and shows the draft.
        var contents = icEmailDesign.contents || {}, editors = icEmailDesign.editors || {};
        var box = $('#ic-email-content'), contentForm = $('#ic-email-content-form'), editButton = $('#ic-email-edit-content');
        var columns = $('.ic-email-design-columns'), editing = null, draftTimer = null, draftXhr = null, base = null, baseRevision = -1, epoch = 0, lastField = null, saving = false;
        var blockSelector = 'p,h1,h2,h3,h4,h5,h6,li,td,th,div,blockquote,pre';
        function layoutValue() { return form.find('[name="design[layout]"]:checked').val(); }
        function normalizeText(text) { return (text || '').replace(/[\s ]+/g, ' ').trim(); }
        function sameLines(text) { return (text || '').replace(/\r\n?/g, '\n'); }
        function contentKey() { return box.attr('data-sample'); }
        function updateEditButton() {
            var key = $('#ic-email-preview-select').val();
            if (contents[key] && box.length) { editButton.attr('href', '#ic-email-content').prop('hidden', false); }
            else if (editors[key]) { editButton.attr('href', editors[key]).prop('hidden', false); }
            else { editButton.attr('href', '#ic-email-content').prop('hidden', true); }
        }
        // Values the editor shows for the layout picked in the form (same values the preview renders).
        function shownValues(key) { var d = contents[key]; return d ? ((d.layouts && d.layouts[layoutValue()]) || d) : null; }
        function draftFields() {
            var fields = {subject: $('#ic-email-content-subject').val(), text: $('#ic-email-content-text').val()};
            var heading = $('#ic-email-content-heading');
            if (heading.length && !heading.prop('disabled')) { fields.heading = heading.val(); }
            return fields;
        }
        function isDirty() {
            var data = shownValues(contentKey()), fields = draftFields();
            if (!data) { return false; }
            return fields.subject !== data.subject || sameLines(fields.text) !== sameLines(data.text) || (typeof fields.heading === 'string' && fields.heading !== data.heading);
        }
        function updateContentFields() {
            var data = contents[contentKey()] || {}, modern = layoutValue() === 'modern';
            box.find('.ic-email-content-heading').prop('hidden', !(data.hasHeading && modern));
            box.find('.ic-email-content-default').prop('hidden', !(modern && data.modern && normalizeText($('#ic-email-content-text').val()) !== normalizeText(data.modern)));
            $('#ic-email-content-dirty').prop('hidden', !(editing && isDirty()));
        }
        function contentStatus(message) { box.find('.ic-email-content-status').text(message || ''); }
        function fillContent(key) {
            var data = contents[key], shown = shownValues(key);
            if (!data) { return; }
            base = null; baseRevision = -1; epoch++;
            box.attr('data-sample', key);
            contentForm.find('[name="sample"]').val(key);
            contentForm.find('[name="hash"]').val(data.hash);
            box.find('.ic-email-content-label').text(data.label);
            $('#ic-email-content-subject').val(shown.subject);
            $('#ic-email-content-heading').val(shown.heading).attr('placeholder', data.modernTitle).prop('disabled', !data.hasHeading);
            $('#ic-email-content-heading-help').text(icEmailDesign.headingHelp.replace('%s', data.modernTitle));
            $('#ic-email-content-text').val(shown.text);
            var list = box.find('.ic-email-content-token-list').empty();
            data.tokens.forEach(function (token) {
                $('<button>', {type: 'button', 'class': 'button button-small ic-email-token', 'data-token': token[0], title: token[1], text: token[0]}).appendTo(list);
                list.append(' ');
            });
            box.find('.ic-email-content-tokens').prop('hidden', !data.tokens.length);
            // Hint only for emails whose own text sanitizer removes HTML tags.
            box.find('.ic-email-content-plain').prop('hidden', !data.plainText);
            if (data.plainText) { $('#ic-email-content-text').attr('aria-describedby', 'ic-email-content-text-help'); } else { $('#ic-email-content-text').removeAttr('aria-describedby'); }
            contentStatus('');
            updateContentFields();
        }
        function markChanged(node) { node.style.backgroundColor = '#fff8c5'; node.style.outline = '1px solid #e6c75c'; }
        function leafBlocks(doc) {
            return Array.prototype.filter.call(doc.body.querySelectorAll(blockSelector), function (node) { return !node.querySelector(blockSelector) && normalizeText(node.textContent) !== ''; });
        }
        // Highlight the heading and the text blocks that differ from the saved preview.
        var known = null;
        function savedBlocks() {
            var saved = cache[currentKey()];
            if (!saved) { return null; }
            if (!known || known.html !== saved) {
                var doc = new DOMParser().parseFromString(saved, 'text/html'), heading = doc.querySelector('h1'), counts = {};
                leafBlocks(doc).forEach(function (node) { var text = normalizeText(node.textContent); counts[text] = (counts[text] || 0) + 1; });
                known = {html: saved, heading: heading ? normalizeText(heading.textContent) : null, counts: counts};
            }
            return known;
        }
        function highlightDoc(draft) {
            var saved = savedBlocks();
            if (!saved) { return draft; }
            var draftHeading = draft.querySelector('h1'), counts = $.extend({}, saved.counts);
            if (draftHeading && (saved.heading === null || normalizeText(draftHeading.textContent) !== saved.heading)) { markChanged(draftHeading); }
            leafBlocks(draft).forEach(function (node) {
                var text = normalizeText(node.textContent);
                if (node.tagName === 'H1' || node.closest('h1')) { return; }
                if (counts[text]) { counts[text] -= 1; } else { markChanged(node); }
            });
            return draft;
        }
        // Keep the document's own doctype (Classic is HTML 4.01), so a reconcile never changes it.
        function serialize(doc) {
            var type = doc.doctype;
            return (type && (type.publicId || type.systemId) ? new XMLSerializer().serializeToString(type) : '<!doctype html>') + doc.documentElement.outerHTML;
        }
        // Run markers: S + index + M opens a run of the text, E closes it. The server keeps them through
        // feature composition, so the preview can patch the edited runs before the server answers.
        var S = '', M = '', E = '', markerChars = /[-]/g, tokenPattern = /^\[[^\[\]\n]+\]$/;
        function isToken(part) { return tokenPattern.test(part); }
        function sepKey(sep) { return isToken(sep) ? sep : '\n\n'; }
        function splitText(text) {
            var parts = sameLines(text).split(/(\[[^\[\]\n]+\]|\n[ \t]*\n\s*)/), split = {runs: [], seps: []};
            parts.forEach(function (part, index) { (index % 2 ? split.seps : split.runs).push(part); });
            return split;
        }
        function plainRun(run) { return /\S/.test(run) && !/[<>]/.test(run); }
        function markText(split) {
            return split.runs.map(function (run, index) {
                return (plainRun(run) ? S + index + M + run + E : run) + (index < split.seps.length ? split.seps[index] : '');
            }).join('');
        }
        function annotate(html, split) {
            var doc = new DOMParser().parseFromString(html, 'text/html'), starts = {}, ends = [], nodes = [];
            var walker = doc.createTreeWalker(doc.documentElement, NodeFilter.SHOW_TEXT), node, pattern = /(\d{1,4})|/g;
            while ((node = walker.nextNode())) { if (/[-]/.test(node.nodeValue)) { nodes.push(node); } }
            // Isolate every marker in its own text node.
            nodes.forEach(function (text) {
                var value = text.nodeValue, match, last = 0, parent = text.parentNode, pieces = [];
                pattern.lastIndex = 0;
                while ((match = pattern.exec(value))) {
                    if (match.index > last) { pieces.push(doc.createTextNode(value.slice(last, match.index).replace(markerChars, ''))); }
                    var marker = doc.createTextNode('');
                    if (match[1] !== undefined) { starts[match[1]] = marker; } else { ends.push(marker); }
                    pieces.push(marker);
                    last = match.index + match[0].length;
                }
                if (last < value.length) { pieces.push(doc.createTextNode(value.slice(last).replace(markerChars, ''))); }
                pieces.forEach(function (piece) { parent.insertBefore(piece, text); });
                parent.removeChild(text);
            });
            Object.keys(starts).forEach(function (index) {
                var start = starts[index], end = null;
                for (var i = 0; i < ends.length; i++) {
                    if (start.compareDocumentPosition(ends[i]) & Node.DOCUMENT_POSITION_FOLLOWING) { end = ends[i]; break; }
                }
                if (!end || end.parentNode !== start.parentNode || !doc.body.contains(start)) { return; }
                var span = doc.createElement('span');
                span.setAttribute('data-ic-run', index);
                start.parentNode.insertBefore(span, start);
                while (start.nextSibling && start.nextSibling !== end) { span.appendChild(start.nextSibling); }
            });
            Object.keys(starts).forEach(function (index) { starts[index].remove(); });
            ends.forEach(function (end) { end.remove(); });
            Array.prototype.forEach.call(doc.querySelectorAll('*'), function (element) {
                Array.prototype.forEach.call(element.attributes, function (attribute) {
                    if (/[-]/.test(attribute.value)) { attribute.value = attribute.value.replace(markerChars, ''); }
                });
            });
            return {doc: doc, runs: split.runs, seps: split.seps};
        }
        function setRunContent(span, text) {
            var doc = span.ownerDocument;
            span.textContent = '';
            if (span.closest('pre')) { span.appendChild(doc.createTextNode(text)); return; }
            text.split('\n').forEach(function (line, index) {
                if (index) { span.appendChild(doc.createElement('br')); }
                if (line) { span.appendChild(doc.createTextNode(line)); }
            });
        }
        function runSpan(doc, index) { return doc.querySelector('[data-ic-run="' + index + '"]'); }
        // A paragraph that holds nothing but the run (white space and line breaks aside).
        function ownParagraph(span) {
            var block = span.parentNode;
            if (!block || block.nodeName !== 'P') { return null; }
            return Array.prototype.every.call(block.childNodes, function (child) {
                return child === span || (child.nodeType === 3 && !/\S/.test(child.nodeValue)) || child.nodeName === 'BR';
            }) ? block : null;
        }
        function paragraphWith(doc, block, text) {
            var paragraph = block.cloneNode(false), span = doc.createElement('span');
            paragraph.appendChild(span);
            setRunContent(span, text);
            return paragraph;
        }
        // Changed region between the base split and the current one, aligned to runs.
        function changedRegion(old, now) {
            var a = [], b = [];
            old.runs.forEach(function (run, i) { a.push(run); if (i < old.seps.length) { a.push(sepKey(old.seps[i])); } });
            now.runs.forEach(function (run, i) { b.push(run); if (i < now.seps.length) { b.push(sepKey(now.seps[i])); } });
            var prefix = 0, suffix = 0, shorter = Math.min(a.length, b.length);
            while (prefix < shorter && a[prefix] === b[prefix]) { prefix++; }
            while (suffix < shorter - prefix && a[a.length - 1 - suffix] === b[b.length - 1 - suffix]) { suffix++; }
            prefix -= prefix % 2; suffix -= suffix % 2;
            return {start: prefix / 2, oldEnd: (a.length - suffix - 1) / 2, newEnd: (b.length - suffix - 1) / 2};
        }
        // Patch runs start..oldEnd of the base with runs start..newEnd of the current text when the region
        // holds only plain text and paragraph breaks: Enter and Backspace over a break update instantly.
        function patchParagraphs(doc, old, now, region) {
            var i, spans = [], newRuns = now.runs.slice(region.start, region.newEnd + 1);
            for (i = region.start; i < region.oldEnd; i++) { if (isToken(old.seps[i])) { return false; } }
            for (i = region.start; i < region.newEnd; i++) { if (isToken(now.seps[i])) { return false; } }
            if (newRuns.some(function (run) { return /[<>]/.test(run); })) { return false; }
            for (i = region.start; i <= region.oldEnd; i++) {
                var span = runSpan(doc, i);
                if (span) { spans.push(span); } else if (/\S/.test(old.runs[i])) { return false; }
            }
            var texts = newRuns.filter(function (run) { return /\S/.test(run); }), anchor, block, mode = 'in';
            if (spans.length) {
                anchor = spans[0];
                if (texts.length > 1 || !texts.length) { block = ownParagraph(anchor) || (texts.length > 1 && anchor.parentNode.nodeName === 'P' ? anchor.parentNode : null); if (!block && texts.length > 1) { return false; } }
                if (spans.slice(1).some(function (other) { return !ownParagraph(other); })) { return false; }
            } else {
                if (!texts.length) { return true; }
                var before = region.start > 0 && !isToken(old.seps[region.start - 1]) ? runSpan(doc, region.start - 1) : null;
                var after = region.oldEnd < old.runs.length - 1 && !isToken(old.seps[region.oldEnd]) ? runSpan(doc, region.oldEnd + 1) : null;
                if (before && before.parentNode.nodeName === 'P') { anchor = before; mode = 'after'; }
                else if (after && ownParagraph(after)) { anchor = after; mode = 'before'; }
                else { return false; }
                block = anchor.parentNode;
            }
            spans.slice(1).forEach(function (other) { other.parentNode.remove(); });
            if (mode === 'before') {
                texts.forEach(function (text) { block.parentNode.insertBefore(paragraphWith(doc, block, text), block); });
                return true;
            }
            var rest = mode === 'in' ? texts.slice(1) : texts, last = block, tail = [];
            if (mode === 'in') {
                if (!texts.length) {
                    if (block) { block.remove(); } else { setRunContent(anchor, ''); }
                    return true;
                }
                setRunContent(anchor, texts[0]);
                // Content after the run belongs after the last new paragraph, as when Enter splits a paragraph.
                for (var next = anchor.nextSibling; rest.length && next; next = next.nextSibling) { tail.push(next); }
            }
            rest.forEach(function (text) {
                var paragraph = paragraphWith(doc, block, text);
                last.parentNode.insertBefore(paragraph, last.nextSibling);
                last = paragraph;
            });
            tail.forEach(function (node) { last.appendChild(node); });
            return true;
        }
        // Tokens added or removed: show the region as typed until the server renders the sample values.
        function patchTyped(doc, old, now, region) {
            var parts = [], first = null, i;
            for (i = region.start; i <= region.newEnd; i++) { parts.push(now.runs[i]); if (i < region.newEnd) { parts.push(now.seps[i]); } }
            if (/[<>]/.test(parts.join(''))) { return false; }
            for (i = region.start; i <= region.oldEnd; i++) {
                var span = runSpan(doc, i);
                if (!span) { continue; }
                if (first) { setRunContent(span, ''); } else { first = span; }
            }
            if (first) { setRunContent(first, parts.join('')); }
            return !!first;
        }
        // Whether every change of the text could be patched; otherwise the shown preview waits for the server.
        function patchText(doc, text) {
            var complete = true, now = splitText(text), sameSeps = now.seps.length === base.seps.length && now.seps.every(function (sep, i) { return sepKey(sep) === sepKey(base.seps[i]); });
            if (sameSeps) {
                now.runs.forEach(function (run, i) {
                    if (run === base.runs[i]) { return; }
                    var span = runSpan(doc, i);
                    if (span && plainRun(run)) { setRunContent(span, run); } else if (!patchParagraphs(doc, base, now, {start: i, oldEnd: i, newEnd: i})) { complete = false; }
                });
                return complete;
            }
            var region = changedRegion(base, now);
            return patchParagraphs(doc, base, now, region) || patchTyped(doc, base, now, region);
        }
        // Text that waits for the server (markup, unplaceable runs): keep the shown preview as it is and
        // only bring its heading and title up to date, as text.
        function updateShownHeading(fields, data) {
            var live = stagedHtml === null ? loadedPreview() : null;
            if (!live) { return; }
            var title = live.querySelector('h1[data-ic-email="title"]'), saved = savedBlocks();
            if (title && typeof fields.heading === 'string' && !box.find('.ic-email-content-heading').prop('hidden')) {
                var text = fields.heading.trim() || data.modernTitle || title.textContent;
                if (title.textContent !== text) { title.textContent = text; }
                if (saved && saved.heading !== null && normalizeText(text) === saved.heading) { title.style.backgroundColor = ''; title.style.outline = ''; } else { markChanged(title); }
            }
            if (live.title !== fields.subject) { live.title = fields.subject; }
        }
        // Instant preview: patch the last server render on every keystroke; the server reconciles later.
        function instantRender() {
            if (!editing || !base) { return; }
            var fields = draftFields(), data = contents[contentKey()] || {}, clone = base.doc.cloneNode(true);
            if (!patchText(clone, fields.text)) {
                // Never fall back to an older render: the server reconcile replaces the shown preview.
                updateShownHeading(fields, data);
                $('#ic-email-content-dirty').prop('hidden', !isDirty());
                return;
            }
            var title = clone.querySelector('h1[data-ic-email="title"]');
            if (title && typeof fields.heading === 'string' && !box.find('.ic-email-content-heading').prop('hidden')) { title.textContent = fields.heading.trim() || data.modernTitle || title.textContent; }
            clone.title = fields.subject;
            // The fields are shown even when they equal the text the page loaded with (the stored text may
            // have changed since); only unsaved edits are highlighted.
            var html = serialize(isDirty() ? highlightDoc(clone) : clone);
            serverHtml = html;
            stagePreview(html, clone);
            $('#ic-email-content-dirty').prop('hidden', !isDirty());
        }
        // Server render of the draft (with run markers). The newest render received becomes the patch base,
        // even when typing went on meanwhile: the current fields are then re-applied on top of it.
        function requestDraft() {
            window.clearTimeout(draftTimer);
            if (!editing) { return; }
            // Never drop the only request that can provide a base.
            if (draftXhr && base) { draftXhr.abort(); }
            var current = revision, sentEpoch = epoch, sentKey = currentKey(), fields = draftFields(), sent = splitText(fields.text), xhr;
            var data = form.serializeArray().filter(function (field) { return field.name !== 'action'; });
            data.push({name: 'action', value: 'ic_email_design_preview'}, {name: 'marks', value: '1'});
            fields.text = markText(sent);
            Object.keys(fields).forEach(function (name) { data.push({name: 'content[' + name + ']', value: fields[name]}); });
            xhr = draftXhr = $.post(icEmailDesign.ajaxUrl, data);
            xhr.done(function (response) {
                if (!editing || sentEpoch !== epoch || sentKey !== currentKey() || current < baseRevision) { return; }
                if (!response.success || !response.data.html) { if (current === revision) { $('#ic-email-preview-status').text(response.data || ''); } return; }
                base = annotate(response.data.html, sent);
                baseRevision = current;
                if (current !== revision) { instantRender(); return; }
                $('#ic-email-preview-status').text('');
                var clone = base.doc.cloneNode(true), html = serialize(isDirty() ? highlightDoc(clone) : clone);
                if (html !== activeHtml) { serverHtml = html; stagePreview(html); }
            }).always(function () { if (draftXhr === xhr) { draftXhr = null; } });
        }
        function showSavedPreview() {
            window.clearTimeout(draftTimer);
            if (draftXhr) { draftXhr.abort(); }
            serverRefresh(false);
        }
        function draftRefresh() {
            window.clearTimeout(draftTimer);
            draftTimer = window.setTimeout(requestDraft, 300);
        }
        function setPreviewSwitch(on) {
            $('#ic-email-content-preview-switch').prop('checked', on);
            $('body').toggleClass('ic-email-content-preview-on', on);
            if (on && box[0]) { document.documentElement.style.setProperty('--ic-email-content-head', Math.ceil(box[0].getBoundingClientRect().bottom) + 'px'); fitPreview(); }
        }
        function openContent() {
            var key = $('#ic-email-preview-select').val();
            if (!contents[key] || !box.length) { return; }
            if (editing !== key) { fillContent(key); }
            editing = key;
            if (!cache[currentKey()]) { serverRefresh(false); }
            if (!base) { requestDraft(); }
            box.appendTo(columns).prop('hidden', false).addClass('ic-email-content-popup').attr({role: 'dialog', 'aria-labelledby': 'ic-email-content-title'});
            columns.addClass('ic-email-content-open');
            form[0].inert = true;
            setPreviewSwitch(false);
            updateContentFields();
            $('#ic-email-content-result').text('');
            $('#ic-email-content-subject').trigger('focus');
        }
        function closeContent(restore) {
            if (!editing) { return; }
            var key = editing;
            editing = null;
            base = null; baseRevision = -1; epoch++;
            if (restore) { fillContent(key); }
            box.prop('hidden', true).removeClass('ic-email-content-popup');
            columns.removeClass('ic-email-content-open');
            form[0].inert = false;
            setPreviewSwitch(false);
            $('#ic-email-content-dirty').prop('hidden', true);
            showSavedPreview();
            editButton.trigger('focus');
        }
        if (box.length) {
            box.prop('hidden', true);
            box.find('.ic-email-content-close, .ic-email-content-switch').prop('hidden', false);
            editButton.on('click', function (event) {
                if ((editButton.attr('href') || '').charAt(0) !== '#') { return; }
                event.preventDefault();
                openContent();
            });
            box.on('click', '.ic-email-content-close, .ic-email-content-cancel', function (event) { event.preventDefault(); closeContent(true); });
            $(document).on('keydown', function (event) { if (editing && event.key === 'Escape') { event.preventDefault(); closeContent(true); } });
            box.on('focus', 'input[type="text"], textarea', function () { lastField = this; });
            box.on('click', '.ic-email-token', function () {
                var field = lastField && $.contains(box[0], lastField) && !lastField.disabled ? lastField : $('#ic-email-content-text')[0];
                var start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length, end = typeof field.selectionEnd === 'number' ? field.selectionEnd : start;
                field.setRangeText($(this).attr('data-token'), start, end, 'end');
                field.focus();
                $(field).trigger('input');
            });
            box.on('click', '.ic-email-content-default', function () {
                var data = contents[contentKey()];
                if (data && data.modern) { $('#ic-email-content-text').val(data.modern).trigger('input'); }
            });
            box.on('input', 'input[type="text"], textarea', function () { contentStatus(''); updateContentFields(); revision++; instantRender(); draftRefresh(); });
            box.on('change', '#ic-email-content-preview-switch', function () { setPreviewSwitch(this.checked); });
            contentForm.on('submit', function (event) {
                event.preventDefault();
                if (saving) { return; }
                var key = contentKey(), fields = draftFields(), button = contentForm.find('.ic-email-content-save');
                var data = {action: 'ic_email_design_content', _wpnonce: icEmailDesign.nonce, sample: key, hash: contentForm.find('[name="hash"]').val(), layout: layoutValue()};
                Object.keys(fields).forEach(function (name) { data['content[' + name + ']'] = fields[name]; });
                saving = true;
                button.prop('disabled', true).attr('aria-busy', 'true');
                $.post(icEmailDesign.ajaxUrl, data).done(function (response) {
                    if (!response.success) { contentStatus(response.data && response.data.message || icEmailDesign.saveFailed); return; }
                    if (response.data.editor) { contents[key] = response.data.editor; } else {
                        var saved = contents[key];
                        saved.subject = response.data.values.subject; saved.heading = response.data.values.heading; saved.text = response.data.values.text;
                        saved.hash = response.data.hash; saved.customized = response.data.customized;
                    }
                    cache = {};
                    closeContent(true);
                    $('#ic-email-content-result').text(icEmailDesign.textSaved);
                }).fail(function (xhr) {
                    var body = xhr.responseJSON;
                    contentStatus(body && body.data && body.data.message || icEmailDesign.saveFailed);
                }).always(function () {
                    saving = false;
                    button.prop('disabled', false).removeAttr('aria-busy');
                });
            });
        }
        $('#ic-email-preview-select').on('change', function () {
            updateEditButton();
            $('#ic-email-content-result').text('');
            if (!editing) { return; }
            var key = $(this).val();
            if (contents[key]) { editing = key; fillContent(key); requestDraft(); } else { closeContent(true); }
        });
        updateEditButton();
        if (icEmailDesign.edit && box.length) { openContent(); }
        contrast();
        updateCustomCount();
    });
}(jQuery));
