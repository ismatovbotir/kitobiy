import { canScanBarcodes, createIsbnDetector } from './barcode';
import { coverUpload } from './cover-upload';
import { labelDesigner } from './label-designer';

window.Kitobiy = { canScanBarcodes, createIsbnDetector };

document.addEventListener('alpine:init', () => {
    window.Alpine.data('coverUpload', coverUpload);
    window.Alpine.data('labelDesigner', labelDesigner);
});
