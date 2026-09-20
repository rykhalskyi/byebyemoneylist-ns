import { enableAutoUnmount, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import InitializeCategoriesDialog from '../../../src/components/InitializeCategoriesDialog.vue'

enableAutoUnmount(afterEach)

function render(props: Record<string, unknown> = {}) {
	return mount(InitializeCategoriesDialog, {
		props: { open: true, ...props },
		global: { stubs: { teleport: true } },
	})
}

describe('InitializeCategoriesDialog', () => {
	it('emits confirm when the user opts in', async () => {
		const wrapper = render()

		const createButton = wrapper.findAll('button').find((button) => button.text() === 'Create')
		expect(createButton).toBeDefined()
		await createButton!.trigger('click')

		expect(wrapper.emitted('confirm')).toHaveLength(1)
	})

	it('closes without confirming when the user opts out', async () => {
		const wrapper = render()

		const notNowButton = wrapper.findAll('button').find((button) => button.text() === 'Not now')
		expect(notNowButton).toBeDefined()
		await notNowButton!.trigger('click')

		expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
		expect(wrapper.emitted('confirm')).toBeUndefined()
	})

	it('shows the error when creation failed', () => {
		const wrapper = render({ error: 'Failed to create the category. Please try again.' })

		expect(wrapper.text()).toContain('Failed to create the category')
	})
})
