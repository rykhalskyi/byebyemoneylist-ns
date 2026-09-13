import { mdiCalendarMonth, mdiCalendarToday, mdiCartPlus, mdiInformationOutline, mdiReceiptText, mdiShape } from '@mdi/js'
import { t } from '../utils/l10n.ts'

export type DashboardWidgetType
	= | 'spentToday'
		| 'categorySpending'
		| 'thisMonth'
		| 'addPurchase'
		| 'scanPurchase'
		| 'info'

export interface DashboardWidgetConfig {
	id: string
	type: DashboardWidgetType
	categoryId?: string | null
}

export interface DashboardWidgetDefinition {
	type: DashboardWidgetType
	label: string
	icon: string
	requiresCategory: boolean
}

export function dashboardWidgetDefinitions(): DashboardWidgetDefinition[] {
	return [
		{ type: 'spentToday', label: t('Spent today'), icon: mdiCalendarToday, requiresCategory: false },
		{ type: 'thisMonth', label: t('Spent this month'), icon: mdiCalendarMonth, requiresCategory: false },
		{ type: 'categorySpending', label: t('Spent in category'), icon: mdiShape, requiresCategory: true },
		{ type: 'addPurchase', label: t('Add purchase'), icon: mdiCartPlus, requiresCategory: false },
		{ type: 'scanPurchase', label: t('Scan purchase'), icon: mdiReceiptText, requiresCategory: false },
		{ type: 'info', label: t('Info'), icon: mdiInformationOutline, requiresCategory: false },
	]
}

export function isDashboardWidgetType(value: unknown): value is DashboardWidgetType {
	return dashboardWidgetDefinitions().some((definition) => definition.type === value)
}
