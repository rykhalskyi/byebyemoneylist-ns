<script setup lang="ts">
import type { ImportStatus, PurchaseImport, PurchaseImportSummary } from '../types.ts'

import { mdiAlertCircle, mdiArrowLeft, mdiInbox } from '@mdi/js'
import { onMounted, ref } from 'vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import ImportReview from '../components/imports/ImportReview.vue'
import { discardImport, fetchPurchaseImport, fetchPurchaseImports } from '../services/purchaseImportsApi.ts'
import { formatDate, formatTotal } from '../utils/format.ts'
import { t } from '../utils/l10n.ts'

const emit = defineEmits<{ close: [] }>()

const imports = ref<PurchaseImportSummary[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const busyId = ref<string | null>(null)
const selected = ref<PurchaseImport | null>(null)

onMounted(loadImports)

async function loadImports() {
	loading.value = true
	error.value = null
	try {
		const all = await fetchPurchaseImports()
		imports.value = all.filter((entry) => isReviewable(entry.status))
	} catch {
		error.value = t('Could not load incoming lists.')
	} finally {
		loading.value = false
	}
}

async function openReview(entry: PurchaseImportSummary) {
	busyId.value = entry.id
	error.value = null
	try {
		selected.value = await fetchPurchaseImport(entry.id)
	} catch {
		error.value = t('Could not load the incoming list.')
	} finally {
		busyId.value = null
	}
}

async function onDiscard(entry: PurchaseImportSummary) {
	busyId.value = entry.id
	error.value = null
	try {
		await discardImport(entry.id)
		await loadImports()
	} catch {
		error.value = t('Could not discard the incoming list.')
	} finally {
		busyId.value = null
	}
}

async function onApproved() {
	selected.value = null
	await loadImports()
}

function closeReview() {
	selected.value = null
}

function statusLabel(status: ImportStatus): string {
	switch (status) {
		case 'pending':
			return t('Pending review')
		case 'in_review':
			return t('In review')
		case 'approved':
			return t('Imported')
		case 'discarded':
			return t('Discarded')
		default:
			return status
	}
}

function isReviewable(status: ImportStatus): boolean {
	return status === 'pending' || status === 'in_review'
}
</script>

<template>
	<div :class="$style.wrapper">
		<ImportReview
			v-if="selected !== null"
			:purchaseImport="selected"
			@close="closeReview"
			@approved="onApproved" />

		<template v-else>
			<div :class="$style.header">
				<div :class="$style['header-main']">
					<NcButton type="button" variant="tertiary" @click="emit('close')">
						<template #icon>
							<NcIconSvgWrapper :path="mdiArrowLeft" :size="20" />
						</template>
						{{ t('Back to lists') }}
					</NcButton>
					<div>
						<h2>{{ t('Inbox') }}</h2>
						<p :class="$style.subtitle">
							{{ t('Lists sent from the Android app appear here for review.') }}
						</p>
					</div>
				</div>
			</div>

			<div v-if="loading" :class="$style.center">
				<NcLoadingIcon />
			</div>

			<NcEmptyContent
				v-else-if="error"
				:name="t('Could not load incoming lists.')"
				:description="error">
				<template #icon>
					<NcIconSvgWrapper :path="mdiAlertCircle" :size="64" />
				</template>
				<template #action>
					<NcButton type="button" @click="loadImports">
						{{ t('Try again') }}
					</NcButton>
				</template>
			</NcEmptyContent>

			<NcEmptyContent
				v-else-if="imports.length === 0"
				:name="t('No incoming lists')"
				:description="t('Lists sent from the Android app appear here for review.')">
				<template #icon>
					<NcIconSvgWrapper :path="mdiInbox" :size="64" />
				</template>
			</NcEmptyContent>

			<div v-else :class="$style.list">
				<div v-for="entry in imports" :key="entry.id" :class="$style.row">
					<div :class="$style['row-main']">
						<strong>{{ entry.name }}</strong>
						<span :class="$style.meta">
							<span>{{ entry.storeName ?? t('Unknown store') }}</span>
							<span>{{ formatDate(entry.purchaseDate ?? entry.createdAt) }}</span>
							<span>{{ t('Items') }}: {{ entry.itemCount }}</span>
							<span v-if="entry.finalTotal !== null">
								{{ formatTotal(entry.finalTotal) }} {{ entry.currency ?? '' }}
							</span>
						</span>
					</div>
					<span :class="[$style.status, $style[`status-${entry.status}`]]">
						{{ statusLabel(entry.status) }}
					</span>
					<div :class="$style.actions">
						<NcButton
							type="button"
							variant="secondary"
							:disabled="busyId === entry.id"
							@click="openReview(entry)">
							{{ t('Review') }}
						</NcButton>
						<NcButton
							v-if="isReviewable(entry.status)"
							type="button"
							variant="tertiary"
							:disabled="busyId === entry.id"
							@click="onDiscard(entry)">
							{{ t('Discard') }}
						</NcButton>
					</div>
				</div>
			</div>
		</template>
	</div>
</template>

<style module>
.wrapper {
	display: flex;
	flex-direction: column;
	gap: 16px;
	box-sizing: border-box;
	padding: 16px;
	width: 100%;
}

.header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 16px;
	flex-wrap: wrap;
}

.header-main {
	display: flex;
	align-items: flex-start;
	gap: 16px;
	flex-wrap: wrap;
}

.subtitle {
	margin: 0;
	opacity: 0.7;
}

.list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.row {
	display: grid;
	grid-template-columns: minmax(200px, 1fr) auto auto;
	gap: 16px;
	align-items: center;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	padding: 12px 16px;
}

.row-main {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.meta {
	display: flex;
	gap: 12px;
	flex-wrap: wrap;
	opacity: 0.7;
	font-size: 0.9em;
}

.actions {
	display: flex;
	gap: 8px;
}

.status {
	border-radius: var(--border-radius-pill);
	padding: 2px 10px;
	font-size: 0.85em;
	background: var(--color-background-dark);
}

.status-approved {
	background: var(--color-success);
	color: var(--color-success-text);
}

.status-discarded {
	opacity: 0.6;
}

.center {
	display: flex;
	justify-content: center;
	padding: 24px;
}

@media (max-width: 700px) {
	.row {
		grid-template-columns: 1fr;
	}
}
</style>
