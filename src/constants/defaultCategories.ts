import type { CategoryBatchItem } from '../types.ts'

import { t } from '../utils/l10n.ts'
import { CategoryColors } from './categoryColors.ts'

export interface DefaultCategoryChild {
	key: string
	name: string
	color: string
	emoji: string
}

export interface DefaultCategory extends DefaultCategoryChild {
	income?: boolean
	children: DefaultCategoryChild[]
}

export const DEFAULT_CATEGORIES: DefaultCategory[] = [
	{
		key: 'supermarket',
		name: 'Supermarket',
		color: CategoryColors.GREEN,
		emoji: '🛒',
		children: [
			{ key: 'supermarket-bakery', name: 'Bakery', color: CategoryColors.YELLOW, emoji: '🥐' },
			{ key: 'supermarket-dairy', name: 'Dairy', color: CategoryColors.YELLOW, emoji: '🥛' },
			{ key: 'supermarket-eggs', name: 'Eggs', color: CategoryColors.YELLOW, emoji: '🥚' },
			{ key: 'supermarket-meat', name: 'Meat & Poultry', color: CategoryColors.RED, emoji: '🥩' },
			{ key: 'supermarket-seafood', name: 'Seafood', color: CategoryColors.BLUE, emoji: '🐟' },
			{ key: 'supermarket-cereals-muesli', name: 'Cereals & Muesli', color: CategoryColors.ORANGE, emoji: '🥣' },
			{ key: 'supermarket-produce', name: 'Fruits & Vegetables', color: CategoryColors.GREEN, emoji: '🥦' },
			{ key: 'supermarket-frozen', name: 'Frozen Foods', color: CategoryColors.BLUE, emoji: '🧊' },
			{ key: 'supermarket-beverages', name: 'Beverages', color: CategoryColors.BLUE, emoji: '🥤' },
			{ key: 'supermarket-snacks', name: 'Snacks & Sweets', color: CategoryColors.ORANGE, emoji: '🍿' },
			{ key: 'supermarket-pantry', name: 'Pantry & Spices', color: CategoryColors.TEAL, emoji: '🗄️' },
		],
	},
	{
		key: 'health-beauty',
		name: 'Health & Beauty',
		color: CategoryColors.DEFAULT_COLOR,
		emoji: '🏥',
		children: [
			{ key: 'health-beauty-personal-care', name: 'Personal Care', color: CategoryColors.PURPLE, emoji: '🧴' },
			{ key: 'health-beauty-pharmacy', name: 'Pharmacy', color: CategoryColors.PURPLE, emoji: '💊' },
		],
	},
	{
		key: 'household',
		name: 'Household',
		color: CategoryColors.DEFAULT_COLOR,
		emoji: '🏠',
		children: [
			{ key: 'household-cleaning', name: 'Cleaning Supplies', color: CategoryColors.TEAL, emoji: '🧹' },
			{ key: 'household-paper-goods', name: 'Paper Products', color: CategoryColors.TEAL, emoji: '🧻' },
			{ key: 'household-kitchen', name: 'Kitchen Supplies', color: CategoryColors.TEAL, emoji: '🍳' },
			{ key: 'household-laundry', name: 'Laundry Supplies', color: CategoryColors.TEAL, emoji: '🧺' },
		],
	},
	{
		key: 'automotive',
		name: 'Automotive',
		color: CategoryColors.PURPLE,
		emoji: '🚗',
		children: [
			{ key: 'automotive-fuel', name: 'Fuel', color: CategoryColors.RED, emoji: '⛽' },
			{ key: 'automotive-maintenance', name: 'Maintenance', color: CategoryColors.ORANGE, emoji: '🔧' },
		],
	},
	{
		key: 'services',
		name: 'Services & Subs',
		color: CategoryColors.DEFAULT_COLOR,
		emoji: '🧾',
		children: [
			{ key: 'services-utilities', name: 'Utilities', color: CategoryColors.BLUE, emoji: '💡' },
			{ key: 'services-rent', name: 'Rent & Mortgage', color: CategoryColors.BLUE, emoji: '🏢' },
			{ key: 'services-subscriptions', name: 'Subscriptions', color: CategoryColors.PURPLE, emoji: '🔁' },
		],
	},
	{
		key: 'lifestyle',
		name: 'Lifestyle',
		color: CategoryColors.DEFAULT_COLOR,
		emoji: '🎉',
		children: [
			{ key: 'lifestyle-restaurants', name: 'Restaurants & Cafes', color: CategoryColors.ORANGE, emoji: '🍽️' },
			{ key: 'lifestyle-entertainment', name: 'Entertainment', color: CategoryColors.ORANGE, emoji: '🎬' },
		],
	},
	{
		key: 'income',
		name: 'Income',
		color: CategoryColors.GREEN,
		emoji: '📈',
		income: true,
		children: [
			{ key: 'income-salary', name: 'Salary', color: CategoryColors.GREEN, emoji: '💰' },
			{ key: 'income-freelance', name: 'Freelance', color: CategoryColors.GREEN, emoji: '💻' },
		],
	},
]

export function buildDefaultCategoryPayload(): CategoryBatchItem[] {
	const payload: CategoryBatchItem[] = []

	for (const category of DEFAULT_CATEGORIES) {
		const income = category.income ?? false

		payload.push({
			name: t(category.name),
			color: category.color,
			emoji: category.emoji,
			income,
			tempId: category.key,
			status: 'confirmed',
		})

		for (const child of category.children) {
			payload.push({
				name: t(child.name),
				color: child.color,
				emoji: child.emoji,
				parentId: category.key,
				income,
				tempId: child.key,
				status: 'confirmed',
			})
		}
	}

	return payload
}
