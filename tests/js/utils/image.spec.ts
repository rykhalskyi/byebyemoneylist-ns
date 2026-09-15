import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { normalizeReceiptImage } from '../../../src/utils/image.ts'

let imageSize = { width: 2048, height: 1024 }

vi.stubGlobal('Image', class {
	get naturalWidth(): number {
		return imageSize.width
	}

	get naturalHeight(): number {
		return imageSize.height
	}

	onload: (() => void) | null = null
	onerror: (() => void) | null = null

	set src(_value: string) {
		queueMicrotask(() => this.onload?.())
	}
})

const createdCanvases: HTMLCanvasElement[] = []
const originalCreateElement = document.createElement.bind(document)
const drawImage = vi.fn()

describe('normalizeReceiptImage', () => {
	beforeEach(() => {
		imageSize = { width: 2048, height: 1024 }
		createdCanvases.length = 0
		drawImage.mockClear()

		Object.defineProperty(URL, 'createObjectURL', { configurable: true, writable: true, value: vi.fn(() => 'blob:source') })
		Object.defineProperty(URL, 'revokeObjectURL', { configurable: true, writable: true, value: vi.fn() })

		vi.spyOn(document, 'createElement').mockImplementation((tagName: string) => {
			const element = originalCreateElement(tagName)
			if (tagName === 'canvas') {
				createdCanvases.push(element as HTMLCanvasElement)
			}
			return element
		})
		vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({ drawImage } as unknown as CanvasRenderingContext2D)
		vi.spyOn(HTMLCanvasElement.prototype, 'toBlob').mockImplementation((callback: BlobCallback, type?: string) => {
			callback(new Blob(['jpeg'], { type: type ?? 'image/jpeg' }))
		})
	})

	afterEach(() => {
		vi.restoreAllMocks()
	})

	it('re-encodes the receipt as a JPEG blob', async () => {
		const result = await normalizeReceiptImage(new File(['x'], 'receipt.png', { type: 'image/png' }))

		expect(result).toBeInstanceOf(Blob)
		expect(result.type).toBe('image/jpeg')
		expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:source')
	})

	it('downscales the longest edge to the max dimension', async () => {
		await normalizeReceiptImage(new File(['x'], 'receipt.png', { type: 'image/png' }))

		expect(createdCanvases).toHaveLength(1)
		expect(createdCanvases[0].width).toBe(1024)
		expect(createdCanvases[0].height).toBe(512)
		expect(drawImage).toHaveBeenCalledOnce()
	})

	it('does not upscale images smaller than the max dimension', async () => {
		imageSize = { width: 800, height: 600 }
		await normalizeReceiptImage(new File(['x'], 'receipt.png', { type: 'image/png' }))

		expect(createdCanvases[0].width).toBe(800)
		expect(createdCanvases[0].height).toBe(600)
	})
})
