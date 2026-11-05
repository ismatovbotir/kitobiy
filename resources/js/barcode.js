/**
 * Returns a BarcodeDetector that reads EAN-13 (ISBN) barcodes.
 *
 * Uses the browser's built-in detector when it exists (Chrome on Android).
 * Elsewhere (iPhone Safari, Firefox, desktop) it lazy-loads the zxing-wasm
 * ponyfill, with the .wasm file served from our own build instead of a CDN.
 */
export async function createIsbnDetector() {
    const formats = ['ean_13'];

    if ('BarcodeDetector' in window) {
        const supported = await window.BarcodeDetector.getSupportedFormats().catch(() => []);

        if (supported.includes('ean_13')) {
            return new window.BarcodeDetector({ formats });
        }
    }

    const [{ BarcodeDetector, prepareZXingModule }, { default: wasmUrl }] = await Promise.all([
        import('barcode-detector/ponyfill'),
        import('zxing-wasm/reader/zxing_reader.wasm?url'),
    ]);

    prepareZXingModule({
        overrides: {
            locateFile: (path, prefix) => (path.endsWith('.wasm') ? wasmUrl : prefix + path),
        },
    });

    return new BarcodeDetector({ formats });
}

/**
 * Camera scanning only needs camera access; the detector itself is always available.
 */
export function canScanBarcodes() {
    return !! navigator.mediaDevices?.getUserMedia;
}
