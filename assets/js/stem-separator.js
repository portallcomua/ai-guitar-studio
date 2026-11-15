(function () {
    'use strict';

    window.AIGSStemSeparator = {
        ready: false,
        model: null,

        init: async function () {
            this.ready = false;

            return {
                ready: false,
                message:
                    'Модель AI-розділення вокалу та гітари не підключена.'
            };
        },

        separate: async function (audioBuffer) {
            if (!audioBuffer) {
                throw new Error('Спочатку завантаж композицію.');
            }

            if (!this.ready || !this.model) {
                throw new Error(
                    'Розділення вокалу та гітари недоступне: ' +
                    'AI-модель ще не встановлена.'
                );
            }

            return await this.model.separate(audioBuffer);
        },

        setTrackEnabled: function (trackName, enabled) {
            if (!this.ready || !this.model) {
                throw new Error(
                    'Немає моделі для керування окремими доріжками.'
                );
            }

            if (
                trackName !== 'vocals' &&
                trackName !== 'guitar'
            ) {
                throw new Error('Невідома аудіодоріжка.');
            }

            return this.model.setTrackEnabled(
                trackName,
                Boolean(enabled)
            );
        }
    };
})();
