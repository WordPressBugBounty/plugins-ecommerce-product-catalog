/* global jQuery, NodeFilter, Node */
/*
 * Live Preview box for editable email templates.
 *
 * Plain-text edits, the heading and the subject are patched into the shown preview at once.
 * A debounced server render of the unsaved values (nothing is stored) then reconciles the
 * preview document in place, so the frame never reloads, flashes or loses its scroll position.
 * The script never writes editor form fields and never marks the post as changed.
 */
(function ($) {
    'use strict';
    $(function () {
        var cfg = $('.ic-email-template-live[data-post-id]').first();
        if (!cfg.length || typeof window.icEmailTemplatePreview === 'undefined') { return; }
        var settings = window.icEmailTemplatePreview, mode = cfg.attr('data-mode') === 'plain' ? 'plain' : 'design';
        var frame = null, h3 = $(), pre = $();
        if (mode === 'design') {
            frame = cfg.closest('.inside').find('iframe').first()[0];
            if (!frame) { return; }
        } else {
            h3 = cfg.prevAll('h3').first();
            pre = cfg.nextAll('pre').first();
            if (!pre.length) { return; }
        }
        var status = $('<p class="description ic-email-template-live-status" role="status"></p>').text(settings.live);
        status.insertAfter(mode === 'design' ? $(frame) : pre);

        var subjectSelector = mode === 'design' ? '#ic-email-template-subject' : 'input[name="template_subject"]';
        var headingSelector = '#ic-email-template-heading';
        var state = null, revision = 0, timer = null, xhr = null, started = false;
        // seq numbers every request; accepted is the seq of the newest render shown, so an older render never
        // replaces a newer one, whatever order the responses arrive in.
        var seq = 0, accepted = 0;
        // base: the split of the last accepted server render; shown: the run texts currently displayed.
        var base = null, shown = null, baseText = null, pending = null, loadBound = false;

        // Run markers: S + index + M opens a plain-text run of the body, E closes it.
        var S = '\uE000', M = '\uE001', E = '\uE002', markerChars = /[\uE000-\uE002]/g;
        var tokenizer = /(<[^>]*>|\[[^\[\]\n]*\]|&[#a-zA-Z0-9]+;|[ \t]*(?:\r\n|\r|\n)\s*)/;
        var rawText = /^<(script|style|title|textarea)\b/i;

        function headingField() { return $(headingSelector); }
        function editor() {
            var ed = window.tinymce && window.tinymce.get ? window.tinymce.get('content') : null;
            return ed && ed.initialized ? ed : null;
        }
        // TinyMCE selection bookmarks (left in the content while WordPress switches editor tabs) are
        // never part of the saved body; drop them so they never reach the base split or the server.
        var bookmarkSpan = /<span\b[^>]*(?:data-mce-type=["']bookmark["']|\bmce_SELRES_(?:start|end)\b)[^>]*>[\s\S]*?<\/span>/gi;
        function cleanBody(body) {
            return String(body).replace(bookmarkSpan, '').replace(/\uFEFF/g, '');
        }
        function readBody() {
            var ed = editor();
            if (ed && !ed.isHidden()) { return cleanBody(ed.getContent()); }
            // WordPress also writes the bookmark into the textarea just before it switches to Visual.
            return cleanBody($('#content').val() || '');
        }
        function read() {
            var heading = headingField();
            return {
                subject: String($(subjectSelector).first().val() || ''),
                heading: heading.length ? String(heading.val() || '') : null,
                body: readBody()
            };
        }

        // Separators are tags, shortcodes, entities and line breaks; runs are the text between them.
        function split(body) {
            var parts = String(body).split(tokenizer), result = {runs: [], seps: [], markable: []}, raw = null, open;
            for (var i = 0; i < parts.length; i++) {
                var part = parts[i] === undefined ? '' : parts[i];
                if (i % 2) {
                    result.seps.push(part);
                    if (raw) {
                        if (new RegExp('^</' + raw + '\\s*>', 'i').test(part)) { raw = null; }
                    } else if ((open = rawText.exec(part)) && !/\/>$/.test(part)) {
                        raw = open[1].toLowerCase();
                    }
                } else {
                    result.runs.push(part);
                    result.markable.push(!raw && /\S/.test(part) && !/[<>\[\]&\uE000-\uE002]/.test(part));
                }
            }
            return result;
        }
        function mark(sp) {
            return sp.runs.map(function (run, index) {
                return (sp.markable[index] ? S + index + M + run + E : run) + (index < sp.seps.length ? sp.seps[index] : '');
            }).join('');
        }
        function sameList(a, b) {
            if (a.length !== b.length) { return false; }
            for (var i = 0; i < a.length; i++) { if (a[i] !== b[i]) { return false; } }
            return true;
        }

        // Parse a marked render and wrap each run occurrence in a span; strip every marker afterwards.
        function annotate(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html'), starts = [], ends = [], nodes = [];
            var walker = doc.createTreeWalker(doc.documentElement, NodeFilter.SHOW_TEXT), node, pattern = /\uE000(\d{1,4})\uE001|\uE002/g;
            while ((node = walker.nextNode())) { if (/[\uE000-\uE002]/.test(node.nodeValue)) { nodes.push(node); } }
            nodes.forEach(function (text) {
                var value = text.nodeValue, match, last = 0, parent = text.parentNode, pieces = [];
                pattern.lastIndex = 0;
                while ((match = pattern.exec(value))) {
                    if (match.index > last) { pieces.push(doc.createTextNode(value.slice(last, match.index).replace(markerChars, ''))); }
                    var marker = doc.createTextNode('');
                    if (match[1] !== undefined) { starts.push({index: match[1], node: marker}); } else { ends.push(marker); }
                    pieces.push(marker);
                    last = match.index + match[0].length;
                }
                if (last < value.length) { pieces.push(doc.createTextNode(value.slice(last).replace(markerChars, ''))); }
                pieces.forEach(function (piece) { parent.insertBefore(piece, text); });
                parent.removeChild(text);
            });
            starts.forEach(function (start) {
                var end = null;
                for (var i = 0; i < ends.length; i++) {
                    if (start.node.compareDocumentPosition(ends[i]) & Node.DOCUMENT_POSITION_FOLLOWING) { end = ends[i]; break; }
                }
                if (!end || end.parentNode !== start.node.parentNode || !doc.body || !doc.body.contains(start.node)) { return; }
                var span = doc.createElement('span');
                span.setAttribute('data-ic-run', start.index);
                start.node.parentNode.insertBefore(span, start.node);
                while (start.node.nextSibling && start.node.nextSibling !== end) { span.appendChild(start.node.nextSibling); }
            });
            starts.forEach(function (start) { if (start.node.parentNode) { start.node.parentNode.removeChild(start.node); } });
            ends.forEach(function (end) { if (end.parentNode) { end.parentNode.removeChild(end); } });
            Array.prototype.forEach.call(doc.querySelectorAll('*'), function (element) {
                Array.prototype.forEach.call(element.attributes, function (attribute) {
                    if (/[\uE000-\uE002]/.test(attribute.value)) { attribute.value = attribute.value.replace(markerChars, ''); }
                });
            });
            return doc;
        }
        // Plain mode: split the rendered text into run and literal segments.
        function annotateText(text) {
            var segments = [], pattern = /\uE000(\d{1,4})\uE001([^\uE000-\uE002]*)\uE002/g, match, last = 0;
            text = String(text);
            while ((match = pattern.exec(text))) {
                if (match.index > last) { segments.push({run: null, text: text.slice(last, match.index).replace(markerChars, '')}); }
                segments.push({run: match[1], text: match[2]});
                last = match.index + match[0].length;
            }
            if (last < text.length) { segments.push({run: null, text: text.slice(last).replace(markerChars, '')}); }
            return segments;
        }
        function joinText(segments) { return segments.map(function (segment) { return segment.text; }).join(''); }

        function liveDoc() {
            try {
                var doc = frame.contentDocument;
                return doc && doc.readyState === 'complete' && doc.body ? doc : null;
            } catch (e) {
                return null;
            }
        }
        // Body runs that differ from the shown preview, or null when only a server render can show the change.
        function changedRuns(body) {
            if (!base) { return null; }
            var sp = split(body), changed = [];
            if (!sameList(sp.seps, base.seps)) { return null; }
            for (var i = 0; i < sp.runs.length; i++) {
                if (sp.runs[i] === shown[i]) { continue; }
                if (!sp.markable[i] || !base.markable[i]) { return null; }
                changed.push(i);
            }
            return {runs: sp.runs, changed: changed};
        }
        // Modern layout: a set heading replaces the email's first title; an empty one shows the email type's own
        // title as the first h1, which the first typed heading character replaces at once. Classic ignores headings.
        function isModern(live) {
            var walker = live.createTreeWalker(live.body, NodeFilter.SHOW_COMMENT), node;
            while ((node = walker.nextNode())) { if (node.nodeValue === 'ic-email-modern') { return true; } }
            return false;
        }
        function titleElement(live) {
            var title = live.querySelector('h1[data-ic-email="title"]');
            if (title || !isModern(live)) { return title; }
            return live.body.querySelector('h1');
        }
        function simpleText(value) { return '' !== value && !/[\[<>&%]/.test(value); }

        function instant(next) {
            var diff = changedRuns(next.body), subject = next.subject.trim(), i;
            if (mode === 'design') {
                var live = liveDoc();
                if (!live || !base) { return; }
                if (diff && diff.changed.length) {
                    var targets = [], ok = true;
                    diff.changed.forEach(function (index) {
                        var spans = live.querySelectorAll('[data-ic-run="' + index + '"]');
                        if (!spans.length) { ok = false; }
                        Array.prototype.forEach.call(spans, function (span) {
                            if (span.children.length) { ok = false; }
                            targets.push({index: index, span: span});
                        });
                    });
                    if (ok) {
                        targets.forEach(function (target) {
                            if (target.span.textContent !== diff.runs[target.index]) { target.span.textContent = diff.runs[target.index]; }
                        });
                        diff.changed.forEach(function (index) { shown[index] = diff.runs[index]; });
                    }
                }
                if (next.heading !== null && simpleText(next.heading.trim())) {
                    var title = titleElement(live);
                    if (title && title.textContent !== next.heading.trim()) { title.textContent = next.heading.trim(); }
                }
                if (simpleText(subject) && live.title !== subject) { live.title = subject; }
                return;
            }
            if (simpleText(subject)) { h3.text(subject); }
            if (baseText && diff && diff.changed.length) {
                for (i = 0; i < diff.changed.length; i++) {
                    var found = false;
                    for (var j = 0; j < baseText.length; j++) {
                        if (baseText[j].run === String(diff.changed[i])) { found = true; }
                    }
                    if (!found) { return; }
                }
                baseText.forEach(function (segment) {
                    if (segment.run !== null && diff.changed.indexOf(Number(segment.run)) !== -1) { segment.text = diff.runs[Number(segment.run)]; }
                });
                diff.changed.forEach(function (index) { shown[index] = diff.runs[index]; });
                pre.text(joinText(baseText));
            }
        }

        // In-place document sync: the frame is never reloaded; only differing nodes and attributes change.
        function syncAttrs(live, target) {
            var names = {};
            Array.prototype.forEach.call(live.attributes, function (a) { names[a.name] = true; });
            Array.prototype.forEach.call(target.attributes, function (a) { names[a.name] = true; });
            Object.keys(names).forEach(function (name) {
                var value = target.getAttribute(name);
                if (live.getAttribute(name) === value) { return; }
                try {
                    if (value === null) { live.removeAttribute(name); } else { live.setAttribute(name, value); }
                } catch (e) { /* An invalid attribute name cannot be copied; the rest still syncs. */ }
            });
        }
        function syncNode(live, target) {
            if (live.isEqualNode(target)) { return; }
            if (live.nodeType !== 1) {
                if (live.nodeValue !== target.nodeValue) { live.nodeValue = target.nodeValue; }
                return;
            }
            syncAttrs(live, target);
            syncChildren(live, target);
        }
        function syncChildren(live, target) {
            var a = Array.prototype.slice.call(live.childNodes), b = Array.prototype.slice.call(target.childNodes), start = 0, end = 0;
            while (start < a.length && start < b.length && a[start].isEqualNode(b[start])) { start++; }
            while (end < a.length - start && end < b.length - start && a[a.length - 1 - end].isEqualNode(b[b.length - 1 - end])) { end++; }
            var before = end ? a[a.length - end] : null, count = Math.max(a.length - start - end, b.length - start - end);
            for (var i = 0; i < count; i++) {
                var x = start + i < a.length - end ? a[start + i] : null, y = start + i < b.length - end ? b[start + i] : null;
                if (x && y && x.nodeType === y.nodeType && x.nodeName === y.nodeName) {
                    syncNode(x, y);
                    continue;
                }
                if (y) { live.insertBefore(live.ownerDocument.importNode(y, true), x || before); }
                if (x) { live.removeChild(x); }
            }
        }
        function syncInPlace(live, doc) {
            syncAttrs(live.documentElement, doc.documentElement);
            if (live.head && doc.head) { syncChildren(live.head, doc.head); }
            syncAttrs(live.body, doc.body);
            syncChildren(live.body, doc.body);
            if (live.title !== doc.title) { live.title = doc.title; }
        }
        function sameState(a, b) { return a.subject === b.subject && a.heading === b.heading && a.body === b.body; }
        // Whether a render of `sent` can be shown with the current field values patched over it at once, so
        // no older text is ever painted. Otherwise a superseded render is dropped and the next one reconciles.
        function rebaseable(now, sent, sp, doc, segments) {
            if (now.subject !== sent.subject && !simpleText(now.subject.trim())) { return false; }
            if (now.heading !== sent.heading && (mode !== 'design' || now.heading === null || !simpleText(now.heading.trim()) || !titleElement(doc))) { return false; }
            if (now.body === sent.body) { return true; }
            var current = split(now.body);
            if (!sameList(current.seps, sp.seps)) { return false; }
            for (var i = 0; i < current.runs.length; i++) {
                if (current.runs[i] === sp.runs[i]) { continue; }
                if (!current.markable[i] || !sp.markable[i]) { return false; }
                if (mode === 'design') {
                    var spans = doc.querySelectorAll('[data-ic-run="' + i + '"]');
                    if (!spans.length) { return false; }
                    for (var j = 0; j < spans.length; j++) { if (spans[j].children.length) { return false; } }
                } else if (!segments.some(function (segment) { return segment.run === String(i); })) {
                    return false;
                }
            }
            return true;
        }
        function applyPending() {
            var live = liveDoc();
            if (!live) {
                if (!loadBound) {
                    loadBound = true;
                    $(frame).one('load', function () { loadBound = false; applyPending(); });
                }
                return;
            }
            if (!pending) { return; }
            var next = pending;
            pending = null;
            if (next.seq < accepted) { return; }
            var changed = state && !sameState(state, next.sent);
            if (changed && !rebaseable(state, next.sent, next.split, next.doc, null)) { return; }
            accepted = next.seq;
            // Sync and re-apply the newer input in one task, so the older render text is never painted.
            syncInPlace(live, next.doc);
            base = next.split;
            shown = next.split.runs.slice();
            if (changed) { instant(state); }
        }

        function fail(message) { status.text(settings.failed + (message ? ' ' + message : '')); }
        function accept(data, sp, sent, id) {
            if (mode === 'design') {
                if (data.mode !== 'design' || typeof data.html !== 'string') { return; }
                if (pending && pending.seq > id) { return; }
                pending = {doc: annotate(data.html), split: sp, sent: sent, seq: id};
                applyPending();
                return;
            }
            if (typeof data.body !== 'string' || id < accepted) { return; }
            var segments = annotateText(data.body), changed = state && !sameState(state, sent);
            if (changed && !rebaseable(state, sent, sp, null, segments)) { return; }
            accepted = id;
            h3.text(String(data.subject || ''));
            baseText = segments;
            base = sp;
            shown = sp.runs.slice();
            pre.text(joinText(baseText));
            if (changed) { instant(state); }
        }
        function request() {
            window.clearTimeout(timer);
            timer = null;
            if (xhr) { xhr.abort(); }
            var sent = revision, current = state, sp = split(current.body), id = ++seq;
            var data = {
                action: cfg.attr('data-action'),
                post_id: cfg.attr('data-post-id'),
                _wpnonce: cfg.attr('data-nonce'),
                subject: current.subject,
                body: mark(sp),
                marks: 1
            };
            if (current.heading !== null) { data.heading = current.heading; }
            var call = $.ajax({url: settings.ajaxUrl, type: 'POST', data: data, dataType: 'json'});
            xhr = call;
            call.done(function (response) {
                var superseded = sent !== revision;
                if (!response || !response.success || !response.data) {
                    if (!superseded) { fail(response && response.data && response.data.message); }
                    return;
                }
                if (!superseded) { status.text(settings.live); }
                // A superseded render still becomes the base when the newer input can be patched over it.
                accept(response.data, sp, current, id);
            }).fail(function (jqXHR, textStatus) {
                if (textStatus === 'abort' || sent !== revision) { return; }
                fail(jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message);
            }).always(function () {
                if (xhr === call) { xhr = null; }
            });
        }
        function schedule() {
            window.clearTimeout(timer);
            timer = window.setTimeout(request, 300);
        }
        function onInput() {
            if (!started) { return; }
            var next = read();
            if (state && next.subject === state.subject && next.heading === state.heading && next.body === state.body) { return; }
            state = next;
            revision++;
            instant(next);
            schedule();
        }
        function later() { window.setTimeout(onInput, 0); }
        function start() {
            if (started) { return; }
            started = true;
            state = read();
            request();
        }
        function bindEditor(ed) {
            if (!ed || ed.id !== 'content' || ed.icEmailTemplatePreview) { return; }
            ed.icEmailTemplatePreview = true;
            ed.on('input keyup change undo redo paste ExecCommand SetContent show', later);
        }

        $(document).on('input change keyup', subjectSelector + ', ' + headingSelector + ', #content', onInput);
        $(document).on('click', '#content-tmce, #content-html, #qt_content_toolbar .ed_button', later);
        $(document).on('tinymce-editor-init', function (event, ed) {
            bindEditor(ed);
            if (!ed || ed.id !== 'content') { return; }
            // A late init (the page loaded in the Text tab) happens after the editor's own show/SetContent events:
            // re-read now so the editor's serialization becomes the base instead of the raw textarea text.
            if (started) { later(); } else { start(); }
        });
        bindEditor(editor());
        // The base render waits for the Visual editor, so the first request already uses its content.
        if ($('#wp-content-wrap').hasClass('tmce-active') && window.tinymce && !editor()) {
            window.setTimeout(start, 5000);
        } else {
            start();
        }
    });
}(jQuery));
