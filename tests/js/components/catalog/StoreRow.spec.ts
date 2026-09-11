import type { Store } from '../../../../src/types.ts'

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import StoreRow from '../../../../src/components/catalog/StoreRow.vue'

function store(overrides: Partial<Store> = {}): Store {
	return {
		id: 's1',
		name: 'Aldi',
		address: 'Hauptstraße 1',
		categoryIds: [],
		...overrides,
	}
}

async function render(props: { store: Store, accentColor?: string | null, search?: string }) {
	const wrapper = mount(StoreRow, { props })
	await nextTick()
	return wrapper
}

describe('StoreRow', () => {
	it('renders the name and address', async () => {
		const wrapper = await render({ store: store() })
		expect(wrapper.text()).toContain('Aldi')
		expect(wrapper.text()).toContain('Hauptstraße 1')
	})

	it('omits the address when there is none', async () => {
		const wrapper = await render({ store: store({ address: null }) })
		expect(wrapper.text()).not.toContain('Hauptstraße')
	})

	it('applies the accent color', async () => {
		const wrapper = await render({ store: store(), accentColor: '#00ff00' })
		expect(wrapper.attributes('style')).toContain('rgb(0, 255, 0)')
	})

	it('emits edit', async () => {
		const value = store()
		const wrapper = await render({ store: value })
		await wrapper.find('button[aria-label="Edit Aldi"]').trigger('click')
		expect(wrapper.emitted('edit')?.[0]).toEqual([value])
	})

	it('emits delete', async () => {
		const value = store()
		const wrapper = await render({ store: value })
		await wrapper.find('button[aria-label="Delete Aldi"]').trigger('click')
		expect(wrapper.emitted('delete')?.[0]).toEqual([value])
	})
})
