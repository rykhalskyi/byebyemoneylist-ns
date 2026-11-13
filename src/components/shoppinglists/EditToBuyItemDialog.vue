<script setup lang="ts">
import type { ListItem } from '../../types.ts'

import { ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { updateListItem } from '../../services/listsApi.ts'
import { t } from '../../utils/l10n.ts'
import { itemName } from '../../utils/listDisplay.ts'

const props = defineProps<{
	open: boolean
	listId: string
	item: ListItem | null
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	updated: [item: ListItem]
}>()

const name = ref('')
const submitting = ref(false)
const error = ref<string | null>(null)
const nameField = ref<InstanceType<typeof NcTextField> | null>(null)

watch(
	() => props.open,
	(open) => {
		if (!open) {
			return
		}
		name.value = props.item === null ? '' : itemName(props.item)
		error.value = null
		submitting.value = false
		requestAnimationFrame(() => nameField.value?.focus())
	},
	{ immediate: true },
)

async function onSubmit() {
	const item = props.item
	const trimmed = name.value.trim()
	if (item === null || trimmed === '' || submitting.value) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const updated = await updateListItem(props.listId, item.id, { customName: trimmed })
		emit('updated', updated)
		emit('update:open', false)
	} catch {
		error.value = t('Failed to rename the item. Please try again.')
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="t('Edit item')"
		:open="props.open"
		size="small"
		isForm
		@submit="onSubmit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<NcTextField
				ref="nameField"
				v-model="name"
				:label="t('Item name')"
				:disabled="submitting" />
			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
		</div>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="submitting"
				@click="emit('update:open', false)">
				{{ t('Cancel') }}
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="name.trim() === '' || submitting">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				{{ t('Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
