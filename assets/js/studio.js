(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var app = document.getElementById('aigs-app');

        if (!app) {
            return;
        }

        var fileInput = document.getElementById('aigs-audio-file');
        var urlInput = document.getElementById('aigs-audio-url');
        var loadUrlButton = document.getElementById('aigs-load-url');
        var fileInfo = document.getElementById('aigs-file-info');
        var analyzeButton = document.getElementById('aigs-analyze');

        var keyOutput = document.getElementById('aigs-key');
        var bpmOutput = document.getElementById('aigs-bpm');
        var durationOutput = document.getElementById('aigs-duration');

        var transpose = document.getElementById('aigs-transpose');
        var transposeValue = document.getElementById('aigs-transpose-value');

        var vocalsToggle = document.getElementById('aigs-vocals-toggle');
        var guitarToggle = document.getElementById('aigs-guitar-toggle');

        var subtitlesToggle = document.getElementById('aigs-subtitles-toggle');
        var translationToggle = document.getElementById('aigs-translation-toggle');

        var originalLyrics = document.getElementById('aigs-lyrics-original');
        var translatedLyrics = document.getElementById('aigs-lyrics-translation');

        var saveButton = document.getElementById('aigs-save-song');
        var songTitle = document.getElementById('aigs-song-title');
        var songNotes = document.getElementById('aigs-song-notes');

        var collectionSearch = document.getElementById('aigs-collection-search');
        var collectionSort = document.getElementById('aigs-collection-sort');
        var collectionList = document.getElementById('aigs-collection-list');

        var analysisStatus = document.getElementById('aigs-analysis-status');
        var processingStatus = document.getElementById('aigs-processing-status');
        var lyricsStatus = document.getElementById('aigs-lyrics-status');
        var saveStatus = document.getElementById('aigs-save-status');

        var currentAnalysis = null;
        var currentFileInfo = null;

        function setStatus(element, message, type) {
            if (!element) {
                return;
            }

            element.textContent = message || '';
            element.classList.remove('is-error', 'is-success');

            if (type === 'error') {
                element.classList.add('is-error');
            }

            if (type === 'success') {
                element.classList.add('is-success');
            }
        }

        function formatBytes(bytes) {
            if (!bytes) {
                return '0 Б';

            }

            var units = ['Б', 'КБ', 'МБ', 'ГБ'];
            var index = Math.floor(
                Math.log(bytes) / Math.log(1024)
            );

            index = Math.min(index, units.length - 1);

            return (
                (bytes / Math.pow(1024, index)).toFixed(2) +
                ' ' +
                units[index]
            );
        }

        function enableAfterLoad(info) {
            currentFileInfo = info;

            fileInfo.textContent =
                'Файл: ' + info.name +
                ' · Тривалість: ' +
                AIGSAudio.getDurationText(info.duration) +
                ' · Розмір: ' +
                formatBytes(info.size);

            durationOutput.textContent =
                AIGSAudio.getDurationText(info.duration);

            analyzeButton.disabled = false;
            saveButton.disabled = false;

            songTitle.value = info.name.replace(/\.[^.]+$/, '');

            keyOutput.textContent = '—';
            bpmOutput.textContent = '—';

            currentAnalysis = null;

            setStatus(
                analysisStatus,
                'Файл завантажено. Можна запускати аналіз.',
                'success'
            );

            setStatus(
                processingStatus,
                'Оригінал доступний. AI-розділення та транспонування ще не підключені.'
            );

            transpose.disabled = true;
            vocalsToggle.disabled = true;
            guitarToggle.disabled = true;
        }

        async function loadFile(file) {
            try {
                fileInfo.textContent = 'Завантаження та аналіз аудіофайлу...';

                var info = await AIGSAudio.loadFile(file);

                enableAfterLoad(info);
            } catch (error) {
                fileInfo.textContent = error.message;

                setStatus(
                    analysisStatus,
                    error.message,
                    'error'
                );
            }
        }

        fileInput.addEventListener('change', function () {
            if (fileInput.files && fileInput.files[0]) {
                loadFile(fileInput.files[0]);
            }
        });

        loadUrlButton.addEventListener('click', async function () {
            var url = urlInput.value.trim();

            if (!url) {
                setStatus(
                    analysisStatus,
                    'Встав пряме посилання на аудіофайл.',
                    'error'
                );
                return;
            }

            loadUrlButton.disabled = true;
            loadUrlButton.textContent = 'Завантаження...';

            try {
                var info = await AIGSAudio.loadUrl(url);
                enableAfterLoad(info);
            } catch (error) {
                setStatus(
                    analysisStatus,
                    error.message,
                    'error'
                );

                fileInfo.textContent = error.message;
            } finally {
                loadUrlButton.disabled = false;
                loadUrlButton.textContent = 'Завантажити';
            }
        });

        analyzeButton.addEventListener('click', async function () {
            var buffer = AIGSAudio.getOriginalBuffer();

            if (!buffer) {
                setStatus(
                    analysisStatus,
                    'Спочатку завантаж композицію.',
                    'error'
                );
                return;
            }

            analyzeButton.disabled = true;
            analyzeButton.textContent = 'Аналіз...';

            setStatus(
                analysisStatus,
                'Виконується попередній аналіз спектра...'
            );

            try {
                currentAnalysis = await AIGSChordAnalyzer.analyze(buffer);

                keyOutput.textContent = currentAnalysis.key;
                bpmOutput.textContent = currentAnalysis.bpm
                    ? currentAnalysis.bpm + ' BPM'
                    : 'Не визначено';

                renderChords(currentAnalysis.chords);

                setStatus(
                    analysisStatus,
                    'Попередній аналіз завершено. Точність визначення акордів не гарантована.',
                    'success'
                );
            } catch (error) {
                setStatus(
                    analysisStatus,
                    error.message,
                    'error'
                );
            } finally {
                analyzeButton.disabled = false;
                analyzeButton.textContent = 'Проаналізувати композицію';
            }
        });

        function renderChords(chords) {
            var container = document.getElementById(
                'aigs-chord-timeline'
            );

            var diagram = document.getElementById(
                'aigs-chord-diagram'
            );

            container.textContent = '';

            if (!chords || !chords.length) {
                container.textContent = 'Акорди не визначені.';
                return;
            }

            chords.forEach(function (chord) {
                var button = document.createElement('button');

                button.type = 'button';
                button.className = 'aigs-chord-item';
                button.textContent = chord.name;

                button.addEventListener('click', function () {
                    diagram.textContent =
                        'Визначений акорд: ' + chord.name +
                        '. Схема аплікатури для цього акорду ще не підключена.';
                });

                container.appendChild(button);
            });
        }

        transpose.addEventListener('input', function () {
            transposeValue.textContent = transpose.value;
        });

        vocalsToggle.addEventListener('change', function () {
            vocalsToggle.checked = false;

            setStatus(
                processingStatus,
                'AI-модель вокалу не підключена. Доріжка не змінена.',
                'error'
            );
        });

        guitarToggle.addEventListener('change', function () {
            guitarToggle.checked = false;

            setStatus(
                processingStatus,
                'AI-модель гітари не підключена. Доріжка не змінена.',
                'error'
            );
        });

        subtitlesToggle.addEventListener('change', function () {
            originalLyrics.classList.toggle(
                'is-hidden',
                !subtitlesToggle.checked
            );
        });

        translationToggle.addEventListener('change', function () {
            translatedLyrics.classList.toggle(
                'is-hidden',
                !translationToggle.checked
            );
        });

        saveButton.addEventListener('click', async function () {
            if (!AIGS_CONFIG.loggedIn) {
                setStatus(
                    saveStatus,
                    AIGS_CONFIG.messages.loginRequired,
                    'error'
                );
                return;
            }

            if (!currentFileInfo) {
                setStatus(
                    saveStatus,
                    'Спочатку завантаж композицію.',
                    'error'
                );
                return;
            }

            var title = songTitle.value.trim();

            if (!title) {
                setStatus(
                    saveStatus,
                    'Вкажи назву композиції.',
                    'error'
                );
                return;
            }

            saveButton.disabled = true;
            saveButton.textContent = 'Збереження...';

            try {
                var song = await AIGSCollection.save({
                    title: title,
                    notes: songNotes.value,
                    key: currentAnalysis ? currentAnalysis.key : '',
                    transpose: 0,
                    lyrics: '',
                    translation: '',
                    chords: currentAnalysis
                        ? currentAnalysis.chords.map(function (chord) {
                            return chord.name;
                        }).join(', ')
                        : ''
                });

                setStatus(
                    saveStatus,
                    AIGS_CONFIG.messages.saved,
                    'success'
                );

                renderCollection();
            } catch (error) {
                setStatus(
                    saveStatus,
                    error.message,
                    'error'
                );
            } finally {
                saveButton.disabled = false;
                saveButton.textContent = 'Зберегти в колекцію';
            }
        });

        function createSongCard(song) {
            var card = document.createElement('div');
            card.className = 'aigs-song-card';

            var title = document.createElement('h3');
            title.textContent = song.title || 'Без назви';

            var key = document.createElement('p');
            key.textContent = 'Тональність: ' + (song.key || 'Не визначена');

            var notes = document.createElement('p');
            notes.textContent = 'Нотатки: ' + (song.notes || 'Немає');

            var date = document.createElement('p');
            date.textContent = song.created
                ? 'Додано: ' + new Date(
                    song.created * 1000
                ).toLocaleString('uk-UA')
                : '';

            var actions = document.createElement('div');
            actions.className = 'aigs-song-actions';

            var editButton = document.createElement('button');
            editButton.type = 'button';
            editButton.textContent = 'Редагувати нотатки';

            editButton.addEventListener('click', async function () {
                var newNotes = window.prompt(
                    'Введи нотатки до композиції:',
                    song.notes || ''
                );

                if (newNotes === null) {
                    return;
                }

                try {
                    await AIGSCollection.updateNotes(
                        song.id,
                        newNotes
                    );

                    renderCollection();

                    setStatus(
                        saveStatus,
                        'Нотатки оновлено.',
                        'success'
                    );
                } catch (error) {
                    setStatus(
                        saveStatus,
                        error.message,
                        'error'
                    );
                }
            });

            var deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'aigs-delete-button';
            deleteButton.textContent = 'Видалити';

            deleteButton.addEventListener('click', async function () {
                if (!window.confirm('Видалити цю композицію з колекції?')) {
                    return;
                }

                try {
                    await AIGSCollection.remove(song.id);
                    renderCollection();

                    setStatus(
                        saveStatus,
                        AIGS_CONFIG.messages.deleted,
                        'success'
                    );
                } catch (error) {
                    setStatus(
                        saveStatus,
                        error.message,
                        'error'
                    );
                }
            });

            actions.appendChild(editButton);
            actions.appendChild(deleteButton);

            card.appendChild(title);
            card.appendChild(key);
            card.appendChild(notes);
            card.appendChild(date);
            card.appendChild(actions);

            return card;
        }

        function renderCollection() {
            var query = collectionSearch.value || '';
            var sort = collectionSort.value || 'newest';

            var items = AIGSCollection.search(query);
            items = AIGSCollection.sort(items, sort);

            collectionList.textContent = '';

            if (!items.length) {
                var empty = document.createElement('p');
                empty.textContent = 'Композицій не знайдено.';
                collectionList.appendChild(empty);
                return;
            }

            items.forEach(function (song) {
                collectionList.appendChild(
                    createSongCard(song)
                );
            });
        }

        collectionSearch.addEventListener(
            'input',
            renderCollection
        );

        collectionSort.addEventListener(
            'change',
            renderCollection
        );

        async function initializeCollection() {
            if (!AIGS_CONFIG.loggedIn) {
                collectionList.textContent =
                    'Увійди на сайт, щоб користуватися особистою колекцією.';
                saveButton.disabled = true;
                return;
            }

            try {
                await AIGSCollection.load();
                renderCollection();
            } catch (error) {
                collectionList.textContent = error.message;
            }
        }

        initializeCollection();

        /*
         * AI-функції не активуються без підключених моделей.
         * Це запобігає показу фальшивих результатів.
         */

        AIGSStemSeparator.init().then(function (result) {
            setStatus(processingStatus, result.message);
        });

        AIGSLyrics.init().then(function (result) {
            setStatus(lyricsStatus, result.message);
        });

        AIGSTranslation.init();

        AIGSPitchShifter.init();

    });
})();
