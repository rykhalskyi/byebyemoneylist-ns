import type { Category, Product } from '../../../../src/types.ts'

import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import ProductMergeDialog from '../../../../src/components/catalog/ProductMergeDialog.vue'
import * as api from '../../../../src/services/listsApi.ts'

vi.mock('../../../../src/services/listsApi.ts', () => ({
	fetchProductPicture: vi.fn(),
	mergeProducts: vi.fn(),
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

const dairy: Category = {
	id: 'c1',
	name: 'Dairy',
	color: '#ff0000',
	emoji: '🥛',
	parentId: null,
	income: false,
	status: 'confirmed',
}

const source = product({ id: 'p1', name: 'Milk', aliases: ['M'] })
const duplicate = product({ id: 'p2', name: 'Milch', aliases: ['Milch', 'M'] })
const bread = product({ id: 'p3', name: 'Bread' })

async function render(props: Record<string, unknown> = {}) {
	const wrapper = mount(ProductMergeDialog, {
		props: {
			open: false,
			source,
			products: [source, duplicate, bread],
			categories: [dairy],
			...props,
		},
		global: { stubs: { teleport: true } },
	})
	await wrapper.setProps({ open: true })
	await flushPromises()
	await nextTick()
	return wrapper
}

function candidate(wrapper: Awaited<ReturnType<typeof render>>, name: string) {
	return wrapper.findAll('button').find((button) => button.text().includes(name))
}

enableAutoUnmount(afterEach)

describe('ProductMergeDialog', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('lists every product except the source as a merge candidate', async () => {
		const wrapper = await render()
		expect(candidate(wrapper, 'Milch')).toBeDefined()
		expect(candidate(wrapper, 'Bread')).toBeDefined()
		expect(wrapper.findAll('button').filter((button) => button.text() === 'Milk')).toHaveLength(0)
	})

	it('filters the candidates with the search field', async () => {
		const wrapper = await render()
		await wrapper.find('input').setValue('Bread')
		await nextTick()
		expect(candidate(wrapper, 'Bread')).toBeDefined()
		expect(candidate(wrapper, 'Milch')).toBeUndefined()
	})

	it('shows the concatenated aliases after choosing a duplicate', async () => {
		const wrapper = await render()
		await candidate(wrapper, 'Milch')!.trigger('click')
		await nextTick()
		expect(wrapper.text()).toContain('Merged aliases')
		expect(wrapper.text()).toContain('Milch')
	})

	it('lets the user reuse the duplicate name', async () => {
		const wrapper = await render()
		await candidate(wrapper, 'Milch')!.trigger('click')
		await nextTick()
		const useButton = wrapper.findAll('button').find((button) => button.text() === 'Use “Milch”')
		await useButton!.trigger('click')
		await nextTick()
		expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Milch')
	})

	it('merges the products and emits the result', async () => {
		const merged = product({ id: 'p1', name: 'Milch', aliases: ['M'] })
		vi.mocked(api.mergeProducts).mockResolvedValue(merged)
		const wrapper = await render()
		await candidate(wrapper, 'Milch')!.trigger('click')
		await nextTick()

		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(api.mergeProducts).toHaveBeenCalledWith({
			primaryId: 'p1',
			secondaryId: 'p2',
			name: 'Milk',
			categoryId: null,
			barcode: null,
			isFavorite: false,
			isSubscription: false,
			isIncome: false,
			pictureFrom: 'none',
		})
		expect(wrapper.emitted('merged')?.[0]).toEqual([merged, 'p2'])
		expect(wrapper.emitted('update:open')?.[0]).toEqual([false])
	})

	it('shows an error when the merge fails', async () => {
		vi.mocked(api.mergeProducts).mockRejectedValue(new Error('boom'))
		const wrapper = await render()
		await candidate(wrapper, 'Milch')!.trigger('click')
		await nextTick()

		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(wrapper.text()).toContain('Failed to merge the products')
		expect(wrapper.emitted('merged')).toBeUndefined()
	})
})
