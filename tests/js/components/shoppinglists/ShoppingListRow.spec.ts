import type { ListItem, ShoppingList } from '../../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcChip from '@nextcloud/vue/components/NcChip'
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
		kind: 'PURCHASE',
		isActive: false,
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

async function render(props: { list: ShoppingList, expanded?: boolean, items?: ListItem[] }) {
	const wrapper = mount(ShoppingListRow, {
		props: {
			list: props.list,
			stores: [],
			categories: [],
			products: [],
			items: props.items ?? [],
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

	it('marks an owned shared list and opens the share management dialog from the mark', async () => {
		const wrapper = await render({ list: list({ hasShares: true }) })
		const marks = wrapper.findAllComponents(NcChip).filter((chip) => chip.text().includes('Shared'))
		expect(marks).toHaveLength(1)

		await marks[0]!.trigger('click')
		expect(wrapper.emitted('manageShares')).toHaveLength(1)
		expect(wrapper.emitted('share')).toBeUndefined()
	})

	it('shows no shared mark on owned lists without shares', async () => {
		const wrapper = await render({ list: list() })
		expect(wrapper.findAllComponents(NcChip).some((chip) => chip.text().includes('Shared'))).toBe(false)
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
		const shareAction = owned.findAllComponents(NcActionButton).find((button) => button.text() === 'Share')
		expect(shareAction).toBeDefined()
		await shareAction!.find('button').trigger('click')
		expect(owned.emitted('share')).toHaveLength(1)
		expect(owned.emitted('manageShares')).toBeUndefined()

		const shared = await render({ list: list({ sharedBy: 'alice' }) })
		await openMenu(shared)
		expect(shared.findAllComponents(NcActionButton).map((button) => button.text())).not.toContain('Share')
	})

	it('renders shared read-only lists with a read-only hint when expanded', async () => {
		const wrapper = await render({ list: list({ sharedBy: 'alice', shareMode: 'readonly' }), expanded: true })
		expect(wrapper.text()).toContain('This list is shared with you and is read-only.')
		expect(wrapper.text()).not.toContain('Add product')
	})

	it('greys out revoked lists and hides the copy action', async () => {
		const wrapper = await render({ list: list({ sharedBy: 'alice', revoked: true }) })
		expect(wrapper.html()).toContain('revoked')
		expect(wrapper.findAllComponents(NcActionButton).map((button) => button.text())).not.toContain('Copy to my catalog')
	})

	it('renders the To Buy card with an active mark, plain-text rows and open/delete actions', async () => {
		const items: ListItem[] = [
			{
				id: 'i1',
				listId: 'l1',
				productId: null,
				productName: 'Milk',
				customName: 'Milk',
				price: null,
				quantity: 1,
				isChecked: false,
				position: 0,
				discount: null,
				createdAt: null,
				updatedAt: null,
			},
		]
		const wrapper = await render({
			list: list({ kind: 'NEED_TO_BUY', isActive: true }),
			expanded: true,
			items,
		})

		expect(wrapper.text()).toContain('Active')
		expect(wrapper.text()).toContain('Milk')
		expect(wrapper.text()).toContain('Add item')
		expect(wrapper.text()).not.toContain('Shared by')

		await openMenu(wrapper)
		const names = wrapper.findAllComponents(NcActionButton).map((button) => button.text())
		expect(names).toContain('Open list')
		expect(names).toContain('Delete')
		expect(names).not.toContain('Share')
		expect(names).not.toContain('View receipt')
	})
})
