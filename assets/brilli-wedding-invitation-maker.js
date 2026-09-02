(function () {
    'use strict';

    function getSettings(wrapper) {
        try {
            return JSON.parse(wrapper.getAttribute('data-settings') || '{}');
        } catch (error) {
            return {};
        }
    }

    function encodeGuestName(name) {
        return encodeURIComponent((name || '').trim());
    }

    function replaceAll(text, token, value) {
        return String(text || '').split(token).join(value || '');
    }

    function replacePlaceholders(text, data) {
        var output = String(text || '');

        output = replaceAll(output, '{name}', data.name);
        output = replaceAll(output, '{phone}', data.phone);
        output = replaceAll(output, '{encoded_name}', data.encodedName);
        output = replaceAll(output, '{invitation_url}', data.invitationUrl);

        return output;
    }

    function makeUrlFromBase(baseUrl, param, encodedName) {
        var url = String(baseUrl || '').trim();
        var hash = '';
        var hashIndex;
        var separator;

        if (!url) {
            url = 'https://brillian.my.id/';
        }

        hashIndex = url.indexOf('#');
        if (hashIndex !== -1) {
            hash = url.substring(hashIndex);
            url = url.substring(0, hashIndex);
        }

        separator = url.indexOf('?') === -1 ? '?' : (url.endsWith('?') || url.endsWith('&') ? '' : '&');

        return url + separator + encodeURIComponent(param || 'to') + '=' + encodedName + hash;
    }

    function buildInvitationUrl(settings, name, phone, language) {
        var encodedName = encodeGuestName(name);
        var isEnglish = language === 'en';
        var customTemplate = String(isEnglish ? (settings.customUrlEn || '') : (settings.customUrlId || '')).trim();
        var baseUrl = isEnglish ? (settings.baseUrlEn || 'https://brillian.my.id/en/') : (settings.baseUrlId || 'https://brillian.my.id/');
        var param = settings.urlParam || 'to';

        if (customTemplate) {
            return replacePlaceholders(customTemplate, {
                name: name,
                phone: phone,
                encodedName: encodedName,
                invitationUrl: ''
            });
        }

        return makeUrlFromBase(baseUrl, param, encodedName);
    }

    function normalizePhone(phone) {
        var clean = String(phone || '').replace(/[^0-9+]/g, '');

        if (!clean) {
            return '';
        }

        if (clean.charAt(0) === '+') {
            return clean.replace(/[^0-9]/g, '');
        }

        if (clean.indexOf('08') === 0) {
            return '62' + clean.substring(1);
        }

        if (clean.indexOf('8') === 0) {
            return '62' + clean;
        }

        return clean.replace(/[^0-9]/g, '');
    }

    function buildWhatsAppUrl(phone, message) {
        var normalizedPhone = normalizePhone(phone);
        var text = encodeURIComponent(message || '');

        if (normalizedPhone) {
            return 'https://wa.me/' + normalizedPhone + '?text=' + text;
        }

        return 'https://wa.me/?text=' + text;
    }

    function legacyCopyValue(textarea) {
        var activeElement = document.activeElement;
        var copied = false;

        textarea.focus();
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);

        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        if (activeElement && typeof activeElement.focus === 'function') {
            activeElement.focus();
        }

        return copied;
    }

    function copyValue(textarea) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(textarea.value).catch(function () {
                if (!legacyCopyValue(textarea)) {
                    return Promise.reject(new Error('Clipboard is unavailable.'));
                }

                return undefined;
            });
        }

        if (legacyCopyValue(textarea)) {
            return Promise.resolve();
        }

        return Promise.reject(new Error('Clipboard is unavailable.'));
    }

    function flashButtonLabel(button, temporaryLabel) {
        var originalLabel = button.getAttribute('data-original-label') || button.textContent;

        if (button.brilliWimFlashTimer) {
            window.clearTimeout(button.brilliWimFlashTimer);
        }

        button.setAttribute('data-original-label', originalLabel);
        button.textContent = temporaryLabel;

        button.brilliWimFlashTimer = window.setTimeout(function () {
            button.textContent = originalLabel;
            button.brilliWimFlashTimer = null;
        }, 1600);
    }

    function init(wrapper) {
        var settings = getSettings(wrapper);
        var strings = settings.i18n || {};
        var nameInput = wrapper.querySelector('.brilli-wim__name');
        var phoneInput = wrapper.querySelector('.brilli-wim__phone');
        var generateButton = wrapper.querySelector('.brilli-wim__generate');
        var result = wrapper.querySelector('.brilli-wim__result');
        var urlId = wrapper.querySelector('.brilli-wim__url--id');
        var urlEn = wrapper.querySelector('.brilli-wim__url--en');
        var tabs = Array.prototype.slice.call(wrapper.querySelectorAll('.brilli-wim__tab'));
        var panels = Array.prototype.slice.call(wrapper.querySelectorAll('.brilli-wim__panel'));
        var messageFields = Array.prototype.slice.call(wrapper.querySelectorAll('.brilli-wim__message'));
        var copyButtons = Array.prototype.slice.call(wrapper.querySelectorAll('.brilli-wim__copy'));
        var whatsappLinks = Array.prototype.slice.call(wrapper.querySelectorAll('.brilli-wim__whatsapp'));
        var notice = wrapper.querySelector('.brilli-wim__notice');

        if (wrapper.getAttribute('data-brilli-wim-initialized') === 'true') {
            return;
        }

        if (!nameInput || !phoneInput || !generateButton || !result || !urlId || !urlEn || !tabs.length || !messageFields.length) {
            return;
        }

        wrapper.setAttribute('data-brilli-wim-initialized', 'true');

        function getString(key, fallback) {
            return typeof strings[key] === 'string' && strings[key] ? strings[key] : fallback;
        }

        function setNotice(text, state) {
            if (notice) {
                notice.textContent = text || '';

                if (text && state) {
                    notice.setAttribute('data-state', state);
                } else {
                    notice.removeAttribute('data-state');
                }
            }
        }

        function findMessage(templateKey, language) {
            return wrapper.querySelector(
                '.brilli-wim__message[data-template="' + templateKey + '"][data-language="' + language + '"]'
            );
        }

        function getMessageTemplate(templateKey, language) {
            if (!settings.messages || !settings.messages[templateKey]) {
                return '';
            }

            return settings.messages[templateKey][language] || '';
        }

        function activateTab(tab, moveFocus) {
            var templateKey = tab.getAttribute('data-template');

            tabs.forEach(function (candidate) {
                var isActive = candidate === tab;

                candidate.setAttribute('aria-selected', isActive ? 'true' : 'false');
                candidate.setAttribute('tabindex', isActive ? '0' : '-1');
            });

            panels.forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-template') !== templateKey;
            });

            if (moveFocus) {
                tab.focus();
            }
        }

        function scrollToResult() {
            var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var requestFrame = window.requestAnimationFrame || function (callback) {
                callback();
            };

            if (typeof result.scrollIntoView !== 'function') {
                return;
            }

            requestFrame(function () {
                result.scrollIntoView({
                    behavior: prefersReducedMotion ? 'auto' : 'smooth',
                    block: 'start'
                });
            });
        }

        function generate() {
            var name = nameInput.value.trim();
            var phone = phoneInput.value.trim();
            var invitationUrls;
            var encodedName;

            if (!name) {
                setNotice(getString('nameRequired', 'Masukkan nama tamu untuk membuat undangan.'), 'error');
                nameInput.setAttribute('aria-invalid', 'true');
                nameInput.focus();
                return false;
            }

            nameInput.removeAttribute('aria-invalid');

            encodedName = encodeGuestName(name);
            invitationUrls = {
                id: buildInvitationUrl(settings, name, phone, 'id'),
                en: buildInvitationUrl(settings, name, phone, 'en')
            };

            urlId.value = invitationUrls.id;
            urlEn.value = invitationUrls.en;

            messageFields.forEach(function (textarea) {
                var templateKey = textarea.getAttribute('data-template');
                var language = textarea.getAttribute('data-language');

                textarea.value = replacePlaceholders(getMessageTemplate(templateKey, language), {
                    name: name,
                    phone: phone,
                    encodedName: encodedName,
                    invitationUrl: invitationUrls[language]
                });
            });

            whatsappLinks.forEach(function (link) {
                var templateKey = link.getAttribute('data-template');
                var language = link.getAttribute('data-language');
                var textarea = findMessage(templateKey, language);

                link.href = buildWhatsAppUrl(phone, textarea ? textarea.value : '');
            });

            result.hidden = false;
            setNotice(getString('generated', 'Tiga versi undangan berhasil dibuat dan siap dibagikan.'), 'success');
            scrollToResult();
            return true;
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                activateTab(tab, false);
            });

            tab.addEventListener('keydown', function (event) {
                var targetIndex = index;

                if (event.key === 'ArrowRight') {
                    targetIndex = (index + 1) % tabs.length;
                } else if (event.key === 'ArrowLeft') {
                    targetIndex = (index - 1 + tabs.length) % tabs.length;
                } else if (event.key === 'Home') {
                    targetIndex = 0;
                } else if (event.key === 'End') {
                    targetIndex = tabs.length - 1;
                } else {
                    return;
                }

                event.preventDefault();
                activateTab(tabs[targetIndex], true);
            });
        });

        generateButton.addEventListener('click', generate);

        nameInput.addEventListener('input', function () {
            if (nameInput.value.trim()) {
                nameInput.removeAttribute('aria-invalid');

                if (notice && notice.getAttribute('data-state') === 'error') {
                    setNotice('', '');
                }
            }
        });

        [nameInput, phoneInput].forEach(function (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    generate();
                }
            });
        });

        copyButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var templateKey = button.getAttribute('data-template');
                var language = button.getAttribute('data-language');
                var textarea = findMessage(templateKey, language);

                if (!textarea) {
                    return;
                }

                if (!textarea.value && !generate()) {
                    return;
                }

                copyValue(textarea).then(function () {
                    setNotice(
                        language === 'en'
                            ? getString('copyEnSuccess', 'English message copied.')
                            : getString('copyIdSuccess', 'Kalimat Indonesia berhasil disalin.'),
                        'success'
                    );
                    flashButtonLabel(
                        button,
                        language === 'en'
                            ? getString('copiedEn', 'Copied')
                            : getString('copiedId', 'Tersalin')
                    );
                }).catch(function () {
                    setNotice(
                        getString('copyError', 'Pesan tidak dapat disalin. Silakan salin secara manual.'),
                        'error'
                    );
                });
            });
        });

        whatsappLinks.forEach(function (link) {
            link.addEventListener('click', function (event) {
                var templateKey = link.getAttribute('data-template');
                var language = link.getAttribute('data-language');
                var textarea = findMessage(templateKey, language);

                if ((!textarea || !textarea.value) && !generate()) {
                    event.preventDefault();
                }
            });
        });
    }

    function initAll() {
        Array.prototype.forEach.call(document.querySelectorAll('.brilli-wim'), init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
}());
