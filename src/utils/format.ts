import { getCanonicalLocale } from './l10n.ts'

export function formatDate(iso: string | null): string {
	if (iso === null) {
		return ''
	}
	const date = new Date(iso)
	if (Number.isNaN(date.getTime())) {
		return ''
	}
	return date.toLocaleDateString(getCanonicalLocale(), { dateStyle: 'medium' })
}

export function formatMonth(month: number): string {
	return new Date(2000, month - 1, 1).toLocaleDateString(getCanonicalLocale(), { month: 'long' })
}

export function formatTotal(total: number | null): string {
	if (total === null) {
		return ''
	}
	return new Intl.NumberFormat(getCanonicalLocale(), {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	}).format(total)
}
