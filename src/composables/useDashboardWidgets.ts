import type { DashboardWidgetConfig, DashboardWidgetType } from '../constants/dashboardWidgets.ts'

import { ref, watch } from 'vue'
import { isDashboardWidgetType } from '../constants/dashboardWidgets.ts'

export const DASHBOARD_WIDGETS_STORAGE_KEY = 'byebyemoneylist.dashboard.widgets'

function createWidgetId(): string {
	if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
		return crypto.randomUUID()
	}
	return `w-${Date.now()}-${Math.random().toString(16).slice(2)}`
}

function normalizeConfig(entry: unknown): DashboardWidgetConfig | null {
	if (typeof entry !== 'object' || entry === null) {
		return null
	}
	const candidate = entry as Record<string, unknown>
	if (typeof candidate.id !== 'string' || !isDashboardWidgetType(candidate.type)) {
		return null
	}
	const categoryId = typeof candidate.categoryId === 'string' ? candidate.categoryId : null
	return { id: candidate.id, type: candidate.type, categoryId }
}

export function loadDashboardWidgets(): DashboardWidgetConfig[] {
	try {
		const raw = window.localStorage.getItem(DASHBOARD_WIDGETS_STORAGE_KEY)
		if (raw === null) {
			return []
		}
		const parsed: unknown = JSON.parse(raw)
		if (!Array.isArray(parsed)) {
			return []
		}
		const widgets: DashboardWidgetConfig[] = []
		for (const entry of parsed) {
			const config = normalizeConfig(entry)
			if (config !== null) {
				widgets.push(config)
			}
		}
		return widgets
	} catch {
		return []
	}
}

function saveDashboardWidgets(widgets: DashboardWidgetConfig[]): void {
	try {
		window.localStorage.setItem(DASHBOARD_WIDGETS_STORAGE_KEY, JSON.stringify(widgets))
	} catch {
		// Storage may be unavailable (private mode, quota); the dashboard still works.
	}
}

export function useDashboardWidgets() {
	const widgets = ref<DashboardWidgetConfig[]>(loadDashboardWidgets())

	watch(widgets, (value) => saveDashboardWidgets(value), { deep: true })

	function addWidget(type: DashboardWidgetType, categoryId: string | null = null): void {
		const config: DashboardWidgetConfig = {
			id: createWidgetId(),
			type,
			categoryId: type === 'categorySpending' ? categoryId : null,
		}
		widgets.value = [...widgets.value, config]
	}

	function removeWidget(id: string): void {
		widgets.value = widgets.value.filter((widget) => widget.id !== id)
	}

	function reorderWidgets(from: number, to: number): void {
		const length = widgets.value.length
		if (from === to || from < 0 || to < 0 || from >= length || to >= length) {
			return
		}
		const next = [...widgets.value]
		const [moved] = next.splice(from, 1)
		next.splice(to, 0, moved)
		widgets.value = next
	}

	return { widgets, addWidget, removeWidget, reorderWidgets }
}
