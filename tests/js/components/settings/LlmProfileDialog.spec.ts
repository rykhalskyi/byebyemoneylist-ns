import type { LlmProfile } from '../../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LlmProfileDialog from '../../../../src/components/settings/LlmProfileDialog.vue'
import * as llmApi from '../../../../src/services/llmApi.ts'

vi.mock('../../../../src/services/llmApi.ts', () => ({
	createLlmProfile: vi.fn(),
	updateLlmProfile: vi.fn(),
	deleteLlmProfile: vi.fn(),
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

describe('LlmProfileDialog.vue', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('creates a profile on submit when adding', async () => {
		const createdProfile = mockProfile({ id: 'new-1', name: 'New Profile' })
		vi.mocked(llmApi.createLlmProfile).mockResolvedValue(createdProfile)

		const wrapper = mount(LlmProfileDialog, {
			props: {
				open: true,
			},
			global: {
				stubs: {
					teleport: true,
				},
			},
		})
		await flushPromises()

		const nameInput = wrapper.find('input[type="text"]')
		await nameInput.setValue('New Profile')

		const keyInput = wrapper.find('input[type="password"]')
		await keyInput.setValue('sk-test-key-12345')

		const form = wrapper.find('form')
		await form.trigger('submit')
		await flushPromises()

		expect(llmApi.createLlmProfile).toHaveBeenCalledWith(expect.objectContaining({
			name: 'New Profile',
			apiKey: 'sk-test-key-12345',
		}))
		expect(wrapper.emitted('created')?.[0]).toEqual([createdProfile])
		expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
	})

	it('updates a profile on submit when editing', async () => {
		const existingProfile = mockProfile()
		const updatedProfile = { ...existingProfile, name: 'Updated Profile' }
		vi.mocked(llmApi.updateLlmProfile).mockResolvedValue(updatedProfile)

		const wrapper = mount(LlmProfileDialog, {
			props: {
				open: true,
				entity: existingProfile,
			},
			global: {
				stubs: {
					teleport: true,
				},
			},
		})
		await flushPromises()

		const nameInput = wrapper.find('input[type="text"]')
		await nameInput.setValue('Updated Profile')

		const form = wrapper.find('form')
		await form.trigger('submit')
		await flushPromises()

		expect(llmApi.updateLlmProfile).toHaveBeenCalledWith('prof-1', expect.objectContaining({
			name: 'Updated Profile',
		}))
		expect(wrapper.emitted('updated')?.[0]).toEqual([updatedProfile])
		expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
	})

	it('deletes a profile when delete button is clicked in edit mode', async () => {
		const existingProfile = mockProfile()
		vi.mocked(llmApi.deleteLlmProfile).mockResolvedValue(undefined)

		const wrapper = mount(LlmProfileDialog, {
			props: {
				open: true,
				entity: existingProfile,
			},
			global: {
				stubs: {
					teleport: true,
				},
			},
		})
		await flushPromises()

		const deleteBtn = wrapper.findAll('button').find((btn) => btn.text().includes('Delete'))
		expect(deleteBtn).toBeDefined()
		await deleteBtn?.trigger('click')
		await flushPromises()

		expect(llmApi.deleteLlmProfile).toHaveBeenCalledWith('prof-1')
		expect(wrapper.emitted('deleted')?.[0]).toEqual(['prof-1'])
		expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
	})
})
