import type { LlmProfile } from '../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LlmProfileCard from '../../../src/components/settings/LlmProfileCard.vue'
import LlmProfileDialog from '../../../src/components/settings/LlmProfileDialog.vue'
import Settings from '../../../src/views/Settings.vue'
import * as llmApi from '../../../src/services/llmApi.ts'

vi.mock('../../../src/services/llmApi.ts', () => ({
	fetchLlmProfiles: vi.fn(),
	createLlmProfile: vi.fn(),
	updateLlmProfile: vi.fn(),
	deleteLlmProfile: vi.fn(),
	activateLlmProfile: vi.fn(),
}))

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

describe('Settings.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('renders empty state when there are no profiles', async () => {
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([])

		const wrapper = mount(Settings)
		await flushPromises()

		expect(wrapper.text()).toContain('No LLM profiles configured')
		expect(wrapper.findAllComponents(LlmProfileCard).length).toBe(0)
	})

	it('renders list of profile cards when profiles exist', async () => {
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([
			mockProfile({ id: 'prof-1', name: 'DeepSeek Profile', isActive: true }),
			mockProfile({ id: 'prof-2', name: 'SiliconFlow Profile', provider: 'siliconflow' }),
		])

		const wrapper = mount(Settings)
		await flushPromises()

		const cards = wrapper.findAllComponents(LlmProfileCard)
		expect(cards.length).toBe(2)
		expect(wrapper.text()).toContain('DeepSeek Profile')
		expect(wrapper.text()).toContain('SiliconFlow Profile')
	})

	it('activates a profile when selected', async () => {
		const profile1 = mockProfile({ id: 'prof-1', name: 'DeepSeek', isActive: false })
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([profile1])
		vi.mocked(llmApi.activateLlmProfile).mockResolvedValue(undefined)

		const wrapper = mount(Settings)
		await flushPromises()

		const card = wrapper.findComponent(LlmProfileCard)
		card.vm.$emit('select', profile1)
		await flushPromises()

		expect(llmApi.activateLlmProfile).toHaveBeenCalledWith('prof-1', true)
	})

	it('opens dialog when add profile button is clicked', async () => {
		vi.mocked(llmApi.fetchLlmProfiles).mockResolvedValue([])

		const wrapper = mount(Settings)
		await flushPromises()

		const addButtons = wrapper.findAll('button')
		const addButton = addButtons.find((btn) => btn.text().includes('Add LLM Profile'))
		expect(addButton).toBeDefined()
		await addButton?.trigger('click')

		const dialog = wrapper.findComponent(LlmProfileDialog)
		expect(dialog.props('open')).toBe(true)
		expect(dialog.props('entity')).toBeUndefined()
	})
})
