<?php
/**
 * Plugin Name: AI Guitar Studio
 * Description: Обробка аудіо, транспонування, MP3/WAV експорт та особиста колекція композицій.
 * Version: 2.0.0
 * Author: UA Tools
 */

if (!defined('ABSPATH')) exit;

class AI_Guitar_Studio_200 {

    const VERSION = '2.0.0';
    const MAX_SIZE = 52428800; // 50 MB

    public function __construct() {
        add_shortcode('ai_guitar_studio', [$this, 'render']);

        add_action('wp_ajax_ags_upload', [$this, 'ajax_upload']);
        add_action('wp_ajax_ags_list', [$this, 'ajax_list']);
        add_action('wp_ajax_ags_delete', [$this, 'ajax_delete']);
    }

    private function authorized() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Увійдіть у свій обліковий запис.'], 401);
        }

        check_ajax_referer('ags_nonce', 'nonce');
    }

    private function owner() {
        return get_current_user_id();
    }

    private function is_audio($file) {
        $allowed = [
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'ogg'  => 'audio/ogg',
            'm4a'  => 'audio/mp4',
            'aac'  => 'audio/aac',
            'flac' => 'audio/flac',
            'webm' => 'audio/webm'
        ];

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (!isset($allowed[$ext])) return false;

        $check = wp_check_filetype($file);

        return !empty($check['type']) &&
            (
                strpos($check['type'], 'audio/') === 0 ||
                $check['type'] === 'video/mp4'
            );
    }

    private function save_audio($path, $filename, $title, $kind, $parent = 0) {

        if (!file_exists($path) || !is_readable($path)) {
            return new WP_Error('missing_file', 'Файл не знайдено.');
        }

        if (!$this->is_audio($filename)) {
            return new WP_Error('invalid_type', 'Непідтримуваний аудіоформат.');
        }

        $size = filesize($path);

        if (!$size || $size > self::MAX_SIZE) {
            return new WP_Error('file_size', 'Файл порожній або перевищує 50 МБ.');
        }

        $upload = wp_upload_bits(
            sanitize_file_name($filename),
            null,
            file_get_contents($path)
        );

        if (!empty($upload['error'])) {
            return new WP_Error('upload_error', $upload['error']);
        }

        $filetype = wp_check_filetype($upload['file']);

        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_text_field($title),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => $this->owner()
        ];

        $id = wp_insert_attachment($attachment, $upload['file']);

        if (is_wp_error($id)) {
            @unlink($upload['file']);
            return $id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        wp_update_attachment_metadata(
            $id,
            wp_generate_attachment_metadata($id, $upload['file'])
        );

        update_post_meta($id, '_ags_owner', $this->owner());
        update_post_meta($id, '_ags_kind', sanitize_key($kind));
        update_post_meta($id, '_ags_parent', absint($parent));
        update_post_meta($id, '_ags_title', sanitize_text_field($title));

        return $id;
    }

    public function ajax_upload() {
        $this->authorized();

        $kind = isset($_POST['kind'])
            ? sanitize_key($_POST['kind'])
            : 'original';

        $title = isset($_POST['title'])
            ? sanitize_text_field($_POST['title'])
            : '';

        $parent = isset($_POST['parent'])
            ? absint($_POST['parent'])
            : 0;

        if ($kind === 'original' && isset($_POST['audio_url'])) {

            $url = esc_url_raw(wp_unslash($_POST['audio_url']));

            if (!$url || !wp_http_validate_url($url)) {
                wp_send_json_error(['message' => 'Некоректне посилання.']);
            }

            $response = wp_safe_remote_get($url, [
                'timeout' => 35,
                'redirection' => 2,
                'limit_response_size' => self::MAX_SIZE,
                'headers' => [
                    'Accept' => 'audio/*'
                ]
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error([
                    'message' => 'Не вдалося завантажити аудіо: ' . $response->get_error_message()
                ]);
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $content_type = wp_remote_retrieve_header($response, 'content-type');

            if ($code !== 200 || empty($body) || strlen($body) > self::MAX_SIZE) {
                wp_send_json_error(['message' => 'Сервер не повернув аудіофайл або файл завеликий.']);
            }

            if (strpos((string)$content_type, 'audio/') !== 0) {
                wp_send_json_error(['message' => 'Посилання не повернуло аудіофайл. Потрібне пряме посилання на файл.']);
            }

            $path = wp_tempnam($url);

            if (!$path || file_put_contents($path, $body) === false) {
                wp_send_json_error(['message' => 'Не вдалося зберегти завантажений файл.']);
            }

            $url_path = parse_url($url, PHP_URL_PATH);
            $filename = sanitize_file_name(basename($url_path));

            if (!$filename || strpos($filename, '.') === false) {
                $filename = 'audio-' . time() . '.mp3';
            }

            $id = $this->save_audio(
                $path,
                $filename,
                $title ?: pathinfo($filename, PATHINFO_FILENAME),
                'original'
            );

            @unlink($path);

        } elseif (!empty($_FILES['audio']['tmp_name'])) {

            $file = $_FILES['audio'];

            if (!empty($file['error'])) {
                wp_send_json_error(['message' => 'Помилка завантаження файлу.']);
            }

            if ($file['size'] > self::MAX_SIZE) {
                wp_send_json_error(['message' => 'Максимальний розмір — 50 МБ.']);
            }

            $filename = sanitize_file_name($file['name']);

            $id = $this->save_audio(
                $file['tmp_name'],
                $filename,
                $title ?: pathinfo($filename, PATHINFO_FILENAME),
                $kind,
                $parent
            );

        } else {
            wp_send_json_error(['message' => 'Файл не вибрано.']);
        }

        if (is_wp_error($id)) {
            wp_send_json_error(['message' => $id->get_error_message()]);
        }

        wp_send_json_success([
            'id' => $id,
            'title' => get_the_title($id),
            'url' => wp_get_attachment_url($id),
            'kind' => get_post_meta($id, '_ags_kind', true)
        ]);
    }

    public function ajax_list() {
        $this->authorized();

        $items = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => 300,
            'author' => $this->owner(),
            'meta_key' => '_ags_owner',
            'meta_value' => $this->owner(),
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $result = [];

        foreach ($items as $item) {
            $url = wp_get_attachment_url($item->ID);

            if (!$url) continue;

            $result[] = [
                'id' => $item->ID,
                'title' => get_post_meta($item->ID, '_ags_title', true) ?: $item->post_title,
                'url' => $url,
                'kind' => get_post_meta($item->ID, '_ags_kind', true),
                'parent' => (int)get_post_meta($item->ID, '_ags_parent', true),
                'date' => get_the_date('Y-m-d H:i', $item->ID),
                'timestamp' => get_post_time('U', true, $item->ID)
            ];
        }

        wp_send_json_success($result);
    }

    public function ajax_delete() {
        $this->authorized();

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if (!$id || (int)get_post_meta($id, '_ags_owner', true) !== $this->owner()) {
            wp_send_json_error(['message' => 'Запис не знайдено.']);
        }

        wp_delete_attachment($id, true);

        wp_send_json_success(['message' => 'Запис видалено.']);
    }

    public function render() {

        if (!is_user_logged_in()) {
            return '<div class="ags-login">Для роботи з AI Guitar Studio увійдіть у свій обліковий запис WordPress.</div>';
        }

        $nonce = wp_create_nonce('ags_nonce');
        $ajax = admin_url('admin-ajax.php');

        ob_start();
        ?>

        <div id="ags-app" class="ags-app"
             data-ajax="<?php echo esc_url($ajax); ?>"
             data-nonce="<?php echo esc_attr($nonce); ?>">

            <h2>🎸 AI Guitar Studio 2.0</h2>

            <section class="ags-panel">

                <h3>1. Додати композицію</h3>

                <label class="ags-label">Завантажити аудіофайл</label>
                <input type="file" id="ags-file" accept="audio/*">

                <button type="button" id="ags-upload" class="ags-button">
                    Додати до колекції
                </button>

                <div class="ags-separator">або</div>

                <label class="ags-label">Пряме посилання на аудіофайл</label>
                <input type="url" id="ags-url"
                       placeholder="https://example.com/song.mp3">

                <button type="button" id="ags-url-add" class="ags-button">
                    Додати за посиланням
                </button>

                <div id="ags-status" class="ags-status"></div>

            </section>

            <section class="ags-panel">

                <h3>2. Оригінальна композиція</h3>

                <audio id="ags-original" controls preload="metadata"></audio>

                <p id="ags-key">Тональність: визначається після аналізу</p>

                <label class="ags-label">
                    Транспонування:
                    <strong id="ags-shift-label">0 півтонів</strong>
                </label>

                <div class="ags-slider-row">
                    <span>-12</span>
                    <input type="range" id="ags-shift"
                           min="-12" max="12" step="1" value="0">
                    <span>+12</span>
                </div>

                <div class="ags-buttons">
                    <button type="button" id="ags-minus">−</button>
                    <button type="button" id="ags-reset">0</button>
                    <button type="button" id="ags-plus">+</button>
                </div>

            </section>

            <section class="ags-panel">

                <h3>3. Оброблена композиція</h3>

                <audio id="ags-result" controls preload="metadata"></audio>

                <div id="ags-result-info">
                    Спочатку додайте аудіофайл.
                </div>

                <button type="button" id="ags-save" class="ags-button" disabled>
                    Зберегти оброблену композицію в колекцію
                </button>

                <button type="button" id="ags-download" class="ags-button ags-secondary" disabled>
                    Завантажити MP3
                </button>

                <button type="button" id="ags-download-wav" class="ags-button ags-secondary" disabled>
                    Завантажити WAV
                </button>

            </section>

            <section class="ags-panel">

                <h3>4. Видалення голосу та гітари</h3>

                <p class="ags-warning">
                    AI-розділення не підключене в цій автономній версії.
                    Ці операції не виконуються, доки не буде встановлено
                    та підключено модель розділення аудіо.
                </p>

                <label class="ags-disabled">
                    <input type="checkbox" disabled>
                    Видалити голос — AI-модель не підключена
                </label>

                <label class="ags-disabled">
                    <input type="checkbox" disabled>
                    Видалити гітару — AI-модель не підключена
                </label>

            </section>

            <section class="ags-panel">

                <h3>5. Моя колекція</h3>

                <input type="search" id="ags-search"
                       placeholder="Пошук композиції...">

                <div class="ags-sort-row">
                    <label>Сортувати:</label>
                    <select id="ags-sort">
                        <option value="newest">Спочатку нові</option>
                        <option value="oldest">Спочатку старі</option>
                        <option value="az">Назва А–Я</option>
                        <option value="za">Назва Я–А</option>
                        <option value="original">Оригінали</option>
                        <option value="processed">Оброблені</option>
                    </select>
                </div>

                <div id="ags-collection">
                    Завантаження колекції...
                </div>

            </section>

        </div>

        <style>
        .ags-app {
            max-width: 850px;
            margin: 20px auto;
            color: #202b3a;
            font-family: Arial, sans-serif;
        }

        .ags-app h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .ags-panel {
            background: #fff;
            border: 1px solid #dce3ec;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,.04);
        }

        .ags-panel h3 {
            margin-top: 0;
            font-size: 19px;
        }

        .ags-label {
            display: block;
            font-weight: 600;
            margin: 14px 0 7px;
        }

        .ags-app input[type=file],
        .ags-app input[type=url],
        .ags-app input[type=search],
        .ags-app select {
            width: 100%;
            max-width: 100%;
            padding: 11px;
            border: 1px solid #ccd5df;
            border-radius: 7px;
            box-sizing: border-box;
            margin-bottom: 10px;
            background: #fff;
        }

        .ags-app audio {
            display: block;
            width: 100%;
            margin: 12px 0;
        }

        .ags-button {
            display: block;
            width: 100%;
            border: 0;
            border-radius: 8px;
            padding: 13px;
            background: #1769aa;
            color: white;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }

        .ags-button:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .ags-secondary {
            background: #34495e;
        }

        .ags-separator {
            text-align: center;
            padding: 12px;
            color: #777;
        }

        .ags-status {
            margin-top: 12px;
            overflow-wrap: anywhere;
        }

        .ags-slider-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ags-slider-row input {
            flex: 1;
            min-width: 0;
        }

        .ags-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .ags-buttons button {
            flex: 1;
            padding: 10px;
            border: 1px solid #ccd5df;
            border-radius: 7px;
            background: #f3f6f9;
            cursor: pointer;
            font-size: 18px;
        }

        .ags-warning {
            padding: 12px;
            background: #fff5df;
            border-radius: 8px;
            color: #76520c;
            line-height: 1.5;
        }

        .ags-disabled {
            display: block;
            margin: 12px 0;
            color: #777;
        }

        .ags-sort-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ags-sort-row label {
            white-space: nowrap;
        }

        .ags-sort-row select {
            margin: 0;
        }

        .ags-item {
            border: 1px solid #e0e5eb;
            border-radius: 9px;
            padding: 12px;
            margin-top: 12px;
            overflow-wrap: anywhere;
        }

        .ags-item-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .ags-item-meta {
            color: #777;
            font-size: 12px;
        }

        .ags-item audio {
            margin: 8px 0;
        }

        .ags-item-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .ags-item-actions button,
        .ags-item-actions a {
            display: inline-block;
            border: 0;
            border-radius: 6px;
            padding: 9px 12px;
            background: #edf2f7;
            color: #203040;
            text-decoration: none;
            cursor: pointer;
        }

        .ags-empty {
            padding: 15px;
            color: #777;
            text-align: center;
        }

        @media(max-width:600px) {
            .ags-panel {
                padding: 13px;
            }

            .ags-sort-row {
                display: block;
            }

            .ags-sort-row select {
                margin-top: 7px;
            }
        }
        </style>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/lamejs/1.2.1/lame.min.js"></script>

        <script>
        (function() {
            'use strict';

            const root = document.getElementById('ags-app');
            if (!root || root.dataset.initialized === '1') return;
            root.dataset.initialized = '1';

            const ajax = root.dataset.ajax;
            const nonce = root.dataset.nonce;

            const $ = id => document.getElementById(id);

            const fileInput = $('ags-file');
            const originalPlayer = $('ags-original');
            const resultPlayer = $('ags-result');
            const slider = $('ags-shift');

            let audioContext = null;
            let originalBuffer = null;
            let resultBuffer = null;
            let originalId = 0;
            let resultBlob = null;
            let resultUrl = null;
            let originalObjectUrl = null;
            let currentTitle = '';
            let currentKey = 'Невідомо';
            let collection = [];
            let processing = false;

            function status(message, error) {
                const el = $('ags-status');
                el.textContent = message || '';
                el.style.color = error ? '#b42318' : '#16803c';
            }

            function post(data, file) {
                const form = new FormData();

                form.append('nonce', nonce);

                Object.keys(data).forEach(key => {
                    form.append(key, data[key]);
                });

                if (file) form.append('audio', file);

                return fetch(ajax, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: form
                }).then(response => response.json());
            }

            async function uploadFile(file, kind, parent) {
                return post({
                    action: 'ags_upload',
                    kind: kind || 'original',
                    parent: parent || 0,
                    title: currentTitle || file.name
                }, file);
            }

            async function getAudioContext() {
                if (!audioContext) {
                    const AudioContextClass =
                        window.AudioContext || window.webkitAudioContext;

                    if (!AudioContextClass) {
                        throw new Error('Ваш браузер не підтримує Web Audio API.');
                    }

                    audioContext = new AudioContextClass();
                }

                if (audioContext.state === 'suspended') {
                    await audioContext.resume();
                }

                return audioContext;
            }

            function detectKey(buffer) {
                const data = buffer.getChannelData(0);
                const sampleRate = buffer.sampleRate;
                const size = Math.min(data.length, sampleRate * 12);
                const segment = data.subarray(0, size);

                const notes = [
                    'C','C#','D','D#','E','F',
                    'F#','G','G#','A','A#','B'
                ];

                const chroma = new Array(12).fill(0);

                for (let frequency = 65; frequency <= 1000; frequency *= 1.025) {
                    const bin = Math.round(frequency * size / sampleRate);
                    if (bin < 1 || bin >= size / 2) continue;

                    let real = 0;
                    let imaginary = 0;

                    for (let i = 0; i < size; i += 4) {
                        const angle = 2 * Math.PI * bin * i / size;
                        real += segment[i] * Math.cos(angle);
                        imaginary -= segment[i] * Math.sin(angle);
                    }

                    const magnitude = Math.sqrt(real * real + imaginary * imaginary);
                    const midi = Math.round(69 + 12 * Math.log2(frequency / 440));
                    const note = ((midi % 12) + 12) % 12;

                    chroma[note] += magnitude;
                }

                let best = 0;

                for (let i = 1; i < 12; i++) {
                    if (chroma[i] > chroma[best]) best = i;
                }

                return notes[best];
            }

            function shiftedKey(key, shift) {
                const notes = [
                    'C','C#','D','D#','E','F',
                    'F#','G','G#','A','A#','B'
                ];

                const index = notes.indexOf(key);

                if (index < 0) return key;

                return notes[(index + shift + 120) % 12];
            }

            function wavBlob(buffer) {
                const channels = buffer.numberOfChannels;
                const sampleRate = buffer.sampleRate;
                const frames = buffer.length;

                const bytes = 44 + frames * channels * 2;
                const output = new ArrayBuffer(bytes);
                const view = new DataView(output);

                function writeString(offset, string) {
                    for (let i = 0; i < string.length; i++) {
                        view.setUint8(offset + i, string.charCodeAt(i));
                    }
                }

                writeString(0, 'RIFF');
                view.setUint32(4, bytes - 8, true);
                writeString(8, 'WAVE');
                writeString(12, 'fmt ');
                view.setUint32(16, 16, true);
                view.setUint16(20, 1, true);
                view.setUint16(22, channels, true);
                view.setUint32(24, sampleRate, true);
                view.setUint32(28, sampleRate * channels * 2, true);
                view.setUint16(32, channels * 2, true);
                view.setUint16(34, 16, true);
                writeString(36, 'data');
                view.setUint32(40, frames * channels * 2, true);

                let offset = 44;

                for (let i = 0; i < frames; i++) {
                    for (let channel = 0; channel < channels; channel++) {
                        let sample = buffer.getChannelData(channel)[i];

                        sample = Math.max(-1, Math.min(1, sample));

                        view.setInt16(
                            offset,
                            sample < 0 ? sample * 32768 : sample * 32767,
                            true
                        );

                        offset += 2;
                    }
                }

                return new Blob([output], {type: 'audio/wav'});
            }

            function mp3Blob(buffer) {
                if (!window.lamejs || !window.lamejs.Mp3Encoder) {
                    throw new Error('MP3-кодер не завантажився. Можна завантажити WAV.');
                }

                const channels = Math.min(buffer.numberOfChannels, 2);
                const sampleRate = buffer.sampleRate;
                const encoder = new lamejs.Mp3Encoder(channels, sampleRate, 192);
                const left = buffer.getChannelData(0);
                const right = channels > 1 ? buffer.getChannelData(1) : null;

                const block = 1152;
                const chunks = [];

                function toInt16(data, start, end) {
                    const result = new Int16Array(end - start);

                    for (let i = start; i < end; i++) {
                        let value = Math.max(-1, Math.min(1, data[i]));
                        result[i - start] = value < 0
                            ? value * 32768
                            : value * 32767;
                    }

                    return result;
                }

                for (let i = 0; i < left.length; i += block) {
                    const end = Math.min(i + block, left.length);
                    const l = toInt16(left, i, end);

                    let encoded;

                    if (channels > 1) {
                        const r = toInt16(right, i, end);
                        encoded = encoder.encodeBuffer(l, r);
                    } else {
                        encoded = encoder.encodeBuffer(l);
                    }

                    if (encoded.length) {
                        chunks.push(new Uint8Array(encoded));
                    }
                }

                const final = encoder.flush();

                if (final.length) {
                    chunks.push(new Uint8Array(final));
                }

                return new Blob(chunks, {type: 'audio/mpeg'});
            }

            function releaseResult() {
                if (resultUrl) {
                    URL.revokeObjectURL(resultUrl);
                    resultUrl = null;
                }

                resultPlayer.removeAttribute('src');
                resultPlayer.load();

                resultBuffer = null;
                resultBlob = null;

                $('ags-save').disabled = true;
                $('ags-download').disabled = true;
                $('ags-download-wav').disabled = true;
            }

            async function processAudio() {
                if (!originalBuffer || processing) return;

                processing = true;

                const shift = Number(slider.value);
                $('ags-shift-label').textContent =
                    (shift > 0 ? '+' : '') + shift + ' півтонів';

                $('ags-result-info').textContent = 'Обробка композиції...';

                releaseResult();

                try {
                    if (shift === 0) {
                        resultBuffer = originalBuffer;
                    } else {
                        const context = await getAudioContext();

                        const rate = Math.pow(2, shift / 12);
                        const length = Math.ceil(originalBuffer.length / rate);

                        if (length > context.sampleRate * 60 * 30) {
                            throw new Error('Композиція занадто довга для браузерної обробки.');
                        }

                        const offline = new OfflineAudioContext(
                            originalBuffer.numberOfChannels,
                            length,
                            originalBuffer.sampleRate
                        );

                        const source = offline.createBufferSource();
                        source.buffer = originalBuffer;
                        source.playbackRate.value = rate;
                        source.connect(offline.destination);
                        source.start(0);

                        resultBuffer = await offline.startRendering();
                    }

                    resultBlob = wavBlob(resultBuffer);
                    resultUrl = URL.createObjectURL(resultBlob);

                    resultPlayer.src = resultUrl;
                    resultPlayer.load();

                    $('ags-result-info').textContent =
                        'Готово. Тональність: ' +
                        shiftedKey(currentKey, shift) +
                        '. Зміна швидкості також відбувається.';

                    $('ags-save').disabled = false;
                    $('ags-download').disabled = false;
                    $('ags-download-wav').disabled = false;

                } catch (error) {
                    $('ags-result-info').textContent =
                        'Помилка обробки: ' + error.message;

                } finally {
                    processing = false;
                }
            }

            function downloadBlob(blob, filename) {
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');

                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();

                setTimeout(() => URL.revokeObjectURL(url), 30000);
            }

            async function loadAudioFile(file) {
                if (!file) return;

                if (file.size > 50 * 1024 * 1024) {
                    status('Максимальний розмір файлу — 50 МБ.', true);
                    return;
                }

                currentTitle = file.name.replace(/\.[^.]+$/, '');

                status('Завантаження композиції...');

                try {
                    const context = await getAudioContext();
                    const data = await file.arrayBuffer();

                    originalBuffer = await context.decodeAudioData(data);

                    if (originalObjectUrl) {
                        URL.revokeObjectURL(originalObjectUrl);
                    }

                    originalObjectUrl = URL.createObjectURL(file);
                    originalPlayer.src = originalObjectUrl;
                    originalPlayer.load();

                    currentKey = detectKey(originalBuffer);

                    $('ags-key').textContent =
                        'Визначена тональність: ' + currentKey +
                        ' (орієнтовно)';

                    slider.value = 0;
                    $('ags-shift-label').textContent = '0 півтонів';

                    await processAudio();

                    const result = await uploadFile(file, 'original', 0);

                    if (!result.success) {
                        throw new Error(result.data?.message || 'Не вдалося зберегти оригінал.');
                    }

                    originalId = result.data.id;

                    status('Композицію додано до колекції.');

                    await loadCollection();

                } catch (error) {
                    status('Помилка: ' + error.message, true);
                }
            }

            $('ags-upload').addEventListener('click', function() {
                const file = fileInput.files[0];

                if (!file) {
                    status('Спочатку виберіть аудіофайл.', true);
                    return;
                }

                loadAudioFile(file);
            });

            fileInput.addEventListener('change', function() {
                if (this.files[0]) loadAudioFile(this.files[0]);
            });

            slider.addEventListener('input', function() {
                const shift = Number(this.value);

                $('ags-shift-label').textContent =
                    (shift > 0 ? '+' : '') + shift + ' півтонів';
            });

            slider.addEventListener('change', processAudio);

            $('ags-minus').addEventListener('click', function() {
                slider.value = Math.max(-12, Number(slider.value) - 1);
                processAudio();
            });

            $('ags-plus').addEventListener('click', function() {
                slider.value = Math.min(12, Number(slider.value) + 1);
                processAudio();
            });

            $('ags-reset').addEventListener('click', function() {
                slider.value = 0;
                processAudio();
            });

            root.addEventListener('keydown', function(event) {
                if (event.target.matches('input,textarea,select')) return;

                if (event.key === 'ArrowLeft') {
                    slider.value = Math.max(-12, Number(slider.value) - 1);
                    processAudio();
                }

                if (event.key === 'ArrowRight') {
                    slider.value = Math.min(12, Number(slider.value) + 1);
                    processAudio();
                }
            });

            $('ags-download').addEventListener('click', function() {
                if (!resultBuffer) return;

                try {
                    const blob = mp3Blob(resultBuffer);
                    downloadBlob(blob, currentTitle + '-processed.mp3');
                } catch (error) {
                    status(error.message, true);
                }
            });

            $('ags-download-wav').addEventListener('click', function() {
                if (!resultBuffer) return;

                downloadBlob(
                    wavBlob(resultBuffer),
                    currentTitle + '-processed.wav'
                );
            });

            $('ags-save').addEventListener('click', async function() {
                if (!resultBuffer || !originalId) {
                    status('Спочатку додайте композицію.', true);
                    return;
                }

                try {
                    status('Збереження обробленої композиції...');

                    const blob = mp3Blob(resultBuffer);

                    const file = new File(
                        [blob],
                        currentTitle + '-processed.mp3',
                        {type: 'audio/mpeg'}
                    );

                    const response = await uploadFile(
                        file,
                        'processed',
                        originalId
                    );

                    if (!response.success) {
                        throw new Error(response.data?.message || 'Не вдалося зберегти.');
                    }

                    status('Оброблену композицію збережено в колекції.');

                    await loadCollection();

                } catch (error) {
                    status(error.message, true);
                }
            });

            $('ags-url-add').addEventListener('click', async function() {
                const url = $('ags-url').value.trim();

                if (!url) {
                    status('Вставте пряме посилання на аудіофайл.', true);
                    return;
                }

                try {
                    status('Завантаження аудіо за посиланням...');

                    const response = await post({
                        action: 'ags_upload',
                        kind: 'original',
                        audio_url: url,
                        title: url.split('/').pop().split('?')[0]
                    });

                    if (!response.success) {
                        throw new Error(response.data?.message || 'Помилка завантаження.');
                    }

                    $('ags-url').value = '';

                    status('Аудіо додано. Відкрийте його в колекції.');

                    await loadCollection();

                } catch (error) {
                    status(error.message, true);
                }
            });

            async function loadCollection() {
                const response = await post({action: 'ags_list'});

                if (!response.success) {
                    $('ags-collection').textContent =
                        response.data?.message || 'Не вдалося завантажити колекцію.';
                    return;
                }

                collection = response.data || [];
                renderCollection();
            }

            function renderCollection() {
                const search = $('ags-search').value.trim().toLowerCase();
                const sort = $('ags-sort').value;

                let items = collection.filter(item => {
                    return (item.title || '').toLowerCase().includes(search);
                });

                if (sort === 'newest') {
                    items.sort((a,b) => b.timestamp - a.timestamp);
                } else if (sort === 'oldest') {
                    items.sort((a,b) => a.timestamp - b.timestamp);
                } else if (sort === 'az') {
                    items.sort((a,b) => a.title.localeCompare(b.title, 'uk'));
                } else if (sort === 'za') {
                    items.sort((a,b) => b.title.localeCompare(a.title, 'uk'));
                } else if (sort === 'original') {
                    items = items.filter(item => item.kind === 'original');
                } else if (sort === 'processed') {
                    items = items.filter(item => item.kind === 'processed');
                }

                const container = $('ags-collection');
                container.replaceChildren();

                if (!items.length) {
                    container.innerHTML =
                        '<div class="ags-empty">У колекції поки немає композицій.</div>';
                    return;
                }

                items.forEach(item => {
                    const card = document.createElement('div');
                    card.className = 'ags-item';

                    const title = document.createElement('div');
                    title.className = 'ags-item-title';
                    title.textContent = item.title;

                    const meta = document.createElement('div');
                    meta.className = 'ags-item-meta';
                    meta.textContent =
                        (item.kind === 'processed' ? 'Оброблена композиція' : 'Оригінал') +
                        ' • ' + item.date;

                    const audio = document.createElement('audio');
                    audio.controls = true;
                    audio.preload = 'none';
                    audio.src = item.url;

                    const actions = document.createElement('div');
                    actions.className = 'ags-item-actions';

                    const download = document.createElement('a');
                    download.href = item.url;
                    download.download = '';
                    download.textContent = 'Завантажити';

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.textContent = 'Видалити';

                    remove.addEventListener('click', async function() {
                        if (!confirm('Видалити цей запис із колекції?')) return;

                        const result = await post({
                            action: 'ags_delete',
                            id: item.id
                        });

                        if (result.success) {
                            await loadCollection();
                        } else {
                            alert(result.data?.message || 'Помилка видалення.');
                        }
                    });

                    actions.append(download, remove);
                    card.append(title, meta, audio, actions);
                    container.appendChild(card);
                });
            }

            $('ags-search').addEventListener('input', renderCollection);
            $('ags-sort').addEventListener('change', renderCollection);

            loadCollection();

        })();
        </script>

        <?php
        return ob_get_clean();
    }
}

new AI_Guitar_Studio_200();
