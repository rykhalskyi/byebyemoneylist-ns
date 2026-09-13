import type { DashboardSpending } from '../types.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

interface OcsData<T> {
	ocs: {
		data: T
	}
}

export interface SpendingQuery {
	from: string
	to: string
	categoryId?: string | null
}

export async function fetchSpending(query: SpendingQuery): Promise<DashboardSpending> {
	const params: Record<string, string> = { from: query.from, to: query.to }
	if (query.categoryId !== null && query.categoryId !== undefined && query.categoryId !== '') {
		params.categoryId = query.categoryId
	}
	const { data } = await axios.get<OcsData<DashboardSpending>>(
		generateOcsUrl('/apps/byebyemoneylist/api/dashboard/spending'),
		{ params },
	)
	return data.ocs.data
}
