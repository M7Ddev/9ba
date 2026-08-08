/**
 * image.js
 * ---------------------------------------------------------------------------
 * Shrinks a photo in the browser before it is uploaded.
 *
 * Why this exists: a phone camera produces a far bigger file than reading a
 * coffee label needs. An iPhone shoots 4032x3024 at 3-8 MB; the same label is
 * perfectly legible at 1600 px and ~250 KB. Without this step phone photos were
 * rejected outright, while small images copied from a website worked — which
 * looked like the scanner being broken rather than an upload limit.
 *
 * Doing it client-side also means the large file never crosses the network,
 * which matters on mobile data, and fewer pixels means fewer tokens.
 */

/** Longest edge, in pixels, of the uploaded image. Plenty for label text. */
const MAX_EDGE = 1600;

/** JPEG quality. 0.85 keeps small print sharp at roughly a quarter the size. */
const QUALITY = 0.85;

/**
 * Decode a file into something canvas can draw, honouring EXIF orientation.
 *
 * Phone photos are often stored sideways with an orientation flag rather than
 * physically rotated. `createImageBitmap` applies that flag; a plain <img> in a
 * canvas would not, and the label would reach the model upside down.
 *
 * @param {File} file
 */
async function decode(file) {
  if ('createImageBitmap' in window) {
    try {
      return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
      // Some browsers reject the options object; fall through to the <img> path.
    }
  }

  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();

    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('decode failed'));
    };

    img.src = url;
  });
}

/**
 * Downscale an image file and re-encode it as JPEG.
 *
 * Returns the ORIGINAL file if anything goes wrong — a scan that works with a
 * large upload is better than one that fails because the optimisation did.
 * The server validates size and type regardless.
 *
 * @param {File} file
 * @returns {Promise<File>}
 */
export async function shrinkImage(file) {
  try {
    const source = await decode(file);

    const { width, height } = source;
    const scale = Math.min(1, MAX_EDGE / Math.max(width, height));

    // Already small enough and already a format the API accepts: leave it be.
    if (scale === 1 && file.size < 2_000_000 && file.type !== 'image/heic') {
      return file;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(width * scale);
    canvas.height = Math.round(height * scale);

    const context = canvas.getContext('2d');
    context.drawImage(source, 0, 0, canvas.width, canvas.height);
    source.close?.();

    const blob = await new Promise((resolve) =>
      canvas.toBlob(resolve, 'image/jpeg', QUALITY)
    );

    if (!blob) return file;

    return new File([blob], 'bag.jpg', { type: 'image/jpeg' });
  } catch (error) {
    console.warn('[صَبّة] Could not shrink the image; uploading the original.', error);
    return file;
  }
}
