(function () {
    'use strict';

    window.AIGSAudio = {
        context: null,
        originalBuffer: null,
        originalUrl: null,
        fileName: '',
        file: null,

        getContext: function () {
            if (!this.context) {
                var AudioContextClass =
                    window.AudioContext || window.webkitAudioContext;

                if (!AudioContextClass) {
                    throw new Error('Браузер не підтримує Web Audio API.');
                }

                this.context = new AudioContextClass();
            }

            return this.context;
        },

        decode: async function (arrayBuffer) {
            var context = this.getContext();

            if (context.state === 'suspended') {
                await context.resume();
            }

            return await context.decodeAudioData(arrayBuffer.slice(0));
        },

        loadFile: async function (file) {
            if (!file || !file.type || !file.type.startsWith('audio/')) {
                throw new Error('Вибраний файл не схожий на аудіофайл.');
            }

            var maxSize = 200 * 1024 * 1024;

            if (file.size > maxSize) {
                throw new Error('Максимальний розмір файлу — 200 МБ.');
            }

            var arrayBuffer = await file.arrayBuffer();
            var decoded = await this.decode(arrayBuffer);

            this.releaseUrl();

            this.originalBuffer = decoded;
            this.file = file;
            this.fileName = file.name || 'audio';
            this.originalUrl = URL.createObjectURL(file);

            var player = document.getElementById('aigs-original-audio');

            if (player) {
                player.src = this.originalUrl;
                player.load();
            }

            return {
                name: this.fileName,
                duration: decoded.duration,
                sampleRate: decoded.sampleRate,
                channels: decoded.numberOfChannels,
                size: file.size
            };
        },

        loadUrl: async function (url) {
            url = String(url || '').trim();

            if (!/^https?:\/\//i.test(url)) {
                throw new Error('Вкажи правильне HTTP або HTTPS посилання.');
            }

            var response = await fetch(url, {
                method: 'GET',
                mode: 'cors'
            });

            if (!response.ok) {
                throw new Error(
                    'Сервер не дозволив завантажити файл. HTTP ' +
                    response.status
                );
            }

            var blob = await response.blob();

            if (!blob.type.startsWith('audio/')) {
                throw new Error(
                    'За посиланням не знайдено аудіофайл. ' +
                    'Перевір, чи це пряме посилання на MP3, WAV або інший аудіофайл.'
                );
            }

            var extension = blob.type.split('/')[1] || 'audio';

            var file = new File(
                [blob],
                'audio-from-url.' + extension,
                { type: blob.type }
            );

            return await this.loadFile(file);
        },

        releaseUrl: function () {
            if (this.originalUrl) {
                URL.revokeObjectURL(this.originalUrl);
                this.originalUrl = null;
            }
        },

        getDurationText: function (seconds) {
            if (!Number.isFinite(seconds)) {
                return '—';
            }

            var minutes = Math.floor(seconds / 60);
            var remaining = Math.floor(seconds % 60);

            return (
                String(minutes).padStart(2, '0') +
                ':' +
                String(remaining).padStart(2, '0')
            );
        },

        getOriginalBuffer: function () {
            return this.originalBuffer;
        },

        getFileName: function () {
            return this.fileName;
        },

        getOriginalBlob: function () {
            return this.file;
        },

        downloadOriginal: function () {
            if (!this.file) {
                throw new Error('Спочатку завантаж композицію.');
            }

            var url = URL.createObjectURL(this.file);
            var link = document.createElement('a');

            link.href = url;
            link.download = this.fileName || 'original-audio';

            document.body.appendChild(link);
            link.click();
            link.remove();

            setTimeout(function () {
                URL.revokeObjectURL(url);
            }, 2000);
        },

        exportWav: function (audioBuffer, filename) {
            if (!audioBuffer) {
                throw new Error('Немає аудіо для експорту.');
            }

            var channels = audioBuffer.numberOfChannels;
            var sampleRate = audioBuffer.sampleRate;
            var frames = audioBuffer.length;
            var bytesPerSample = 2;
            var blockAlign = channels * bytesPerSample;
            var dataSize = frames * blockAlign;

            var buffer = new ArrayBuffer(44 + dataSize);
            var view = new DataView(buffer);

            function writeString(offset, string) {
                for (var i = 0; i < string.length; i++) {
                    view.setUint8(offset + i, string.charCodeAt(i));
                }
            }

            writeString(0, 'RIFF');
            view.setUint32(4, 36 + dataSize, true);
            writeString(8, 'WAVE');
            writeString(12, 'fmt ');
            view.setUint32(16, 16, true);
            view.setUint16(20, 1, true);
            view.setUint16(22, channels, true);
            view.setUint32(24, sampleRate, true);
            view.setUint32(28, sampleRate * blockAlign, true);
            view.setUint16(32, blockAlign, true);
            view.setUint16(34, 16, true);
            writeString(36, 'data');
            view.setUint32(40, dataSize, true);

            var channelData = [];

            for (var channel = 0; channel < channels; channel++) {
                channelData.push(audioBuffer.getChannelData(channel));
            }

            var offset = 44;

            for (var frame = 0; frame < frames; frame++) {
                for (var ch = 0; ch < channels; ch++) {
                    var sample = channelData[ch][frame];

                    sample = Math.max(-1, Math.min(1, sample));

                    var intSample = sample < 0
                        ? sample * 32768
                        : sample * 32767;

                    view.setInt16(offset, intSample, true);
                    offset += 2;
                }
            }

            var blob = new Blob(
                [buffer],
                { type: 'audio/wav' }
            );

            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');

            link.href = url;
            link.download = filename || 'processed-audio.wav';

            document.body.appendChild(link);
            link.click();
            link.remove();

            setTimeout(function () {
                URL.revokeObjectURL(url);
            }, 3000);

            return blob;
        },

        cleanup: function () {
            this.releaseUrl();
            this.originalBuffer = null;
            this.file = null;
            this.fileName = '';
        }
    };
})();
