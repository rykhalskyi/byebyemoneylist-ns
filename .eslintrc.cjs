module.exports = {
	extends: [
		'./node_modules/@nextcloud/eslint-config/typescript.js',
	],
	rules: {
		'jsdoc/require-jsdoc': 'off',
		'vue/first-attribute-linebreak': 'off',
		// key must be on <template> when it wraps multiple nodes (Vue compiler requirement)
		'vue/no-v-for-template-key': 'off',
	},
}
