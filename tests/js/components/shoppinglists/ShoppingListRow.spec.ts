import type { ShoppingList } from '../../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import ShoppingListRow from '../../../../src/components/shoppinglists/ShoppingListRow.vue'

vi.mock('../../../../src/services/listsApi.ts', () => ({
	fetchProductPicture: vi.fn(),
}))

function list(overrides: Partial<ShoppingList> = {}): ShoppingList {
	return {
		id: 'l1',
		name: 'Weekly groceries',
		storeId: null,
		categoryId: null,
		status: 'new',
		finalTotal: null,
		totalPrice: null,
		createdAt: null,
		isIncome: false,
		isSubscription: false,
		isRecurring: false,
		hasReceipt: false,
		...overrides,
	}
}

async function render(props: { list: ShoppingList, expanded?: boolean }) {
	const wrapper = mount(ShoppingListRow, {
		props: {
			list: props.list,
			stores: [],
			categories: [],
			products: [],
			items: [],
			itemsLoading: false,
			itemsError: '',
			expanded: props.expanded ?? false,
		},
	})
	await nextTick()
	return wrapper
}

async function openMenu(wrapper: Awaited<ReturnType<typeof render>>) {
	await wrapper.find('.action-item__menutoggle').trigger('click')
	await flushPromises()
	await nextTick()
	await flushPromises()
	await nextTick()
}

describe('ShoppingListRow', () => {
	it('renders the list name', async () => {
		const wrapper = await render({ list: list() })
		expect(wrapper.text()).toContain('Weekly groceries')
	})

	it('marks a shared list and offers copying instead of deleting', async () => {
		const wrapper = await render({ list: list({ sharedBy: 'alice', shareMode: 'readonly' }) })
		expect(wrapper.text()).toContain('alice')

		await openMenu(wrapper)
		const names = wrapper.findAllComponents(NcActionButton).map((button) => button.text())
		expect(names).toContain('Copy to my catalog')
		expect(names).not.toContain('Delete')
	})

	it('offers delete for owned lists and no copy action', async () => {
		const wrapper = await render({ list: list({ hasReceipt: true }) })

		await openMenu(wrapper)
		const names = wrapper.findAllComponents(NcActionButton).map((button) => button.text())
		expect(names).toContain('Delete')
		expect(names).not.toContain('Copy to my catalog')
	})

	it('offers a share action for owned lists but not for shared ones', async () => {
		const owned = await render({ list: list() })
		await openMenu(owned)
		expect(owned.findAllComponents(NcActionButton).map((button) => button.text())).toContain('Share')

		const shared = await render({ list: list({ sharedBy: 'alice' }) })
		await openMenu(shared)
		expect(shared.findAllComponents(NcActionButton).map((button) => button.text())).not.toContain('Share')
	})

	it('renders shared read-only lists with a read-only hint when expanded', async () => {
		const wrapper = await render({ list: list({ sharedBy: 'alice', shareMode: 'readonly' }), expanded: true })
		expect(wrapper.text()).toContain('This list is shared with you and is read-only.')
		expect(wrapper.text()).not.toContain('Add product')
	})

	it('greys out revoked lists', async () => {
		const wrapper = await render({ list: list({ sharedBy: 'alice', revoked: true }) })
		expect(wrapper.html()).toContain('revoked')
	})
})
