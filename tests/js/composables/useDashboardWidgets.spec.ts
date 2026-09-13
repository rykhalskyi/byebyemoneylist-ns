import { beforeEach, describe, expect, it } from 'vitest'
import { nextTick } from 'vue'
import {
	DASHBOARD_WIDGETS_STORAGE_KEY,
	loadDashboardWidgets,
	useDashboardWidgets,
} from '../../../src/composables/useDashboardWidgets.ts'

describe('useDashboardWidgets', () => {
	beforeEach(() => {
		window.localStorage.clear()
	})

	it('starts empty and adds widgets in order', async () => {
		const { widgets, addWidget } = useDashboardWidgets()

		expect(widgets.value).toHaveLength(0)

		addWidget('spentToday')
		addWidget('thisMonth')
		await nextTick()

		expect(widgets.value.map((widget) => widget.type)).toEqual(['spentToday', 'thisMonth'])
		expect(loadDashboardWidgets().map((widget) => widget.type)).toEqual(['spentToday', 'thisMonth'])
	})

	it('keeps the category only for the category widget', async () => {
		const { widgets, addWidget } = useDashboardWidgets()

		addWidget('categorySpending', 'cat-1')
		addWidget('spentToday', 'cat-2')
		await nextTick()

		expect(widgets.value[0].categoryId).toBe('cat-1')
		expect(widgets.value[1].categoryId).toBeNull()
	})

	it('removes a widget by id', async () => {
		const { widgets, addWidget, removeWidget } = useDashboardWidgets()

		addWidget('spentToday')
		addWidget('thisMonth')
		const [first] = widgets.value

		removeWidget(first.id)
		await nextTick()

		expect(widgets.value).toHaveLength(1)
		expect(widgets.value[0].type).toBe('thisMonth')
	})

	it('reorders widgets and ignores invalid indices', async () => {
		const { widgets, addWidget, reorderWidgets } = useDashboardWidgets()

		addWidget('spentToday')
		addWidget('thisMonth')
		addWidget('info')

		reorderWidgets(0, 2)
		expect(widgets.value.map((widget) => widget.type)).toEqual(['thisMonth', 'info', 'spentToday'])

		reorderWidgets(-1, 5)
		expect(widgets.value.map((widget) => widget.type)).toEqual(['thisMonth', 'info', 'spentToday'])
		await nextTick()
	})

	it('degrades malformed stored data to an empty dashboard', () => {
		window.localStorage.setItem(DASHBOARD_WIDGETS_STORAGE_KEY, '{"not":"an array"}')
		expect(loadDashboardWidgets()).toEqual([])

		window.localStorage.setItem(DASHBOARD_WIDGETS_STORAGE_KEY, '[{"id":"a","type":"nope"}]')
		expect(loadDashboardWidgets()).toEqual([])

		window.localStorage.setItem(DASHBOARD_WIDGETS_STORAGE_KEY, 'not json')
		expect(loadDashboardWidgets()).toEqual([])
	})
})
