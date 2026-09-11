import type { Ref } from 'vue'

import { computed, ref, watch } from 'vue'

export interface PagedList<T> {
	visibleCount: Ref<number>
	visible: Ref<T[]>
	hasMore: Ref<boolean>
	remaining: Ref<number>
	loadMore: () => void
	reset: () => void
}

/**
 * Client-side paging over an already filtered list. The visible window resets
 * automatically whenever the source array identity changes (new query, tab or
 * data load) and can be reset manually.
 *
 * @param source the filtered list to page through
 * @param pageSize number of items per page
 */
export function usePagedList<T>(source: Ref<T[]>, pageSize = 50): PagedList<T> {
	const visibleCount = ref(pageSize)

	const visible = computed(() => source.value.slice(0, visibleCount.value))
	const remaining = computed(() => Math.max(source.value.length - visibleCount.value, 0))
	const hasMore = computed(() => remaining.value > 0)

	function reset() {
		visibleCount.value = pageSize
	}

	function loadMore() {
		visibleCount.value += pageSize
	}

	watch(source, reset)

	return { visibleCount, visible, hasMore, remaining, loadMore, reset }
}
