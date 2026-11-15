
<?php
/**
 * Plugin Name: AI Guitar Studio
 * Plugin URI: https://uaserver.pp.ua
 * Description: Студія для аналізу музики, роботи з акордами, транспонування та особистої колекції композицій.
 * Version: 3.0.0
 * Author: UA Server
 * Text Domain: ai-guitar-studio
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIGS_VERSION', '3.0.0');
define('AIGS_PATH', plugin_dir_path(__FILE__));
define('AIGS_URL', plugin_dir_url(__FILE__));

/**
 * Підключення стилів і скриптів.
 */
function aigs_enqueue_assets() {
    if (!is_singular()) {
        return;
    }

    global $post;

    if (
        !$post ||
        !has_shortcode($post->post_content, 'ai_guitar_studio')
    ) {
        return;
    }

    wp_enqueue_style(
        'aigs-studio',
        AIGS_URL . 'assets/css/studio.css',
        array(),
        AIGS_VERSION
    );

    $scripts = array(
        'aigs-audio-engine' => 'audio-engine.js',
        'aigs-pitch-shifter' => 'pitch-shifter.js',
        'aigs-chord-analyzer' => 'chord-analyzer.js',
        'aigs-stem-separator' => 'stem-separator.js',
        'aigs-lyrics-recognition' => 'lyrics-recognition.js',
        'aigs-translation' => 'translation.js',
        'aigs-collection' => 'collection.js',
        'aigs-studio' => 'studio.js',
    );

    $dependencies = array();

    foreach ($scripts as $handle => $filename) {
        wp_enqueue_script(
            $handle,
            AIGS_URL . 'assets/js/' . $filename,
            $dependencies,
            AIGS_VERSION,
            true
        );

        $dependencies[] = $handle;
    }

    wp_localize_script(
        'aigs-studio',
        'AIGS_CONFIG',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aigs_nonce'),
            'loggedIn' => is_user_logged_in(),
            'version' => AIGS_VERSION,
            'messages' => array(
                'loginRequired' => 'Увійдіть на сайт, щоб зберігати композиції.',
                'saveError' => 'Не вдалося зберегти композицію.',
                'deleteError' => 'Не вдалося видалити композицію.',
                'saved' => 'Композицію збережено.',
                'deleted' => 'Композицію видалено.',
            ),
        )
    );
}
add_action('wp_enqueue_scripts', 'aigs_enqueue_assets');


/**
 * Основний інтерфейс студії.
 */
function aigs_studio_shortcode() {
    ob_start();
    ?>
    <div id="aigs-app" class="aigs-app">

        <header class="aigs-header">
            <div class="aigs-brand">
                <div class="aigs-logo">🎸</div>
                <div>
                    <h1>AI Guitar Studio</h1>
                    <p>Музика. Акорди. Твоя гітара.</p>
                </div>
            </div>
            <span class="aigs-version">3.0.0</span>
        </header>

        <main class="aigs-main">

            <section class="aigs-panel aigs-upload-panel">
                <h2>1. Завантаж композицію</h2>

                <label for="aigs-audio-file" class="aigs-file-label">
                    <span class="aigs-upload-icon">🎵</span>
                    <strong>Вибрати аудіофайл</strong>
                    <small>MP3, WAV, OGG, M4A — залежно від підтримки браузера</small>
                </label>

                <input
                    type="file"
                    id="aigs-audio-file"
                    accept="audio/*"
                >

                <div class="aigs-url-row">
                    <input
                        type="url"
                        id="aigs-audio-url"
                        placeholder="Або встав пряме посилання на аудіофайл"
                    >
                    <button type="button" id="aigs-load-url">
                        Завантажити
                    </button>
                </div>

                <div id="aigs-file-info" class="aigs-file-info">
                    Композицію ще не вибрано.
                </div>
            </section>


            <section class="aigs-panel">
                <h2>2. Прослуховування</h2>

                <audio
                    id="aigs-original-audio"
                    controls
                    preload="metadata"
                ></audio>

                <div class="aigs-player-status" id="aigs-player-status">
                    Очікування аудіофайлу.
                </div>
            </section>


            <section class="aigs-panel">
                <h2>3. Аналіз композиції</h2>

                <div class="aigs-analysis-grid">

                    <div class="aigs-info-card">
                        <span>Тональність</span>
                        <strong id="aigs-key">—</strong>
                    </div>

                    <div class="aigs-info-card">
                        <span>Темп</span>
                        <strong id="aigs-bpm">—</strong>
                    </div>

                    <div class="aigs-info-card">
                        <span>Тривалість</span>
                        <strong id="aigs-duration">—</strong>
                    </div>

                </div>

                <button
                    type="button"
                    id="aigs-analyze"
                    class="aigs-primary-button"
                    disabled
                >
                    Проаналізувати композицію
                </button>

                <div id="aigs-analysis-status" class="aigs-status"></div>
            </section>


            <section class="aigs-panel">
                <h2>4. Акорди та гітара</h2>

                <div id="aigs-chord-timeline" class="aigs-chord-timeline">
                    Тут з'явиться послідовність акордів.
                </div>

                <div id="aigs-chord-diagram" class="aigs-chord-diagram">
                    Вибери акорд, щоб побачити схему його постановки.
                </div>
            </section>


            <section class="aigs-panel">
                <h2>5. Обробка композиції</h2>

                <div class="aigs-control-group">
                    <label for="aigs-transpose">
                        Транспонування:
                        <strong id="aigs-transpose-value">0</strong>
                        півтонів
                    </label>

                    <input
                        type="range"
                        id="aigs-transpose"
                        min="-12"
                        max="12"
                        step="1"
                        value="0"
                        disabled
                    >

                    <div class="aigs-range-labels">
                        <span>-12</span>
                        <span>Оригінал</span>
                        <span>+12</span>
                    </div>
                </div>

                <div class="aigs-toggle-list">

                    <label class="aigs-toggle">
                        <input
                            type="checkbox"
                            id="aigs-vocals-toggle"
                            disabled
                        >
                        <span>Вокал</span>
                        <small>Увімкнути / вимкнути вокальну доріжку</small>
                    </label>

                    <label class="aigs-toggle">
                        <input
                            type="checkbox"
                            id="aigs-guitar-toggle"
                            disabled
                        >
                        <span>Гітара</span>
                        <small>Увімкнути / вимкнути гітарну доріжку</small>
                    </label>

                </div>

                <div id="aigs-processing-status" class="aigs-status">
                    Обробка стане доступною після завантаження композиції.
                </div>

                <div class="aigs-processed-player">
                    <h3>Оброблена композиція</h3>

                    <audio
                        id="aigs-processed-audio"
                        controls
                        preload="metadata"
                    ></audio>

                    <button
                        type="button"
                        id="aigs-download-processed"
                        disabled
                    >
                        Завантажити оброблену композицію
                    </button>
                </div>
            </section>


            <section class="aigs-panel">
                <h2>6. Текст пісні та переклад</h2>

                <div class="aigs-lyrics-controls">
                    <label>
                        <input
                            type="checkbox"
                            id="aigs-subtitles-toggle"
                            checked
                        >
                        Показувати оригінальний текст
                    </label>

                    <label>
                        <input
                            type="checkbox"
                            id="aigs-translation-toggle"
                        >
                        Показувати український переклад
                    </label>
                </div>

                <div id="aigs-lyrics-original" class="aigs-lyrics">
                    Тут з'явиться розпізнаний текст пісні.
                </div>

                <div id="aigs-lyrics-translation" class="aigs-lyrics aigs-translation">
                    Тут з'явиться український переклад.
                </div>

                <div id="aigs-lyrics-status" class="aigs-status"></div>
            </section>


            <section class="aigs-panel aigs-collection-panel">
                <h2>7. Моя колекція</h2>

                <p>
                    Зберігай композицію тільки після завершення обробки.
                    У колекції можна буде шукати, сортувати та редагувати нотатки.
                </p>

                <div class="aigs-save-form">

                    <label for="aigs-song-title">Назва композиції</label>
                    <input
                        type="text"
                        id="aigs-song-title"
                        placeholder="Назва пісні або виконавець"
                    >

                    <label for="aigs-song-notes">Мої нотатки</label>
                    <textarea
                        id="aigs-song-notes"
                        rows="3"
                        placeholder="Наприклад: ля мінор, без вокалу і гітари..."
                    ></textarea>

                    <button
                        type="button"
                        id="aigs-save-song"
                        class="aigs-primary-button"
                        disabled
                    >
                        Зберегти в колекцію
                    </button>

                    <div id="aigs-save-status" class="aigs-status"></div>
                </div>

                <div class="aigs-collection-tools">
                    <input
                        type="search"
                        id="aigs-collection-search"
                        placeholder="Пошук композицій..."
                    >

                    <select id="aigs-collection-sort">
                        <option value="newest">Спочатку нові</option>
                        <option value="oldest">Спочатку старі</option>
                        <option value="title">За назвою</option>
                    </select>
                </div>

                <div id="aigs-collection-list">
                    <p>Твоя колекція поки порожня.</p>
                </div>

            </section>

        </main>

        <footer class="aigs-footer">
            AI Guitar Studio 3.0.0 · UA Server
        </footer>

    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('ai_guitar_studio', 'aigs_studio_shortcode');


/**
 * Перевірка запитів AJAX.
 */
function aigs_check_ajax_access() {
    check_ajax_referer('aigs_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(
            array('message' => 'Для цієї дії потрібно увійти на сайт.'),
            401
        );
    }
}


/**
 * Отримання колекції поточного користувача.
 */
function aigs_ajax_get_collection() {
    aigs_check_ajax_access();

    $collection = get_user_meta(
        get_current_user_id(),
        'aigs_collection',
        true
    );

    if (!is_array($collection)) {
        $collection = array();
    }

    wp_send_json_success(array(
        'collection' => array_values($collection),
    ));
}
add_action('wp_ajax_aigs_get_collection', 'aigs_ajax_get_collection');


/**
 * Збереження композиції в особисту колекцію.
 */
function aigs_ajax_save_song() {
    aigs_check_ajax_access();

    $title = isset($_POST['title'])
        ? sanitize_text_field(wp_unslash($_POST['title']))
        : '';

    $notes = isset($_POST['notes'])
        ? sanitize_textarea_field(wp_unslash($_POST['notes']))
        : '';

    $key = isset($_POST['key'])
        ? sanitize_text_field(wp_unslash($_POST['key']))
        : '';

    $transpose = isset($_POST['transpose'])
        ? intval($_POST['transpose'])
        : 0;

    $lyrics = isset($_POST['lyrics'])
        ? sanitize_textarea_field(wp_unslash($_POST['lyrics']))
        : '';

    $translation = isset($_POST['translation'])
        ? sanitize_textarea_field(wp_unslash($_POST['translation']))
        : '';

    $chords = isset($_POST['chords'])
        ? sanitize_textarea_field(wp_unslash($_POST['chords']))
        : '';

    if ($title === '') {
        wp_send_json_error(array(
            'message' => 'Вкажи назву композиції.',
        ), 400);
    }

    $user_id = get_current_user_id();

    $collection = get_user_meta(
        $user_id,
        'aigs_collection',
        true
    );

    if (!is_array($collection)) {
        $collection = array();
    }

    $song_id = wp_generate_uuid4();

    $song = array(
        'id' => $song_id,
        'title' => $title,
        'notes' => $notes,
        'key' => $key,
        'transpose' => max(-12, min(12, $transpose)),
        'lyrics' => $lyrics,
        'translation' => $translation,
        'chords' => $chords,
        'created' => current_time('timestamp'),
    );

    $collection[] = $song;

    update_user_meta(
        $user_id,
        'aigs_collection',
        $collection
    );

    wp_send_json_success(array(
        'message' => 'Композицію збережено.',
        'song' => $song,
    ));
}
add_action('wp_ajax_aigs_save_song', 'aigs_ajax_save_song');


/**
 * Видалення композиції з особистої колекції.
 */
function aigs_ajax_delete_song() {
    aigs_check_ajax_access();

    $song_id = isset($_POST['song_id'])
        ? sanitize_text_field(wp_unslash($_POST['song_id']))
        : '';

    if ($song_id === '') {
        wp_send_json_error(array(
            'message' => 'Не вказано композицію.',
        ), 400);
    }

    $user_id = get_current_user_id();

    $collection = get_user_meta(
        $user_id,
        'aigs_collection',
        true
    );

    if (!is_array($collection)) {
        $collection = array();
    }

    $updated = array();

    foreach ($collection as $song) {
        if (
            isset($song['id']) &&
            $song['id'] !== $song_id
        ) {
            $updated[] = $song;
        }
    }

    update_user_meta(
        $user_id,
        'aigs_collection',
        array_values($updated)
    );

    wp_send_json_success(array(
        'message' => 'Композицію видалено.',
    ));
}
add_action('wp_ajax_aigs_delete_song', 'aigs_ajax_delete_song');


/**
 * Оновлення нотаток композиції.
 */
function aigs_ajax_update_notes() {
    aigs_check_ajax_access();

    $song_id = isset($_POST['song_id'])
        ? sanitize_text_field(wp_unslash($_POST['song_id']))
        : '';

    $notes = isset($_POST['notes'])
        ? sanitize_textarea_field(wp_unslash($_POST['notes']))
        : '';

    if ($song_id === '') {
        wp_send_json_error(array(
            'message' => 'Не вказано композицію.',
        ), 400);
    }

    $user_id = get_current_user_id();

    $collection = get_user_meta(
        $user_id,
        'aigs_collection',
        true
    );

    if (!is_array($collection)) {
        $collection = array();
    }

    $found = false;

    foreach ($collection as &$song) {
        if (
            isset($song['id']) &&
            $song['id'] === $song_id
        ) {
            $song['notes'] = $notes;
            $found = true;
            break;
        }
    }

    unset($song);

    if (!$found) {
        wp_send_json_error(array(
            'message' => 'Композицію не знайдено.',
        ), 404);
    }

    update_user_meta(
        $user_id,
        'aigs_collection',
        array_values($collection)
    );

    wp_send_json_success(array(
        'message' => 'Нотатки оновлено.',
    ));
}
add_action('wp_ajax_aigs_update_notes', 'aigs_ajax_update_notes');
