export function formatDate(iso: string | null): string {
	if (iso === null) {
		return ''
	}
	const date = new Date(iso)
	if (Number.isNaN(date.getTime())) {
		return ''
	}
	return date.toLocaleDateString(undefined, { dateStyle: 'medium' })
}

export function formatTotal(total: number | null): string {
	if (total === null) {
		return ''
	}
	return new Intl.NumberFormat(undefined, {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	}).format(total)
}
