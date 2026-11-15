<?php
/**
 * Plugin Name: AI Guitar Studio
 * Description: MP3 Guitar Studio — зміна тональності, аналіз аудіо та експорт MP3.
 * Version: 1.0.1
 * Author: UA Tools
 */

if (!defined('ABSPATH')) exit;

add_shortcode('ai_guitar_studio', 'aigs_101_shortcode');

function aigs_101_shortcode() {
    $id = 'aigs_' . wp_rand(10000, 9999999);

    ob_start();
    ?>
    <div id="<?php echo esc_attr($id); ?>" class="aigs-app">

      <style>
        #<?php echo esc_attr($id); ?> {
          background:#151923;
          color:#fff;
          padding:18px;
          border-radius:16px;
          max-width:760px;
          margin:15px auto;
          font-family:Arial,sans-serif;
          box-sizing:border-box;
        }

        #<?php echo esc_attr($id); ?> * {
          box-sizing:border-box;
        }

        #<?php echo esc_attr($id); ?> h2 {
          margin:0 0 18px;
          color:white;
          font-size:25px;
        }

        #<?php echo esc_attr($id); ?> .panel {
          background:#222a38;
          padding:15px;
          margin:13px 0;
          border-radius:12px;
        }

        #<?php echo esc_attr($id); ?> button,
        #<?php echo esc_attr($id); ?> select {
          font-size:16px;
          border:0;
          border-radius:9px;
          padding:13px;
        }

        #<?php echo esc_attr($id); ?> button {
          background:#e5b43e;
          color:#171717;
          font-weight:bold;
          cursor:pointer;
        }

        #<?php echo esc_attr($id); ?> button:disabled {
          opacity:.4;
          cursor:not-allowed;
        }

        #<?php echo esc_attr($id); ?> .main-button {
          width:100%;
          margin-top:12px;
        }

        #<?php echo esc_attr($id); ?> .upload {
          display:block;
          width:100%;
          padding:13px;
          background:#303b4e;
          border:1px dashed #8996aa;
          border-radius:9px;
          color:white;
        }

        #<?php echo esc_attr($id); ?> .key-display {
          text-align:center;
          font-size:42px;
          font-weight:bold;
          color:#f0bd48;
          margin:12px 0;
        }

        #<?php echo esc_attr($id); ?> .slider {
          width:100%;
          height:36px;
          accent-color:#e5b43e;
          cursor:pointer;
          touch-action:pan-y;
        }

        #<?php echo esc_attr($id); ?> .slider-labels {
          display:flex;
          justify-content:space-between;
          color:#aeb8c9;
          font-size:12px;
        }

        #<?php echo esc_attr($id); ?> .controls {
          display:flex;
          align-items:center;
          justify-content:center;
          gap:14px;
        }

        #<?php echo esc_attr($id); ?> .arrow {
          width:54px;
          height:48px;
          font-size:23px;
        }

        #<?php echo esc_attr($id); ?> .number {
          font-size:20px;
          min-width:95px;
          text-align:center;
          font-weight:bold;
        }

        #<?php echo esc_attr($id); ?> .checkrow {
          display:flex;
          align-items:center;
          gap:12px;
          padding:13px 0;
          border-bottom:1px solid #3c4554;
          font-size:16px;
        }

        #<?php echo esc_attr($id); ?> .checkrow input {
          width:22px;
          height:22px;
          accent-color:#e5b43e;
        }

        #<?php echo esc_attr($id); ?> .status {
          background:#303b4e;
          padding:12px;
          border-radius:8px;
          font-size:13px;
          line-height:1.5;
          margin-top:12px;
          overflow-wrap:anywhere;
        }

        #<?php echo esc_attr($id); ?> audio {
          width:100%;
          margin-top:10px;
        }

        #<?php echo esc_attr($id); ?> .muted {
          color:#aeb8c9;
          font-size:13px;
          line-height:1.5;
        }

        #<?php echo esc_attr($id); ?> .green {
          background:#23834f;
          color:white;
        }

        #<?php echo esc_attr($id); ?> .small {
          font-size:12px;
        }

        @media(max-width:480px) {
          #<?php echo esc_attr($id); ?> {
            padding:12px;
            border-radius:10px;
          }
          #<?php echo esc_attr($id); ?> h2 {
            font-size:22px;
          }
          #<?php echo esc_attr($id); ?> .key-display {
            font-size:36px;
          }
        }
      </style>

      <h2>🎸 AI Guitar Studio</h2>

      <div class="panel">
        <strong>1. Завантажити композицію</strong>

        <br><br>

        <input
          class="upload file"
          type="file"
          accept="audio/*,.mp3,.wav,.m4a,.ogg"
        >

        <div class="status file-status">
          Вибери MP3 або WAV.
        </div>
      </div>

      <div class="panel">
        <strong>2. Тональність</strong>

        <div class="key-display">
          <span class="key">—</span>
        </div>

        <div class="controls">
          <button class="arrow down" type="button" disabled>−</button>
          <div class="number">
            <span class="shift">0</span>
            <span class="small">півтонів</span>
          </div>
          <button class="arrow up" type="button" disabled>+</button>
        </div>

        <br>

        <input
          class="slider"
          type="range"
          min="-12"
          max="12"
          step="1"
          value="0"
          disabled
        >

        <div class="slider-labels">
          <span>−12</span>
          <span>Оригінал</span>
          <span>+12</span>
        </div>

        <div class="status key-status">
          Завантаж композицію для аналізу.
        </div>
      </div>

      <div class="panel">
        <strong>3. Видалення інструментів</strong>

        <label class="checkrow">
          <input type="checkbox" class="remove-voice">
          <span>🎤 Видалити голос</span>
        </label>

        <label class="checkrow">
          <input type="checkbox" class="remove-guitar">
          <span>🎸 Видалити гітару</span>
        </label>

        <p class="muted">
          У цій версії функції розділення голосу та гітари
          ще не підключені. Для цього потрібна окрема AI-модель.
          Позначення галочок поки не змінює звук.
        </p>
      </div>

      <div class="panel">
        <strong>4. Оригінальна композиція</strong>
        <audio class="original" controls></audio>

        <br><br>

        <strong>5. Оброблена композиція</strong>
        <audio class="result" controls></audio>

        <button class="main-button export" type="button" disabled>
          <span>⬇ Зберегти результат у MP3</span>
        </button>

        <div class="status export-status">
          Спочатку завантаж композицію.
        </div>
      </div>

      <div class="status">
        AI Guitar Studio 1.0.1 · UA Tools
        <br>
        Обробка відбувається у браузері. Оригінальний файл не змінюється.
      </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/lamejs@1.2.1/lame.min.js"></script>

    <script>
    (function() {

      const root = document.getElementById(
        <?php echo wp_json_encode($id); ?>
      );

      if (!root) return;

      const $ = selector => root.querySelector(selector);

      const fileInput = $('.file');
      const slider = $('.slider');

      const keyDisplay = $('.key');
      const shiftDisplay = $('.shift');

      const originalPlayer = $('.original');
      const resultPlayer = $('.result');

      const fileStatus = $('.file-status');
      const keyStatus = $('.key-status');
      const exportStatus = $('.export-status');

      const down = $('.down');
      const up = $('.up');
      const exportButton = $('.export');

      const notes = [
        'C','C#','D','D#','E','F',
        'F#','G','G#','A','A#','B'
      ];

      let audioContext = null;
      let audioBuffer = null;
      let originalUrl = null;
      let resultUrl = null;
      let currentShift = 0;
      let originalKey = null;
      let processing = false;

      function message(element, text) {
        element.textContent = text;
      }

      function getNoteName(index) {
        return notes[(index + 120) % 12];
      }

      function transposeName(rootNote, mode, shift) {
        const index = notes.indexOf(rootNote);

        if (index < 0) return '—';

        return getNoteName(index + shift) + mode;
      }

      /*
       * Простий FFT для оцінки спектра.
       */
      function fft(real, imag) {

        const n = real.length;

        for (let i=1,j=0; i<n; i++) {

          let bit = n >> 1;

          for (; j & bit; bit >>= 1) {
            j ^= bit;
          }

          j ^= bit;

          if (i < j) {
            [real[i],real[j]] = [real[j],real[i]];
            [imag[i],imag[j]] = [imag[j],imag[i]];
          }
        }

        for (let len=2; len<=n; len<<=1) {

          const angle = -2*Math.PI/len;
          const wr0 = Math.cos(angle);
          const wi0 = Math.sin(angle);

          for (let i=0; i<n; i+=len) {

            let wr=1;
            let wi=0;

            for (let j=0; j<len/2; j++) {

              const u=i+j;
              const v=u+len/2;

              const tr=real[v]*wr-imag[v]*wi;
              const ti=real[v]*wi+imag[v]*wr;

              real[v]=real[u]-tr;
              imag[v]=imag[u]-ti;

              real[u]+=tr;
              imag[u]+=ti;

              const nr=wr*wr0-wi*wi0;

              wi=wr*wi0+wi*wr0;
              wr=nr;
            }
          }
        }
      }

      /*
       * Визначення приблизної тональності.
       */
      function detectKey(buffer) {

        const data = buffer.getChannelData(0);

        const size = 4096;
        const hop = 16384;

        const chroma = new Float64Array(12);

        let frames = 0;

        for (
          let offset=0;
          offset+size<data.length;
          offset+=hop
        ) {

          const real = new Float64Array(size);
          const imag = new Float64Array(size);

          for (let i=0; i<size; i++) {

            const window =
              0.5 - 0.5*Math.cos(2*Math.PI*i/(size-1));

            real[i] = data[offset+i]*window;
          }

          fft(real,imag);

          for (let bin=2; bin<size/2; bin++) {

            const frequency =
              bin*buffer.sampleRate/size;

            if (frequency<65 || frequency>1200) continue;

            const midi =
              69+12*Math.log2(frequency/440);

            const pitch = Math.round(midi);

            const distance = Math.abs(midi-pitch);

            if (distance>0.4) continue;

            const magnitude = Math.hypot(
              real[bin],imag[bin]
            );

            chroma[(pitch%12+12)%12] += magnitude;
          }

          frames++;
        }

        if (!frames) return null;

        const major = [
          6.35,2.23,3.48,2.33,4.38,4.09,
          2.52,5.19,2.39,3.66,2.29,2.88
        ];

        const minor = [
          6.33,2.68,3.52,5.38,2.6,3.53,
          2.54,4.75,3.98,2.69,3.34,3.17
        ];

        let best = -Infinity;
        let answer = null;

        for (let rootNote=0; rootNote<12; rootNote++) {

          for (const mode of ['','m']) {

            const profile = mode ? minor : major;

            let score=0;

            for (let i=0; i<12; i++) {
              score += chroma[(rootNote+i)%12]*profile[i];
            }

            if (score>best) {

              best=score;

              answer = {
                root:notes[rootNote],
                mode:mode
              };
            }
          }
        }

        return answer;
      }

      /*
       * Завантаження аудіо.
       */
      async function loadFile(file) {

        if (audioContext && audioContext.state === 'closed') {
          audioContext = null;
        }

        if (!audioContext) {

          const AudioCtx =
            window.AudioContext || window.webkitAudioContext;

          if (!AudioCtx) {
            throw new Error('Web Audio API не підтримується.');
          }

          audioContext = new AudioCtx();
        }

        const data = await file.arrayBuffer();

        return await audioContext.decodeAudioData(data);
      }

      /*
       * Перерахунок повзунка.
       */
      function updateShift(value) {

        currentShift = Number(value);

        slider.value = currentShift;

        shiftDisplay.textContent =
          (currentShift>0?'+':'')+currentShift;

        if (originalKey) {

          keyDisplay.textContent = transposeName(
            originalKey.root,
            originalKey.mode,
            currentShift
          );
        }

        down.disabled = currentShift <= -12;
        up.disabled = currentShift >= 12;

        if (resultUrl) {
          URL.revokeObjectURL(resultUrl);
          resultUrl = null;
        }

        resultPlayer.pause();
        resultPlayer.removeAttribute('src');
        resultPlayer.load();

        exportButton.disabled = true;

        message(
          exportStatus,
          'Налаштування змінено. Натисни зберегти для створення нового MP3.'
        );
      }

      slider.addEventListener('input',function() {
        updateShift(this.value);
      });

      down.addEventListener('click',function() {
        updateShift(Math.max(-12,currentShift-1));
      });

      up.addEventListener('click',function() {
        updateShift(Math.min(12,currentShift+1));
      });

      /*
       * Керування стрілками на комп'ютері.
       */
      root.addEventListener('keydown',function(event) {

        if (event.target.matches('input,select,textarea')) return;

        if (event.key === 'ArrowLeft') {
          event.preventDefault();
          updateShift(Math.max(-12,currentShift-1));
        }

        if (event.key === 'ArrowRight') {
          event.preventDefault();
          updateShift(Math.min(12,currentShift+1));
        }
      });

      /*
       * Завантаження композиції.
       */
      fileInput.addEventListener('change',async function() {

        const file=this.files[0];

        if (!file) return;

        if (file.size>100*1024*1024) {

          message(fileStatus,'Максимальний розмір — 100 МБ.');
          return;
        }

        try {

          message(fileStatus,'Завантаження аудіо...');

          slider.disabled=true;
          down.disabled=true;
          up.disabled=true;
          exportButton.disabled=true;

          if (originalUrl) URL.revokeObjectURL(originalUrl);
          if (resultUrl) URL.revokeObjectURL(resultUrl);

          originalUrl=URL.createObjectURL(file);

          originalPlayer.src=originalUrl;

          audioBuffer=await loadFile(file);

          message(
            fileStatus,
            'Файл завантажено. Тривалість: '+
            Math.floor(audioBuffer.duration/60)+':'+
            String(Math.floor(audioBuffer.duration%60)).padStart(2,'0')
          );

          message(keyStatus,'Визначаю тональність...');

          // Аналіз виконується без блокування першого оновлення UI.
          await new Promise(resolve=>setTimeout(resolve,50));

          originalKey=detectKey(audioBuffer);

          if (!originalKey) {
            throw new Error('Не вдалося визначити тональність.');
          }

          currentShift=0;

          keyDisplay.textContent =
            originalKey.root+originalKey.mode;

          slider.value=0;
          slider.disabled=false;

          shiftDisplay.textContent='0';

          down.disabled=false;
          up.disabled=false;

          message(
            keyStatus,
            'Попередньо визначена тональність: '+
            originalKey.root+originalKey.mode+
            '. Точність залежить від аранжування.'
          );

          message(
            exportStatus,
            'Можна налаштувати тональність і створити MP3.'
          );

        } catch(error) {

          message(fileStatus,'Помилка: '+error.message);

          slider.disabled=true;
          exportButton.disabled=true;
        }
      });

      /*
       * Створення аудіо зі зміною висоти.
       *
       * ВАЖЛИВО: тут змінюється також темп.
       * Збереження темпу буде окремим алгоритмом.
       */
      async function createProcessedBuffer() {

        const rate=Math.pow(2,currentShift/12);

        const sampleRate=44100;

        const length=Math.max(
          1,
          Math.ceil(audioBuffer.length/rate)
        );

        const Offline =
          window.OfflineAudioContext ||
          window.webkitOfflineAudioContext;

        if (!Offline) {
          throw new Error('OfflineAudioContext не підтримується.');
        }

        const context=new Offline(
          audioBuffer.numberOfChannels,
          length,
          sampleRate
        );

        const source=context.createBufferSource();

        source.buffer=audioBuffer;
        source.playbackRate.value=rate;

        source.connect(context.destination);
        source.start(0);

        return await context.startRendering();
      }

      /*
       * Конвертація в MP3.
       */
      async function encodeMp3(buffer) {

        if (!window.lamejs || !window.lamejs.Mp3Encoder) {
          throw new Error(
            'Не завантажився MP3-кодер. Перевір інтернет-з’єднання.'
          );
        }

        const Encoder=window.lamejs.Mp3Encoder;

        const sampleRate=buffer.sampleRate;
        const channels=Math.min(2,buffer.numberOfChannels);

        const encoder=new Encoder(
          channels,
          sampleRate,
          192
        );

        const left=buffer.getChannelData(0);
        const right=channels===2 ?
          buffer.getChannelData(1) : null;

        const chunkSize=1152;

        const chunks=[];

        function convertFloatToInt16(data,start,end) {

          const output=new Int16Array(end-start);

          for (let i=start,j=0; i<end; i++,j++) {

            const sample=Math.max(-1,Math.min(1,data[i]));

            output[j]=sample<0 ?
              sample*32768 : sample*32767;
          }

          return output;
        }

        for (let i=0; i<left.length; i+=chunkSize) {

          const end=Math.min(i+chunkSize,left.length);

          const l=convertFloatToInt16(left,i,end);

          let encoded;

          if (channels===2) {

            const r=convertFloatToInt16(right,i,end);

            encoded=encoder.encodeBuffer(l,r);

          } else {

            encoded=encoder.encodeBuffer(l);
          }

          if (encoded.length) {
            chunks.push(new Uint8Array(encoded));
          }

          if (i%(chunkSize*100)===0) {
            message(
              exportStatus,
              'Створення MP3: '+
              Math.round(i/left.length*100)+'%'
            );

            await new Promise(resolve=>setTimeout(resolve,0));
          }
        }

        const final=encoder.flush();

        if (final.length) {
          chunks.push(new Uint8Array(final));
        }

        return new Blob(chunks,{type:'audio/mpeg'});
      }

      /*
       * Кнопка збереження.
       */
      exportButton.addEventListener('click',async function() {

        if (!audioBuffer || processing) return;

        processing=true;
        exportButton.disabled=true;

        try {

          message(
            exportStatus,
            'Обробка аудіо. Не закривай сторінку...'
          );

          const processedBuffer=await createProcessedBuffer();

          message(
            exportStatus,
            'Кодування MP3...'
          );

          const blob=await encodeMp3(processedBuffer);

          if (resultUrl) URL.revokeObjectURL(resultUrl);

          resultUrl=URL.createObjectURL(blob);

          resultPlayer.src=resultUrl;
          resultPlayer.load();

          const link=document.createElement('a');

          link.href=resultUrl;
          link.download='AI-Guitar-Studio.mp3';

          document.body.appendChild(link);
          link.click();
          link.remove();

          message(
            exportStatus,
            'MP3 створено. Файл завантажується на пристрій.'
          );

        } catch(error) {

          message(
            exportStatus,
            'Помилка: '+error.message
          );

        } finally {

          processing=false;
          exportButton.disabled=false;
        }
      });

      /*
       * При зміні галочок повідомляємо,
       * що функція ще не підключена.
       */
      $('.remove-voice').addEventListener('change',function() {

        if (this.checked) {
          message(
            exportStatus,
            'Видалення голосу потребує AI-модуля розділення аудіо. Поки не застосовується.'
          );
        }
      });

      $('.remove-guitar').addEventListener('change',function() {

        if (this.checked) {
          message(
            exportStatus,
            'Видалення гітари потребує AI-модуля розділення аудіо. Поки не застосовується.'
          );
        }
      });

    })();
    </script>

    <?php
    return ob_get_clean();
}
