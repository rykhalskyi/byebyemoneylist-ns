<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createCategory, fetchCategories, updateCategory } from '../services/listsApi'
import type { Category } from '../types'
import { CATEGORY_COLOR_PALETTE } from '../constants/categoryColors'

const props = defineProps<{ open: boolean; entity?: Category }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [category: Category]
	updated: [category: Category]
}>()

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
		emoji.value = entity?.emoji ?? ''
		color.value = entity?.color ?? ''
		parent.value = null
		income.value = entity?.income ?? false
		requestAnimationFrame(() => nameField.value?.focus())

		loading.value = true
		try {
			categories.value = await fetchCategories()
			if (entity?.parentId) {
				parent.value = categories.value.find((candidate) => candidate.id === entity.parentId) ?? null
			}
		} catch {
			error.value = 'Failed to load categories.'
		} finally {
			loading.value = false
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
		const payload = {
			name: name.value.trim(),
			color: color.value || null,
			emoji: emoji.value || null,
			parentId: parent.value?.id ?? null,
			income: income.value,
		}
		const category = props.entity === undefined
			? await createCategory(payload)
			: await updateCategory(props.entity.id, payload)
		emit(props.entity === undefined ? 'created' : 'updated', category)
		emit('update:open', false)
	} catch {
		error.value = isEditing.value ? 'Failed to update the category. Please try again.' : 'Failed to create the category. Please try again.'
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="isEditing ? 'Edit category' : 'New category'"
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
				<div :class="$style['color-row']">
					<button
						v-for="candidate in CATEGORY_COLOR_PALETTE"
						:key="candidate"
						type="button"
						:class="[$style['color-swatch'], { [$style.selected]: color === candidate }]"
						:style="{ backgroundColor: candidate }"
						:title="candidate"
						@click="color = candidate" />
					<button
						type="button"
						:class="[$style['color-swatch'], $style.clear, { [$style.selected]: color === '' }]"
						title="No color"
						@click="color = ''">
						<span>✕</span>
					</button>
				</div>
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

.color-row {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: center;
}

.color-swatch {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border-radius: 50%;
	padding: 0;
	border: 2px solid var(--color-border);
	cursor: pointer;
	font-size: 14px;
	color: var(--color-text-maxcontrast);
}

.color-swatch.selected {
	border-color: var(--color-primary);
	box-shadow: 0 0 0 2px var(--color-primary);
}

.color-swatch.clear {
	background: transparent;
	border-style: dashed;
}

.emoji-value {
	font-size: 20px;
	line-height: 1;
}

.error {
	color: var(--color-error);
	margin: 0;
}

:global(.nc-select__dropdown) {
	z-index: 10002;
}
</style>
