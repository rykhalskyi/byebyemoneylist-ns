<script setup lang="ts">
import type { ListItem } from '../types.ts'

import { ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { addToBuyItem } from '../../services/listsApi.ts'
import { t } from '../../utils/l10n.ts'

const props = defineProps<{
	open: boolean
	listId: string
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	added: [item: ListItem]
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
		name.value = ''
		error.value = null
		submitting.value = false
		requestAnimationFrame(() => nameField.value?.focus())
	},
	{ immediate: true },
)

async function onSubmit() {
	const trimmed = name.value.trim()
	if (trimmed === '' || submitting.value) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const item = await addToBuyItem(props.listId, trimmed)
		emit('added', item)
		emit('update:open', false)
	} catch {
		error.value = t('Failed to add the item. Please try again.')
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="t('Add item')"
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
				:placeholder="t('e.g. Milk')"
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
				{{ t('Add') }}
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
