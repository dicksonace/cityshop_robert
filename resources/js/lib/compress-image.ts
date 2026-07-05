const MAX_WIDTH = 1920;
const JPEG_QUALITY = 0.85;
const SKIP_BELOW_BYTES = 800 * 1024;

/**
 * Resize and compress phone camera photos before upload so multi-image posts
 * stay under PHP post limits and upload faster on mobile networks.
 */
export async function compressProductImage(file: File): Promise<File> {
    if (!file.type.startsWith('image/') || file.type === 'image/gif') {
        return file;
    }

    if (file.size <= SKIP_BELOW_BYTES) {
        return file;
    }

    return new Promise((resolve) => {
        const img = new Image();
        const url = URL.createObjectURL(file);

        img.onload = () => {
            URL.revokeObjectURL(url);

            let { width, height } = img;

            if (width > MAX_WIDTH) {
                height = Math.round((height * MAX_WIDTH) / width);
                width = MAX_WIDTH;
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;

            const ctx = canvas.getContext('2d');
            if (!ctx) {
                resolve(file);
                return;
            }

            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob(
                (blob) => {
                    if (!blob || blob.size >= file.size) {
                        resolve(file);
                        return;
                    }

                    const baseName = file.name.replace(/\.[^.]+$/, '') || 'image';
                    resolve(
                        new File([blob], `${baseName}.jpg`, {
                            type: 'image/jpeg',
                            lastModified: Date.now(),
                        }),
                    );
                },
                'image/jpeg',
                JPEG_QUALITY,
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(file);
        };

        img.src = url;
    });
}

export async function compressProductImages(files: File[]): Promise<File[]> {
    return Promise.all(files.map((file) => compressProductImage(file)));
}
