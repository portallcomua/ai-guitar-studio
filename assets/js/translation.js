(function () {
    'use strict';

    window.AIGSTranslation = {
        ready: false,
        translator: null,

        init: async function () {
            this.ready = false;

            return {
                ready: false,
                message:
                    'Модель перекладу українською ще не підключена.'
            };
        },

        translate: async function (text, sourceLanguage) {
            text = String(text || '').trim();

            if (!text) {
                throw new Error('Немає тексту для перекладу.');
            }

            if (!this.ready || !this.translator) {
                throw new Error(
                    'Переклад недоступний: модель перекладу ' +
                    'ще не підключена.'
                );
            }

            return await this.translator.translate({
                text: text,
                sourceLanguage: sourceLanguage || 'auto',
                targetLanguage: 'uk'
            });
        }
    };
})();
