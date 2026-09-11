import { getCanonicalLocale, t as translate, n as translatePlural } from '@nextcloud/l10n'

export { getCanonicalLocale }

export const APPLICATION_ID = 'byebyemoneylist'

export type TranslationVars = Record<string, string | number>

export function t(text: string, vars?: TranslationVars): string {
	return translate(APPLICATION_ID, text, vars)
}

export function n(singular: string, plural: string, count: number, vars?: TranslationVars): string {
	return translatePlural(APPLICATION_ID, singular, plural, count, vars)
}
