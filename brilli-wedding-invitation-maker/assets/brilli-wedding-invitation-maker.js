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

    function replacePlaceholders(text, data) {
        return String(text || '')
            .replaceAll('{name}', data.name)
            .replaceAll('{phone}', data.phone)
            .replaceAll('{encoded_name}', data.encodedName)
            .replaceAll('{invitation_url}', data.invitationUrl);
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

    function copyValue(textarea, setNotice, message) {
        textarea.focus();
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(textarea.value).then(function () {
                setNotice(message);
            }).catch(function () {
                document.execCommand('copy');
                setNotice(message);
            });
        } else {
            document.execCommand('copy');
            setNotice(message);
        }
    }

    function init(wrapper) {
        var settings = getSettings(wrapper);
        var nameInput = wrapper.querySelector('.brilli-wim__name');
        var phoneInput = wrapper.querySelector('.brilli-wim__phone');
        var generateButton = wrapper.querySelector('.brilli-wim__generate');
        var result = wrapper.querySelector('.brilli-wim__result');
        var urlId = wrapper.querySelector('.brilli-wim__url--id');
        var urlEn = wrapper.querySelector('.brilli-wim__url--en');
        var messageId = wrapper.querySelector('.brilli-wim__message--id');
        var messageEn = wrapper.querySelector('.brilli-wim__message--en');
        var copyId = wrapper.querySelector('.brilli-wim__copy--id');
        var copyEn = wrapper.querySelector('.brilli-wim__copy--en');
        var whatsappId = wrapper.querySelector('.brilli-wim__whatsapp--id');
        var whatsappEn = wrapper.querySelector('.brilli-wim__whatsapp--en');
        var notice = wrapper.querySelector('.brilli-wim__notice');

        function setNotice(text) {
            if (notice) {
                notice.textContent = text || '';
            }
        }

        function generate() {
            var name = nameInput.value.trim();
            var phone = phoneInput.value.trim();
            var invitationUrlId;
            var invitationUrlEn;
            var encodedName;
            var generatedMessageId;
            var generatedMessageEn;

            if (!name) {
                setNotice('Nama wajib diisi.');
                nameInput.focus();
                return false;
            }

            encodedName = encodeGuestName(name);
            invitationUrlId = buildInvitationUrl(settings, name, phone, 'id');
            invitationUrlEn = buildInvitationUrl(settings, name, phone, 'en');

            generatedMessageId = replacePlaceholders(settings.messageId, {
                name: name,
                phone: phone,
                encodedName: encodedName,
                invitationUrl: invitationUrlId
            });

            generatedMessageEn = replacePlaceholders(settings.messageEn, {
                name: name,
                phone: phone,
                encodedName: encodedName,
                invitationUrl: invitationUrlEn
            });

            urlId.value = invitationUrlId;
            urlEn.value = invitationUrlEn;
            messageId.value = generatedMessageId;
            messageEn.value = generatedMessageEn;

            if (whatsappId) {
                whatsappId.href = buildWhatsAppUrl(phone, generatedMessageId);
            }

            if (whatsappEn) {
                whatsappEn.href = buildWhatsAppUrl(phone, generatedMessageEn);
            }

            result.hidden = false;
            setNotice('Undangan berhasil digenerate.');
            return true;
        }

        if (!nameInput || !phoneInput || !generateButton || !result || !urlId || !urlEn || !messageId || !messageEn) {
            return;
        }

        generateButton.addEventListener('click', generate);

        [nameInput, phoneInput].forEach(function (input) {
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    generate();
                }
            });
        });

        if (copyId) {
            copyId.addEventListener('click', function () {
                if (!messageId.value && !generate()) {
                    return;
                }
                copyValue(messageId, setNotice, 'Kalimat Indonesia berhasil dicopy.');
            });
        }

        if (copyEn) {
            copyEn.addEventListener('click', function () {
                if (!messageEn.value && !generate()) {
                    return;
                }
                copyValue(messageEn, setNotice, 'English message copied.');
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.brilli-wim').forEach(init);
    });
}());
