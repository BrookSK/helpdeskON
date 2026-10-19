/**
 * recording-upload.js — Transportador de gravação de videochamada (protocolo v2).
 *
 * Implementa o lado cliente descrito no diagnóstico de 14/10/2026:
 *  - Cada evento do MediaRecorder é persistido em IndexedDB ANTES de enviar, e a
 *    confirmação local aguarda o evento `complete` da transação (não só o sucesso
 *    de um request). Não mantém a reunião inteira em RAM.
 *  - Eventos grandes são divididos em pedaços de até 1 MiB; cada pedaço leva
 *    sequência, tamanho e SHA-256 (Web Crypto). O servidor confirma (ACK) só
 *    após persistir; falhas transitórias têm retry limitado.
 *  - Ao finalizar, envia count/bytes/manifest (SHA-256 do manifesto) e o servidor
 *    confere integridade e publica. Reenvio idempotente: mesma seq + mesmos bytes
 *    não duplicam.
 *  - Após fechamento abrupto, uma captura com fim confirmado pode ser reenviada a
 *    partir do IndexedDB; um trecho sem fim confirmado é oferecido como download
 *    parcial.
 *
 * Uso (ver room.php):
 *   const up = new RecordingUploader({ base, roomToken, sess, recordedByName });
 *   await up.open();
 *   // para cada blob do MediaRecorder: await up.enqueue(blob);
 *   // ao parar: const res = await up.finalize(durationSec);
 *
 * Sem dependências externas. Requer IndexedDB, Web Crypto (crypto.subtle) e fetch.
 */
(function (global) {
  'use strict';

  const CHUNK_SIZE = 1048576;        // 1 MiB por pedaço enviado
  const MAX_RETRIES = 5;             // tentativas por pedaço em falha transitória
  const DB_PREFIX = 'vc_rec_';       // um banco por sessão

  function toHex(buffer) {
    const b = new Uint8Array(buffer);
    let s = '';
    for (let i = 0; i < b.length; i++) s += b[i].toString(16).padStart(2, '0');
    return s;
  }

  async function sha256Hex(arrayBuffer) {
    const digest = await crypto.subtle.digest('SHA-256', arrayBuffer);
    return toHex(digest);
  }

  function sleep(ms) { return new Promise((r) => setTimeout(r, ms)); }

  class RecordingUploader {
    constructor(opts) {
      this.base = opts.base.replace(/\/+$/, '');
      this.roomToken = opts.roomToken;
      this.sess = opts.sess;
      this.recordedByName = opts.recordedByName || '';
      this.db = null;
      this.seq = 0;              // última sequência GERADA (enfileirada)
      this.sentSeq = 0;          // última sequência confirmada pelo servidor
      this.bytes = 0;            // total de bytes confirmados
      this.chunks = [];          // {seq,size,sha256} confirmados (para manifesto)
      this.pump = Promise.resolve(); // serializa o envio na ordem
      this.carry = new Uint8Array(0); // bytes acumulados que ainda não fecharam 1 MiB
      this.started = false;
      this.failed = false;
    }

    dbName() { return DB_PREFIX + this.roomToken + '_' + this.sess; }

    open() {
      return new Promise((resolve, reject) => {
        const req = indexedDB.open(this.dbName(), 1);
        req.onupgradeneeded = () => {
          const db = req.result;
          if (!db.objectStoreNames.contains('chunks')) db.createObjectStore('chunks', { keyPath: 'seq' });
          if (!db.objectStoreNames.contains('meta')) db.createObjectStore('meta', { keyPath: 'k' });
        };
        req.onsuccess = () => { this.db = req.result; resolve(this); };
        req.onerror = () => reject(req.error);
      });
    }

    /** Persiste um registro em IndexedDB e só resolve no `complete` da transação. */
    _put(store, value) {
      return new Promise((resolve, reject) => {
        const tx = this.db.transaction(store, 'readwrite');
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error || new Error('tx_abort'));
        tx.objectStore(store).put(value);
      });
    }

    _get(store, key) {
      return new Promise((resolve, reject) => {
        const tx = this.db.transaction(store, 'readonly');
        const r = tx.objectStore(store).get(key);
        r.onsuccess = () => resolve(r.result || null);
        r.onerror = () => reject(r.error);
      });
    }

    async start() {
      if (this.started) return;
      const fd = new FormData();
      fd.append('sess', this.sess);
      fd.append('recorded_by_name', this.recordedByName);
      const res = await fetch(`${this.base}/videocall/recStart/${this.roomToken}`, { method: 'POST', body: fd })
        .then((r) => r.json()).catch(() => ({ error: 'network' }));
      if (res && res.error) throw new Error('recStart: ' + res.error);
      this.started = true;
      await this._put('meta', { k: 'session', sess: this.sess, startedAt: Date.now() });
    }

    /**
     * Recebe um Blob do MediaRecorder, acumula e corta em pedaços de 1 MiB.
     * Cada pedaço fechado é persistido em IndexedDB e colocado na fila de envio.
     * Resolve quando os pedaços derivados deste blob estão GRAVADOS localmente
     * (o envio ao servidor segue em background pela fila ordenada).
     */
    async enqueue(blob) {
      if (!this.started) await this.start();
      const buf = new Uint8Array(await blob.arrayBuffer());
      // Junta com o que sobrou do blob anterior.
      const merged = new Uint8Array(this.carry.length + buf.length);
      merged.set(this.carry, 0);
      merged.set(buf, this.carry.length);

      let offset = 0;
      while (merged.length - offset >= CHUNK_SIZE) {
        const slice = merged.slice(offset, offset + CHUNK_SIZE);
        offset += CHUNK_SIZE;
        await this._persistAndQueue(slice);
      }
      this.carry = merged.slice(offset);
    }

    /** Fecha o pedaço residual (chamado no stop) para não perder a cauda. */
    async flushCarry() {
      if (this.carry.length > 0) {
        const slice = this.carry;
        this.carry = new Uint8Array(0);
        await this._persistAndQueue(slice);
      }
    }

    async _persistAndQueue(bytes) {
      this.seq += 1;
      const seq = this.seq;
      const sha = await sha256Hex(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength));
      // Grava localmente ANTES de enviar; aguarda o complete da transação.
      await this._put('chunks', { seq, bytes, size: bytes.length, sha256: sha, acked: false });
      // Enfileira o envio preservando a ordem.
      this.pump = this.pump.then(() => this._sendChunk(seq, bytes, sha)).catch((e) => { this.failed = true; throw e; });
    }

    async _sendChunk(seq, bytes, sha) {
      let attempt = 0;
      while (true) {
        attempt++;
        const fd = new FormData();
        fd.append('sess', this.sess);
        fd.append('seq', String(seq));
        fd.append('sha256', sha);
        fd.append('chunk', new Blob([bytes], { type: 'application/octet-stream' }), 'c.bin');
        let res, httpOk = false, status = 0;
        try {
          const r = await fetch(`${this.base}/videocall/recChunkV2/${this.roomToken}`, { method: 'POST', body: fd });
          status = r.status;
          httpOk = r.ok;
          res = await r.json().catch(() => null);
        } catch (e) { res = null; }

        // Sucesso (ou duplicado idempotente): confirma e segue.
        if (httpOk && res && res.success && (res.status === 'ok' || res.status === 'duplicate')) {
          this.sentSeq = Math.max(this.sentSeq, seq);
          this.bytes = typeof res.bytes === 'number' ? res.bytes : this.bytes + bytes.length;
          this.chunks.push({ seq, size: bytes.length, sha256: sha });
          await this._put('chunks', { seq, bytes, size: bytes.length, sha256: sha, acked: true });
          return;
        }
        // Limite de sessão: para de forma controlada; a cópia local é preservada.
        if (status === 413) { this.failed = true; throw new Error('limit'); }
        // Conflito de integridade/sequência não é recuperável por retry cego.
        if (status === 409 || status === 422) { this.failed = true; throw new Error('integrity:' + (res && res.reason || status)); }
        // Transitório (rede, 503/507): retry com backoff limitado.
        if (attempt >= MAX_RETRIES) { this.failed = true; throw new Error('exhausted'); }
        await sleep(Math.min(1000 * attempt, 4000));
      }
    }

    manifestText() {
      const sorted = this.chunks.slice().sort((a, b) => a.seq - b.seq);
      return sorted.map((c) => `${c.seq}:${c.size}:${c.sha256}`).join('\n') + (sorted.length ? '\n' : '');
    }

    async manifestHash() {
      const enc = new TextEncoder().encode(this.manifestText());
      return sha256Hex(enc.buffer);
    }

    /**
     * Finaliza: garante a cauda, drena a fila, e envia count/bytes/manifest.
     * Retorna { ok, token, url } em sucesso. Em falha, lança — o chamador deve
     * oferecer Reenviar / Baixar cópia local (a captura fica no IndexedDB).
     */
    async finalize(durationSec) {
      await this.flushCarry();
      await this.pump; // propaga erro se algum pedaço falhou de forma definitiva

      const count = this.chunks.length;
      const bytes = this.chunks.reduce((s, c) => s + c.size, 0);
      const manifest = await this.manifestHash();

      const fd = new FormData();
      fd.append('sess', this.sess);
      fd.append('ended', '1');
      fd.append('count', String(count));
      fd.append('bytes', String(bytes));
      fd.append('manifest', manifest);
      fd.append('duration_sec', String(durationSec || 0));
      fd.append('recorded_by_name', this.recordedByName);

      const res = await fetch(`${this.base}/videocall/recFinalizeV2/${this.roomToken}`, { method: 'POST', body: fd })
        .then((r) => r.json()).catch(() => ({ error: 'network' }));
      if (res && res.error) throw new Error('recFinalize: ' + (res.reason || res.error));
      await this._put('meta', { k: 'finalized', token: res.token, at: Date.now() });
      return { ok: true, token: res.token, url: res.url, duration: res.duration_sec };
    }

    /** Monta um Blob local com todos os pedaços gravados (para download de cópia). */
    async localBlob() {
      return new Promise((resolve, reject) => {
        const tx = this.db.transaction('chunks', 'readonly');
        const store = tx.objectStore('chunks');
        const parts = [];
        const cur = store.openCursor();
        cur.onsuccess = () => {
          const c = cur.result;
          if (c) { parts.push({ seq: c.value.seq, bytes: c.value.bytes }); c.continue(); }
          else {
            parts.sort((a, b) => a.seq - b.seq);
            resolve(new Blob(parts.map((p) => p.bytes), { type: 'video/webm' }));
          }
        };
        cur.onerror = () => reject(cur.error);
      });
    }

    /** Dispara o download da cópia local (fallback quando o envio não fecha). */
    async downloadLocal(filename) {
      const blob = await this.localBlob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename || ('gravacao_' + this.sess + '.webm');
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 10000);
    }

    /** Remove o banco local após publicação confirmada. */
    async destroy() {
      try { if (this.db) this.db.close(); } catch (e) {}
      await new Promise((resolve) => {
        const r = indexedDB.deleteDatabase(this.dbName());
        r.onsuccess = r.onerror = r.onblocked = () => resolve();
      });
    }
  }

  global.RecordingUploader = RecordingUploader;
})(window);
