<script setup lang="ts">
import { mdiChevronDown } from '@mdi/js'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { t } from '../../utils/l10n.ts'

const props = defineProps<{
	owner: string
	expanded: boolean
}>()

const emit = defineEmits<{
	toggle: []
}>()
</script>

<template>
	<section :class="$style.section">
		<button
			type="button"
			:class="$style.header"
			:aria-expanded="props.expanded"
			@click="emit('toggle')">
			<span :class="$style.label">{{ t('Shared by {owner}', { owner: props.owner }) }}</span>
			<NcIconSvgWrapper
				inline
				:path="mdiChevronDown"
				:size="20"
				:class="[$style.chevron, { [$style['chevron-open']]: props.expanded }]" />
		</button>

		<div v-if="props.expanded" :class="$style.items">
			<slot />
		</div>
	</section>
</template>

<style module>
.section {
	margin-top: 8px;
}

.header {
	display: flex;
	align-items: center;
	gap: 8px;
	box-sizing: border-box;
	width: 100% !important;
	margin: 0 !important;
	padding: 8px !important;
	border: none;
	border-radius: var(--border-radius);
	background: transparent;
	color: var(--color-text-maxcontrast);
	font-size: 0.95em;
	font-weight: 600;
	text-align: start;
	cursor: pointer;
}

.header:hover {
	background: var(--color-background-hover);
}

.header:focus {
	outline: none;
}

.header:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.label {
	flex: 1;
	min-width: 0;
}

.items {
	border-inline-start: 2px solid var(--color-border);
	margin-inline-start: 16px;
	padding-inline-start: 8px;
}

.chevron {
	flex: 0 0 auto;
	transform-origin: center;
	transition: transform 0.2s ease;
}

.chevron-open {
	transform: rotate(180deg);
}
</style>
