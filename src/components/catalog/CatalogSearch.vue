<script setup lang="ts">
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { t } from '../../utils/l10n.ts'

const props = withDefaults(defineProps<{
	modelValue: string
	placeholder?: string
	label?: string
}>(), {
	placeholder: t('Search'),
	label: t('Search'),
})

const emit = defineEmits<{
	'update:modelValue': [value: string]
}>()

function onUpdate(value: string | number) {
	emit('update:modelValue', String(value))
}

function onClear() {
	emit('update:modelValue', '')
}
</script>

<template>
	<NcTextField
		:modelValue="props.modelValue"
		:label="props.label"
		:placeholder="props.placeholder"
		type="search"
		:showTrailingButton="props.modelValue.length > 0"
		trailingButtonIcon="close"
		:trailingButtonLabel="t('Clear search')"
		@update:modelValue="onUpdate"
		@trailingButtonClick="onClear" />
</template>
