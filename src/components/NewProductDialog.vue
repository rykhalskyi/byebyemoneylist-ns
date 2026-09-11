<script setup lang="ts">
import type { Category, Product } from '../types.ts'

import { mdiImagePlus, mdiTrashCan } from '@mdi/js'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { createProduct, deleteProduct, deleteProductPicture, fetchCategories, fetchProductPicture, updateProduct, uploadProductPicture } from '../services/listsApi.ts'
import { t } from '../utils/l10n.ts'

const props = defineProps<{ open: boolean, entity?: Product, preset?: 'subscription' | 'income' }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	created: [product: Product]
	updated: [product: Product]
}>()

const name = ref('')
const category = ref<Category | null>(null)
const barcode = ref('')
const aliases = ref('')
const favorite = ref(false)
const subscription = ref(false)
const income = ref(false)
const categories = ref<Category[]>([])
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const nameField = ref<InstanceType<typeof NcTextField> | null>(null)

const pictureFile = ref<File | null>(null)
const picturePreview = ref<string | null>(null)
const pictureObjectUrl = ref<string | null>(null)
const removePicture = ref(false)
const pictureInput = ref<HTMLInputElement | null>(null)

const isEditing = computed(() => props.entity !== undefined)
const hadPicture = computed(() => props.entity?.hasPicture ?? false)

const availableCategories = computed(() => categories.value.filter((item) => item.income || !income.value))

const canSubmit = computed(() => name.value.trim() !== '' && !submitting.value)

watch(income, (isIncome) => {
	if (!isIncome && category.value?.income) {
		category.value = null
	}
})

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
		category.value = null
		barcode.value = entity?.barcode ?? ''
		aliases.value = entity?.aliases.join(', ') ?? ''
		favorite.value = entity?.isFavorite ?? false
		subscription.value = entity?.isSubscription ?? props.preset === 'subscription'
		income.value = entity?.isIncome ?? props.preset === 'income'
		resetPicture()
		requestAnimationFrame(() => nameField.value?.focus())

		loading.value = true
		try {
			categories.value = await fetchCategories()
			if (entity?.categoryId) {
				category.value = categories.value.find((candidate) => candidate.id === entity.categoryId) ?? null
			}
		} catch {
			error.value = t('Failed to load categories.')
		} finally {
			loading.value = false
		}

		if (entity?.hasPicture) {
			try {
				const picture = await fetchProductPicture(entity.id)
				picturePreview.value = picture?.dataUrl ?? null
			} catch {
				picturePreview.value = null
			}
		}
	},
)

function resetPicture() {
	if (pictureObjectUrl.value !== null) {
		URL.revokeObjectURL(pictureObjectUrl.value)
	}
	pictureObjectUrl.value = null
	pictureFile.value = null
	picturePreview.value = null
	removePicture.value = false
}

onBeforeUnmount(resetPicture)

function choosePicture() {
	pictureInput.value?.click()
}

function onPictureSelected(event: Event) {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	input.value = ''
	if (file === undefined) {
		return
	}
	if (pictureObjectUrl.value !== null) {
		URL.revokeObjectURL(pictureObjectUrl.value)
	}
	pictureObjectUrl.value = URL.createObjectURL(file)
	pictureFile.value = file
	picturePreview.value = pictureObjectUrl.value
	removePicture.value = false
}

function clearPicture() {
	if (pictureObjectUrl.value !== null) {
		URL.revokeObjectURL(pictureObjectUrl.value)
	}
	pictureObjectUrl.value = null
	pictureFile.value = null
	picturePreview.value = null
	removePicture.value = hadPicture.value
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
			categoryId: category.value?.id ?? null,
			barcode: barcode.value.trim() || null,
			aliases: aliases.value
				.split(',')
				.map((alias) => alias.trim())
				.filter((alias) => alias !== ''),
			isFavorite: favorite.value,
			isSubscription: subscription.value,
			isIncome: income.value,
		}
		const entity = props.entity
		const isNew = entity === undefined
		const product = isNew
			? await createProduct(payload)
			: await updateProduct(entity.id, payload)

		let savedProduct = product
		if (pictureFile.value !== null) {
			try {
				await uploadProductPicture(product.id, pictureFile.value)
				savedProduct = { ...product, hasPicture: true }
			} catch {
				if (isNew) {
					await deleteProduct(product.id).catch(() => undefined)
				}
				error.value = t('Failed to upload the picture. Please try again.')
				return
			}
		} else if (removePicture.value && !isNew && hadPicture.value) {
			try {
				await deleteProductPicture(product.id)
				savedProduct = { ...product, hasPicture: false }
			} catch {
				error.value = t('Failed to delete the picture. Please try again.')
				return
			}
		}

		emit(isNew ? 'created' : 'updated', savedProduct)
		emit('update:open', false)
	} catch {
		error.value = isEditing.value ? t('Failed to update the product. Please try again.') : t('Failed to create the product. Please try again.')
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="isEditing ? t('Edit product') : t('New product')"
		:open="props.open"
		size="normal"
		isForm
		@submit="onSubmit"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<NcTextField
				ref="nameField"
				v-model="name"
				:label="t('Name')"
				:placeholder="t('e.g. Milk')"
				:disabled="submitting"
				:error="name.trim() === '' && name.length > 0"
				:helperText="t('The product name is required.')" />

			<NcSelect
				v-model="category"
				label="name"
				:inputLabel="t('Category')"
				:placeholder="t('No category')"
				:options="availableCategories"
				:loading="loading"
				:disabled="submitting"
				clearable />

			<NcTextField
				v-model="barcode"
				:label="t('Barcode')"
				:placeholder="t('e.g. 4001686310542')"
				:disabled="submitting" />

			<NcTextField
				v-model="aliases"
				:label="t('Aliases (comma-separated)')"
				:placeholder="t('e.g. M, Milch')"
				:disabled="submitting"
				:helperText="t('Alternative names used on receipts.')" />

			<div :class="$style.picture">
				<img
					v-if="picturePreview"
					:src="picturePreview"
					:alt="name || t('Product picture')"
					:class="$style['picture-preview']">
				<div v-else :class="$style['picture-empty']">
					{{ t('No picture') }}
				</div>
				<input
					ref="pictureInput"
					type="file"
					accept="image/jpeg,image/png,image/webp,image/gif"
					:class="$style['picture-input']"
					:disabled="submitting"
					@change="onPictureSelected">
				<div :class="$style['picture-actions']">
					<NcButton
						type="button"
						variant="secondary"
						:disabled="submitting"
						@click="choosePicture">
						<template #icon>
							<NcIconSvgWrapper :path="mdiImagePlus" :size="20" />
						</template>
						{{ picturePreview ? t('Replace picture') : t('Upload picture') }}
					</NcButton>
					<NcButton
						v-if="picturePreview"
						type="button"
						variant="secondary"
						:disabled="submitting"
						@click="clearPicture">
						<template #icon>
							<NcIconSvgWrapper :path="mdiTrashCan" :size="20" />
						</template>
						{{ t('Remove') }}
					</NcButton>
				</div>
				<p :class="$style['picture-hint']">
					{{ t('JPEG, PNG, WebP or GIF, up to 4 MB.') }}
				</p>
			</div>

			<NcCheckboxRadioSwitch v-model="favorite" type="switch" :disabled="submitting">
				{{ t('Favorite') }}
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch v-model="subscription" type="switch" :disabled="submitting">
				{{ t('Subscription') }}
			</NcCheckboxRadioSwitch>

			<NcCheckboxRadioSwitch v-model="income" type="switch" :disabled="submitting">
				{{ t('Income') }}
			</NcCheckboxRadioSwitch>

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
				{{ t('Cancel') }}
			</NcButton>
			<NcButton type="submit" variant="primary" :disabled="!canSubmit">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				{{ isEditing ? t('Save') : t('Create') }}
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

.picture {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
}

.picture-preview {
	max-width: 100%;
	max-height: 200px;
	border-radius: var(--border-radius);
	object-fit: contain;
}

.picture-empty {
	color: var(--color-text-maxcontrast);
	padding: 16px 0;
}

.picture-input {
	display: none;
}

.picture-actions {
	display: flex;
	gap: 8px;
}

.picture-hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
	font-size: var(--font-size-small, 13px);
}
</style>
