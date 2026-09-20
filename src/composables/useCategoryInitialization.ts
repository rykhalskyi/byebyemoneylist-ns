import type { Category } from '../types.ts'

import { ref } from 'vue'
import { buildDefaultCategoryPayload } from '../constants/defaultCategories.ts'
import { createCategoriesBatch, fetchCategories } from '../services/listsApi.ts'
import { t } from '../utils/l10n.ts'

export function useCategoryInitialization() {
	const busy = ref(false)
	const error = ref<string | null>(null)

	async function shouldPrompt(): Promise<boolean> {
		try {
			const categories = await fetchCategories()
			return categories.length === 0
		} catch {
			return false
		}
	}

	async function initialize(): Promise<Category[] | null> {
		busy.value = true
		error.value = null

		try {
			const existing = await fetchCategories()
			if (existing.length > 0) {
				return existing
			}

			return await createCategoriesBatch(buildDefaultCategoryPayload(), true)
		} catch {
			error.value = t('Failed to create the category. Please try again.')
			return null
		} finally {
			busy.value = false
		}
	}

	return { busy, error, shouldPrompt, initialize }
}
