import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import { CATEGORY_COLOR_PALETTE } from '../../../src/constants/categoryColors.ts'
import { buildDefaultCategoryPayload, DEFAULT_CATEGORIES } from '../../../src/constants/defaultCategories.ts'

const projectRoot = join(dirname(fileURLToPath(import.meta.url)), '..', '..', '..')

function bundle(language: string): { translations: Record<string, string> } {
	return JSON.parse(readFileSync(join(projectRoot, 'l10n', `${language}.json`), 'utf8'))
}

const languages = ['en', 'de', 'uk']
const bundles = Object.fromEntries(languages.map((language) => [language, bundle(language)]))

const roots = DEFAULT_CATEGORIES
const children = DEFAULT_CATEGORIES.flatMap((category) => category.children)

describe('defaultCategories', () => {
	it('mirrors the Android default set (7 roots, 26 children)', () => {
		expect(roots).toHaveLength(7)
		expect(children).toHaveLength(26)
	})

	it('uses unique tempIds and palette colors', () => {
		const keys = [...roots, ...children].map((category) => category.key)
		expect(new Set(keys).size).toBe(keys.length)
		for (const category of [...roots, ...children]) {
			expect(CATEGORY_COLOR_PALETTE).toContain(category.color)
			expect(category.emoji.length).toBeGreaterThan(0)
		}
	})

	it('marks only the Income branch as income', () => {
		const incomeRoots = roots.filter((category) => category.income).map((category) => category.key)
		expect(incomeRoots).toEqual(['income'])
	})

	it('has every category name translated in en, de and uk', () => {
		for (const category of [...roots, ...children]) {
			for (const language of languages) {
				expect(bundles[language].translations[category.name], `${language}: ${category.name}`).toBeDefined()
			}
		}
	})

	it('builds a confirmed batch payload with resolvable parents', () => {
		const payload = buildDefaultCategoryPayload()
		expect(payload).toHaveLength(33)
		expect(payload.every((item) => item.status === 'confirmed')).toBe(true)

		const tempIds = new Set(payload.map((item) => item.tempId))
		for (const item of payload) {
			if (item.parentId !== undefined && item.parentId !== null) {
				expect(tempIds.has(item.parentId)).toBe(true)
			}
		}

		const income = payload.filter((item) => item.income)
		expect(income.map((item) => item.tempId)).toEqual([
			'income',
			'income-salary',
			'income-freelance',
		])
	})
})
