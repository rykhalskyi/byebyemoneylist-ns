export interface DashboardRange {
	from: string
	to: string
}

export function todayRange(now: Date = new Date()): DashboardRange {
	const from = new Date(now.getFullYear(), now.getMonth(), now.getDate())
	const to = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1)
	return { from: from.toISOString(), to: to.toISOString() }
}

export function monthRange(now: Date = new Date()): DashboardRange {
	const from = new Date(now.getFullYear(), now.getMonth(), 1)
	const to = new Date(now.getFullYear(), now.getMonth() + 1, 1)
	return { from: from.toISOString(), to: to.toISOString() }
}
