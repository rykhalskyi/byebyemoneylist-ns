import type { AnalyticsOverview } from '../types.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

interface OcsData<T> {
	ocs: {
		data: T
	}
}

export async function fetchAnalyticsOverview(from: string, to: string): Promise<AnalyticsOverview> {
	const { data } = await axios.get<OcsData<AnalyticsOverview>>(
		generateOcsUrl('/apps/byebyemoneylist/api/analytics/overview'),
		{ params: { from, to } },
	)
	return data.ocs.data
}
