(function () {
    'use strict';

    window.AIGSCollection = {
        items: [],

        request: async function (action, data) {
            if (!window.AIGS_CONFIG) {
                throw new Error('Налаштування плагіна не знайдені.');
            }

            var formData = new FormData();

            formData.append('action', action);
            formData.append('nonce', AIGS_CONFIG.nonce);

            Object.keys(data || {}).forEach(function (key) {
                formData.append(key, data[key]);
            });

            var response = await fetch(AIGS_CONFIG.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });

            var result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.data && result.data.message
                        ? result.data.message
                        : 'Помилка роботи з колекцією.'
                );
            }

            return result.data;
        },

        load: async function () {
            if (!AIGS_CONFIG.loggedIn) {
                this.items = [];
                return [];
            }

            var result = await this.request(
                'aigs_get_collection'
            );

            this.items = result.collection || [];

            return this.items;
        },

        save: async function (song) {
            if (!AIGS_CONFIG.loggedIn) {
                throw new Error(
                    'Увійди на сайт, щоб зберігати композиції.'
                );
            }

            var result = await this.request(
                'aigs_save_song',
                {
                    title: song.title || '',
                    notes: song.notes || '',
                    key: song.key || '',
                    transpose: song.transpose || 0,
                    lyrics: song.lyrics || '',
                    translation: song.translation || '',
                    chords: song.chords || ''
                }
            );

            this.items.push(result.song);

            return result.song;
        },

        remove: async function (songId) {
            await this.request(
                'aigs_delete_song',
                {
                    song_id: songId
                }
            );

            this.items = this.items.filter(function (song) {
                return song.id !== songId;
            });

            return true;
        },

        updateNotes: async function (songId, notes) {
            await this.request(
                'aigs_update_notes',
                {
                    song_id: songId,
                    notes: notes
                }
            );

            var item = this.items.find(function (song) {
                return song.id === songId;
            });

            if (item) {
                item.notes = notes;
            }

            return true;
        },

        search: function (query) {
            query = String(query || '').toLowerCase().trim();

            if (!query) {
                return this.items.slice();
            }

            return this.items.filter(function (song) {
                return (
                    String(song.title || '').toLowerCase().includes(query) ||
                    String(song.notes || '').toLowerCase().includes(query) ||
                    String(song.key || '').toLowerCase().includes(query)
                );
            });
        },

        sort: function (items, mode) {
            var result = items.slice();

            if (mode === 'title') {
                result.sort(function (a, b) {
                    return String(a.title || '').localeCompare(
                        String(b.title || ''),
                        'uk'
                    );
                });
            } else if (mode === 'oldest') {
                result.sort(function (a, b) {
                    return Number(a.created || 0) -
                        Number(b.created || 0);
                });
            } else {
                result.sort(function (a, b) {
                    return Number(b.created || 0) -
                        Number(a.created || 0);
                });
            }

            return result;
        }
    };
})();
