import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import SharedOwnerSection from '../../../../src/components/catalog/SharedOwnerSection.vue'

function render(expanded: boolean) {
	return mount(SharedOwnerSection, {
		props: { owner: 'alice', expanded },
		slots: { default: '<p>Child</p>' },
	})
}

describe('SharedOwnerSection', () => {
	it('labels the section with the owner', () => {
		const wrapper = render(true)
		expect(wrapper.text()).toContain('alice')
	})

	it('renders the slot only when expanded', () => {
		expect(render(true).text()).toContain('Child')
		expect(render(false).text()).not.toContain('Child')
	})

	it('emits toggle on header click', async () => {
		const wrapper = render(true)
		await wrapper.find('button').trigger('click')
		expect(wrapper.emitted('toggle')).toHaveLength(1)
	})
})
