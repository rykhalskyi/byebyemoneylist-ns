<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createStore } from '../services/listsApi'
import type { Store } from '../types'

const props = defineProps<{ open: boolean }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [store: Store]
}>()

const name = ref('')
const submitting = ref(false)
const error = ref<string | null>(null)
const nameField = ref<InstanceType<typeof NcTextField> | null>(null)

const canSubmit = computed(() => name.value.trim() !== '' && !submitting.value)

watch(
	() => props.open,
	(open) => {
		if (open) {
			error.value = null
			submitting.value = false
			name.value = ''
			requestAnimationFrame(() => nameField.value?.focus())
		}
	},
)

function onCancel() {
	emit('update:open', false)
}

async function onSubmit() {
	if (!canSubmit.value) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const store = await createStore({ name: name.value.trim() })
		emit('created', store)
		emit('update:open', false)
	} catch {
		error.value = 'Failed to create the store. Please try again.'
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="'New store'"
		:open="props.open"
		size="normal"
		is-form
		@submit="onSubmit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<NcTextField
				ref="nameField"
				v-model="name"
				label="Name"
				placeholder="e.g. Aldi"
				:disabled="submitting"
				:error="name.trim() === '' && name.length > 0"
				helper-text="The store name is required." />
			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
		</div>
		<template #actions>
			<NcButton type="button"
				variant="secondary"
				:disabled="submitting"
				@click="onCancel">
				Cancel
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="!canSubmit">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				Create
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
