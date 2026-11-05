/**
 * Cover photo upload (Alpine). Shrinks the photo in the browser first: phone
 * photos are larger than the upload limit of most shared hosts and slow on
 * mobile data. If the browser cannot do it, the original goes up and the
 * server checks it as usual.
 */
const MAX_SIDE = 1200;
const JPEG_QUALITY = 0.85;

async function shrinkPhoto(file) {
    const objectUrl = URL.createObjectURL(file);

    try {
        const image = new Image();
        image.src = objectUrl;
        await image.decode();

        const scale = Math.min(1, MAX_SIDE / Math.max(image.naturalWidth, image.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(image.naturalWidth * scale);
        canvas.height = Math.round(image.naturalHeight * scale);

        const context = canvas.getContext('2d');
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));

        return blob ? new File([blob], 'cover.jpg', { type: 'image/jpeg' }) : file;
    } catch {
        return file;
    } finally {
        URL.revokeObjectURL(objectUrl);
    }
}

export function coverUpload() {
    return {
        uploading: false,
        failed: false,

        async choose(event) {
            const [file] = event.target.files;

            if (!file) {
                return;
            }

            this.uploading = true;
            this.failed = false;

            this.$wire.upload(
                'coverPhoto',
                await shrinkPhoto(file),
                () => {
                    this.uploading = false;
                },
                () => {
                    this.uploading = false;
                    this.failed = true;
                },
            );

            event.target.value = '';
        },
    };
}
