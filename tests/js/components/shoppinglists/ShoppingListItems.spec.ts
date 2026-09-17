import type { Category, ListItem, Product } from '../../../../src/types.ts'

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import ShoppingListItems from '../../../../src/components/shoppinglists/ShoppingListItems.vue'
import { fetchProductPicture } from '../../../../src/services/listsApi.ts'

vi.mock('../../../../src/services/listsApi.ts', () => ({
	fetchProductPicture: vi.fn(),
}))

function item(overrides: Partial<ListItem> = {}): ListItem {
	return {
		id: 'i1',
		listId: 'l1',
		productId: 'p1',
		productName: 'Milk',
		price: 2,
		quantity: 1,
		isChecked: false,
		createdAt: null,
		...overrides,
	}
}

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

const dairy: Category = {
	id: 'c1',
	name: 'Dairy',
	color: '#ff0000',
	emoji: '🥛',
	parentId: null,
	income: false,
}

async function render(props: { items: ListItem[], products?: Product[], categories?: Category[] }) {
	const wrapper = mount(ShoppingListItems, {
		props: {
			items: props.items,
			loading: false,
			error: '',
			addLabel: 'Add product',
			products: props.products ?? [],
			categories: props.categories ?? [],
		},
	})
	await nextTick()
	return wrapper
}

describe('ShoppingListItems', () => {
	it('shows the category emoji instead of a color strip', async () => {
		const wrapper = await render({
			items: [item()],
			products: [product({ categoryId: 'c1' })],
			categories: [dairy],
		})
		expect(wrapper.text()).toContain('🥛')
		expect(wrapper.find('[style*="border-inline-start"]').exists()).toBe(false)
	})

	it('falls back to a package icon when the product has no category', async () => {
		const wrapper = await render({ items: [item()], products: [product()] })
		expect(wrapper.text()).not.toContain('🥛')
		expect(wrapper.find('svg').exists()).toBe(true)
	})

	it('shows the product picture instead of the category bubble', async () => {
		vi.mocked(fetchProductPicture).mockResolvedValue({ dataUrl: 'data:image/png;base64,abc', mime: 'image/png' })
		const wrapper = await render({
			items: [item({ productId: 'p-img' })],
			products: [product({ id: 'p-img', categoryId: 'c1', hasPicture: true })],
			categories: [dairy],
		})
		await flushPromises()
		const img = wrapper.find('img')
		expect(img.exists()).toBe(true)
		expect(img.attributes('src')).toBe('data:image/png;base64,abc')
		expect(wrapper.text()).not.toContain('🥛')
	})
})
