const MAX_DIMENSION = 1024
const JPEG_QUALITY = 0.6

/**
 * Downscale an image and re-encode it as JPEG on a canvas. Drawing into a canvas
 * drops all EXIF metadata (including GPS) and the re-encode shrinks the upload,
 * mirroring the Android scanner's bitmap normalization.
 *
 * @param file the original receipt image
 * @param maxDimension the longest edge of the result (default 1024)
 * @param quality the JPEG quality between 0 and 1 (default 0.6)
 * @return the normalized JPEG blob
 */
export async function normalizeReceiptImage(file: File, maxDimension = MAX_DIMENSION, quality = JPEG_QUALITY): Promise<Blob> {
	const objectUrl = URL.createObjectURL(file)
	try {
		const image = await loadImage(objectUrl)
		const longestEdge = Math.max(image.naturalWidth, image.naturalHeight)
		const scale = longestEdge > 0 ? Math.min(1, maxDimension / longestEdge) : 1
		const width = Math.max(1, Math.round(image.naturalWidth * scale))
		const height = Math.max(1, Math.round(image.naturalHeight * scale))

		const canvas = document.createElement('canvas')
		canvas.width = width
		canvas.height = height

		const context = canvas.getContext('2d')
		if (context === null) {
			throw new Error('Canvas is not supported')
		}
		context.drawImage(image, 0, 0, width, height)

		const blob = await canvasToBlob(canvas, quality)
		if (blob === null) {
			throw new Error('Failed to encode the image')
		}
		return blob
	} finally {
		URL.revokeObjectURL(objectUrl)
	}
}

function loadImage(src: string): Promise<HTMLImageElement> {
	return new Promise((resolve, reject) => {
		const image = new Image()
		image.onload = () => resolve(image)
		image.onerror = () => reject(new Error('Failed to load the image'))
		image.src = src
	})
}

function canvasToBlob(canvas: HTMLCanvasElement, quality: number): Promise<Blob | null> {
	return new Promise((resolve) => {
		canvas.toBlob((blob) => resolve(blob), 'image/jpeg', quality)
	})
}
