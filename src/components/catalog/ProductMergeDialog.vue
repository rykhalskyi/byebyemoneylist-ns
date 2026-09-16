<script setup lang="ts">
import type Fuse from 'fuse.js'
import type { Category, Product, ProductPicture } from '../../types.ts'

import { mdiCallMerge, mdiMagnify } from '@mdi/js'
import { computed, ref, shallowRef, watch } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { fetchProductPicture, mergeProducts } from '../../services/listsApi.ts'
import { t } from '../../utils/l10n.ts'
import { createProductFuse, search } from '../../utils/search.ts'

const props = defineProps<{
	open: boolean
	source?: Product
	products: Product[]
	categories: Category[]
}>()

const emit = defineEmits<{
	'update:open': [open: boolean]
	merged: [product: Product, mergedAwayId: string]
}>()

const step = ref<'select' | 'compare'>('select')
const targetQuery = ref('')
const target = ref<Product | null>(null)

const name = ref('')
const barcode = ref('')
const category = ref<Category | null>(null)
const favorite = ref(false)
const subscription = ref(false)
const income = ref(false)
const pictureFrom = ref<'primary' | 'secondary' | 'none'>('none')

const pictureA = ref<ProductPicture | null>(null)
const pictureB = ref<ProductPicture | null>(null)
const pictureLoading = ref(false)

const submitting = ref(false)
const error = ref<string | null>(null)

const candidates = computed(() => props.products.filter((product) => product.id !== props.source?.id))

const productFuse = shallowRef<Fuse<Product> | null>(null)
watch(candidates, (items) => {
	productFuse.value = createProductFuse(items)
}, { immediate: true })

const filteredCandidates = computed(() => search(productFuse.value, candidates.value, targetQuery.value))

const availableCategories = computed(() => props.categories.filter((item) => item.income || !income.value))

const canMerge = computed(() => name.value.trim() !== '' && !submitting.value)

const mergedAliases = computed(() => {
	const source = props.source
	const other = target.value
	if (source === undefined || other === null) {
		return []
	}
	const resultName = name.value.trim().toLowerCase()
	const seen = new Set<string>()
	const aliases: string[] = []
	for (const candidate of [...source.aliases, ...other.aliases, source.name, other.name]) {
		const trimmed = candidate.trim()
		const lower = trimmed.toLowerCase()
		if (trimmed === '' || lower === resultName || seen.has(lower)) {
			continue
		}
		seen.add(lower)
		aliases.push(trimmed)
	}
	return aliases
})

const categoryName = computed(() => category.value?.name ?? t('No category'))

const hasPicture = computed(() => (props.source?.hasPicture ?? false) || (target.value?.hasPicture ?? false))

watch(income, (isIncome) => {
	if (!isIncome && category.value?.income) {
		category.value = null
	}
})

watch(
	() => props.open,
	(open) => {
		if (!open) {
			return
		}
		reset()
	},
)

function reset() {
	step.value = 'select'
	targetQuery.value = ''
	target.value = null
	name.value = ''
	barcode.value = ''
	category.value = null
	favorite.value = false
	subscription.value = false
	income.value = false
	pictureFrom.value = 'none'
	pictureA.value = null
	pictureB.value = null
	pictureLoading.value = false
	submitting.value = false
	error.value = null
}

function chooseTarget(product: Product) {
	const source = props.source
	if (source === undefined) {
		return
	}
	target.value = product
	name.value = source.name
	barcode.value = source.barcode ?? ''
	category.value = props.categories.find((item) => item.id === source.categoryId) ?? null
	favorite.value = source.isFavorite || product.isFavorite
	subscription.value = source.isSubscription || product.isSubscription
	income.value = source.isIncome || product.isIncome
	pictureFrom.value = source.hasPicture ? 'primary' : (product.hasPicture ? 'secondary' : 'none')
	step.value = 'compare'
	void loadPictures()
}

async function loadPictures() {
	const source = props.source
	const other = target.value
	if (source === undefined || other === null) {
		return
	}
	pictureA.value = null
	pictureB.value = null
	pictureLoading.value = true
	const [a, b] = await Promise.all([
		source.hasPicture ? fetchProductPicture(source.id).catch(() => null) : Promise.resolve(null),
		other.hasPicture ? fetchProductPicture(other.id).catch(() => null) : Promise.resolve(null),
	])
	pictureA.value = a
	pictureB.value = b
	pictureLoading.value = false
}

function backToSelect() {
	step.value = 'select'
	target.value = null
}

function close() {
	emit('update:open', false)
}

async function onMerge() {
	const source = props.source
	const other = target.value
	if (source === undefined || other === null || !canMerge.value) {
		return
	}
	submitting.value = true
	error.value = null
	try {
		const product = await mergeProducts({
			primaryId: source.id,
			secondaryId: other.id,
			name: name.value.trim(),
			categoryId: category.value?.id ?? null,
			barcode: barcode.value.trim() || null,
			isFavorite: favorite.value,
			isSubscription: subscription.value,
			isIncome: income.value,
			pictureFrom: pictureFrom.value,
		})
		emit('merged', product, other.id)
		emit('update:open', false)
	} catch {
		error.value = t('Failed to merge the products. Please try again.')
	} finally {
		submitting.value = false
	}
}
</script>

<template>
	<NcDialog
		:name="t('Merge products')"
		:open="open"
		size="normal"
		isForm
		@submit="onMerge"
		@update:open="emit('update:open', $event)">
		<template v-if="step === 'select'">
			<div :class="$style.select">
				<p :class="$style.hint">
					{{ t('Choose the duplicate product to merge into “{name}”.', { name: source?.name ?? '' }) }}
				</p>
				<NcTextField
					v-model="targetQuery"
					:label="t('Search products')"
					:placeholder="t('Search by name, barcode or alias')">
					<template #icon>
						<NcIconSvgWrapper :path="mdiMagnify" :size="20" />
					</template>
				</NcTextField>

				<NcEmptyContent
					v-if="filteredCandidates.length === 0"
					:name="t('No products match your search.')" />

				<ul v-else :class="$style['candidate-list']">
					<li v-for="product in filteredCandidates" :key="product.id">
						<button type="button" :class="$style.candidate" @click="chooseTarget(product)">
							<span :class="$style['candidate-name']">{{ product.name }}</span>
							<span v-if="product.aliases.length > 0" :class="$style['candidate-meta']">
								{{ product.aliases.join(', ') }}
							</span>
						</button>
					</li>
				</ul>
			</div>
		</template>

		<div v-else-if="source && target" :class="$style.form">
			<div :class="$style.field">
				<label :class="$style.label">{{ t('Name') }}</label>
				<div :class="$style['quick-actions']">
					<NcButton type="button" variant="secondary" @click="name = source.name">
						{{ t('Use “{name}”', { name: source.name }) }}
					</NcButton>
					<NcButton type="button" variant="secondary" @click="name = target.name">
						{{ t('Use “{name}”', { name: target.name }) }}
					</NcButton>
				</div>
				<NcTextField v-model="name" :label="t('Merged name')" />
			</div>

			<div :class="$style.field">
				<label :class="$style.label">{{ t('Barcode') }}</label>
				<div v-if="source.barcode || target.barcode" :class="$style['quick-actions']">
					<NcButton
						v-if="source.barcode"
						type="button"
						variant="secondary"
						@click="barcode = source.barcode ?? ''">
						{{ t('Use “{name}”', { name: source.barcode }) }}
					</NcButton>
					<NcButton
						v-if="target.barcode"
						type="button"
						variant="secondary"
						@click="barcode = target.barcode ?? ''">
						{{ t('Use “{name}”', { name: target.barcode }) }}
					</NcButton>
				</div>
				<NcTextField v-model="barcode" :label="t('Merged barcode')" />
			</div>

			<div :class="$style.field">
				<NcSelect
					v-model="category"
					label="name"
					:inputLabel="t('Category')"
					:placeholder="t('No category')"
					:options="availableCategories"
					:disabled="submitting"
					clearable />
			</div>

			<div v-if="hasPicture" :class="$style.field">
				<label :class="$style.label">{{ t('Picture') }}</label>
				<div v-if="pictureLoading" :class="$style.center">
					<NcLoadingIcon />
				</div>
				<div v-else :class="$style.pictures">
					<button
						v-if="source.hasPicture"
						type="button"
						:class="[$style['picture-option'], { [$style['picture-selected']]: pictureFrom === 'primary' }]"
						@click="pictureFrom = 'primary'">
						<span :class="$style['picture-title']">{{ t('Keep “{name}”', { name: source.name }) }}</span>
						<img
							v-if="pictureA"
							:src="pictureA.dataUrl"
							:alt="source.name"
							:class="$style['picture-image']">
					</button>
					<button
						v-if="target.hasPicture"
						type="button"
						:class="[$style['picture-option'], { [$style['picture-selected']]: pictureFrom === 'secondary' }]"
						@click="pictureFrom = 'secondary'">
						<span :class="$style['picture-title']">{{ t('Keep “{name}”', { name: target.name }) }}</span>
						<img
							v-if="pictureB"
							:src="pictureB.dataUrl"
							:alt="target.name"
							:class="$style['picture-image']">
					</button>
				</div>
				<NcButton
					v-if="pictureFrom !== 'none'"
					type="button"
					variant="tertiary"
					@click="pictureFrom = 'none'">
					{{ t('No picture') }}
				</NcButton>
			</div>

			<div :class="$style.field">
				<NcCheckboxRadioSwitch v-model="favorite" type="switch" :disabled="submitting">
					{{ t('Favorite') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="subscription" type="switch" :disabled="submitting">
					{{ t('Subscription') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-model="income" type="switch" :disabled="submitting">
					{{ t('Income') }}
				</NcCheckboxRadioSwitch>
			</div>

			<div :class="$style.field">
				<label :class="$style.label">{{ t('Merged aliases') }}</label>
				<div v-if="mergedAliases.length > 0" :class="$style.chips">
					<NcChip
						v-for="alias in mergedAliases"
						:key="alias"
						:text="alias"
						noClose />
				</div>
				<p v-else :class="$style.hint">
					{{ t('No aliases will be added.') }}
				</p>
			</div>

			<div :class="$style.summary">
				<span :class="$style['summary-name']">{{ name.trim() || t('Merged name') }}</span>
				<span :class="$style['summary-meta']">{{ categoryName }}</span>
				<span v-if="barcode.trim()" :class="$style['summary-meta']">{{ barcode.trim() }}</span>
			</div>

			<p v-if="error" :class="$style.error">
				{{ error }}
			</p>
		</div>

		<template #actions>
			<NcButton
				v-if="step === 'compare'"
				type="button"
				variant="secondary"
				:disabled="submitting"
				@click="backToSelect">
				{{ t('Back') }}
			</NcButton>
			<NcButton
				type="button"
				variant="secondary"
				:disabled="submitting"
				@click="close">
				{{ t('Cancel') }}
			</NcButton>
			<NcButton
				v-if="step === 'compare'"
				type="submit"
				variant="primary"
				:disabled="!canMerge">
				<template #icon>
					<NcLoadingIcon v-if="submitting" />
					<NcIconSvgWrapper v-else :path="mdiCallMerge" :size="20" />
				</template>
				{{ t('Merge') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style module>
.select,
.form {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.center {
	display: flex;
	justify-content: center;
	padding: 8px 0;
}

.candidate-list {
	display: flex;
	flex-direction: column;
	gap: 4px;
	list-style: none;
	margin: 0;
	max-height: 320px;
	overflow-y: auto;
	padding: 0;
}

.candidate {
	background: none;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	cursor: pointer;
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: 8px 12px;
	text-align: start;
	width: 100%;
}

.candidate:hover {
	background-color: var(--color-background-hover);
}

.candidate-name {
	font-weight: bold;
}

.candidate-meta {
	color: var(--color-text-maxcontrast);
}

.field {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.label {
	font-weight: bold;
}

.quick-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.pictures {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.picture-option {
	background: none;
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius);
	cursor: pointer;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 4px;
	padding: 8px;
}

.picture-selected {
	border-color: var(--color-primary);
	background-color: var(--color-primary-light);
}

.picture-title {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.picture-image {
	border-radius: var(--border-radius);
	height: 80px;
	object-fit: cover;
	width: 80px;
}

.chips {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.summary {
	background-color: var(--color-background-dark);
	display: flex;
	flex-direction: column;
	gap: 2px;
	padding: 12px 16px;
}

.summary-name {
	font-weight: bold;
}

.summary-meta {
	color: var(--color-text-maxcontrast);
}

.error {
	color: var(--color-error);
	margin: 0;
}
</style>
