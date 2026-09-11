import { describe, expect, it } from 'vitest'
import { nextTick, ref } from 'vue'
import { usePagedList } from '../../../src/composables/usePagedList.ts'

function range(length: number): number[] {
	return Array.from({ length }, (_, index) => index)
}

describe('usePagedList', () => {
	it('shows only the first page initially', () => {
		const paging = usePagedList(ref(range(120)), 50)
		expect(paging.visible.value).toEqual(range(50))
		expect(paging.remaining.value).toBe(70)
		expect(paging.hasMore.value).toBe(true)
	})

	it('appends the next page on loadMore', () => {
		const paging = usePagedList(ref(range(120)), 50)
		paging.loadMore()
		expect(paging.visible.value).toHaveLength(100)
		expect(paging.remaining.value).toBe(20)
		paging.loadMore()
		expect(paging.visible.value).toHaveLength(120)
		expect(paging.hasMore.value).toBe(false)
	})

	it('does not report more pages when the list is short', () => {
		const paging = usePagedList(ref(range(10)), 50)
		expect(paging.visible.value).toEqual(range(10))
		expect(paging.hasMore.value).toBe(false)
	})

	it('resets to the first page when the source changes', async () => {
		const source = ref(range(120))
		const paging = usePagedList(source, 50)
		paging.loadMore()
		expect(paging.visible.value).toHaveLength(100)

		source.value = range(3)
		await nextTick()
		expect(paging.visible.value).toEqual([0, 1, 2])
		expect(paging.remaining.value).toBe(0)
	})

	it('resets on demand', () => {
		const paging = usePagedList(ref(range(120)), 50)
		paging.loadMore()
		paging.reset()
		expect(paging.visible.value).toHaveLength(50)
	})
})
