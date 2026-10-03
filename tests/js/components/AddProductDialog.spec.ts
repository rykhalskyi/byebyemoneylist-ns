import type { ListItem, Product } from '../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import AddProductDialog from '../../../src/components/AddProductDialog.vue'
import * as api from '../../../src/services/listsApi.ts'

vi.mock('../../../src/services/listsApi.ts', () => ({
	fetchProducts: vi.fn(),
	createProduct: vi.fn(),
	addListItem: vi.fn(),
}))

function product(overrides: Partial<Product> = {}): Product {
	return {
		id: 'p1',
		name: 'Milk',
		barcode: null,
		categoryId: null,
		aliases: [],
		isFavorite: false,
		status: 'reviewed',
		isSubscription: false,
		isIncome: false,
		lastPrice: null,
		lastPriceDate: null,
		hasPicture: false,
		...overrides,
	}
}

function addedItem(): ListItem {
	return {
		id: 'i1',
		listId: 'l1',
		productId: 'p1',
		productName: 'Milk',
		price: null,
		quantity: 1,
		isChecked: false,
		createdAt: null,
	}
}

async function render(sharedOwner: string | null) {
	const wrapper = mount(AddProductDialog, {
		props: { open: false, listId: 'l1', sharedOwner },
		global: { stubs: { teleport: true } },
	})
	await wrapper.setProps({ open: true })
	await flushPromises()
	await nextTick()
	return wrapper
}

async function selectMilk(wrapper: Awaited<ReturnType<typeof render>>) {
	const row = wrapper.findAllComponents(NcListItem).find((item) => item.text().includes('Milk'))
	expect(row, 'product row').toBeDefined()
	row!.vm.$emit('click')
	await nextTick()
}

function buttonByText(wrapper: Awaited<ReturnType<typeof render>>, text: string) {
	return wrapper.findAll('button').find((button) => button.text().trim() === text)
}

async function submit(wrapper: Awaited<ReturnType<typeof render>>) {
	const add = buttonByText(wrapper, 'Add to list')
	expect(add, 'add button').toBeDefined()
	await add!.trigger('click')
	await nextTick()
}

describe('AddProductDialog publishing', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		vi.mocked(api.addListItem).mockResolvedValue(addedItem())
	})

	it('asks before publishing an own product to a shared list', async () => {
		vi.mocked(api.fetchProducts).mockResolvedValue([product()])
		const wrapper = await render('alice')
		await selectMilk(wrapper)
		await submit(wrapper)

		expect(api.addListItem).not.toHaveBeenCalled()
		const confirm = buttonByText(wrapper, 'Share')
		expect(confirm, 'share confirmation').toBeDefined()
		await confirm!.trigger('click')
		await flushPromises()

		expect(api.addListItem).toHaveBeenCalledWith('l1', expect.objectContaining({ publishToOwner: true }))
	})

	it('does not ask when the list is not shared', async () => {
		vi.mocked(api.fetchProducts).mockResolvedValue([product()])
		const wrapper = await render(null)
		await selectMilk(wrapper)
		await submit(wrapper)
		await flushPromises()

		expect(api.addListItem).toHaveBeenCalledWith('l1', expect.objectContaining({ publishToOwner: false }))
	})

	it('does not ask when using an already-shared product', async () => {
		vi.mocked(api.fetchProducts).mockResolvedValue([product({ shared: true, owner: 'alice' })])
		const wrapper = await render('alice')
		await selectMilk(wrapper)
		await submit(wrapper)
		await flushPromises()

		expect(api.addListItem).toHaveBeenCalledWith('l1', expect.objectContaining({ publishToOwner: false }))
	})
})
