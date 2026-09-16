<script setup lang="ts">
import type { ReceiptPicture } from '../types.ts'

import { mdiTrashCan } from '@mdi/js'
import { ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ConfirmDialog from './ConfirmDialog.vue'
import { deleteListReceipt, fetchListReceipt } from '../services/listsApi.ts'
import { t } from '../utils/l10n.ts'

const props = defineProps<{
	open: boolean
	listId: string
	listName?: string
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	deleted: [listId: string]
}>()

const receipt = ref<ReceiptPicture | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const confirmDelete = ref(false)
const deleting = ref(false)

watch(
	() => props.open,
	async (open) => {
		if (!open) {
			return
		}
		receipt.value = null
		error.value = null
		confirmDelete.value = false
		deleting.value = false
		loading.value = true
		try {
			receipt.value = await fetchListReceipt(props.listId)
		} catch {
			error.value = t('Failed to load the receipt.')
		} finally {
			loading.value = false
		}
	},
)

function close() {
	emit('update:open', false)
}

async function onConfirmDelete() {
	deleting.value = true
	error.value = null
	try {
		await deleteListReceipt(props.listId)
		emit('deleted', props.listId)
		emit('update:open', false)
	} catch {
		error.value = t('Failed to delete the receipt. Please try again.')
	} finally {
		deleting.value = false
		confirmDelete.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="t('Receipt')"
		:open="open"
		size="normal"
		@update:open="emit('update:open', $event)">
		<div :class="$style.content">
			<div v-if="loading" :class="$style.center">
				<NcLoadingIcon />
			</div>
			<img
				v-else-if="receipt"
				:src="receipt.dataUrl"
				:alt="listName ?? t('Receipt')"
				:class="$style.image">
			<p v-else :class="$style.empty">
				{{ t('No receipt') }}
			</p>
			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
		</div>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="deleting"
				@click="close">
				{{ t('Close') }}
			</NcButton>
			<NcButton
				v-if="receipt"
				type="button"
				variant="secondary"
				:disabled="deleting"
				@click="confirmDelete = true">
				<template #icon>
					<NcIconSvgWrapper :path="mdiTrashCan" :size="20" />
				</template>
				{{ t('Delete') }}
			</NcButton>
		</template>
	</NcDialog>

	<ConfirmDialog
		:open="confirmDelete"
		:title="t('Delete receipt')"
		:message="t('Delete the receipt for {name}? This cannot be undone.', { name: listName ?? '' })"
		:busy="deleting"
		@update:open="confirmDelete = $event"
		@confirm="onConfirmDelete" />
</template>

<style module>
.content {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
}

.center {
	display: flex;
	justify-content: center;
	padding: 16px 0;
}

.image {
	max-width: 100%;
	max-height: 480px;
	border-radius: var(--border-radius);
	object-fit: contain;
}

.empty {
	color: var(--color-text-maxcontrast);
	margin: 0;
	padding: 24px 0;
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
