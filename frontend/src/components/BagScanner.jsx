import { useRef, useState } from 'react';

import { scanBag } from '../lib/api.js';
import { shrinkImage } from '../lib/image.js';

/**
 * BagScanner
 * Photograph the coffee bag and let Gemini read the label, instead of typing
 * the origin, process, roast and tasting notes by hand.
 *
 * The image is sent to the backend, forwarded to Gemini and discarded — it is
 * never stored. The preview URL is revoked when it is replaced.
 */
export default function BagScanner({ t, onScanned, disabled }) {
  const inputRef = useRef(null);
  const [preview, setPreview] = useState(null);
  const [scanning, setScanning] = useState(false);
  const [message, setMessage] = useState(null); // { kind: 'ok' | 'warn' | 'error', text }

  async function handleFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    // Replace the previous preview and release its blob URL.
    setPreview((current) => {
      if (current) URL.revokeObjectURL(current);
      return URL.createObjectURL(file);
    });

    setScanning(true);
    setMessage(null);

    try {
      // Phone photos are far larger than the label needs, and used to be
      // rejected by the upload limits before reaching the model.
      const upload = await shrinkImage(file);

      const beans = await scanBag(upload);

      if (!beans.found) {
        setMessage({ kind: 'warn', text: t.scanNotCoffee });
        return;
      }

      onScanned(beans);
      setMessage({ kind: 'ok', text: beans.bean_name ? `${t.scanFilled} — ${beans.bean_name}` : t.scanFilled });
    } catch (error) {
      // A rejected upload is about the photo, not the coffee form — the generic
      // "check your inputs" message sent people hunting through the dropdowns.
      const text =
        error.message === 'VALIDATION'
          ? t.scanRejected
          : (t.errors[error.message] ?? t.errors.UNKNOWN);

      setMessage({ kind: 'error', text });
    } finally {
      setScanning(false);
      // Clear the input so picking the same file again still fires onChange.
      if (inputRef.current) inputRef.current.value = '';
    }
  }

  return (
    <div className="scanner">
      {/*
        accept="image/*" rather than a list of types: an iPhone stores photos as
        HEIC, and a narrow accept list greys them out in the picker. Whatever is
        chosen gets re-encoded to JPEG by shrinkImage before upload.
      */}
      <input
        ref={inputRef}
        type="file"
        accept="image/*"
        onChange={handleFile}
        className="visually-hidden"
        id="bag-photo"
        disabled={disabled || scanning}
      />

      <div className="scanner-row">
        <label htmlFor="bag-photo" className={`btn btn-outline btn-small ${disabled || scanning ? 'btn-disabled' : ''}`}>
          {scanning ? t.scanning : t.scanBag}
        </label>
        <span className="hint scanner-hint">{t.scanHint}</span>
      </div>

      {preview && <img src={preview} alt="" className="scanner-preview" />}

      {message && (
        <p className={message.kind === 'error' ? 'error' : message.kind === 'warn' ? 'hint hint-warn' : 'success'}>
          {message.text}
        </p>
      )}
    </div>
  );
}
