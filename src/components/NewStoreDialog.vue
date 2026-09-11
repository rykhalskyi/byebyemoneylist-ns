<script setup lang="ts">
import type { Category, Store } from '../types.ts'

import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createStore, fetchCategories, updateStore } from '../services/listsApi.ts'

const props = defineProps<{ open: boolean, entity?: Store }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [store: Store]
	updated: [store: Store]
}>()

const name = ref('')
const address = ref('')
const selectedCategories = ref<Category[]>([])
const categories = ref<Category[]>([])
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const nameField = ref<InstanceType<typeof NcTextField> | null>(null)

const isEditing = computed(() => props.entity !== undefined)

const canSubmit = computed(() => name.value.trim() !== '' && !submitting.value)

watch(
	() => props.open,
	async (open) => {
		if (!open) {
			return
		}
		error.value = null
		submitting.value = false
		const entity = props.entity
		name.value = entity?.name ?? ''
		address.value = entity?.address ?? ''
		selectedCategories.value = []
		requestAnimationFrame(() => nameField.value?.focus())

		loading.value = true
		try {
			categories.value = await fetchCategories()
			if (entity !== undefined) {
				const ids = new Set(entity.categoryIds)
				selectedCategories.value = categories.value.filter((category) => ids.has(category.id))
			}
		} catch {
			error.value = 'Failed to load categories.'
		} finally {
			loading.value = false
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
		const payload = {
			name: name.value.trim(),
			address: address.value.trim() || null,
			categoryIds: selectedCategories.value.map((category) => category.id),
		}
		const store = props.entity === undefined
			? await createStore(payload)
			: await updateStore(props.entity.id, payload)
		emit(props.entity === undefined ? 'created' : 'updated', store)
		emit('update:open', false)
	} catch {
		error.value = isEditing.value ? 'Failed to update the store. Please try again.' : 'Failed to create the store. Please try again.'
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="isEditing ? 'Edit store' : 'New store'"
		:open="props.open"
		size="normal"
		isForm
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
				helperText="The store name is required." />

			<NcTextField
				v-model="address"
				label="Address"
				placeholder="e.g. Hauptstraße 1, 10115 Berlin"
				:disabled="submitting" />

			<NcSelect
				v-model="selectedCategories"
				label="name"
				inputLabel="Categories"
				placeholder="No categories"
				:options="categories"
				:loading="loading"
				:disabled="submitting"
				:multiple="true"
				:keepOpen="true"
				clearable />

			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
		</div>
		<template #actions>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="submitting"
				@click="onCancel">
				Cancel
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="!canSubmit">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				{{ isEditing ? 'Save' : 'Create' }}
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
