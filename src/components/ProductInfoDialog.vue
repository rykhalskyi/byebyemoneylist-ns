<script setup lang="ts">
import type { Category, Product, ProductPicture, ProductPrice, Store } from '../types.ts'

import { mdiImagePlus, mdiTrashCan } from '@mdi/js'
import { computed, ref, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ConfirmDialog from './ConfirmDialog.vue'
import { deleteProductPicture, fetchProductPicture, fetchProductPrices, uploadProductPicture } from '../services/listsApi.ts'
import { formatDate, formatTotal } from '../utils/format.ts'

const props = defineProps<{
	open: boolean
	product?: Product
	categories: Category[]
	stores: Store[]
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	updated: [product: Product]
}>()

const prices = ref<ProductPrice[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const picture = ref<ProductPicture | null>(null)
const pictureLoading = ref(false)
const pictureError = ref<string | null>(null)
const pictureBusy = ref(false)
const confirmPictureDelete = ref(false)
const pictureInput = ref<HTMLInputElement | null>(null)

const categoryName = computed(() => {
	const categoryId = props.product?.categoryId
	if (categoryId === null || categoryId === undefined) {
		return ''
	}
	return props.categories.find((category) => category.id === categoryId)?.name ?? ''
})

watch(
	() => props.open,
	async (open) => {
		if (!open) {
			return
		}
		error.value = null
		prices.value = []
		picture.value = null
		pictureError.value = null
		confirmPictureDelete.value = false
		const product = props.product
		if (product === undefined) {
			return
		}

		loading.value = true
		pictureLoading.value = true
		try {
			prices.value = await fetchProductPrices(product.id)
		} catch {
			error.value = 'Failed to load the price history.'
		} finally {
			loading.value = false
		}

		try {
			picture.value = await fetchProductPicture(product.id)
		} catch {
			pictureError.value = 'Failed to load the picture.'
		} finally {
			pictureLoading.value = false
		}
	},
)

function storeName(storeId: string | null): string {
	if (storeId === null) {
		return ''
	}
	return props.stores.find((store) => store.id === storeId)?.name ?? ''
}

function priceMeta(price: ProductPrice): string {
	return [formatDate(price.date), storeName(price.storeId)].filter(Boolean).join(' · ')
}

function close() {
	emit('update:open', false)
}

function choosePicture() {
	pictureInput.value?.click()
}

async function onPictureSelected(event: Event) {
	const input = event.target as HTMLInputElement
	const file = input.files?.[0]
	input.value = ''
	if (file === undefined || props.product === undefined) {
		return
	}

	pictureBusy.value = true
	pictureError.value = null
	try {
		picture.value = await uploadProductPicture(props.product.id, file)
		emit('updated', { ...props.product, hasPicture: true })
	} catch {
		pictureError.value = 'Failed to upload the picture.'
	} finally {
		pictureBusy.value = false
	}
}

async function onConfirmPictureDelete() {
	if (props.product === undefined) {
		return
	}
	pictureBusy.value = true
	pictureError.value = null
	try {
		await deleteProductPicture(props.product.id)
		picture.value = null
		emit('updated', { ...props.product, hasPicture: false })
	} catch {
		pictureError.value = 'Failed to delete the picture.'
	} finally {
		pictureBusy.value = false
		confirmPictureDelete.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="product?.name ?? 'Product'"
		:open="open"
		size="normal"
		@update:open="emit('update:open', $event)">
		<div v-if="product" :class="$style.content">
			<div :class="$style.picture">
				<div v-if="pictureLoading" :class="$style.center">
					<NcLoadingIcon />
				</div>
				<img
					v-else-if="picture"
					:src="picture.dataUrl"
					:alt="product.name"
					:class="$style['picture-image']">
				<div v-else :class="$style['picture-empty']">
					No picture
				</div>
				<div :class="$style['picture-actions']">
					<input
						ref="pictureInput"
						type="file"
						accept="image/jpeg,image/png,image/webp,image/gif"
						:class="$style['picture-input']"
						:disabled="pictureBusy"
						@change="onPictureSelected">
					<NcButton
						type="button"
						variant="secondary"
						:disabled="pictureBusy"
						@click="choosePicture">
						<template #icon>
							<NcIconSvgWrapper :path="mdiImagePlus" :size="20" />
						</template>
						{{ picture ? 'Replace' : 'Upload' }}
					</NcButton>
					<NcButton
						v-if="picture"
						type="button"
						variant="secondary"
						:disabled="pictureBusy"
						@click="confirmPictureDelete = true">
						<template #icon>
							<NcIconSvgWrapper :path="mdiTrashCan" :size="20" />
						</template>
						Delete
					</NcButton>
				</div>
				<p v-if="pictureError" :class="$style.error">
					{{ pictureError }}
				</p>
			</div>

			<dl :class="$style.details">
				<div v-if="categoryName" :class="$style.row">
					<dt :class="$style['row-label']">
						Category
					</dt>
					<dd :class="$style['row-value']">
						{{ categoryName }}
					</dd>
				</div>
				<div v-if="product.barcode" :class="$style.row">
					<dt :class="$style['row-label']">
						Barcode
					</dt>
					<dd :class="$style['row-value']">
						{{ product.barcode }}
					</dd>
				</div>
				<div v-if="product.aliases.length > 0" :class="$style.row">
					<dt :class="$style['row-label']">
						Aliases
					</dt>
					<dd :class="$style['row-value']">
						{{ product.aliases.join(', ') }}
					</dd>
				</div>
				<div
					v-if="product.isFavorite || product.isSubscription || product.isIncome"
					:class="$style.row">
					<dt :class="$style['row-label']">
						Flags
					</dt>
					<dd :class="[$style['row-value'], $style.flags]">
						<NcChip
							v-if="product.isFavorite"
							text="Favorite"
							noClose />
						<NcChip
							v-if="product.isSubscription"
							text="Subscription"
							variant="primary"
							noClose />
						<NcChip
							v-if="product.isIncome"
							text="Income"
							variant="success"
							noClose />
					</dd>
				</div>
			</dl>

			<h5 :class="$style['section-title']">
				Price history
			</h5>

			<div v-if="loading" :class="$style.center">
				<NcLoadingIcon />
			</div>

			<p v-else-if="error" :class="$style.error">
				{{ error }}
			</p>

			<p v-else-if="prices.length === 0" :class="$style.empty">
				No price history yet.
			</p>

			<ul v-else :class="$style.prices">
				<li v-for="price in prices" :key="price.id" :class="$style.price">
					<span :class="$style['price-value']">{{ formatTotal(price.value) }}</span>
					<span :class="$style['price-meta']">{{ priceMeta(price) }}</span>
				</li>
			</ul>
		</div>
		<template #actions>
			<NcButton type="button" variant="secondary" @click="close">
				Close
			</NcButton>
		</template>
	</NcDialog>

	<ConfirmDialog
		:open="confirmPictureDelete"
		title="Delete picture"
		message="Delete this product's picture? This cannot be undone."
		:busy="pictureBusy"
		@update:open="confirmPictureDelete = $event"
		@confirm="onConfirmPictureDelete" />
</template>

<style module>
.content {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.picture {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
}

.picture-image {
	max-width: 100%;
	max-height: 240px;
	border-radius: var(--border-radius);
	object-fit: contain;
}

.picture-empty {
	color: var(--color-text-maxcontrast);
	padding: 24px 0;
}

.picture-actions {
	display: flex;
	gap: 8px;
}

.picture-input {
	display: none;
}

.details {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin: 0;
}

.row {
	display: flex;
	gap: 8px;
}

.row-label {
	color: var(--color-text-maxcontrast);
	flex: 0 0 96px;
	margin: 0;
}

.row-value {
	margin: 0;
	min-width: 0;
	overflow-wrap: anywhere;
}

.flags {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.section-title {
	margin: 0;
}

.center {
	display: flex;
	justify-content: center;
	padding: 16px 0;
}

.error {
	color: var(--color-error);
	margin: 0;
}

.empty {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.prices {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.price {
	display: flex;
	justify-content: space-between;
	gap: 16px;
	padding: 8px 12px;
	border-radius: var(--border-radius);
	background-color: var(--color-background-hover);
}

.price-value {
	font-weight: bold;
}

.price-meta {
	color: var(--color-text-maxcontrast);
	min-width: 0;
	text-align: end;
}
</style>
