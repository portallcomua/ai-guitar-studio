(function () {
    'use strict';

    window.AIGSChordAnalyzer = {
        noteNames: [
            'До', 'До♯', 'Ре', 'Ре♯', 'Мі', 'Фа',
            'Фа♯', 'Соль', 'Соль♯', 'Ля', 'Ля♯', 'Сі'
        ],

        chordNames: [
            'C', 'C#', 'D', 'D#', 'E', 'F',
            'F#', 'G', 'G#', 'A', 'A#', 'B'
        ],

        frequencies: function () {
            var result = [];

            for (var note = 0; note < 12; note++) {
                result.push(
                    440 * Math.pow(2, (note - 9) / 12)
                );
            }

            return result;
        },

        analyze: async function (audioBuffer) {
            if (!audioBuffer) {
                throw new Error('Немає аудіо для аналізу.');
            }

            var sampleRate = audioBuffer.sampleRate;
            var channel = audioBuffer.getChannelData(0);
            var duration = audioBuffer.duration;

            var frameSize = 16384;
            var hopSize = 8192;
            var frequencies = this.frequencies();
            var energy = new Array(12).fill(0);
            var frames = 0;

            function goertzel(data, start, length, frequency) {
                var omega = 2 * Math.PI * frequency / sampleRate;
                var coefficient = 2 * Math.cos(omega);
                var q0 = 0;
                var q1 = 0;
                var q2 = 0;

                for (var i = 0; i < length; i++) {
                    var sample = data[start + i] || 0;

                    q0 = coefficient * q1 - q2 + sample;
                    q2 = q1;
                    q1 = q0;
                }

                return Math.max(
                    0,
                    q1 * q1 + q2 * q2 - coefficient * q1 * q2
                );
            }

            var maxFrames = Math.min(
                120,
                Math.floor((channel.length - frameSize) / hopSize)
            );

            if (maxFrames < 1) {
                throw new Error('Композиція надто коротка для аналізу.');
            }

            var stride = Math.max(
                1,
                Math.floor(maxFrames / 60)
            );

            for (
                var start = 0;
                start + frameSize < channel.length;
                start += hopSize * stride
            ) {
                for (var note = 0; note < 12; note++) {
                    var baseFrequency = frequencies[note];
                    var value = 0;

                    for (var harmonic = 1; harmonic <= 4; harmonic++) {
                        var frequency = baseFrequency * harmonic;

                        if (frequency < sampleRate / 2) {
                            value += goertzel(
                                channel,
                                start,
                                frameSize,
                                frequency
                            ) / harmonic;
                        }
                    }

                    energy[note] += value;
                }

                frames++;

                if (frames >= 60) {
                    break;
                }
            }

            var total = energy.reduce(function (a, b) {
                return a + b;
            }, 0);

            if (!total) {
                throw new Error('Не вдалося визначити спектр композиції.');
            }

            var sorted = energy
                .map(function (value, index) {
                    return {
                        index: index,
                        value: value
                    };
                })
                .sort(function (a, b) {
                    return b.value - a.value;
                });

            var root = sorted[0].index;

            var majorThird = (root + 4) % 12;
            var minorThird = (root + 3) % 12;
            var fifth = (root + 7) % 12;

            var majorScore =
                energy[root] +
                energy[majorThird] +
                energy[fifth];

            var minorScore =
                energy[root] +
                energy[minorThird] +
                energy[fifth];

            var chordType = minorScore > majorScore
                ? 'm'
                : '';

            var chord = this.chordNames[root] + chordType;

            var keyName = this.noteNames[root] +
                (chordType ? ' мінор' : ' мажор');

            var bpm = this.estimateTempo(
                channel,
                sampleRate
            );

            return {
                key: keyName,
                root: root,
                chord: chord,
                chords: [{
                    name: chord,
                    time: 0
                }],
                bpm: bpm,
                duration: duration,
                confidence: 'Низька / попередня оцінка',
                noteEnergy: energy
            };
        },

        estimateTempo: function (data, sampleRate) {
            /*
             * Оцінка темпу через автокореляцію
             * амплітудної огинаючої.
             * Результат орієнтовний.
             */

            var windowSize = 1024;
            var envelope = [];

            for (
                var i = 0;
                i + windowSize < data.length;
                i += windowSize
            ) {
                var sum = 0;

                for (var j = 0; j < windowSize; j++) {
                    sum += Math.abs(data[i + j]);
                }

                envelope.push(sum / windowSize);
            }

            if (envelope.length < 20) {
                return null;
            }

            var rate = sampleRate / windowSize;
            var minLag = Math.floor(rate * 60 / 180);
            var maxLag = Math.floor(rate * 60 / 60);

            var bestLag = 0;
            var bestScore = -Infinity;

            for (
                var lag = minLag;
                lag <= maxLag && lag < envelope.length / 2;
                lag++
            ) {
                var score = 0;

                for (
                    var k = 0;
                    k < envelope.length - lag;
                    k++
                ) {
                    score += envelope[k] * envelope[k + lag];
                }

                if (score > bestScore) {
                    bestScore = score;
                    bestLag = lag;
                }
            }

            if (!bestLag) {
                return null;
            }

            var bpm = Math.round(60 * rate / bestLag);

            while (bpm < 70) {
                bpm *= 2;
            }

            while (bpm > 180) {
                bpm /= 2;
            }

            return Math.round(bpm);
        }
    };
})();
