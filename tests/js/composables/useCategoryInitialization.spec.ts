import type { Category } from '../../../src/types.ts'

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useCategoryInitialization } from '../../../src/composables/useCategoryInitialization.ts'
import * as api from '../../../src/services/listsApi.ts'

vi.mock('../../../src/services/listsApi.ts', () => ({
	fetchCategories: vi.fn(),
	createCategoriesBatch: vi.fn(),
}))

function category(overrides: Partial<Category> = {}): Category {
	return {
		id: 'cat-1',
		name: 'Groceries',
		color: null,
		emoji: null,
		parentId: null,
		income: false,
		...overrides,
	}
}

describe('useCategoryInitialization', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('prompts only when the user has no categories', async () => {
		vi.mocked(api.fetchCategories).mockResolvedValueOnce([])
		expect(await useCategoryInitialization().shouldPrompt()).toBe(true)

		vi.mocked(api.fetchCategories).mockResolvedValueOnce([category()])
		expect(await useCategoryInitialization().shouldPrompt()).toBe(false)
	})

	it('does not prompt when categories cannot be loaded', async () => {
		vi.mocked(api.fetchCategories).mockRejectedValue(new Error('boom'))
		expect(await useCategoryInitialization().shouldPrompt()).toBe(false)
	})

	it('creates the confirmed default set for an empty account', async () => {
		vi.mocked(api.fetchCategories).mockResolvedValue([])
		vi.mocked(api.createCategoriesBatch).mockResolvedValue([category()])

		const { initialize, busy, error } = useCategoryInitialization()
		const result = await initialize()

		expect(result).toHaveLength(1)
		expect(busy.value).toBe(false)
		expect(error.value).toBeNull()
		expect(api.createCategoriesBatch).toHaveBeenCalledTimes(1)
		expect(api.createCategoriesBatch).toHaveBeenCalledWith(expect.any(Array), true)

		const [payload] = vi.mocked(api.createCategoriesBatch).mock.calls[0]
		expect(payload).toHaveLength(33)
		expect(payload.every((item) => item.status === 'confirmed')).toBe(true)
	})

	it('re-checks before creating and skips when categories appeared', async () => {
		vi.mocked(api.fetchCategories).mockResolvedValue([category()])

		const result = await useCategoryInitialization().initialize()

		expect(result).toHaveLength(1)
		expect(api.createCategoriesBatch).not.toHaveBeenCalled()
	})

	it('surfaces an error and reports failure when creation fails', async () => {
		vi.mocked(api.fetchCategories).mockResolvedValue([])
		vi.mocked(api.createCategoriesBatch).mockRejectedValue(new Error('boom'))

		const { initialize, busy, error } = useCategoryInitialization()
		const result = await initialize()

		expect(result).toBeNull()
		expect(busy.value).toBe(false)
		expect(error.value).toBe('Failed to create the category. Please try again.')
	})
})
