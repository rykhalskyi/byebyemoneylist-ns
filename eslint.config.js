import { recommended } from '@nextcloud/eslint-config'

export default [
	...recommended,
	{
		files: ['**/*.ts', '**/*.mts', '**/*.cts', '**/*.tsx', '**/*.vue'],
		rules: {
			// This codebase does not require a JSDoc block on every function.
			'jsdoc/require-jsdoc': 'off',
		},
	},
	{
		files: ['**/*.vue'],
		rules: {
			// Single-word view/component names used by this app.
			'vue/multi-word-component-names': ['error', { ignores: ['Menu', 'Catalog', 'Dashboard', 'Settings', 'Analytics'] }],
		},
	},
	{
		files: ['scripts/**/*.mjs'],
		rules: {
			// CLI scripts use console output and do not need JSDoc blocks.
			'jsdoc/require-jsdoc': 'off',
			'no-console': 'off',
		},
	},
]
