import type { Category, Product } from '../../../../src/types.ts'

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import ProductRow from '../../../../src/components/catalog/ProductRow.vue'
import { formatTotal } from '../../../../src/utils/format.ts'

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
	status: 'confirmed',
}

async function render(props: { product: Product, category?: Category | null, search?: string }) {
	const wrapper = mount(ProductRow, { props })
	await nextTick()
	return wrapper
}

describe('ProductRow', () => {
	it('renders the product and category names', async () => {
		const wrapper = await render({ product: product(), category: dairy })
		expect(wrapper.text()).toContain('Milk')
		expect(wrapper.text()).toContain('Dairy')
	})

	it('renders barcode, subscription, income and last price', async () => {
		const wrapper = await render({
			product: product({
				barcode: '1234',
				isSubscription: true,
				isIncome: true,
				lastPrice: 2.5,
			}),
		})
		expect(wrapper.text()).toContain('1234')
		expect(wrapper.text()).toContain('Subscription')
		expect(wrapper.text()).toContain('Income')
		expect(wrapper.text()).toContain(formatTotal(2.5))
	})

	it('emits open when the row is clicked', async () => {
		const value = product()
		const wrapper = await render({ product: value })
		await wrapper.trigger('click')
		expect(wrapper.emitted('open')?.[0]).toEqual([value])
	})

	it('emits edit', async () => {
		const value = product()
		const wrapper = await render({ product: value })
		await wrapper.find('button[aria-label="Edit Milk"]').trigger('click')
		expect(wrapper.emitted('edit')?.[0]).toEqual([value])
	})

	it('emits delete', async () => {
		const value = product()
		const wrapper = await render({ product: value })
		await wrapper.find('button[aria-label="Delete Milk"]').trigger('click')
		expect(wrapper.emitted('delete')?.[0]).toEqual([value])
	})
})
