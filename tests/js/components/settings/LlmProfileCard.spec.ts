import type { LlmProfile } from '../../../../src/types.ts'

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import LlmProfileCard from '../../../../src/components/settings/LlmProfileCard.vue'

function mockProfile(overrides: Partial<LlmProfile> = {}): LlmProfile {
	return {
		id: 'prof-1',
		name: 'My DeepSeek',
		provider: 'deepseek',
		apiKeyMasked: '••••••••1234',
		model: 'deepseek-v4-flash-vision-exp',
		connectTimeoutSeconds: 30,
		readTimeoutSeconds: 60,
		maxTokens: 2048,
		isActive: false,
		createdAt: '2026-09-14T10:00:00Z',
		...overrides,
	}
}

describe('LlmProfileCard.vue', () => {
	it('renders profile details correctly', () => {
		const profile = mockProfile({
			name: 'Test Profile',
			provider: 'deepseek',
			apiKeyMasked: '••••••••5678',
		})

		const wrapper = mount(LlmProfileCard, {
			props: {
				profile,
			},
		})

		expect(wrapper.text()).toContain('Test Profile')
		expect(wrapper.text()).toContain('DeepSeek')
		expect(wrapper.text()).toContain('••••••••5678')
		expect(wrapper.text()).toContain('deepseek-v4-flash-vision-exp')
	})

	it('emits select when card is clicked', async () => {
		const profile = mockProfile()
		const wrapper = mount(LlmProfileCard, {
			props: {
				profile,
			},
		})

		await wrapper.trigger('click')
		expect(wrapper.emitted('select')).toHaveLength(1)
		expect(wrapper.emitted('select')?.[0]).toEqual([profile])
	})

	it('emits edit when edit button is clicked', async () => {
		const profile = mockProfile()
		const wrapper = mount(LlmProfileCard, {
			props: {
				profile,
			},
		})

		const editBtn = wrapper.find('button[aria-label="Edit profile"]')
		await editBtn.trigger('click')
		expect(wrapper.emitted('edit')).toHaveLength(1)
		expect(wrapper.emitted('edit')?.[0]).toEqual([profile])
	})
})
