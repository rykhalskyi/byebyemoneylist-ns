import type { LlmProfile, LlmProfilePayload } from '../types.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

interface OcsData<T> {
	ocs: {
		data: T
	}
}

export async function fetchLlmProfiles(): Promise<LlmProfile[]> {
	const { data } = await axios.get<OcsData<{ profiles: LlmProfile[] }>>(generateOcsUrl('/apps/byebyemoneylist/api/llm-profiles'))
	return data.ocs.data.profiles
}

export async function createLlmProfile(payload: LlmProfilePayload): Promise<LlmProfile> {
	const { data } = await axios.post<OcsData<{ profile: LlmProfile }>>(
		generateOcsUrl('/apps/byebyemoneylist/api/llm-profiles'),
		payload,
	)
	return data.ocs.data.profile
}

export async function updateLlmProfile(id: string, payload: LlmProfilePayload): Promise<LlmProfile> {
	const { data } = await axios.put<OcsData<{ profile: LlmProfile }>>(
		generateOcsUrl(`/apps/byebyemoneylist/api/llm-profiles/${id}`),
		payload,
	)
	return data.ocs.data.profile
}

export async function deleteLlmProfile(id: string): Promise<void> {
	await axios.delete(generateOcsUrl(`/apps/byebyemoneylist/api/llm-profiles/${id}`))
}

export async function activateLlmProfile(id: string, active = true): Promise<void> {
	await axios.post(generateOcsUrl(`/apps/byebyemoneylist/api/llm-profiles/${id}/activate`), { active })
}
