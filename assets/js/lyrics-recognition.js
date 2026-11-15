(function () {
    'use strict';

    window.AIGSLyrics = {
        ready: false,
        recognizer: null,
        lines: [],

        init: async function () {
            this.ready = false;

            return {
                ready: false,
                message:
                    'Модель розпізнавання співу ще не підключена.'
            };
        },

        recognize: async function (audioBuffer) {
            if (!audioBuffer) {
                throw new Error('Спочатку завантаж композицію.');
            }

            if (!this.ready || !this.recognizer) {
                throw new Error(
                    'Розпізнавання тексту пісні недоступне: ' +
                    'модель транскрибування ще не підключена.'
                );
            }

            var result = await this.recognizer.transcribe(
                audioBuffer
            );

            this.lines = Array.isArray(result)
                ? result
                : [];

            return this.lines;
        },

        getLines: function () {
            return this.lines.slice();
        },

        getText: function () {
            return this.lines.map(function (line) {
                return line.text || '';
            }).join('\n');
        },

        clear: function () {
            this.lines = [];
        }
    };
})();
