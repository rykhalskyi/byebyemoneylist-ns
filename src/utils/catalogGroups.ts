export interface OwnerGroup<T> {
	owner: string
	items: T[]
}

export interface GroupedByOwner<T> {
	mine: T[]
	shared: OwnerGroup<T>[]
}

/**
 * Split catalog items into the current user's own items and one group per other
 * owner. Groups are sorted by owner id so the order is stable.
 *
 * @param items the items to group
 * @return the own items plus the per-owner groups
 */
export function groupByOwner<T extends { shared?: boolean, owner?: string }>(items: readonly T[]): GroupedByOwner<T> {
	const mine: T[] = []
	const groups = new Map<string, T[]>()

	for (const item of items) {
		if (item.shared !== true) {
			mine.push(item)
			continue
		}
		const owner = item.owner ?? ''
		const bucket = groups.get(owner) ?? []
		bucket.push(item)
		groups.set(owner, bucket)
	}

	const shared = [...groups.entries()]
		.map(([owner, grouped]): OwnerGroup<T> => ({ owner, items: grouped }))
		.sort((a, b) => a.owner.localeCompare(b.owner))

	return { mine, shared }
}
