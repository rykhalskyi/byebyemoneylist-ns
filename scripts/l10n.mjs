import { readdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const appId = 'byebyemoneylist'
const l10nDir = join(dirname(fileURLToPath(import.meta.url)), '..', 'l10n')

function formatTranslations(translations, level) {
	const pad = '\t'.repeat(level)
	const entries = Object.keys(translations)
		.sort()
		.map((key) => `${pad}\t${JSON.stringify(key)} : ${JSON.stringify(translations[key])}`)
		.join(',\n')
	return `{\n${entries}\n${pad}}`
}

const files = readdirSync(l10nDir).filter((name) => name.endsWith('.json'))

for (const file of files) {
	const language = file.slice(0, -'.json'.length)
	const data = JSON.parse(readFileSync(join(l10nDir, file), 'utf8'))

	if (typeof data.translations !== 'object' || data.translations === null) {
		throw new Error(`${file}: missing "translations" object`)
	}
	if (typeof data.pluralForm !== 'string' || data.pluralForm === '') {
		throw new Error(`${file}: missing "pluralForm" string`)
	}

	const output = `OC.L10N.register(\n\t${JSON.stringify(appId)},\n\t${formatTranslations(data.translations, 1)},\n\t${JSON.stringify(data.pluralForm)});\n`
	writeFileSync(join(l10nDir, `${language}.js`), output, 'utf8')
}

console.log(`Compiled ${files.length} translation file(s) for ${appId}`)
