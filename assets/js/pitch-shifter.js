(function () {
    'use strict';

    window.AIGSPitchShifter = {
        ready: false,
        processor: null,

        init: async function () {
            /*
             * Тут підключатиметься окремий алгоритм
             * pitch-shifting із збереженням темпу.
             *
             * Звичайний playbackRate не підходить:
             * він одночасно змінює висоту тону і швидкість.
             */

            this.ready = false;

            return {
                ready: false,
                message:
                    'Алгоритм транспонування без зміни темпу ' +
                    'ще не підключений.'
            };
        },

        process: async function (audioBuffer, semitones) {
            if (!audioBuffer) {
                throw new Error('Немає вихідної композиції.');
            }

            semitones = Number(semitones);

            if (!Number.isInteger(semitones) ||
                semitones < -12 ||
                semitones > 12) {
                throw new Error('Допустимий діапазон: від -12 до +12.');
            }

            if (semitones === 0) {
                return audioBuffer;
            }

            if (!this.ready || !this.processor) {
                throw new Error(
                    'Транспонування без зміни темпу поки недоступне. ' +
                    'Не змінюю швидкість аудіо замість тональності.'
                );
            }

            return await this.processor.process(
                audioBuffer,
                semitones
            );
        }
    };
})();
