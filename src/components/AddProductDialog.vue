<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { mdiPackageVariant, mdiPlus } from '@mdi/js'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { addListItem, createProduct, fetchProducts } from '../services/listsApi'
import type { ListItem, Product } from '../types'

const props = defineProps<{ open: boolean; listId: string }>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	added: [item: ListItem]
}>()

const products = ref<Product[]>([])
const search = ref('')
const selectedProduct = ref<Product | null>(null)
const creatingNew = ref(false)
const newProductName = ref('')
const price = ref('')
const quantity = ref('1')
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)
const searchField = ref<InstanceType<typeof NcTextField> | null>(null)
const newProductField = ref<InstanceType<typeof NcTextField> | null>(null)

const searchResults = computed(() => {
	const query = search.value.trim().toLowerCase()
	if (query === '') {
		return products.value
	}
	return products.value.filter((product) => {
		const name = product.name.toLowerCase()
		const barcode = product.barcode?.toLowerCase() ?? ''
		const aliases = product.aliases.map((alias) => alias.toLowerCase())
		return name.includes(query) || barcode.includes(query) || aliases.some((alias) => alias.includes(query))
	})
})

const canCreateNew = computed(() => newProductName.value.trim() !== '' && !submitting.value)

const parsedQuantity = computed<number | null>(() => {
	if (quantity.value.trim() === '') {
		return null
	}
	const value = Number.parseFloat(quantity.value)
	return Number.isNaN(value) ? null : value
})

const parsedPrice = computed<number | null>(() => {
	if (price.value.trim() === '') {
		return null
	}
	const value = Number.parseFloat(price.value)
	return Number.isNaN(value) ? null : value
})

const quantityValid = computed(() => {
	const value = parsedQuantity.value
	return value !== null && value > 0
})

const priceValid = computed(() => {
	const value = parsedPrice.value
	return value === null || value >= 0
})

const canSubmit = computed(
	() => selectedProduct.value !== null && quantityValid.value && priceValid.value && !submitting.value,
)

watch(
	() => props.open,
	(open) => {
		if (open) {
			error.value = null
			submitting.value = false
			search.value = ''
			selectedProduct.value = null
			creatingNew.value = false
			newProductName.value = ''
			price.value = ''
			quantity.value = '1'
			loading.value = true
			requestAnimationFrame(() => searchField.value?.focus())
			fetchProducts()
				.then((data) => {
					products.value = data
				})
				.catch(() => {
					error.value = 'Failed to load the product catalog.'
				})
				.finally(() => {
					loading.value = false
				})
		}
	},
)

function selectProduct(product: Product) {
	selectedProduct.value = product
	error.value = null
}

function resetSelection() {
	selectedProduct.value = null
}

function onCancel() {
	emit('update:open', false)
}

async function createNewProduct() {
	if (!canCreateNew.value) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const product = await createProduct({ name: newProductName.value.trim() })
		products.value = [...products.value, product]
		selectedProduct.value = product
		creatingNew.value = false
		newProductName.value = ''
	} catch {
		error.value = 'Failed to create the product. Please try again.'
	} finally {
		submitting.value = false
	}
}

async function onSubmit() {
	if (!canSubmit.value || selectedProduct.value === null) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const item = await addListItem(props.listId, {
			productId: selectedProduct.value.id,
			price: parsedPrice.value,
			quantity: parsedQuantity.value ?? 1,
		})
		emit('added', item)
		emit('update:open', false)
	} catch {
		error.value = 'Failed to add the product to the list. Please try again.'
	} finally {
		submitting.value = false
	}
}

function openCreateNew() {
	creatingNew.value = true
	error.value = null
	requestAnimationFrame(() => newProductField.value?.focus())
}
</script>

<template>
	<NcDialog
		:name="'Add product'"
		:open="props.open"
		size="normal"
		@update:open="emit('update:open', $event)">
		<div :class="$style.form">
			<div v-if="loading" :class="$style.center">
				<NcLoadingIcon />
			</div>

			<template v-else-if="!selectedProduct">
				<NcTextField
					ref="searchField"
					v-model="search"
					label="Search products"
					placeholder="Search by name, barcode or alias" />

				<NcEmptyContent
					v-if="products.length === 0"
					:class="$style['empty-results']"
					name="No products in the catalog yet"
					description="Create the product below to start building up your catalog.">
					<template #icon>
						<NcIconSvgWrapper :path="mdiPackageVariant" :size="64" />
					</template>
				</NcEmptyContent>

				<ul v-else-if="searchResults.length > 0" :class="$style.results">
					<NcListItem
						v-for="product in searchResults"
						:key="product.id"
						:name="product.name"
						:class="$style.result"
						compact
						@click="selectProduct(product)">
						<template #subname>
							<span v-if="product.barcode" :class="$style.result-subname">
								{{ product.barcode }}
							</span>
						</template>
					</NcListItem>
				</ul>

				<p v-else :class="$style['no-results']">
					No products match your search.
				</p>

				<div :class="$style['new-product']">
					<NcButton
						v-if="!creatingNew"
						type="button"
						variant="tertiary"
						:disabled="submitting"
						@click="openCreateNew">
						<template #icon>
							<NcIconSvgWrapper :path="mdiPlus" :size="20" />
						</template>
						Create new product
					</NcButton>
					<div v-else :class="$style['new-product-form']">
						<NcTextField
							ref="newProductField"
							v-model="newProductName"
							label="New product name"
							placeholder="e.g. Milk"
							:disabled="submitting"
							:error="newProductName.trim() === '' && newProductName.length > 0"
							helper-text="The product is added to your catalog." />
						<div :class="$style['new-product-actions']">
							<NcButton
								type="button"
								variant="secondary"
								:disabled="submitting"
								@click="creatingNew = false">
								Cancel
							</NcButton>
							<NcButton
								type="button"
								variant="primary"
								:disabled="!canCreateNew"
								@click="createNewProduct">
								<template #icon>
									<NcLoadingIcon v-if="submitting" />
								</template>
								Create & select
							</NcButton>
						</div>
					</div>
				</div>
			</template>

			<template v-else>
				<div :class="$style.selected">
					<NcListItem
						:name="selectedProduct.name"
						one-line>
						<template #icon>
							<NcIconSvgWrapper :path="mdiPackageVariant" :size="20" />
						</template>
						<template #subname>
							<div :class="$style['selected-actions']">
								<NcButton
									type="button"
									variant="tertiary"
									:disabled="submitting"
									@click="resetSelection">
									Change
								</NcButton>
							</div>
						</template>
					</NcListItem>
				</div>

				<div :class="$style['fields']">
					<NcTextField
						v-model="price"
						type="text"
						inputmode="decimal"
						label="Price (optional)"
						placeholder="e.g. 1.99"
						:disabled="submitting"
						:error="parsedPrice !== null && !priceValid" />
					<NcTextField
						v-model="quantity"
						type="text"
						inputmode="decimal"
						label="Quantity"
						placeholder="e.g. 1.5"
						:disabled="submitting"
						:error="parsedQuantity !== null && !quantityValid"
						helper-text="Any number of units, e.g. 1.5 kg." />
				</div>
			</template>

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
			<NcButton
				type="button"
				variant="primary"
				:disabled="!canSubmit"
				@click="onSubmit">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
				</template>
				Add to list
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

.center {
	display: flex;
	justify-content: center;
	padding: 32px 0;
}

.empty-results {
	padding: 8px 0;
}

.results {
	list-style: none;
	margin: 0;
	padding: 0;
	max-height: 240px;
	overflow-y: auto;
}

.result {
	width: 100%;
}

.result-subname {
	color: var(--color-text-maxcontrast);
}

.no-results {
	color: var(--color-text-maxcontrast);
	margin: 0;
	padding: 8px 0;
	text-align: center;
}

.new-product {
	border-top: 1px solid var(--color-border);
	padding-top: 16px;
}

.new-product-form {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.new-product-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
}

.selected {
	width: 100%;
}

.selected-actions {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-inline-start: auto;
}

.fields {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
