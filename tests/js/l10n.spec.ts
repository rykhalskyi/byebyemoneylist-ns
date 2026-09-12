import { readdirSync, readFileSync, statSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const projectRoot = join(dirname(fileURLToPath(import.meta.url)), '..', '..')

interface Bundle {
	translations: Record<string, string | string[]>
	pluralForm: string
}

function loadBundle(language: string): Bundle {
	return JSON.parse(readFileSync(join(projectRoot, 'l10n', `${language}.json`), 'utf8'))
}

function walk(dir: string): string[] {
	return readdirSync(dir).flatMap((name) => {
		const path = join(dir, name)
		return statSync(path).isDirectory() ? walk(path) : [path]
	})
}

function collectSourceStrings(): { singular: Set<string>, plural: Set<string> } {
	const singular = new Set<string>()
	const plural = new Set<string>()
	for (const file of walk(join(projectRoot, 'src'))) {
		if (!/\.(vue|ts)$/.test(file)) {
			continue
		}
		const source = readFileSync(file, 'utf8')
		for (const match of source.matchAll(/(?<![A-Za-z0-9_])t\(\s*'((?:[^'\\]|\\.)*)'/g)) {
			singular.add(match[1].replace(/\\'/g, "'"))
		}
		for (const match of source.matchAll(/(?<![A-Za-z0-9_])n\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'((?:[^'\\]|\\.)*)'/g)) {
			plural.add(`_${match[1]}_::_${match[2]}_`)
		}
	}
	return { singular, plural }
}

function pluralCount(pluralForm: string): number {
	const match = pluralForm.match(/nplurals\s*=\s*(\d+)/)
	if (match === null) {
		throw new Error(`Cannot parse nplurals from "${pluralForm}"`)
	}
	return Number(match[1])
}

const languages = ['en', 'de', 'uk']
const bundles: Record<string, Bundle> = {}
for (const language of languages) {
	bundles[language] = loadBundle(language)
}

describe('l10n bundles', () => {
	it('have identical key sets across languages', () => {
		const reference = Object.keys(bundles.en.translations).sort()
		for (const language of languages.slice(1)) {
			expect(Object.keys(bundles[language].translations).sort()).toEqual(reference)
		}
	})

	it('store plural entries as arrays matching nplurals', () => {
		for (const language of languages) {
			const expected = pluralCount(bundles[language].pluralForm)
			for (const [key, value] of Object.entries(bundles[language].translations)) {
				if (key.startsWith('_') && key.includes('_::_')) {
					expect(Array.isArray(value), `${language} ${key}`).toBe(true)
					expect((value as string[]).length, `${language} ${key}`).toBe(expected)
				}
			}
		}
	})

	it('cover every string used in the source', () => {
		const { singular, plural } = collectSourceStrings()
		for (const text of singular) {
			expect(bundles.en.translations[text], `missing singular: ${text}`).toBeDefined()
		}
		for (const key of plural) {
			expect(bundles.en.translations[key], `missing plural: ${key}`).toBeDefined()
		}
	})
})
