import type { ListShare, ShoppingList } from '../../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import ShareListDialog from '../../../../src/components/shoppinglists/ShareListDialog.vue'
import * as api from '../../../../src/services/listsApi.ts'

vi.mock('../../../../src/services/listsApi.ts', () => ({
	fetchListShares: vi.fn(),
	shareList: vi.fn(),
	revokeListShare: vi.fn(),
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

function share(overrides: Partial<ListShare> = {}): ListShare {
	return {
		id: 's1',
		listId: 'l1',
		owner: 'alice',
		sharedWith: 'bob',
		mode: 'readonly',
		status: 'active',
		revoked: false,
		listName: 'Weekly groceries',
		createdAt: null,
		updatedAt: null,
		...overrides,
	}
}

async function render() {
	const wrapper = mount(ShareListDialog, {
		props: { open: false, list: list() },
		global: { stubs: { teleport: true } },
	})
	await wrapper.setProps({ open: true })
	await flushPromises()
	await nextTick()
	return wrapper
}

function buttonByText(wrapper: Awaited<ReturnType<typeof render>>, text: string) {
	return wrapper.findAll('button').find((button) => button.text().trim() === text)
}

describe('ShareListDialog', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(api.fetchListShares).mockResolvedValue([])
	})

	it('lists existing shares', async () => {
		vi.mocked(api.fetchListShares).mockResolvedValue([share()])
		const wrapper = await render()
		expect(api.fetchListShares).toHaveBeenCalledWith('l1')
		expect(wrapper.text()).toContain('bob')
	})

	it('shares with a user in read-only mode by default', async () => {
		vi.mocked(api.shareList).mockResolvedValue(share({ sharedWith: 'carol' }))
		const wrapper = await render()

		await wrapper.findComponent(NcTextField).setValue('carol')
		await buttonByText(wrapper, 'Share')!.trigger('click')
		await flushPromises()

		expect(api.shareList).toHaveBeenCalledWith('l1', 'carol', 'readonly')
		expect(wrapper.text()).toContain('carol')
	})

	it('revokes a share', async () => {
		vi.mocked(api.fetchListShares).mockResolvedValue([share()])
		vi.mocked(api.revokeListShare).mockResolvedValue(share({ revoked: true, status: 'revoked' }))
		const wrapper = await render()

		await buttonByText(wrapper, 'Revoke')!.trigger('click')
		await flushPromises()

		expect(api.revokeListShare).toHaveBeenCalledWith('l1', 's1')
		expect(wrapper.text()).toContain('Revoked')
	})
})
