<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createCategory, fetchCategories } from '../services/listsApi'
import type { Category } from '../types'

const props = defineProps<{ open: boolean }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [category: Category]
}>()

const COLORS = ['#e6194b', '#f58231', '#ffe119', '#3cb44b', '#42d4f4', '#4363d8', '#911eb4', '#a9a9a9']

const name = ref('')
const emoji = ref('')
const color = ref('')
const parent = ref<Category | null>(null)
const income = ref(false)
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
			emoji.value = ''
			color.value = ''
			parent.value = null
			income.value = false
			requestAnimationFrame(() => nameField.value?.focus())
		}
	},
)

watch(
	() => props.open,
	async (open) => {
		if (open) {
			try {
				categories.value = await fetchCategories()
			} catch {
				loading.value = false
			}
		}
	},
)

function onEmojiSelect(value: string) {
	emoji.value = value
}

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
		const category = await createCategory({
			name: name.value.trim(),
			color: color.value || null,
			emoji: emoji.value || null,
			parentId: parent.value?.id ?? null,
			income: income.value,
		})
		emit('created', category)
		emit('update:open', false)
	} catch {
		error.value = 'Failed to create the category. Please try again.'
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="'New category'"
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
				placeholder="e.g. Food"
				:disabled="submitting"
				:error="name.trim() === '' && name.length > 0"
				helper-text="The category name is required." />

			<div :class="$style.field">
				<span :class="$style.label">Emoji</span>
				<NcEmojiPicker @select="onEmojiSelect">
					<template #default="{ props: triggerProps }">
						<NcButton v-bind="triggerProps" type="button" :class="$style['emoji-button']">
							<span v-if="emoji" :class="$style['emoji-value']">{{ emoji }}</span>
							<span v-else>Pick an emoji</span>
						</NcButton>
					</template>
				</NcEmojiPicker>
			</div>

			<div :class="$style.field">
				<span :class="$style.label">Color</span>
				<NcColorPicker
					v-model="color"
					:palette="COLORS"
					:clearable="true" />
			</div>

			<NcSelect
				v-model="parent"
				label="name"
				input-label="Parent category"
				placeholder="No parent (top level)"
				:options="categories"
				:loading="loading"
				:disabled="submitting"
				clearable />

			<NcCheckboxRadioSwitch v-model="income" type="switch" :disabled="submitting">
				Income category
			</NcCheckboxRadioSwitch>

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

.field {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.label {
	font-size: var(--default-font-size);
	color: var(--color-text-maxcontrast);
}

.emoji-button {
	justify-content: flex-start;
}

.emoji-value {
	font-size: 20px;
	line-height: 1;
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
