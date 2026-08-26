<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createList, fetchCategories, fetchStores } from '../services/listsApi'
import type { Category, ShoppingList, Store } from '../types'

const props = defineProps<{ open: boolean }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [list: ShoppingList]
}>()

const name = ref('')
const store = ref<Store | null>(null)
const category = ref<Category | null>(null)
const stores = ref<Store[]>([])
const categories = ref<Category[]>([])
const loading = ref(false)
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
			store.value = null
			category.value = null
			requestAnimationFrame(() => nameField.value?.focus())
		}
	},
)

onMounted(async () => {
	try {
		const [storeData, categoryData] = await Promise.all([fetchStores(), fetchCategories()])
		stores.value = storeData
		categories.value = categoryData
	} catch {
		loading.value = false
	}
})

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
		const list = await createList({
			name: name.value.trim(),
			storeId: store.value?.id ?? null,
			categoryId: category.value?.id ?? null,
		})
		emit('created', list)
		emit('update:open', false)
	} catch {
		error.value = 'Failed to create the list. Please try again.'
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="'New list'"
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
				placeholder="e.g. Weekly groceries"
				:disabled="submitting"
				:error="name.trim() === '' && name.length > 0"
				helper-text="The list name is required." />
			<NcSelect
				v-model="store"
				label="name"
				input-label="Store"
				placeholder="Select a store (optional)"
				:options="stores"
				:loading="loading"
				:disabled="submitting"
				clearable />
			<NcSelect
				v-model="category"
				label="name"
				input-label="Category"
				placeholder="Select a category (optional)"
				:options="categories"
				:loading="loading"
				:disabled="submitting"
				clearable />
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
